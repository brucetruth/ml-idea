<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Lexicon\EnglishDictionaryLexicon;
use ML\IDEA\NLP\Lexicon\SemanticExplorer;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Text\Text;

echo "Example 24 - Bi-directional semantic explorer\n";

// Uses inbuilt bundled datasets by default:
// - src/Dataset/wordnet/wn.json
// - src/Dataset/dictionary/en/en.csv
$wordNet = new WordNetLexicon();
$dictionary = new EnglishDictionaryLexicon();
$explorer = new SemanticExplorer($wordNet, $dictionary);

$insights = $explorer->wordInsights('happy');
echo 'Word: ' . $insights['word'] . PHP_EOL;
echo 'Definition: ' . ($insights['definition'] ?? 'n/a') . PHP_EOL;
echo 'Synonyms: ' . implode(', ', array_slice($insights['synonyms'], 0, 5)) . PHP_EOL;

$meaningMatches = $explorer->wordsByMeaning('feeling pleasure joy', 5);
echo 'Words by meaning: ' . implode(', ', $meaningMatches) . PHP_EOL;

$textSemantics = Text::of('happy')->semantics($explorer);
echo 'Text semantics neighbors: ' . implode(', ', array_slice($textSemantics['definitionNeighbors'], 0, 5)) . PHP_EOL;
