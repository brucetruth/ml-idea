<?php

declare(strict_types=1);

namespace ML\IDEA\Pipeline;

use ML\IDEA\Contracts\RegressorInterface;
use ML\IDEA\Contracts\TransformerInterface;
use ML\IDEA\Regression\AbstractRegressor;
use ML\IDEA\Support\Assert;

final class PipelineRegressor extends AbstractRegressor
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
}
