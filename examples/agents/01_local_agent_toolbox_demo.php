<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

ini_set('memory_limit', '768M');

use ML\IDEA\RAG\Agents\ToolCallingAgent;
use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\LlmClientFactory;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\Tools\FreeApiGetTool;
use ML\IDEA\RAG\Tools\GeoResolverTool;
use ML\IDEA\RAG\Tools\RetrievalQaTool;
use ML\IDEA\RAG\Tools\WeatherTool;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

$offline = getenv('MLIDEA_EXAMPLE_OFFLINE');
$offline = $offline === false ? true : !in_array(strtolower((string) $offline), ['0', 'false', 'no'], true);

$kbPath = __DIR__ . '/knowledge_base.txt';
$kbText = is_file($kbPath) ? (string) file_get_contents($kbPath) : '';

// Local default for deterministic demo behavior.
// Set RAG_LLM_PROVIDER=openai|azure|ollama|echo to switch providers.
/** @var LlmClientInterface $llm */
$llm = LlmClientFactory::fromEnv();

$chain = new RetrievalQAChain(
    new HashEmbedder(24),
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(160, 30),
    $llm,
    new LexicalOverlapReranker(),
);
$chain->index([
    new Document('kb-1', $kbText, ['source' => 'local_kb']),
]);

$weatherTool = $offline
    ? new class implements ToolInterface {
        public function name(): string { return 'weather'; }
        public function description(): string { return 'Offline weather fixture tool for fast local demos.'; }
        public function invoke(array $input): string
        {
            $lat = isset($input['lat']) ? (float) $input['lat'] : -15.3875;
            $lon = isset($input['lon']) ? (float) $input['lon'] : 28.3228;
            return (string) json_encode([
                'lat' => $lat,
                'lon' => $lon,
                'current_weather' => [
                    'time' => 'fixture',
                    'temperature' => 22.3,
                    'windspeed' => 5.2,
                    'weathercode' => 1,
                ],
                'source' => 'offline-fixture',
            ], JSON_THROW_ON_ERROR);
        }
    }
    : new WeatherTool();

$freeApiTool = $offline
    ? new class implements ToolInterface {
        public function name(): string { return 'free_api'; }
        public function description(): string { return 'Offline free_api fixture tool for fast local demos.'; }
        public function invoke(array $input): string
        {
            return (string) json_encode([
                'userId' => 1,
                'id' => 1,
                'title' => 'delectus aut autem',
                'completed' => false,
                'source' => 'offline-fixture',
            ], JSON_THROW_ON_ERROR);
        }
    }
    : new FreeApiGetTool([
        'https://api.github.com',
        'https://api.coindesk.com',
        'https://api.publicapis.org',
        'https://jsonplaceholder.typicode.com',
    ]);

$agent = new ToolCallingAgent([
    new RetrievalQaTool($chain),
    new GeoResolverTool(),
    $weatherTool,
    $freeApiTool,
]);

echo "Agent Demo: local knowledge + weather + geo_resolver + free_api\n\n";
echo 'Mode: ' . ($offline ? 'offline-fixture (fast, deterministic)' : 'live APIs') . PHP_EOL . PHP_EOL;

echo "1) rag_qa\n";
echo $agent->run('tool:rag_qa {"question":"How can I persist models?","k":2}') . PHP_EOL . PHP_EOL;

echo "2) weather\n";
echo $agent->run('tool:weather {"lat":-15.3875,"lon":28.3228}') . PHP_EOL . PHP_EOL;

echo "3) geo_resolver (place)\n";
echo $agent->run('tool:geo_resolver {"place":"Lusaka"}') . PHP_EOL . PHP_EOL;

echo "4) geo_resolver (coordinates)\n";
echo $agent->run('tool:geo_resolver {"lat":-15.4167,"lon":28.2833}') . PHP_EOL . PHP_EOL;

echo "5) free_api\n";
echo $agent->run('tool:free_api {"url":"https://jsonplaceholder.typicode.com/todos/1"}') . PHP_EOL;
