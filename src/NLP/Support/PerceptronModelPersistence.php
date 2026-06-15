<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Support;

trait PerceptronModelPersistence
{
    /** @return array{weights: array<string, array<string, float>>, labels: array<int, string>} */
    protected function exportModelState(): array
    {
        return [
            'weights' => $this->weights,
            'labels' => $this->labels,
        ];
    }

    /** @param array{weights?: array<string, array<string, float>>, labels?: array<int, string>} $state */
    protected function importModelState(array $state): void
    {
        $this->weights = is_array($state['weights'] ?? null) ? $state['weights'] : [];
        $this->labels = is_array($state['labels'] ?? null) ? array_values($state['labels']) : [];
    }

    /** @return array{weights: array<string, array<string, float>>, labels: array<int, string>} */
    public function toState(): array
    {
        return $this->exportModelState();
    }

    /** @param array{weights?: array<string, array<string, float>>, labels?: array<int, string>} $state */
    public function loadState(array $state): void
    {
        $this->importModelState($state);
    }

    public function toJson(): string
    {
        return json_encode($this->exportModelState(), JSON_THROW_ON_ERROR);
    }

    public function loadJson(string $json): void
    {
        $state = json_decode($json, true);
        if (!is_array($state)) {
            throw new \InvalidArgumentException('Invalid perceptron model JSON.');
        }

        $this->importModelState($state);
    }
}
