<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Ner\PerceptronNerTagger;
use ML\IDEA\NLP\Pos\PerceptronPosTagger;
use ML\IDEA\NLP\Text\Text;

echo "Example 20 - Trainable POS + NER\n";

$posTrainer = new PerceptronPosTagger();
$posTrainer->train(
    [
        ['Alice', 'runs', 'quickly'],
        ['Bob', 'writes', 'code'],
        ['The', 'fast', 'fox'],
    ],
    [
        ['PROPN', 'VERB', 'ADV'],
        ['PROPN', 'VERB', 'NOUN'],
        ['DET', 'ADJ', 'NOUN'],
    ],
    epochs: 6
);

$posTagged = $posTrainer->tag(Text::of('Alice writes fast code')->toTokens());
echo "POS predictions:\n";
foreach ($posTagged as $row) {
    echo '- ' . $row['token']->text . ' => ' . $row['pos'] . PHP_EOL;
}

$nerTrainer = new PerceptronNerTagger();
$nerTrainer->train(
    [
        ['Alice', 'visited', 'Paris'],
        ['Bob', 'emailed', 'john@example.com'],
    ],
    [
        ['B-PER', 'O', 'B-LOC'],
        ['B-PER', 'O', 'B-CONTACT'],
    ],
    epochs: 8
);

$entities = $nerTrainer->extract('Alice visited Paris and emailed john@example.com');
echo "NER predictions:\n";
foreach ($entities as $e) {
    echo '- ' . $e->text . ' [' . $e->label . ']' . PHP_EOL;
}
