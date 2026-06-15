<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolCircuitBreaker;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Contracts\RetryableToolInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;

$tool = new class () implements ToolInterface, RetryableToolInterface {
    public function name(): string
    {
        return 'unstable_api';
    }

    public function description(): string
    {
        return 'Simulated flaky downstream API';
    }

    public function invoke(array $input): string
    {
        throw new \RuntimeException('HTTP 503 from partner API');
    }

    public function isRetryable(): bool
    {
        return false;
    }
};

$breaker = new ToolCircuitBreaker(failureThreshold: 2, cooldownSeconds: 30);
$executor = new ToolExecutor(circuitBreaker: $breaker);

foreach (range(1, 4) as $attempt) {
    $result = $executor->execute($tool, ['order_id' => 101]);
    echo sprintf(
        "Attempt %d: ok=%s error_type=%s failures=%d open=%s\n",
        $attempt,
        $result->ok ? 'true' : 'false',
        $result->errorType !== '' ? $result->errorType : 'none',
        $breaker->failureCount('unstable_api'),
        $breaker->isOpen('unstable_api') ? 'yes' : 'no',
    );
}
