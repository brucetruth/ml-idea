<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Backends;

use ML\IDEA\NLP\Contracts\NlpModelBackendInterface;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

/** Hugging Face Inference API backend for token-classification NER enrichment. */
final class HuggingFaceInferenceBackend implements NlpModelBackendInterface
{
    public function __construct(
        private readonly string $model,
        private readonly string $apiToken = '',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
        private readonly string $apiUrl = 'https://api-inference.huggingface.co/models',
    ) {
    }

    public function process(string $text, Doc $draft): Doc
    {
        $headers = $this->apiToken !== '' ? ['Authorization' => 'Bearer ' . $this->apiToken] : [];
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($this->model, '/');

        $response = $this->http->postJson($url, $headers, ['inputs' => $text]);
        $entities = $this->parseTokenClassification($response, $text);

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
            attrs: array_merge($draft->attrs, ['backend' => 'huggingface', 'model' => $this->model]),
        );
    }

    /** @return array<int, Entity> */
    private function parseTokenClassification(mixed $response, string $text): array
    {
        if (!is_array($response)) {
            return [];
        }

        $rows = isset($response[0]) && is_array($response[0]) ? $response : [$response];
        if (isset($rows[0][0]) && is_array($rows[0][0])) {
            $rows = $rows[0];
        }

        $entities = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $entityText = (string) ($row['word'] ?? $row['entity'] ?? $row['text'] ?? '');
            $label = strtoupper((string) ($row['entity_group'] ?? $row['label'] ?? 'MISC'));
            if ($entityText === '' || $label === 'O') {
                continue;
            }

            $start = isset($row['start']) ? (int) $row['start'] : mb_strpos($text, $entityText);
            if ($start === false || $start < 0) {
                $start = 0;
            }
            $end = isset($row['end']) ? (int) $row['end'] : ($start + mb_strlen($entityText));
            $score = isset($row['score']) ? (float) $row['score'] : 0.85;

            $entities[] = new Entity($entityText, $label, $start, $end, $score);
        }

        return $entities;
    }
}
