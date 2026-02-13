<?php

declare(strict_types=1);

namespace ML\IDEA\Pipeline;

use ML\IDEA\Classifiers\AbstractClassifier;
use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Contracts\TransformerInterface;
use ML\IDEA\Support\Assert;

final class PipelineClassifier extends AbstractClassifier
{
    /** @var array<int, TransformerInterface> */
    private array $transformers;

    /**
     * @param array<int, TransformerInterface> $transformers
     */
    public function __construct(array $transformers, private readonly ClassifierInterface $classifier)
    {
        $this->transformers = $transformers;
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);

        $x = $samples;
        foreach ($this->transformers as $transformer) {
            $x = $transformer->fitTransform($x);
        }

        $this->classifier->train($x, $labels);
    }

    public function predict(array $sample): int|float|string|bool
    {
        Assert::numericVector($sample);
        $matrix = [$sample];

        foreach ($this->transformers as $transformer) {
            $matrix = $transformer->transform($matrix);
        }

        return $this->classifier->predict($matrix[0]);
    }

    public function predictBatch(array $samples): array
    {
        Assert::numericMatrix($samples);
        $matrix = $samples;

        foreach ($this->transformers as $transformer) {
            $matrix = $transformer->transform($matrix);
        }

        return $this->classifier->predictBatch($matrix);
    }
}
