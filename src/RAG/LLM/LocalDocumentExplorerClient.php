<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Privacy\PIIRedactor;
use ML\IDEA\NLP\Tokenize\SentenceTokenizer;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;
use ML\IDEA\NLP\Vectorize\BM25;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Contracts\StreamingLlmClientInterface;

final class LocalDocumentExplorerClient implements LlmClientInterface, StreamingLlmClientInterface
{
    /** @var array<string, array{text:string, metadata:array<string,mixed>}> */
    private array $sources = [];
    /** @var array<int, array{id:string,docId:string,text:string,startOffset:int,endOffset:int,metadata:array<string,mixed>}> */
    private array $chunks = [];
    /** @var array<string, array<int, array{text:string,label:string,start:int,end:int,confidence:float}>> */
    private array $chunkEntities = [];
    /** @var array<string, bool> */
    private array $dirtyDocs = [];
    /** @var array{size:int,overlap:int} */
    private array $indexConfig = ['size' => 450, 'overlap' => 80];
    private ?BM25 $bm25 = null;
    private bool $indexed = false;

    /** @param array<int, string> $allowlistPaths */
    public function __construct(
        private readonly array $allowlistPaths = [],
        private readonly PIIRedactor $redactor = new PIIRedactor(),
        private readonly RuleBasedNerTagger $nerTagger = new RuleBasedNerTagger(),
    ) {
    }

    /** @param array<string, mixed> $metadata */
    public function addSourceText(string $id, string $text, array $metadata = []): void
    {
        $id = trim($id);
        if ($id === '' || trim($text) === '') {
            return;
        }

        $this->sources[$id] = ['text' => $text, 'metadata' => $metadata];
        $this->dirtyDocs[$id] = true;
    }

