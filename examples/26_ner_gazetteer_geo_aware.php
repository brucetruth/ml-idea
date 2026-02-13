<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Ner\RuleBasedNerTagger;

echo "Example 26 - Gazetteer Aho-Corasick + geo-aware NER\n";

$tagger = (new RuleBasedNerTagger())
    // No manual gazetteer/aliases required: auto-knowledge bootstrap is enabled by default.
    // You can still add custom domain entities with ->withGazetteer(...) if needed.
;

$text = 'I moved from Lusaka, Zambia to the U.S. and called +1 2025550134.';
$entities = $tagger->extract($text);

foreach ($entities as $e) {
   // if (in_array($e->label, ['CITY', 'COUNTRY', 'STATE', 'PHONE'], true)) {
        echo '- ' . $e->text . ' [' . $e->label . ']' . PHP_EOL;
   // }
}
