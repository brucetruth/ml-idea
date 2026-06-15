<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Backends\CallableNlpBackend;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Nlp;

echo 'Example 32 - spaCy/HF-style composable pipeline (' . Nlp::languageCount() . " languages)\n\n";

echo "Named models:\n";
foreach (Nlp::models() as $name => $meta) {
    if (str_contains($name, '_core') || $name === 'multilingual') {
        echo "- {$name}: {$meta['description']}\n";
    }
}

$nlp = Nlp::load('en_core');
echo "\nDefault pipes: " . implode(' -> ', $nlp->pipeNames()) . "\n";

$doc = $nlp->process('Alice visited Paris on Monday.');
echo 'Doc JSON (truncated): ' . mb_substr($doc->toJson(), 0, 200) . "...\n";
echo 'Entity spans: ' . json_encode($doc->spans(), JSON_THROW_ON_ERROR) . "\n";

$lite = $nlp->disablePipes(['tagger']);
$liteDoc = $lite->process('Fast pipeline without POS tagging.');
echo 'Lite doc tokens: ' . count($liteDoc->tokens) . ' (POS omitted)' . PHP_EOL;

$hfStyle = Nlp::blank('en')->withBackend(new CallableNlpBackend(
    static fn (string $text, Doc $draft): Doc => new Doc(
        text: $draft->text,
        tokens: $draft->tokens,
        ents: $draft->ents,
        sents: $draft->sents,
        language: $draft->language,
        attrs: array_merge($draft->attrs, ['hf_ready' => true, 'note' => 'Plug HF Inference API here']),
    ),
));
$hooked = $hfStyle->process('Backend hook demo.');
echo 'Backend attrs: ' . json_encode($hooked->attrs, JSON_THROW_ON_ERROR) . PHP_EOL;

echo "\nNext steps for HF parity: implement NlpModelBackendInterface against api-inference.huggingface.co\n";