    /** @param array<string, mixed> $metadata */
    public function sourceAdd(string $id, string $text, array $metadata = []): void
    {
        $this->addSourceText($id, $text, $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public function addSourcePath(string $path, array $metadata = []): string
    {
        if (!$this->isAllowedPath($path)) {
            throw new \InvalidArgumentException('Path is not allowed by allowlist.');
        }
        if (!is_file($path)) {
            throw new \InvalidArgumentException('Source path not found: ' . $path);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $raw = (string) file_get_contents($path);
        $text = match ($ext) {
            'txt', 'md' => $raw,
            'json' => $this->parseJson($raw),
            'csv' => $this->parseCsv($raw),
            default => $raw,
        };

        $id = 'file:' . md5($path);
        $meta = array_merge($metadata, ['path' => $path, 'extension' => $ext, 'source' => 'file']);
        $this->addSourceText($id, $text, $meta);
        return $id;
    }

    /** @return array<int, array{id:string,size:int,metadata:array<string,mixed>}> */
    public function listSources(): array
    {
        $rows = [];
        foreach ($this->sources as $id => $src) {
            $rows[] = ['id' => $id, 'size' => strlen($src['text']), 'metadata' => $src['metadata']];
        }
        return $rows;
    }

    /** @return array<int, array{id:string,size:int,metadata:array<string,mixed>}> */
    public function sourceList(): array
    {
        return $this->listSources();
    }

    public function removeSource(string $id): bool
    {
        $ok = isset($this->sources[$id]);
        unset($this->sources[$id]);
        if ($ok) {
            $this->dirtyDocs[$id] = true;
        }
        return $ok;
    }

    public function sourceRemove(string $id): bool
    {
        return $this->removeSource($id);
    }

    /** @return array{doc_count:int,total_chars:int,last_indexed_chunks:int} */
    public function sourceStats(): array
    {
        $chars = 0;
        foreach ($this->sources as $src) {
            $chars += strlen($src['text']);
        }
        return ['doc_count' => count($this->sources), 'total_chars' => $chars, 'last_indexed_chunks' => count($this->chunks)];
    }

    /** @return array{doc_count:int,total_chars:int,last_indexed_chunks:int} */
    public function sourceStatsAll(): array
    {
        return $this->sourceStats();
    }

    public function parseText(string $text): string { return $text; }
    public function parseMarkdown(string $markdown): string { return $markdown; }
    public function parseJsonDocument(string $raw): string { return $this->parseJson($raw); }
    public function parseCsvDocument(string $raw): string { return $this->parseCsv($raw); }

    public function buildIndex(int $chunkSize = 450, int $overlap = 80): void
    {
        $fullRebuild = !$this->indexed || $this->indexConfig['size'] !== $chunkSize || $this->indexConfig['overlap'] !== $overlap;
        $this->indexConfig = ['size' => $chunkSize, 'overlap' => $overlap];

        if ($fullRebuild) {
            $this->chunks = [];
            $this->chunkEntities = [];
            foreach ($this->sources as $docId => $src) {
                $this->indexDocument($docId, $src['text'], $src['metadata'], $chunkSize, $overlap);
            }
            $this->dirtyDocs = [];
        } else {
            foreach (array_keys($this->dirtyDocs) as $docId) {
                $this->removeDocumentChunks($docId);
                if (isset($this->sources[$docId])) {
                    $src = $this->sources[$docId];
                    $this->indexDocument($docId, $src['text'], $src['metadata'], $chunkSize, $overlap);
                }
            }
            $this->dirtyDocs = [];
        }

        $this->rebuildBm25();
        $this->indexed = true;
    }

    public function indexBuild(int $chunkSize = 450, int $overlap = 80): void { $this->buildIndex($chunkSize, $overlap); }
    public function indexUpdate(?string $docId = null): void { $this->buildIndex($this->indexConfig['size'], $this->indexConfig['overlap']); }
    public function indexCompact(): void {}

    public function indexSave(string $path): void
    {
        $payload = [
            'sources' => $this->sources,
            'index_config' => $this->indexConfig,
            'chunks' => $this->chunks,
            'chunk_entities' => $this->chunkEntities,
        ];
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function indexLoad(string $path): void
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \InvalidArgumentException('Unable to read index file: ' . $path);
        }
        /** @var array<string,mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->sources = is_array($decoded['sources'] ?? null) ? $decoded['sources'] : [];
        $this->indexConfig = is_array($decoded['index_config'] ?? null)
            ? ['size' => (int) (($decoded['index_config']['size'] ?? 450)), 'overlap' => (int) (($decoded['index_config']['overlap'] ?? 80))]
            : ['size' => 450, 'overlap' => 80];
        $this->chunks = is_array($decoded['chunks'] ?? null) ? $decoded['chunks'] : [];
        $this->chunkEntities = is_array($decoded['chunk_entities'] ?? null) ? $decoded['chunk_entities'] : [];
        $this->dirtyDocs = [];
        $this->rebuildBm25();
        $this->indexed = true;
    }

    /** @param array<string, string> $filters
     * @return array<int, array{id:string,docId:string,score:float,text:string,startOffset:int,endOffset:int,metadata:array<string,mixed>}>
     */
    public function search(string $query, int $topK = 5, array $filters = []): array
    {
        $this->ensureIndex();
        if ($this->bm25 === null) {
            return [];
        }

        $parsed = $this->parseStructuredQuery($query);
        $mergedFilters = array_merge($parsed['filters'], $filters);
        $searchQuery = $parsed['strict'] && $parsed['search_query'] !== '' ? $parsed['search_query'] : $query;
        $rows = $this->bm25->search($searchQuery, max(1, $topK * 10));

        $out = [];
        foreach ($rows as $row) {
            $idx = (int) ($row['id'] ?? -1);
            if (!isset($this->chunks[$idx])) {
                continue;
            }
            $chunk = $this->chunks[$idx];
            if (!$this->matchesFilters($chunk, $mergedFilters) || !$this->matchesQueryConstraints($chunk['text'], $parsed)) {
                continue;
            }
            $score = (float) ($row['score'] ?? 0.0) + $this->constraintBoost($chunk['text'], $parsed);
            $out[] = [
                'id' => $chunk['id'], 'docId' => $chunk['docId'], 'score' => $score,
                'text' => $chunk['text'], 'startOffset' => $chunk['startOffset'], 'endOffset' => $chunk['endOffset'],
                'metadata' => $chunk['metadata'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($out, 0, max(1, $topK));
    }

    /** @param array<string, string> $filters
     * @return array<int, array{id:string,docId:string,score:float,text:string,startOffset:int,endOffset:int,metadata:array<string,mixed>}>
     */
    public function searchBm25(string $query, array $filters = [], int $topK = 5): array
    {
        return $this->search($query, $topK, $filters);
    }

    /** @return array<string,mixed>|null */
    public function fetchDoc(string $docId): ?array
    {
        if (!isset($this->sources[$docId])) {
            return null;
        }
        return ['id' => $docId, 'text' => $this->sources[$docId]['text'], 'metadata' => $this->sources[$docId]['metadata']];
    }

    public function fetchSnippet(string $docId, int $start, int $end): ?string
    {
        $doc = $this->sources[$docId]['text'] ?? null;
        if (!is_string($doc)) {
            return null;
        }
        return substr($doc, max(0, $start), max(0, $end - $start));
    }

    /** @return array<int, array{docId:string,startOffset:int,endOffset:int,match:string}> */
    public function extractRegex(string $pattern, int $limit = 50): array
    {
        $out = [];
        foreach ($this->sources as $docId => $src) {
            if (@preg_match_all($pattern, $src['text'], $m, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }
            foreach (($m[0] ?? []) as $hit) {
                [$match, $offset] = $hit;
                $out[] = ['docId' => $docId, 'startOffset' => (int) $offset, 'endOffset' => (int) $offset + strlen((string) $match), 'match' => (string) $match];
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }
        return $out;
    }

    /** @return array<int, array{text:string,label:string,docId:string,start:int,end:int,confidence:float}> */
    public function extractEntities(array $types = []): array
    {
        $this->ensureIndex();
        $typesNorm = array_map('strtoupper', $types);
        $out = [];
        foreach ($this->chunks as $chunk) {
            $entities = $this->chunkEntities[$chunk['id']] ?? [];
            foreach ($entities as $e) {
                if ($typesNorm !== [] && !in_array(strtoupper((string) $e['label']), $typesNorm, true)) {
                    continue;
                }
                $out[] = [
                    'text' => (string) $e['text'],
                    'label' => (string) $e['label'],
                    'docId' => (string) $chunk['docId'],
                    'start' => (int) $chunk['startOffset'] + (int) $e['start'],
                    'end' => (int) $chunk['startOffset'] + (int) $e['end'],
                    'confidence' => (float) $e['confidence'],
                ];
            }
        }
        return $out;
    }

    /** @return array<int, array{docId:string,text:string,start:int,end:int}> */
    public function summarizeExtractive(string $query, int $maxSentences = 3, int $topK = 5): array
    {
        $hits = $this->search($query, $topK);
        if ($hits === []) {
            return [];
        }
        $sentences = [];
        $splitter = new SentenceTokenizer();
        foreach ($hits as $hit) {
            foreach ($this->sentenceOffsetsInText($hit['text'], $splitter) as $row) {
                $sentence = $row['sentence'];
                $sentences[] = [
                    'docId' => $hit['docId'],
                    'text' => $sentence,
                    'start' => $hit['startOffset'] + $row['start'],
                    'end' => $hit['startOffset'] + $row['end'],
                    'score' => $this->sentenceScore($query, $sentence),
                ];
            }
        }
        usort($sentences, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $picked = array_slice($sentences, 0, max(1, $maxSentences));
        return array_map(static fn (array $s): array => ['docId' => $s['docId'], 'text' => $s['text'], 'start' => $s['start'], 'end' => $s['end']], $picked);
    }

    /** @return array{answer:string,parts:array<int,array{question:string,answer:string,citations:array<int,array{docId:string,start:int,end:int,snippet:string}>}>,citations:array<int,array{docId:string,start:int,end:int,snippet:string}>,tool_calls:array<int,array<string,mixed>>,debug:array<string,mixed>} */
    public function queryMulti(string $prompt, array $options = []): array
    {
        $parts = $this->splitQuestions($prompt);
        $redact = (bool) ($options['redact_pii'] ?? true);
        if ($parts === []) {
            return [
                'answer' => 'No query provided.',
                'parts' => [],
                'citations' => [],
                'tool_calls' => [],
                'debug' => ['parts' => [], 'resolved_parts' => []],
            ];
        }

        $state = ['lastLocation' => null, 'lastSubject' => null];
        $outParts = [];
        $allCitations = [];
        $toolCalls = [];
        $resolvedParts = [];

        foreach ($parts as $question) {
            $resolved = $this->resolveCoreferences($question, $state);
            $resolvedParts[] = $resolved;
            $response = $this->answerSubQuestion($resolved, $state, $options);
            foreach (($response['tool_calls'] ?? []) as $tc) {
                $toolCalls[] = $tc;
            }

            $part = [
                'question' => $question,
                'answer' => $response['answer'],
                'citations' => $response['citations'],
                'debug' => $response['debug'],
            ];
            $outParts[] = $part;
            $allCitations = array_merge($allCitations, $response['citations']);

            $state = $this->updateConversationState($resolved, $state);
            if (($response['inferred_location'] ?? null) !== null) {
                $state['lastLocation'] = $response['inferred_location'];
            }
            if (($response['inferred_subject'] ?? null) !== null) {
                $state['lastSubject'] = $response['inferred_subject'];
            }
        }

        $allCitations = $this->mergeCitations($allCitations);

        $lines = [];
        foreach ($outParts as $i => $part) {
            $lines[] = sprintf('%d) %s', $i + 1, $part['answer']);
            if ($part['citations'] === []) {
                $lines[] = '   Citations: none';
                continue;
            }
            $c = array_map(static fn (array $x): string => sprintf('%s:%d-%d', $x['docId'], $x['start'], $x['end']), $part['citations']);
            $lines[] = '   Citations: ' . implode(', ', $c);
        }
        $answer = implode("\n", $lines);

        if ($redact) {
            $answer = $this->redactor->redact($answer);
            foreach ($outParts as &$part) {
                $part['answer'] = $this->redactor->redact($part['answer']);
                foreach ($part['citations'] as &$c) {
                    $c['snippet'] = $this->redactor->redact($c['snippet']);
                }
                unset($c);
            }
            unset($part);
            foreach ($allCitations as &$c) {
                $c['snippet'] = $this->redactor->redact($c['snippet']);
            }
            unset($c);
        }

        $allSkills = [];
        foreach ($outParts as $part) {
            foreach (($part['debug']['skills'] ?? []) as $s) {
                $allSkills[$s] = true;
            }
        }

        return [
            'answer' => $answer,
            'parts' => $outParts,
            'citations' => $allCitations,
            'tool_calls' => $toolCalls,
            'debug' => [
                'intent' => 'MULTI',
                'skills' => array_values(array_keys($allSkills)),
                'structured_query' => $this->parseStructuredQuery($prompt),
                'parts' => $parts,
                'resolved_parts' => $resolvedParts,
                'state' => $state,
            ],
        ];
    }

    /** @return array<int,string> */
    public function splitQuestions(string $prompt): array
    {
        $q = trim($prompt);
        if ($q === '') {
            return [];
        }

        $first = preg_split('/[?;\n]+/u', $q) ?: [];
        $parts = [];
        foreach ($first as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }

            $sub = preg_split('/\s+and\s+/iu', $piece) ?: [];
            if (count($sub) <= 1) {
                $parts[] = $piece;
                continue;
            }

            $carry = array_shift($sub) ?? '';
            foreach ($sub as $seg) {
                $seg = trim($seg);
                if ($seg === '') {
                    continue;
                }
                if (preg_match('/^(what|where|when|how|which|who|is|are|does|do)\b/i', $seg) === 1) {
                    if (trim($carry) !== '') {
                        $parts[] = trim($carry);
                    }
                    $carry = $seg;
                } else {
                    $carry .= ' and ' . $seg;
                }
            }
            if (trim($carry) !== '') {
                $parts[] = trim($carry);
            }
        }

        return array_slice(array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== '')), 0, 5);
    }

    /** @return array{answer:string,citations:array<int,array{docId:string,start:int,end:int,snippet:string}>,tool_calls:array<int,array<string,mixed>>,debug:array<string,mixed>} */
    public function query(string $prompt, array $options = []): array
    {
        $q = trim($prompt);
        $redact = (bool) ($options['redact_pii'] ?? true);
        $topK = isset($options['topK']) ? max(1, (int) $options['topK']) : 6;
        $filters = isset($options['filters']) && is_array($options['filters']) ? $options['filters'] : [];
        if ($q === '') {
            return ['answer' => 'No query provided.', 'citations' => [], 'tool_calls' => [], 'debug' => ['intent' => 'NONE', 'query' => $prompt]];
        }

        $intent = $this->detectIntent($q);
        $citations = [];
        $answer = '';

        if ($intent === 'ENTITY_COUNTRY') {
            $countries = [];
            foreach ($this->extractEntities(['COUNTRY']) as $e) {
                $countries[mb_strtolower($e['text'])] = $e['text'];
            }
            $answer = 'Countries mentioned: ' . ($countries === [] ? 'none' : implode(', ', array_values($countries)));
        } elseif ($intent === 'GLOSSARY') {
            $answer = $this->buildGlossary(12);
        } elseif ($intent === 'SUMMARY') {
            $summary = $this->summarizeExtractive($q, 4, 6);
            if ($summary === []) {
                $answer = 'No summary evidence found.';
            } else {
                $lines = ['Summary (extractive):'];
                foreach ($summary as $s) {
                    $lines[] = '- ' . $s['text'] . ' [' . $s['docId'] . ':' . $s['start'] . '-' . $s['end'] . ']';
                    $citations[] = ['docId' => $s['docId'], 'start' => $s['start'], 'end' => $s['end'], 'snippet' => $s['text']];
                }
                $answer = implode("\n", $lines);
            }
        } else {
            $hits = $this->searchBm25($q, $filters, $topK);
            $answer = $this->formatHits($hits, false);
            foreach ($hits as $h) {
                $snippet = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $h['text'])), 0, 220);
                $citations[] = ['docId' => $h['docId'], 'start' => $h['startOffset'], 'end' => $h['endOffset'], 'snippet' => $snippet];
            }
        }

        if ($redact) {
            $answer = $this->redactor->redact($answer);
            foreach ($citations as &$c) {
                $c['snippet'] = $this->redactor->redact($c['snippet']);
            }
            unset($c);
        }

        return [
            'answer' => $answer,
            'citations' => $citations,
            'tool_calls' => [],
            'debug' => ['intent' => $intent, 'query' => $q, 'structured' => $this->parseStructuredQuery($q)],
        ];
    }

    public function generate(string $prompt, array $options = []): string
    {
        $result = $this->query($prompt, $options);
        if ((bool) ($options['structured'] ?? false)) {
            return json_encode($result, JSON_THROW_ON_ERROR);
        }
        return (string) $result['answer'];
    }

    public function streamGenerate(string $prompt, array $options = []): iterable
    {
        $text = $this->generate($prompt, $options);
        for ($i = 0, $n = strlen($text); $i < $n; $i += 80) {
            yield substr($text, $i, 80);
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function formatTable(array $rows): string
    {
        if ($rows === []) {
            return '(empty)';
        }
        $headers = array_keys($rows[0]);
        $lines = [implode(' | ', $headers), implode(' | ', array_fill(0, count($headers), '---'))];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $h) {
                $cells[] = (string) ($row[$h] ?? '');
            }
            $lines[] = implode(' | ', $cells);
        }
        return implode("\n", $lines);
    }

    /** @param array<int, array{id:string,docId:string,score:float,text:string,startOffset:int,endOffset:int,metadata:array<string,mixed>}> $hits */
    public function formatCitations(array $hits): string
    {
        if ($hits === []) {
            return 'No citations.';
        }
        return implode(', ', array_map(static fn (array $h): string => sprintf('%s:%d-%d', $h['docId'], (int) $h['startOffset'], (int) $h['endOffset']), $hits));
    }

    public function redactPii(string $text): string { return $this->redactor->redact($text); }

    public function glossary(int $topN = 12): string
    {
        return $this->buildGlossary($topN);
    }

    private function indexDocument(string $docId, string $text, array $metadata, int $chunkSize, int $overlap): void
    {
        foreach ($this->chunkFixed($docId, $text, $metadata, $chunkSize, $overlap) as $chunk) {
            $this->chunks[] = $chunk;
            $entities = [];
            foreach ($this->nerTagger->extract($chunk['text']) as $e) {
                $entities[] = ['text' => $e->text, 'label' => $e->label, 'start' => $e->start, 'end' => $e->end, 'confidence' => $e->confidence];
            }
            $this->chunkEntities[$chunk['id']] = $entities;
        }
    }

    private function removeDocumentChunks(string $docId): void
    {
        $kept = [];
        foreach ($this->chunks as $chunk) {
            if ($chunk['docId'] === $docId) {
                unset($this->chunkEntities[$chunk['id']]);
                continue;
            }
            $kept[] = $chunk;
        }
        $this->chunks = $kept;
    }

    private function rebuildBm25(): void
    {
        $this->bm25 = new BM25();
        foreach ($this->chunks as $chunk) {
            $this->bm25->addDocuments([$chunk['text']]);
        }
        $this->bm25->build();
    }

    /** @return array<int, array{id:string,docId:string,text:string,startOffset:int,endOffset:int,metadata:array<string,mixed>}> */
    private function chunkFixed(string $docId, string $text, array $metadata, int $chunkSize, int $overlap): array
    {
        $chunkSize = max(100, $chunkSize);
        $overlap = max(0, min($overlap, $chunkSize - 1));
        $step = $chunkSize - $overlap;
        $len = strlen($text);
        $out = [];
        $i = 0;
        for ($start = 0; $start < $len; $start += $step) {
            $piece = substr($text, $start, $chunkSize);
            if (trim($piece) === '') {
                continue;
            }
            $out[] = [
                'id' => $docId . '#chunk-' . $i,
                'docId' => $docId,
                'text' => $piece,
                'startOffset' => $start,
                'endOffset' => $start + strlen($piece),
                'metadata' => array_merge($metadata, ['chunk_index' => $i]),
            ];
            $i++;
        }
        return $out;
    }

    private function ensureIndex(): void
    {
        if (!$this->indexed || $this->dirtyDocs !== []) {
            $this->buildIndex($this->indexConfig['size'], $this->indexConfig['overlap']);
        }
    }

    /** @param array<string, string> $filters */
    private function matchesFilters(array $chunk, array $filters): bool
    {
        foreach ($filters as $k => $v) {
            if ($k === 'docId' && (string) $chunk['docId'] !== (string) $v) {
                return false;
            }
            if ($k === 'source' && (string) ($chunk['metadata']['source'] ?? '') !== (string) $v) {
                return false;
            }
            if ($k === 'tag' && (string) ($chunk['metadata']['tag'] ?? '') !== (string) $v) {
                return false;
            }
            if ($k === 'date_from' || $k === 'date_to') {
                $metaDate = (string) ($chunk['metadata']['date'] ?? '');
                if ($metaDate === '') {
                    return false;
                }
                $ym = substr($metaDate, 0, 7);
                if ($k === 'date_from' && $ym < (string) $v) {
                    return false;
                }
                if ($k === 'date_to' && $ym > (string) $v) {
                    return false;
                }
            }
        }
        return true;
    }

    private function formatHits(array $hits, bool $redact): string
    {
        if ($hits === []) {
            return 'No results found.';
        }
        $lines = ['Top results:'];
        foreach ($hits as $i => $hit) {
            $snippet = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $hit['text'])), 0, 180);
            $lines[] = sprintf('%d) %s score=%.4f [%s:%d-%d]', $i + 1, $snippet, (float) $hit['score'], $hit['docId'], (int) $hit['startOffset'], (int) $hit['endOffset']);
        }
        $lines[] = 'Suggested follow-up: "show more", "summarize", "list all countries mentioned"';
        $out = implode("\n", $lines);
        return $redact ? $this->redactor->redact($out) : $out;
    }

