<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Lexicon\EnglishDictionaryLexicon;
use ML\IDEA\NLP\Lexicon\SemanticExplorer;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Text\Text;

echo "Example 24 - Bi-directional semantic explorer\n";

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mlidea_sem_' . uniqid('', true);
mkdir($tmp, 0777, true);

$wordnetPath = $tmp . '/wn.json';
$dictPath = $tmp . '/en.csv';

file_put_contents($wordnetPath, json_encode([
    'words' => [
        'happy' => ['happy.a.01'],
        'joyful' => ['happy.a.01'],
    ],
    'synsets' => [
        'happy.a.01' => [
            'definition' => 'feeling or showing pleasure',
            'synonyms' => ['happy', 'joyful', 'glad'],
        ],
    ],
], JSON_THROW_ON_ERROR));

file_put_contents($dictPath, "word,definition\nhappy,feeling or showing pleasure\nglad,feeling pleasure and joy\ncheerful,noticeably happy and optimistic\n");

$wordNet = new WordNetLexicon($wordnetPath);
$dictionary = new EnglishDictionaryLexicon($dictPath);
$explorer = new SemanticExplorer($wordNet, $dictionary);

$insights = $explorer->wordInsights('happy');
echo 'Word: ' . $insights['word'] . PHP_EOL;
echo 'Definition: ' . ($insights['definition'] ?? 'n/a') . PHP_EOL;
echo 'Synonyms: ' . implode(', ', array_slice($insights['synonyms'], 0, 5)) . PHP_EOL;

$meaningMatches = $explorer->wordsByMeaning('feeling pleasure joy', 5);
echo 'Words by meaning: ' . implode(', ', $meaningMatches) . PHP_EOL;

$textSemantics = Text::of('happy')->semantics($explorer);
echo 'Text semantics neighbors: ' . implode(', ', array_slice($textSemantics['definitionNeighbors'], 0, 5)) . PHP_EOL;
