<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Detect\LanguageRouting;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Translate\EnglishBembaTranslator;

echo "Example 22 - Sentiment + EN->BEM translator\n";

$sentiment = new SentimentAnalyzer();
$sentiment->trainFromBundledDataset(2000);

$sentence = 'this library is amazing and brilliant';
$label = $sentiment->predict($sentence);
$proba = $sentiment->predictProba($sentence);

echo 'Sentence: ' . $sentence . PHP_EOL;
echo 'Sentiment: ' . $label . ' (p=' . round($proba['positive'], 4) . ')' . PHP_EOL;

$translator = new EnglishBembaTranslator();
echo 'Translate: "Above Abdomen" => ' . $translator->translate('Above Abdomen') . PHP_EOL;

$detector = new LanguageDetector();
$sample = 'Ndi ku Lusaka pano';
$detected = $detector->detect($sample);
$route = LanguageRouting::forLanguage($detected);
echo 'Detected language for sample: ' . $detected . PHP_EOL;
echo 'Routing translator direction: ' . $route['translatorDirection'] . PHP_EOL;
