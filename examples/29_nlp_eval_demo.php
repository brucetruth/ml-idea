<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Eval\NlpEval;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\NLP\Ner\PerceptronNerTagger;
use ML\IDEA\NLP\Pos\PerceptronPosTagger;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Text\Text;

echo "Example 29 - NLP evaluation helpers\n";

$pos = new PerceptronPosTagger();
$pos->train(
    [['Alice', 'runs'], ['Bob', 'writes']],
    [['PROPN', 'VERB'], ['PROPN', 'VERB']],
    epochs: 4,
);
$truthPos = [['PROPN', 'VERB']];
$predPos = [array_column($pos->tag(Text::of('Alice runs')->toTokens()), 'pos')];
echo 'POS token accuracy: ' . round(NlpEval::tokenAccuracy(['PROPN', 'VERB'], $predPos[0]), 3) . PHP_EOL;
echo 'POS macro F1: ' . round(NlpEval::tokenMacroF1($truthPos, $predPos), 3) . PHP_EOL;

$ner = new PerceptronNerTagger();
$ner->train(
    [['Alice', 'visited', 'Paris']],
    [['B-PER', 'O', 'B-LOC']],
    epochs: 4,
);
$entities = $ner->extract('Alice visited Paris');
$spanScores = NlpEval::entitySpanScores(
    [new Entity('Paris', 'LOC', 14, 19)],
    $entities,
);
echo 'NER span F1: ' . round($spanScores['f1'], 3) . PHP_EOL;

$sentiment = new SentimentAnalyzer();
$sentiment->train(
    ['great product', 'okay average', 'awful experience'],
    ['positive', 'neutral', 'negative'],
);
$report = NlpEval::sentimentReport(
    ['great product', 'awful experience', 'okay average'],
    ['positive', 'negative', 'neutral'],
    $sentiment,
);
echo 'Sentiment accuracy: ' . round($report['accuracy'], 3) . PHP_EOL;
echo 'Sentiment per-class F1 (positive): ' . round($report['report']['string:"positive"']['f1'] ?? 0.0, 3) . PHP_EOL;

echo 'Sensitive term mask: ' . Text::of('This internal secret draft')->maskSensitiveTerms(['secret', 'internal'])->value() . PHP_EOL;
