<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\LLM\AzureOpenAIToolRoutingModel;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\LLM\OllamaToolRoutingModel;
use ML\IDEA\RAG\LLM\OpenAIToolRoutingModel;
use ML\IDEA\RAG\Tools\DbQueryTool;
use ML\IDEA\RAG\Tools\MathTool;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, customer TEXT, amount REAL)');
$pdo->exec("INSERT INTO orders(customer, amount) VALUES ('Bruce', 120.50), ('Ava', 45.25), ('Kai', 300.00)");

// Default local routing model:
// $router = new HeuristicToolRoutingModel();
//
// Provider-backed alternatives:
// $router = new OpenAIToolRoutingModel((string) getenv('OPENAI_API_KEY'), (string) (getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini'));
// $router = new AzureOpenAIToolRoutingModel((string) getenv('AZURE_OPENAI_API_KEY'), (string) getenv('AZURE_OPENAI_ENDPOINT'), (string) getenv('AZURE_OPENAI_CHAT_DEPLOYMENT'));
// $router = new OllamaToolRoutingModel((string) (getenv('OLLAMA_MODEL') ?: 'llama3.1'));
$router = new HeuristicToolRoutingModel();

$agent = new ToolRoutingAgent(
    $router,
    [
        new DbQueryTool($pdo, allowedTables: ['orders'], readOnly: true, maxRows: 20),
        new MathTool(),
    ]
);

$q1 = 'Use database to show recent orders';
$r1 = $agent->chat($q1);
echo "Q1: {$q1}\n";
echo 'A1: ' . $r1['answer'] . PHP_EOL . PHP_EOL;

$q2 = 'compute (12*9) + sqrt(81)';
$r2 = $agent->chat($q2);
echo "Q2: {$q2}\n";
echo 'A2: ' . $r2['answer'] . PHP_EOL;
