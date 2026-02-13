<?php

declare(strict_types=1);

namespace ML\IDEA\Regression;

use ML\IDEA\Contracts\RegressorInterface;
use ML\IDEA\Support\Hyperparameters;

abstract class AbstractRegressor implements RegressorInterface
{
    protected ?int $randomState = null;

    public function fit(array $samples, array $targets): void
    {
        $this->train($samples, $targets);
    }

    public function predictBatch(array $samples): array
    {
        $predictions = [];
        foreach ($samples as $sample) {
            $predictions[] = $this->predict($sample);
        }

        return $predictions;
    }

    public function getRandomState(): ?int
    {
        return $this->randomState;
    }

    public function setRandomState(?int $randomState): static
    {
        return $this->setParams(['seed' => $randomState, 'randomState' => $randomState]);
    }

    public function getParams(): array
    {
        return Hyperparameters::extract($this);
    }

    public function setParams(array $params): static
    {
        return $this->cloneWithParams($params);
    }

    public function cloneWithParams(array $params): static
    {
        /** @var static $clone */
        $clone = Hyperparameters::cloneWith($this, $params);
        return $clone;
    }
}
