<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\Examples\AiAdmin\Tools\RefundOrderTool;
use ML\IDEA\RAG\Agents\InMemoryToolIdempotencyStore;
use ML\IDEA\RAG\Agents\ToolExecutor;

$store = new AdminStore();
$tool = new RefundOrderTool($store);
$executor = new ToolExecutor(idempotencyStore: new InMemoryToolIdempotencyStore());
$input = ['order_id' => 101, 'reason' => 'duplicate charge per ticket #8842'];

echo "First refund (executes):\n";
$r1 = $executor->execute($tool, $input, approvalGranted: true);
echo json_encode($r1->toArray(), JSON_THROW_ON_ERROR) . PHP_EOL;

echo "\nSecond refund (same order — replayed, no double charge):\n";
$r2 = $executor->execute($tool, $input, approvalGranted: true);
echo json_encode($r2->toArray(), JSON_THROW_ON_ERROR) . PHP_EOL;

echo "\nRefunded orders in store: " . count($store->listOrders(null, 'refunded')) . PHP_EOL;
