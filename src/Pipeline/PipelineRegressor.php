<?php

declare(strict_types=1);

namespace ML\IDEA\Pipeline;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Contracts\RegressorInterface;
use ML\IDEA\Contracts\TransformerInterface;
use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\Model\PipelineSerializer;
use ML\IDEA\Regression\AbstractRegressor;
use ML\IDEA\Support\Assert;

final class PipelineRegressor extends AbstractRegressor implements PersistableModelInterface
{
    /** @var array<int, TransformerInterface> */
    private array $transformers;

    /**
     * @param array<int, TransformerInterface> $transformers
     */
    public function __construct(array $transformers, private readonly RegressorInterface $regressor)
    {
        $this->transformers = $transformers;
    }

    public function train(array $samples, array $targets): void
    {
        Assert::numericMatrix($samples);

        $x = $samples;
        foreach ($this->transformers as $transformer) {
            $x = $transformer->fitTransform($x);
        }

        $this->regressor->train($x, $targets);
    }

    public function predict(array $sample): float
    {
        Assert::numericVector($sample);
        $matrix = [$sample];

        foreach ($this->transformers as $transformer) {
            $matrix = $transformer->transform($matrix);
        }

        return $this->regressor->predict($matrix[0]);
    }

    public function predictBatch(array $samples): array
    {
        Assert::numericMatrix($samples);
        $matrix = $samples;

        foreach ($this->transformers as $transformer) {
            $matrix = $transformer->transform($matrix);
        }

        return $this->regressor->predictBatch($matrix);
    }

    /** @return array<int, TransformerInterface> */
    public function transformers(): array
    {
        return $this->transformers;
    }

    public function regressor(): RegressorInterface
    {
        return $this->regressor;
    }

    public function toArray(): array
    {
        return [
            'type' => 'regressor',
            'transformers' => PipelineSerializer::exportTransformers($this->transformers),
            'estimator' => [
                'class' => $this->regressor::class,
                'state' => self::requirePersistable($this->regressor)->toArray(),
            ],
        ];
    }

    public static function fromArray(array $data): static
    {
        $transformers = [];
        foreach ($data['transformers'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $transformer = PipelineSerializer::loadPersistable($entry);
            if (!$transformer instanceof TransformerInterface) {
                throw new SerializationException('Invalid transformer in pipeline.');
            }
            $transformers[] = $transformer;
        }

        $estimatorEntry = $data['estimator'] ?? $data['regressor'] ?? null;
        if (!is_array($estimatorEntry)) {
            throw new SerializationException('Pipeline missing regressor state.');
        }
        $estimator = PipelineSerializer::loadPersistable($estimatorEntry);
        if (!$estimator instanceof RegressorInterface) {
            throw new SerializationException('Pipeline estimator must be a regressor.');
        }

        return new self($transformers, $estimator);
    }

    private static function requirePersistable(object $model): PersistableModelInterface
    {
        if (!$model instanceof PersistableModelInterface) {
            throw new SerializationException(sprintf('Model %s must implement PersistableModelInterface.', $model::class));
        }

        return $model;
    }
}
