<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Text\Text;

$raw = 'Dr. Jane emailed john.doe@example.com. She is writing quickly about ML systems!';

$text = Text::of($raw)
    ->normalizeUnicode('NFC')
    ->collapseWhitespace();

echo "Example 16 - NLP Text API + POS\n";
echo 'Original: ' . $raw . PHP_EOL;
echo 'Masked:   ' . $text->maskPII()->value() . PHP_EOL;
echo 'Slug:     ' . $text->slug()->value() . PHP_EOL;
echo 'Sentences:' . PHP_EOL;
foreach ($text->sentences() as $s) {
    echo '- ' . $s . PHP_EOL;
}

$tokens = $text->toTokens();
$tagger = new RuleBasedPosTagger();
$tagged = $tagger->tag($tokens);

echo "\nPOS tags:\n";
foreach ($tagged as $row) {
    echo sprintf("- %-15s %s\n", $row['token']->text, $row['pos']);
}
