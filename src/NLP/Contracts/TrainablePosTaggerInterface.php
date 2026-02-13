<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

interface TrainablePosTaggerInterface extends PosTaggerInterface
{
    /**
     * @param array<int, array<int, string>> $sentences
     * @param array<int, array<int, string>> $labels
     */
    public function train(array $sentences, array $labels, int $epochs = 5): void;
}
