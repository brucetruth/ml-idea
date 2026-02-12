<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface RandomStateAwareInterface
{
    public function getRandomState(): ?int;

    public function setRandomState(?int $randomState): static;
}
