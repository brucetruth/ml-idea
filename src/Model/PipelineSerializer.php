<?php

declare(strict_types=1);

namespace ML\IDEA\Model;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\Pipeline\PipelineClassifier;
use ML\IDEA\Pipeline\PipelineRegressor;
use ML\IDEA\Pipeline\TabularPipelineClassifier;
use ML\IDEA\Preprocessing\OneHotEncoder;

/** Serialize full ML pipelines (transformers + estimator) to JSON. */
final class PipelineSerializer
{
    public static function save(PipelineClassifier|PipelineRegressor|TabularPipelineClassifier $pipeline, string $path): void
    {
        $json = json_encode(self::export($pipeline), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json) === false) {
            throw new SerializationException(sprintf('Failed to write pipeline to path: %s', $path));
        }
    }

    /** @return array<string, mixed> */
    public static function export(PipelineClassifier|PipelineRegressor|TabularPipelineClassifier $pipeline): array
    {
        if ($pipeline instanceof TabularPipelineClassifier) {
            return $pipeline->toArray();
        }

        if ($pipeline instanceof PipelineClassifier) {
            return $pipeline->toArray();
        }

        return $pipeline->toArray();
    }

    public static function loadClassifier(string $path): PipelineClassifier|TabularPipelineClassifier
    {
        $payload = self::readPayload($path);
        $type = (string) ($payload['type'] ?? 'numeric');

        return match ($type) {
            'tabular' => TabularPipelineClassifier::fromArray($payload),
            default => PipelineClassifier::fromArray($payload),
        };
    }

    public static function loadRegressor(string $path): PipelineRegressor
    {
        return PipelineRegressor::fromArray(self::readPayload($path));
    }

    /** @return array<string, mixed> */
    private static function readPayload(string $path): array
    {
        if (!is_file($path)) {
            throw new SerializationException(sprintf('Pipeline file not found: %s', $path));
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new SerializationException(sprintf('Failed to read pipeline file: %s', $path));
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new SerializationException('Invalid pipeline payload.');
        }

        return $payload;
    }

    /** @param array<int, PersistableModelInterface|object> $transformers */
    public static function exportTransformers(array $transformers): array
    {
        $out = [];
        foreach ($transformers as $transformer) {
            if ($transformer instanceof PersistableModelInterface) {
                $out[] = ['class' => $transformer::class, 'state' => $transformer->toArray()];
            } else {
                throw new SerializationException(sprintf('Transformer %s is not persistable.', $transformer::class));
            }
        }

        return $out;
    }

    public static function loadPersistable(array $entry): PersistableModelInterface
    {
        $class = $entry['class'] ?? null;
        $state = $entry['state'] ?? null;
        if (!is_string($class) || !is_array($state) || !is_subclass_of($class, PersistableModelInterface::class)) {
            throw new SerializationException('Invalid persistable entry in pipeline payload.');
        }

        /** @var class-string<PersistableModelInterface> $class */
        return $class::fromArray($state);
    }
}
