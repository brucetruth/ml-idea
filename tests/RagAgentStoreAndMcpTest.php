<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\AgentStateStoreFactory;
use ML\IDEA\RAG\Agents\FileAgentStateStore;
use ML\IDEA\RAG\Agents\RedisAgentStateStore;
use ML\IDEA\RAG\MCP\McpHttpTransport;
use ML\IDEA\RAG\MCP\McpJsonRpcClient;
use ML\IDEA\RAG\MCP\McpToolProvider;
use ML\IDEA\RAG\MCP\McpTransportInterface;
use PHPUnit\Framework\TestCase;

final class RagAgentStoreAndMcpTest extends TestCase
{
    public function testAgentStateStoreFactoryDefaultsToFileStore(): void
    {
        $dir = sys_get_temp_dir() . '/mlidea_store_factory_' . uniqid('', true);
        $store = AgentStateStoreFactory::create(['path' => $dir]);

        self::assertInstanceOf(FileAgentStateStore::class, $store);
        $store->save('session-1', new AgentState('goal', 'system'));
        self::assertTrue($store->exists('session-1'));

        @unlink($dir . '/session-1.json');
        @rmdir($dir);
    }

    public function testAgentStateStoreFactoryAutoFallsBackToFileWhenRedisUnavailable(): void
    {
        $dir = sys_get_temp_dir() . '/mlidea_store_fallback_' . uniqid('', true);
        $store = AgentStateStoreFactory::create([
            'driver' => 'auto',
            'path' => $dir,
            'redis' => ['host' => '127.0.0.1', 'port' => 1, 'timeout' => 0.2],
        ]);

        self::assertInstanceOf(FileAgentStateStore::class, $store);
        $store->save('fallback', new AgentState('goal', 'system'));
        self::assertTrue($store->exists('fallback'));

        @unlink($dir . '/fallback.json');
        @rmdir($dir);
    }

    public function testRedisAgentStateStoreRoundTripWhenExtensionAvailable(): void
    {
        if (!extension_loaded('redis') && !class_exists(\Redis::class, false)) {
            self::markTestSkipped('Redis extension not available.');
        }

        try {
            $store = RedisAgentStateStore::connect(['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0.5, 'prefix' => 'mlidea:test:']);
        } catch (\Throwable $e) {
            self::markTestSkipped('Local Redis unavailable: ' . $e->getMessage());
        }

        $store->save('phpunit-session', new AgentState('redis goal', 'system'));
        self::assertTrue($store->exists('phpunit-session'));
        $loaded = $store->load('phpunit-session');
        self::assertNotNull($loaded);
        self::assertSame('redis goal', $loaded->goal);
        $store->delete('phpunit-session');
    }

    public function testMcpToolProviderDiscoversAndInvokesRemoteTools(): void
    {
        $transport = new class () implements McpTransportInterface {
            private int $toolCalls = 0;

            public function send(array $request): array
            {
                $method = (string) ($request['method'] ?? '');
                if ($method === 'initialize') {
                    return ['jsonrpc' => '2.0', 'id' => $request['id'] ?? null, 'result' => ['protocolVersion' => '2024-11-05']];
                }
                if ($method === 'notifications/initialized') {
                    return ['jsonrpc' => '2.0'];
                }
                if ($method === 'tools/list') {
                    return [
                        'jsonrpc' => '2.0',
                        'id' => $request['id'] ?? null,
                        'result' => [
                            'tools' => [[
                                'name' => 'echo',
                                'description' => 'Echo input',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'required' => ['message'],
                                    'properties' => ['message' => ['type' => 'string']],
                                ],
                            ]],
                        ],
                    ];
                }
                if ($method === 'tools/call') {
                    $this->toolCalls++;
                    /** @var array<string, mixed> $params */
                    $params = is_array($request['params'] ?? null) ? $request['params'] : [];
                    return [
                        'jsonrpc' => '2.0',
                        'id' => $request['id'] ?? null,
                        'result' => [
                            'content' => [['type' => 'text', 'text' => 'echo:' . (string) (($params['arguments']['message'] ?? '') ?: '')]],
                            'isError' => false,
                        ],
                    ];
                }

                return ['jsonrpc' => '2.0', 'id' => $request['id'] ?? null, 'result' => new \stdClass()];
            }
        };

        $client = new McpJsonRpcClient($transport);
        $tools = McpToolProvider::discoverTools($client, 'low');
        self::assertCount(1, $tools);
        self::assertSame('echo', $tools[0]->name());

        $output = $tools[0]->invoke(['message' => 'hello']);
        self::assertStringContainsString('echo:hello', $output);
    }

    public function testMcpHttpTransportUsesHttpClient(): void
    {
        $http = new class () implements \ML\IDEA\RAG\Contracts\HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $lastBody = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->lastBody = $jsonBody;
                return ['jsonrpc' => '2.0', 'id' => $jsonBody['id'] ?? null, 'result' => ['ok' => true]];
            }
        };

        $transport = new McpHttpTransport('https://mcp.example.test/rpc', $http);
        $response = $transport->send(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => new \stdClass()]);

        self::assertSame(['ok' => true], $response['result']);
        self::assertSame('initialize', $http->lastBody['method']);
    }
}
