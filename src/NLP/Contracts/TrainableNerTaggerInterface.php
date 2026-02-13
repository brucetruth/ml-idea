<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

interface TrainableNerTaggerInterface extends NerTaggerInterface
{
    /**
     * @param array<int, array<int, string>> $sentences
     * @param array<int, array<int, string>> $bioLabels
     */
    public function train(array $sentences, array $bioLabels, int $epochs = 5): void;
}
