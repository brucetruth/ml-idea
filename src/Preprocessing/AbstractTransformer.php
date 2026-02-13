<?php

declare(strict_types=1);

namespace ML\IDEA\Preprocessing;

use ML\IDEA\Contracts\TransformerInterface;
use ML\IDEA\Support\Hyperparameters;

abstract class AbstractTransformer implements TransformerInterface
{
    protected ?int $randomState = null;

    public function fitTransform(array $samples): array
    {
        $this->fit($samples);
        return $this->transform($samples);
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