    private function sentenceScore(string $query, string $sentence): float
    {
        $tok = new UnicodeWordTokenizer();
        $qTerms = array_unique(array_map(static fn ($t): string => $t->norm, $tok->tokenize($query)));
        $sTerms = array_unique(array_map(static fn ($t): string => $t->norm, $tok->tokenize($sentence)));
        if ($qTerms === [] || $sTerms === []) {
            return 0.0;
        }
        return count(array_intersect($qTerms, $sTerms)) / max(1, count($qTerms));
    }

    private function buildGlossary(int $topN = 12): string
    {
        $freq = [];
        $tok = new UnicodeWordTokenizer();
        foreach ($this->sources as $src) {
            foreach ($tok->tokenize($src['text']) as $t) {
                if (mb_strlen($t->norm) < 4) {
                    continue;
                }
                $freq[$t->norm] = ($freq[$t->norm] ?? 0) + 1;
            }
        }
        arsort($freq);
        $terms = array_slice(array_keys($freq), 0, max(1, $topN));
        return $terms === [] ? 'Glossary: (no terms)' : 'Glossary: ' . implode(', ', $terms);
    }

    private function parseJson(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $raw;
        }
        $lines = [];
        $walker = function (array $node, string $prefix = '') use (&$walker, &$lines): void {
            foreach ($node as $k => $v) {
                $path = $prefix === '' ? (string) $k : $prefix . '.' . (string) $k;
                if (is_array($v)) {
                    $walker($v, $path);
                } elseif (is_scalar($v) || $v === null) {
                    $lines[] = $path . ': ' . (string) $v;
                }
            }
        };
        $walker($decoded);
        return implode("\n", $lines);
    }

    private function parseCsv(string $raw): string
    {
        $rows = preg_split('/\R/u', trim($raw)) ?: [];
        if ($rows === []) {
            return '';
        }
        $header = str_getcsv(array_shift($rows) ?: '');
        $out = [];
        foreach ($rows as $row) {
            if (trim($row) === '') {
                continue;
            }
            $values = str_getcsv($row);
            $pairs = [];
            foreach ($header as $i => $h) {
                $pairs[] = $h . ': ' . (string) ($values[$i] ?? '');
            }
            $out[] = implode(', ', $pairs);
        }
        return implode("\n", $out);
    }

    private function isAllowedPath(string $path): bool
    {
        if ($this->allowlistPaths === []) {
            return true;
        }
        $real = realpath($path);
        if ($real === false) {
            return false;
        }
        foreach ($this->allowlistPaths as $allowed) {
            $allowedReal = realpath($allowed);
            if ($allowedReal !== false && str_starts_with($real, $allowedReal)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{strict:bool,search_query:string,must:array<int,string>,should:array<int,string>,must_not:array<int,string>,phrases:array<int,string>,filters:array<string,string>,near:array<int,array{a:string,b:string,window:int}>} */
    private function parseStructuredQuery(string $query): array
    {
        $q = trim($query);
        $original = $q;
        $filters = [];
        $hasFilter = false;

        if (preg_match_all('/\b(source|tag|doc|docId):([^\s]+)/i', $q, $fm, PREG_SET_ORDER)) {
            foreach ($fm as $m) {
                $key = strtolower((string) $m[1]);
                $value = trim((string) $m[2]);
                $filters[$key === 'doc' || $key === 'docid' ? 'docId' : $key] = $value;
                $hasFilter = true;
            }
            $q = (string) preg_replace('/\b(source|tag|doc|docId):([^\s]+)/i', ' ', $q);
        }

        if (preg_match('/\bdate:([0-9]{4}-[0-9]{2})\.\.([0-9]{4}-[0-9]{2})\b/i', $q, $dm) === 1) {
            $filters['date_from'] = $dm[1];
            $filters['date_to'] = $dm[2];
            $hasFilter = true;
            $q = (string) preg_replace('/\bdate:[0-9]{4}-[0-9]{2}\.\.[0-9]{4}-[0-9]{2}\b/i', ' ', $q);
        }

        $near = [];
        if (preg_match_all('/near\(([^,]+),\s*"([^"]+)",\s*window\s*=\s*(\d+)\)/i', $q, $nm, PREG_SET_ORDER)) {
            foreach ($nm as $n) {
                $near[] = ['a' => mb_strtolower(trim((string) $n[1], " '\"")), 'b' => mb_strtolower(trim((string) $n[2])), 'window' => max(1, (int) $n[3])];
            }
            $q = (string) preg_replace('/near\(([^,]+),\s*"([^"]+)",\s*window\s*=\s*(\d+)\)/i', ' ', $q);
        }

        $phrases = [];
        if (preg_match_all('/(?:\bphrase\s+)?"([^"]+)"/iu', $q, $pm)) {
            foreach (($pm[1] ?? []) as $p) {
                $p = trim((string) $p);
                if ($p !== '') {
                    $phrases[] = mb_strtolower($p);
                }
            }
            $q = (string) preg_replace('/(?:\bphrase\s+)?"([^"]+)"/iu', ' ', $q);
        }

        $must = [];
        $should = [];
        $mustNot = [];
        $op = 'AND';
        $negate = false;
        $hasBoolean = preg_match('/\b(AND|OR|NOT)\b/i', $original) === 1;
        foreach (preg_split('/\s+/u', trim($q)) ?: [] as $part) {
            $u = strtoupper($part);
            if ($u === 'AND' || $u === 'OR') {
                $op = $u;
                continue;
            }
            if ($u === 'NOT') {
                $negate = true;
                continue;
            }
            $term = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}_\-]+/u', '', $part)));
            if ($term === '') {
                continue;
            }
            if ($negate) {
                $mustNot[] = $term;
                $negate = false;
            } elseif ($op === 'OR') {
                $should[] = $term;
            } else {
                $must[] = $term;
            }
        }

        $searchTerms = array_merge($must, $should, $phrases);
        foreach ($near as $n) {
            $searchTerms[] = $n['a'];
            $searchTerms[] = $n['b'];
        }

        return [
            'strict' => $hasFilter || $hasBoolean || $phrases !== [] || $near !== [],
            'search_query' => trim(implode(' ', array_unique(array_filter($searchTerms, static fn ($x): bool => $x !== '')))),
            'must' => array_values(array_unique($must)),
            'should' => array_values(array_unique($should)),
            'must_not' => array_values(array_unique($mustNot)),
            'phrases' => array_values(array_unique($phrases)),
            'filters' => $filters,
            'near' => $near,
        ];
    }

    /** @param array{strict:bool,search_query:string,must:array<int,string>,should:array<int,string>,must_not:array<int,string>,phrases:array<int,string>,filters:array<string,string>,near:array<int,array{a:string,b:string,window:int}>} $parsed */
    private function matchesQueryConstraints(string $text, array $parsed): bool
    {
        if (!$parsed['strict']) {
            return true;
        }
        $hay = mb_strtolower($text);
        foreach ($parsed['phrases'] as $phrase) {
            if (!str_contains($hay, $phrase)) {
                return false;
            }
        }
        foreach ($parsed['must'] as $t) {
            if (!str_contains($hay, $t)) {
                return false;
            }
        }
        foreach ($parsed['must_not'] as $t) {
            if (str_contains($hay, $t)) {
                return false;
            }
        }
        if ($parsed['should'] !== []) {
            $ok = false;
            foreach ($parsed['should'] as $t) {
                if (str_contains($hay, $t)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return false;
            }
        }
        foreach ($parsed['near'] as $n) {
            if (!$this->matchNear($hay, $n['a'], $n['b'], $n['window'])) {
                return false;
            }
        }
        return true;
    }

    private function matchNear(string $hay, string $a, string $b, int $window): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return false;
        }

        $positionsA = $this->allPositions($hay, $a);
        $positionsB = $this->allPositions($hay, $b);
        if ($positionsA === [] || $positionsB === []) {
            return false;
        }

        $best = PHP_INT_MAX;
        foreach ($positionsA as $pa) {
            foreach ($positionsB as $pb) {
                $d = abs($pa - $pb);
                if ($d < $best) {
                    $best = $d;
                }
            }
        }

        // less strict: allow near matches within an expanded window to improve recall,
        // while still preferring tighter distances via score boosts.
        $effectiveWindow = max($window, (int) round($window * 1.8));
        return $best <= $effectiveWindow;
    }

    /** @return array<int, int> */
    private function allPositions(string $hay, string $needle): array
    {
        $positions = [];
        $offset = 0;
        while (true) {
            $pos = mb_stripos($hay, $needle, $offset);
            if (!is_int($pos)) {
                break;
            }
            $positions[] = $pos;
            $offset = $pos + max(1, mb_strlen($needle));
        }

        return $positions;
    }

    /** @param array{strict:bool,search_query:string,must:array<int,string>,should:array<int,string>,must_not:array<int,string>,phrases:array<int,string>,filters:array<string,string>,near:array<int,array{a:string,b:string,window:int}>} $parsed */
    private function constraintBoost(string $text, array $parsed): float
    {
        if (!$parsed['strict']) {
            return 0.0;
        }
        $hay = mb_strtolower($text);
        $boost = 0.0;
        foreach ($parsed['phrases'] as $p) { if (str_contains($hay, $p)) { $boost += 0.2; } }
        foreach ($parsed['must'] as $t) { if (str_contains($hay, $t)) { $boost += 0.05; } }
        foreach ($parsed['should'] as $t) { if (str_contains($hay, $t)) { $boost += 0.03; } }
        foreach ($parsed['near'] as $n) { if ($this->matchNear($hay, $n['a'], $n['b'], $n['window'])) { $boost += 0.25; } }
        return $boost;
    }

    private function detectIntent(string $query): string
    {
        $q = mb_strtolower($query);
        if (str_contains($q, 'countries mentioned')) { return 'ENTITY_COUNTRY'; }
        if (str_contains($q, 'glossary')) { return 'GLOSSARY'; }
        if (str_contains($q, 'summarize') || str_contains($q, 'summary')) { return 'SUMMARY'; }
        return 'SEARCH';
    }

    /** @return array{answer:string,citations:array<int,array{docId:string,start:int,end:int,snippet:string}>,tool_calls:array<int,array<string,mixed>>,inferred_location:?string,inferred_subject:?string,debug:array<string,mixed>} */
    private function answerSubQuestion(string $question, array $state, array $options): array
    {
        $q = trim($question);
        $route = $this->routeSkillsForQuestion($q);
        $toolCalls = [];

        $answer = '';
        $citations = [];
        $inferredLocation = null;
        $inferredSubject = null;
        $geoFactRequired = preg_match('/\b(where\s+is|located|coordinates?|nearest|near)\b/i', $q) === 1;
        $geoResolved = false;

        foreach ($route['skills'] as $skill) {
            if ($skill === 'GeoSkill') {
                $geo = $this->runGeoSkill($q, $state);
                $toolCalls[] = ['tool' => 'geo_skill', 'input' => ['question' => $q], 'result' => ['found' => $geo['found']]];
                if ($geo['found']) {
                    $answer = $geo['answer'];
                    $citations = $geo['citations'];
                    $inferredLocation = $geo['location'];
                    $geoResolved = true;
                } elseif ($route['skills'] === ['GeoSkill']) {
                    $answer = 'Not found in local corpus/datasets.';
                }
                continue;
            }

            if ($skill === 'PolicySkill') {
                $summary = $this->summarizeExtractive($q, 3, 5);
                $toolCalls[] = ['tool' => 'policy_skill', 'input' => ['query' => $q], 'result' => ['count' => count($summary)]];
                if ($summary !== []) {
                    $lines = [];
                    foreach ($summary as $s) {
                        $lines[] = $s['text'];
                        $citations[] = ['docId' => $s['docId'], 'start' => $s['start'], 'end' => $s['end'], 'snippet' => $s['text']];
                    }
                    $answer = implode(' ', $lines);
                }
                continue;
            }

            if ($skill === 'ExtractSkill') {
                $extract = $this->runExtractSkill($q);
                $toolCalls[] = ['tool' => 'extract_skill', 'input' => ['query' => $q], 'result' => ['count' => count($extract['citations'])]];
                if ($extract['answer'] !== '') {
                    $answer = $extract['answer'];
                    $citations = array_merge($citations, $extract['citations']);
                }
                continue;
            }

            if ($skill === 'GlossarySkill') {
                $toolCalls[] = ['tool' => 'glossary_skill', 'input' => ['query' => $q]];
                $answer = $this->buildGlossary(12);
                continue;
            }

            if ($skill === 'SearchSkill') {
                if ($geoFactRequired && !$geoResolved) {
                    $toolCalls[] = ['tool' => 'search_skill', 'input' => ['query' => '(skipped due to unresolved geo fact)'], 'result' => ['count' => 0, 'skipped' => true]];
                    continue;
                }
                $searchQuery = $this->buildSearchQueryWithContext($q, $state);
                $hits = $this->searchBm25($searchQuery, [], 4);
                $needPriceEvidence = preg_match('/\b(price|cost|amount)\b/i', $q) === 1;
                $needHoursEvidence = preg_match('/\b(hours|opening|business start|business hours|open)\b/i', $q) === 1;
                if (($needPriceEvidence || $needHoursEvidence) && !$this->hasRequiredEvidenceInHits($hits, $needPriceEvidence, $needHoursEvidence)) {
                    $hits = [];
                }
                $toolCalls[] = ['tool' => 'search_skill', 'input' => ['query' => $searchQuery], 'result' => ['count' => count($hits)]];
                if ($hits !== []) {
                    $top = $hits[0];
                    $answer = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $top['text'])), 0, 180);
                    foreach ($hits as $h) {
                        $citations[] = [
                            'docId' => $h['docId'],
                            'start' => (int) $h['startOffset'],
                            'end' => (int) $h['endOffset'],
                            'snippet' => mb_substr(trim((string) preg_replace('/\s+/u', ' ', $h['text'])), 0, 220),
                        ];
                    }
                }
            }
        }

        if ($answer === '') {
            $answer = in_array('GeoSkill', $route['skills'], true) ? 'Not found in local corpus/datasets.' : 'Not found in local corpus.';
        }

        if (preg_match('/price\s+of\s+([\p{L}\p{N}\s\-_]+)/iu', $q, $m) === 1) {
            $inferredSubject = trim((string) $m[1]);
        }

        return [
            'answer' => $answer,
            'citations' => $this->mergeCitations($citations),
            'tool_calls' => $toolCalls,
            'inferred_location' => $inferredLocation,
            'inferred_subject' => $inferredSubject,
            'debug' => [
                'intent' => $route['intent'],
                'skills' => $route['skills'],
                'structured_query' => $route['structured_query'],
            ],
        ];
    }

    /** @return array{intent:string,skills:array<int,string>,structured_query:array{strict:bool,search_query:string,must:array<int,string>,should:array<int,string>,must_not:array<int,string>,phrases:array<int,string>,filters:array<string,string>,near:array<int,array{a:string,b:string,window:int}>}} */
    private function routeSkillsForQuestion(string $question): array
    {
        $q = mb_strtolower($question);
        $skills = [];

        $hasCoords = preg_match('/-?\d{1,2}\.\d+\s*,\s*-?\d{1,3}\.\d+/', $question) === 1;
        $hasGeoIntent = $hasCoords || preg_match('/\b(where\s+is|located|near|nearest|coordinates?|city|state|country|\bin\b)\b/i', $question) === 1;
        if ($hasGeoIntent) {
            $skills[] = 'GeoSkill';
        }

        if (preg_match('/\b(countries|cities|states)\s+mentioned\b/i', $question) === 1) {
            $skills[] = 'GeoSkill';
            $skills[] = 'ExtractSkill';
        }

        if (preg_match('/\b(summarize|summary|policy|section|sections)\b/i', $question) === 1) {
            $skills[] = 'PolicySkill';
        }

        if (preg_match('/\b(list|extract|emails?|phones?|ids?|countries?|cities?|states?)\b/i', $question) === 1) {
            $skills[] = 'ExtractSkill';
        }

        if (preg_match('/\b(glossary|terms?)\b/i', $question) === 1) {
            $skills[] = 'GlossarySkill';
        }

        if ($skills === []) {
            $skills[] = 'SearchSkill';
        } elseif (!in_array('SearchSkill', $skills, true) && !in_array('GlossarySkill', $skills, true)) {
            $skills[] = 'SearchSkill';
        }

        $skills = array_values(array_unique($skills));
        $intent = $skills[0] ?? 'SearchSkill';

        return [
            'intent' => $intent,
            'skills' => $skills,
            'structured_query' => $this->parseStructuredQuery($question),
        ];
    }

    /** @return array{found:bool,answer:string,citations:array<int,array{docId:string,start:int,end:int,snippet:string}>,location:?string} */
    private function runGeoSkill(string $question, array $state): array
    {
        $geoClass = 'ML\\IDEA\\Geo\\GeoService';
        if (!class_exists($geoClass)) {
            return ['found' => false, 'answer' => '', 'citations' => [], 'location' => null];
        }

        try {
            /** @var object $geo */
            $geo = new $geoClass();
            $location = $this->extractLocationCandidate($question, $state);
            if ($location === null || !method_exists($geo, 'searchPlace')) {
                return ['found' => false, 'answer' => '', 'citations' => [], 'location' => null];
            }

            /** @var array<int,array<string,mixed>> $hits */
            $hits = $geo->searchPlace($location, 1);
            if ($hits === []) {
                return ['found' => false, 'answer' => '', 'citations' => [], 'location' => null];
            }
            $best = $hits[0];
            $name = (string) ($best['name'] ?? $location);
            $type = (string) ($best['type'] ?? 'PLACE');
            $country = (string) ($best['country_code'] ?? '');
            $answer = $country !== '' ? sprintf('%s is a %s in %s.', $name, $type, $country) : sprintf('%s is a %s.', $name, $type);

            return [
                'found' => true,
                'answer' => $answer,
                'citations' => [['docId' => 'dataset:geo', 'start' => 0, 'end' => 0, 'snippet' => $answer]],
                'location' => $name,
            ];
        } catch (\Throwable) {
            return ['found' => false, 'answer' => '', 'citations' => [], 'location' => null];
        }
    }

    /** @return array{answer:string,citations:array<int,array{docId:string,start:int,end:int,snippet:string}>} */
    private function runExtractSkill(string $question): array
    {
        $q = mb_strtolower($question);
        $citations = [];
        $items = [];

        if (str_contains($q, 'email')) {
            foreach ($this->extractRegex('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', 20) as $m) {
                $items[] = $m['match'];
                $citations[] = ['docId' => $m['docId'], 'start' => $m['startOffset'], 'end' => $m['endOffset'], 'snippet' => $m['match']];
            }
        }

        if (preg_match('/\b(countries|cities|states)\s+mentioned\b/i', $question) === 1) {
            foreach ($this->extractEntities(['COUNTRY', 'CITY', 'STATE']) as $e) {
                $items[] = (string) $e['text'];
                $citations[] = ['docId' => $e['docId'], 'start' => $e['start'], 'end' => $e['end'], 'snippet' => (string) $e['text']];
            }
        }

        $items = array_values(array_unique(array_filter(array_map('trim', $items), static fn (string $x): bool => $x !== '')));
        if ($items === []) {
            return ['answer' => '', 'citations' => []];
        }

        return ['answer' => 'Extracted: ' . implode(', ', $items), 'citations' => $this->mergeCitations($citations)];
    }

    private function buildSearchQueryWithContext(string $question, array $state): string
    {
        $q = $question;
        $loc = isset($state['lastLocation']) && is_string($state['lastLocation']) ? trim($state['lastLocation']) : '';
        if ($loc !== '' && preg_match('/\b(price|hours|opening|business|there|near|located)\b/i', $q) === 1 && mb_stripos($q, $loc) === false) {
            $q .= ' ' . $loc;
        }
        return trim($q);
    }

    private function extractLocationCandidate(string $question, array $state): ?string
    {
        if (preg_match('/^where\s+is\s+(.+)$/i', trim($question), $m) === 1) {
            return trim((string) $m[1]);
        }

        $loc = $this->extractLocationMentionFromQuestion($question);
        if ($loc !== null) {
            return $loc;
        }

        if (preg_match('/\b(there|that place|that city|that country)\b/i', $question) === 1 && isset($state['lastLocation']) && is_string($state['lastLocation'])) {
            return trim($state['lastLocation']);
        }

        return null;
    }

    /** @param array<int, array{id:string,docId:string,score:float,text:string,startOffset:int,endOffset:int,metadata:array<string,mixed>}> $hits */
    private function hasRequiredEvidenceInHits(array $hits, bool $needPrice, bool $needHours): bool
    {
        foreach ($hits as $h) {
            $t = mb_strtolower((string) ($h['text'] ?? ''));
            if ($needPrice && preg_match('/\b(price|cost|zmw|usd|eur|ksh|amount)\b/u', $t) !== 1) {
                continue;
            }
            if ($needHours && preg_match('/\b(open|opening|hours|starts|start|business)\b/u', $t) !== 1) {
                continue;
            }
            return true;
        }

        return false;
    }

    private function resolveCoreferences(string $question, array $state): string
    {
        $resolved = $question;
        $location = isset($state['lastLocation']) && is_string($state['lastLocation']) ? $state['lastLocation'] : null;
        $subject = isset($state['lastSubject']) && is_string($state['lastSubject']) ? $state['lastSubject'] : null;

        if ($location !== null && $location !== '') {
            $resolved = (string) preg_replace('/\b(there|that place|that city|that country)\b/iu', $location, $resolved);
        }

        if ($subject !== null && $subject !== '' && preg_match('/\b(price|hours|opening|business start|business hours)\b/i', $resolved) === 1) {
            $resolved = (string) preg_replace('/\b(it|that)\b/iu', $subject, $resolved);
        }

        return trim($resolved);
    }

    /** @return array{lastLocation:?string,lastSubject:?string} */
    private function updateConversationState(string $question, array $state): array
    {
        $next = $state;

        if (preg_match('/^where\s+is\s+(.+)$/i', $question, $m) === 1) {
            $next['lastLocation'] = trim((string) $m[1]);
        }

        $loc = $this->extractLocationMentionFromQuestion($question);
        if ($loc !== null) {
            $next['lastLocation'] = $loc;
        }

        if (preg_match('/price\s+of\s+([\p{L}\p{N}\s\-_]+)/iu', $question, $m) === 1) {
            $next['lastSubject'] = trim((string) $m[1]);
        }
        if (preg_match('/(?:hours|opening time|business hours|business start)\s+of\s+([\p{L}\p{N}\s\-_]+)/iu', $question, $m) === 1) {
            $next['lastSubject'] = trim((string) $m[1]);
        }

        return [
            'lastLocation' => isset($next['lastLocation']) ? (is_string($next['lastLocation']) ? $next['lastLocation'] : null) : null,
            'lastSubject' => isset($next['lastSubject']) ? (is_string($next['lastSubject']) ? $next['lastSubject'] : null) : null,
        ];
    }

    private function extractLocationMentionFromQuestion(string $question): ?string
    {
        $entities = $this->extractEntities(['CITY', 'COUNTRY', 'STATE']);
        $best = null;
        $len = 0;
        foreach ($entities as $e) {
            $text = trim((string) ($e['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (mb_stripos($question, $text) !== false && mb_strlen($text) > $len) {
                $best = $text;
                $len = mb_strlen($text);
            }
        }

        return $best;
    }

    private function resolveWhereWithGeo(string $place): ?string
    {
        $fqcn = 'ML\\IDEA\\Geo\\GeoService';
        if (!class_exists($fqcn)) {
            return null;
        }

        try {
            /** @var object $geo */
            $geo = new $fqcn();
            if (!method_exists($geo, 'searchPlace')) {
                return null;
            }
            /** @var array<int,array<string,mixed>> $hits */
            $hits = $geo->searchPlace($place, 3);
            if ($hits === []) {
                return null;
            }
            $best = $hits[0];
            $name = (string) ($best['name'] ?? $place);
            $type = (string) ($best['type'] ?? 'PLACE');
            $cc = (string) ($best['country_code'] ?? '');
            $tail = $cc !== '' ? ' (' . $cc . ')' : '';
            return sprintf('%s is a %s%s.', $name, $type, $tail);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<int,array{docId:string,start:int,end:int,snippet:string}> $citations
     * @return array<int,array{docId:string,start:int,end:int,snippet:string}>
     */
    private function mergeCitations(array $citations): array
    {
        $seen = [];
        $out = [];
        foreach ($citations as $c) {
            $key = ($c['docId'] ?? '') . ':' . ($c['start'] ?? 0) . '-' . ($c['end'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $c;
        }
        return $out;
    }

    /** @return array<int,array{sentence:string,start:int,end:int}> */
    private function sentenceOffsetsInText(string $text, SentenceTokenizer $splitter): array
    {
        $parts = $splitter->split($text);
        $out = [];
        $cursor = 0;
        foreach ($parts as $sentenceRaw) {
            $sentence = trim($sentenceRaw);
            if ($sentence === '') {
                continue;
            }

            $pos = strpos($text, $sentence, $cursor);
            if (!is_int($pos)) {
                $pos = strpos($text, $sentence);
            }
            if (!is_int($pos)) {
                continue;
            }

            $start = $pos;
            $end = $pos + strlen($sentence);
            $cursor = $end;
            $out[] = ['sentence' => $sentence, 'start' => $start, 'end' => $end];
        }

        return $out;
    }
}
