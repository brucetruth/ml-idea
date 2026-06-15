<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Nlp;
use ML\IDEA\NLP\Text\Text;

echo "Example 30 - Multilingual detection + spaCy-style API\n";

$mixed = 'Bonjour le monde. The quick brown fox jumps. Ndi ku Lusaka pano.';
echo "Mixed text: {$mixed}\n";
print_r(Text::of($mixed)->languageMixed());
echo 'Segments: ' . json_encode(Text::of($mixed)->languageSegments(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Top languages: ' . json_encode(Text::of($mixed)->languageTop(4), JSON_THROW_ON_ERROR) . PHP_EOL;

echo PHP_EOL . 'spaCy/HF-style loader (' . Nlp::languageCount() . ' languages):' . PHP_EOL;
foreach (Nlp::models() as $name => $meta) {
    echo "- {$name}: {$meta['description']}\n";
}

echo PHP_EOL . 'Sample supported locales: ';
echo implode(', ', array_slice(Nlp::languageNames(), 0, 12)) . ', ...' . PHP_EOL;

$nlp = Nlp::load('en_core');
$doc = $nlp->process('Alice visited Paris and emailed john@example.com.');
echo PHP_EOL . 'Doc summary: ' . json_encode($doc->summary(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Entities: ' . implode(', ', $doc->entityTexts()) . PHP_EOL;
echo 'First tokens: ';
foreach (array_slice($doc->tokens, 0, 5) as $token) {
    echo $token->text() . '/' . ($token->pos ?? '?') . ' ';
}
echo PHP_EOL;

echo PHP_EOL . 'Batch pipe:' . PHP_EOL;
foreach ($nlp->pipe(['Hello world.', 'Le chat dort.']) as $i => $batchDoc) {
    echo ($i + 1) . ') lang=' . $batchDoc->language . ' tokens=' . count($batchDoc->tokens) . PHP_EOL;
}

echo PHP_EOL . "To plug Hugging Face or other backends later, implement NlpModelBackendInterface and call Language::withBackend().\n";
