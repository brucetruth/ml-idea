<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Text\Text;

$text = Text::of('Jean Dupont visited Paris on 2026-02-13. Contact: jean@example.com');

echo "Example 18 - Multilingual POS + NER\n";
echo 'Detected language: ' . $text->language() . PHP_EOL;

$frTagger = new RuleBasedPosTagger('fr');
$tagged = $frTagger->tag($text->toTokens());
echo "POS sample (FR):\n";
foreach (array_slice($tagged, 0, 6) as $row) {
    echo '- ' . $row['token']->text . ' => ' . $row['pos'] . PHP_EOL;
}

$ner = (new RuleBasedNerTagger([
    'paris' => 'LOCATION',
    'jean dupont' => 'PERSON',
]))->extract($text->value());

echo "Entities:\n";
foreach ($ner as $e) {
    echo '- ' . $e->text . ' [' . $e->label . '] score=' . round($e->confidence, 2) . PHP_EOL;
}
