<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\InMemoryAgentMemoryStore;
use ML\IDEA\RAG\Agents\LlmEpisodicMemorySummarizer;
use ML\IDEA\RAG\Agents\TruncatingEpisodicMemorySummarizer;
use ML\IDEA\RAG\LLM\EchoLlmClient;

$episodes = [
    ['tool' => 'get_user', 'ok' => true, 'note' => 'User Bob (id 42) active'],
    ['tool' => 'tag_order', 'ok' => true, 'note' => 'Order 101 tagged billing-review'],
    ['tool' => 'update_support_ticket_status', 'ok' => true, 'note' => 'Ticket #8842 resolved'],
];

echo "Truncating summarizer:\n";
$truncating = new InMemoryAgentMemoryStore(new TruncatingEpisodicMemorySummarizer());
foreach ($episodes as $episode) {
    $truncating->append('demo', $episode);
}
echo $truncating->summarizeForContext('demo') . PHP_EOL;

echo "\nLLM summarizer (EchoLlmClient — no API key needed):\n";
$llm = new InMemoryAgentMemoryStore(new LlmEpisodicMemorySummarizer(new EchoLlmClient()));
foreach ($episodes as $episode) {
    $llm->append('demo', $episode);
}
echo $llm->summarizeForContext('demo') . PHP_EOL;
