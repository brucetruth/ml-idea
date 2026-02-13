<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Text\Text;

echo "Example 19 - NLP Extensibility\n";

$detector = new LanguageDetector();
$detector->addProfile('sw', [' na' => 0.07, ' wa' => 0.06, ' ku' => 0.05, ' ni' => 0.05]);

$sample = 'Ninaenda na wewe mjini.';
$ranked = $detector->rank($sample);
echo 'Top detected language for sample: ' . (string) array_key_first($ranked) . PHP_EOL;

$tagger = (new RuleBasedPosTagger('en'))->withLexicon([
    'ml-idea' => 'PROPN',
    'opensource' => 'ADJ',
]);

$tokens = Text::of('ML-IDEA is opensource')->toTokens();
$tagged = $tagger->tag($tokens);
echo "Custom POS lexicon:\n";
foreach ($tagged as $row) {
    echo '- ' . $row['token']->text . ' => ' . $row['pos'] . PHP_EOL;
}
