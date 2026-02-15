<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Detect\LanguageRouting;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Translate\EnglishBembaTranslator;
use ML\IDEA\RAG\LLM\LlmClientFactory;
use ML\IDEA\RAG\LLM\OpenAILlmClient;

echo "Example 22 - Sentiment + EN->BEM translator\n";

$sentiment = new SentimentAnalyzer();
$sentiment->trainFromBundledDataset(2000);

$sentence = 'this library is amazing and brilliant';
$label = $sentiment->predict($sentence);
$proba = $sentiment->predictProba($sentence);

echo 'Sentence: ' . $sentence . PHP_EOL;
echo 'Sentiment: ' . $label . ' (p=' . round($proba['positive'], 4) . ')' . PHP_EOL;

$translator = new EnglishBembaTranslator();
$sourceText = 'Above Abdomen';
$baseline = $translator->translate($sourceText);

echo "\nTranslation transparency\n";
echo '- Baseline: dictionary + phrase-table hybrid translator' . PHP_EOL;
echo '- Optional LLM assist: post-correction pass over baseline output only' . PHP_EOL;
echo '  (helps with incomplete dictionary coverage, but still requires human validation in production)' . PHP_EOL;

echo 'Translate baseline: "' . $sourceText . '" => ' . $baseline . PHP_EOL;

// Enable LLM-assisted correction by setting TRANSLATION_LLM_ENABLED=1.
//
// Option A (with Env):
//   $llm = LlmClientFactory::fromEnv();
//   Env examples:
//   - RAG_LLM_PROVIDER=openai OPENAI_API_KEY=... OPENAI_CHAT_MODEL=gpt-4o-mini
//   - RAG_LLM_PROVIDER=azure AZURE_OPENAI_API_KEY=... AZURE_OPENAI_ENDPOINT=... AZURE_OPENAI_CHAT_DEPLOYMENT=...
//   - RAG_LLM_PROVIDER=ollama OLLAMA_MODEL=llama3.1
//
// Option B (without Env): construct the client directly, e.g.
//   $llm = new OpenAILlmClient('YOUR_OPENAI_API_KEY', 'gpt-4o-mini');
// then: $translator->withLLM($llm)
$translationLlmEnabled = in_array(strtolower((string) (getenv('TRANSLATION_LLM_ENABLED') ?: '0')), ['1', 'true', 'yes'], true);
if ($translationLlmEnabled) {
    // Default here uses Option A (with Env)
    $llm = LlmClientFactory::fromEnv();

    // Example for Option B (without Env):
    // $llm = new OpenAILlmClient('YOUR_OPENAI_API_KEY', 'gpt-4o-mini');

    $llmTranslator = $translator->withLLM($llm);
    $llmAssisted = $llmTranslator->translate($sourceText);
    echo 'Translate LLM-assisted: "' . $sourceText . '" => ' . $llmAssisted . PHP_EOL;
} else {
    echo 'LLM-assisted translation disabled (set TRANSLATION_LLM_ENABLED=1 to enable).' . PHP_EOL;
}

$detector = new LanguageDetector();
$sample = 'Ndi ku Lusaka pano';
$detected = $detector->detect($sample);
$route = LanguageRouting::forLanguage($detected);
echo 'Detected language for sample: ' . $detected . PHP_EOL;
echo 'Routing translator direction: ' . $route['translatorDirection'] . PHP_EOL;
