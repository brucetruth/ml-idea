<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\MCP;

use ML\IDEA\Exceptions\SerializationException;

final class McpJsonRpcClient
{
    private int $requestId = 0;
    private bool $initialized = false;

    public function __construct(
        private readonly McpTransportInterface $transport,
        private readonly string $protocolVersion = '2024-11-05',
        private readonly string $clientName = 'ml-idea',
        private readonly string $clientVersion = '1.0.0',
    ) {
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->call('initialize', [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => new \stdClass(),
            'clientInfo' => [
                'name' => $this->clientName,
                'version' => $this->clientVersion,
            ],
        ]);

        try {
            $this->notify('notifications/initialized', []);
        } catch (\Throwable) {
            // Some MCP servers do not require the initialized notification.
        }

        $this->initialized = true;
    }

    /** @param array<string, mixed> $params */
    public function call(string $method, array $params = []): mixed
    {
        $this->requestId++;
        $response = $this->transport->send([
            'jsonrpc' => '2.0',
            'id' => $this->requestId,
            'method' => $method,
            'params' => $params === [] ? new \stdClass() : $params,
        ]);

        if (isset($response['error']) && is_array($response['error'])) {
            $message = (string) ($response['error']['message'] ?? 'Unknown MCP error');
            throw new SerializationException('MCP error: ' . $message);
        }

        return $response['result'] ?? null;
    }

    /** @param array<string, mixed> $params */
    public function notify(string $method, array $params = []): void
    {
        $this->transport->send([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params === [] ? new \stdClass() : $params,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listTools(): array
    {
        $this->initialize();
        $result = $this->call('tools/list');
        if (!is_array($result) || !isset($result['tools']) || !is_array($result['tools'])) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $tools */
        $tools = $result['tools'];
        return $tools;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $arguments = []): array
    {
        $this->initialize();
        $result = $this->call('tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);

        return is_array($result) ? $result : [];
    }
}
