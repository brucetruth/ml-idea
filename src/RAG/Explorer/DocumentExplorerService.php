<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Explorer;

use ML\IDEA\RAG\LLM\LocalDocumentExplorerClient;

final class DocumentExplorerService
{
    public function __construct(private readonly LocalDocumentExplorerClient $client = new LocalDocumentExplorerClient())
    {
    }

    /** @param array<string,mixed> $metadata */
    public function addSourceText(string $id, string $text, array $metadata = []): void { $this->client->sourceAdd($id, $text, $metadata); }
    /** @param array<string,mixed> $metadata */
    public function addSourcePath(string $path, array $metadata = []): string { return $this->client->addSourcePath($path, $metadata); }
    public function removeSource(string $id): bool { return $this->client->sourceRemove($id); }
    /** @return array<int, array{id:string,size:int,metadata:array<string,mixed>}> */
    public function listSources(): array { return $this->client->sourceList(); }
    /** @return array{doc_count:int,total_chars:int,last_indexed_chunks:int} */
    public function corpusStats(): array { return $this->client->sourceStatsAll(); }

    public function buildIndex(int $chunkSize = 450, int $overlap = 80): void { $this->client->indexBuild($chunkSize, $overlap); }
    public function saveIndex(string $path): void { $this->client->indexSave($path); }
    public function loadIndex(string $path): void { $this->client->indexLoad($path); }

    /** @param array<string,string> $filters
     * @return array<int, array{id:string,docId:string,score:float,text:string,startOffset:int,endOffset:int,metadata:array<string,mixed>}>
     */
    public function search(string $query, array $filters = [], int $topK = 5): array
    {
        return $this->client->searchBm25($query, $filters, $topK);
    }

    /** @return array<int, array{docId:string,text:string,start:int,end:int}> */
    public function summarize(string $query, int $maxSentences = 3, int $topK = 5): array
    {
        return $this->client->summarizeExtractive($query, $maxSentences, $topK);
    }

    /** @return array<int, array{docId:string,startOffset:int,endOffset:int,match:string}> */
    public function regexExtract(string $pattern, int $limit = 50): array
    {
        return $this->client->extractRegex($pattern, $limit);
    }

    /** @param array<int,string> $types
     * @return array<int, array{text:string,label:string,docId:string,start:int,end:int,confidence:float}>
     */
    public function entityExtract(array $types = []): array
    {
        return $this->client->extractEntities($types);
    }

    public function glossary(int $topN = 12): string
    {
        return $this->client->glossary($topN);
    }

    /** @param array<string,mixed> $options
     * @return array{answer:string,citations:array<int,array{docId:string,start:int,end:int,snippet:string}>,tool_calls:array<int,array<string,mixed>>,debug:array<string,mixed>}
     */
    public function query(string $prompt, array $options = []): array
    {
        return $this->client->query($prompt, $options);
    }

    /** @param array<string,mixed> $options */
    public function generate(string $prompt, array $options = []): string
    {
        return $this->client->generate($prompt, $options);
    }

    /** @param array<string,mixed> $options
     * @return iterable<int,string>
     */
    public function streamGenerate(string $prompt, array $options = []): iterable
    {
        return $this->client->streamGenerate($prompt, $options);
    }
}
