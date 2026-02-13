<?php

declare(strict_types=1);

namespace ML\IDEA\Classifiers;

use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Support\Hyperparameters;

abstract class AbstractClassifier implements ClassifierInterface
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
