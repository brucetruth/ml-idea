<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Nlp;
use ML\IDEA\NLP\Text\Text;

echo 'Example 31 - International language detection (' . Nlp::languageCount() . " languages)\n\n";

$byScript = Nlp::languagesByScript();
echo 'Scripts covered: ' . implode(', ', array_keys($byScript)) . "\n\n";

$samples = [
    'en' => 'The quick brown fox jumps over the lazy dog near London.',
    'fr' => 'Bonjour le monde français avec des accents à Paris.',
    'de' => 'Der schnelle braune Fuchs springt über den faulen Hund.',
    'ja' => '速い茶色の狐は怠惰な犬を飛び越える。',
    'ar' => 'الثعلب البني السريع يقفز فوق الكلب الكسول.',
    'hi' => 'तेज़ भूरी लोमड़ी आलसी कुत्ते के उपर से कूदती है।',
    'sw' => 'Mbweha wa kahawia wa haraka huruka juu ya mbwa mvivu.',
    'bem' => 'Ulubee lwa mushishi ulwenda pa imbwa ya bulumende mu Lusaka.',
];

foreach ($samples as $expected => $text) {
    $det = Text::of($text)->languageWithScore();
    $name = Nlp::languageNames()[$det['language']] ?? $det['language'];
    echo sprintf(
        "%s (%s) conf=%.2f expected=%s\n",
        mb_substr($text, 0, 48),
        $name,
        $det['confidence'],
        $expected,
    );
}

echo "\nLanguage families (sample):\n";
foreach (array_slice(Nlp::languagesByFamily(), 0, 6, true) as $family => $codes) {
    echo "- {$family}: " . count($codes) . ' languages (' . implode(', ', array_slice($codes, 0, 5)) . "...)\n";
}

echo "\nMixed document:\n";
$mixed = 'Hello from London. Bonjour depuis Paris. こんにちは東京から。';
print_r(Text::of($mixed)->languageMixed(0.15));
