<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Backends;

use ML\IDEA\NLP\Contracts\NlpModelBackendInterface;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

/** Ollama-backed neural enrichment for Doc entities (NER-style JSON from /api/generate). */
final class OllamaNlpBackend implements NlpModelBackendInterface
{
    public function __construct(
        private readonly string $model = 'llama3.2',
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
        private readonly string $entityPrompt = 'Extract named entities from the text. Reply with JSON only: {"entities":[{"text":"...","label":"PERSON|ORG|GPE|LOC|DATE|MISC","start":0,"end":0}]}',
    ) {
    }

    public function process(string $text, Doc $draft): Doc
    {
        $prompt = $this->entityPrompt . "\n\nText: " . $text;

        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/api/generate',
            [],
            [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ],
        );

        $raw = (string) ($response['response'] ?? '');
        if ($raw === '') {
            return $draft;
        }

        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            return $draft;
        }

        $entities = $this->parseEntities($parsed, $text);
        if ($entities === []) {
            return $draft;
        }

        $merged = array_merge($draft->ents, $entities);

        return new Doc(
            text: $draft->text,
            tokens: $draft->tokens,
            ents: $merged,
            sents: $draft->sents,
            language: $draft->language,
            languageScores: $draft->languageScores,
            languageSegments: $draft->languageSegments,
            attrs: array_merge($draft->attrs, ['backend' => 'ollama', 'model' => $this->model]),
        );
    }

    /** @return array<int, Entity> */
    private function parseEntities(array $parsed, string $text): array
    {
        $rows = $parsed['entities'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $entities = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $entityText = (string) ($row['text'] ?? '');
            $label = strtoupper((string) ($row['label'] ?? 'MISC'));
            if ($entityText === '') {
                continue;
            }

            $start = isset($row['start']) ? (int) $row['start'] : mb_strpos($text, $entityText);
            if ($start === false || $start < 0) {
                $start = 0;
            }
            $end = isset($row['end']) ? (int) $row['end'] : ($start + mb_strlen($entityText));

            $entities[] = new Entity($entityText, $label, $start, $end, 0.85);
        }

        return $entities;
    }
}
