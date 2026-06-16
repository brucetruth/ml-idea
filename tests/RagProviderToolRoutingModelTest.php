<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Agents\AgentBudget;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\LLM\AnthropicToolRoutingModel;
use ML\IDEA\RAG\LLM\AzureOpenAIToolRoutingModel;
use ML\IDEA\RAG\LLM\OllamaToolRoutingModel;
use ML\IDEA\RAG\LLM\OpenAIToolRoutingModel;
use ML\IDEA\RAG\LLM\ProviderToolCallParser;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagProviderToolRoutingModelTest extends TestCase
{
    public function testOpenAIRoutingModelOmitsTemperatureForGpt5Nano(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;

                return [
                    'choices' => [[
                        'message' => ['content' => '{"type":"final","content":"ok"}'],
                    ]],
                ];
            }
        };

        $model = new OpenAIToolRoutingModel('test-key', model: 'gpt-5-nano', http: $http);
        $model->respond([['role' => 'user', 'content' => 'hello']], []);

        self::assertArrayNotHasKey('temperature', $http->body);
    }

    public function testOpenAIRoutingModelSurfacesProviderErrors(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    'error' => [
                        'message' => 'Unsupported value',
                        'code' => 'unsupported_value',
                    ],
                ];
            }
        };

        $model = new OpenAIToolRoutingModel('test-key', http: $http);

        $this->expectException(\ML\IDEA\Exceptions\SerializationException::class);
        $this->expectExceptionMessage('OpenAI request failed');

        $model->respond([['role' => 'user', 'content' => 'hello']], []);
    }

    public function testOpenAIRoutingModelSendsNativeToolsAndParsesToolCall(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;
                return [
                    'choices' => [[
                        'message' => [
                            'tool_calls' => [[
                                'type' => 'function',
                                'function' => [
                                    'name' => 'math',
                                    'arguments' => '{"expression":"2+2"}',
                                ],
                            ]],
                        ],
                    ]],
                ];
            }
        };

        $model = new OpenAIToolRoutingModel('test-key', http: $http);
        $decision = $model->respond(
            [['role' => 'user', 'content' => 'calculate 2+2']],
            [[
                'name' => 'math',
                'description' => 'Evaluate math.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['expression'],
                    'properties' => ['expression' => ['type' => 'string']],
                ],
            ]]
        );

        self::assertSame('tool_call', $decision['type']);
        self::assertSame('math', $decision['tool']);
        self::assertSame('2+2', $decision['input']['expression']);
        self::assertArrayHasKey('tools', $http->body);
        self::assertSame('function', $http->body['tools'][0]['type']);
        self::assertSame('math', $http->body['tools'][0]['function']['name']);
    }

    public function testAzureRoutingModelOmitsTemperatureForGpt5NanoDeployment(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;

                return [
                    'choices' => [[
                        'message' => ['content' => '{"type":"final","content":"ok"}'],
                    ]],
                ];
            }
        };

        $model = new AzureOpenAIToolRoutingModel('key', 'https://example.test', 'gpt-5-nano', http: $http);
        $model->respond([['role' => 'user', 'content' => 'hello']], []);

        self::assertArrayNotHasKey('temperature', $http->body);
    }

    public function testAzureRoutingModelSurfacesProviderErrors(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    'error' => [
                        'message' => 'Unsupported value',
                        'code' => 'unsupported_value',
                    ],
                ];
            }
        };

        $model = new AzureOpenAIToolRoutingModel('key', 'https://example.test', 'deployment', http: $http);

        $this->expectException(\ML\IDEA\Exceptions\SerializationException::class);
        $this->expectExceptionMessage('Azure OpenAI request failed');

        $model->respond([['role' => 'user', 'content' => 'hello']], []);
    }

    public function testAzureRoutingModelParsesMultipleNativeToolCalls(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;
                return [
                    'choices' => [[
                        'message' => [
                            'tool_calls' => [
                                ['function' => ['name' => 'math', 'arguments' => '{"expression":"3+4"}']],
                                ['function' => ['name' => 'weather', 'arguments' => '{"lat":-15.3,"lon":28.3}']],
                            ],
                        ],
                    ]],
                ];
            }
        };

        $model = new AzureOpenAIToolRoutingModel('key', 'https://example.test', 'deployment', http: $http);
        $decision = $model->respond(
            [['role' => 'user', 'content' => 'math and weather']],
            [
                ['name' => 'math', 'description' => 'Evaluate math.', 'input_schema' => ['type' => 'object', 'properties' => ['expression' => ['type' => 'string']]]],
                ['name' => 'weather', 'description' => 'Weather.', 'input_schema' => ['type' => 'object', 'properties' => ['lat' => ['type' => 'number'], 'lon' => ['type' => 'number']]]],
            ]
        );

        self::assertSame('tool_calls', $decision['type']);
        self::assertCount(2, $decision['tool_calls']);
        self::assertSame('weather', $decision['tool_calls'][1]['tool']);
        self::assertArrayHasKey('tools', $http->body);
        self::assertSame('auto', $http->body['tool_choice']);
    }

    public function testProviderRoutingFallsBackToJsonTextDecision(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    'choices' => [[
                        'message' => ['content' => '{"type":"final","content":"no tool"}'],
                    ]],
                ];
            }
        };

        $model = new OpenAIToolRoutingModel('test-key', http: $http);
        $decision = $model->respond([['role' => 'user', 'content' => 'hello']], []);

        self::assertSame('final', $decision['type']);
        self::assertSame('no tool', $decision['content']);
    }

    public function testOpenAIRoutingModelCapturesUsageAndEstimatedCost(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
                    'choices' => [[
                        'message' => ['content' => '{"type":"final","content":"ok"}'],
                    ]],
                ];
            }
        };

        $model = new OpenAIToolRoutingModel('test-key', http: $http, costPer1kTokens: 0.02);
        $decision = $model->respond([['role' => 'user', 'content' => 'hello']], []);

        self::assertSame('ok', $decision['content']);
        self::assertSame(15, $model->lastUsage()->totalTokens);
        self::assertEqualsWithDelta(0.0003, $model->lastUsage()->estimatedCost, 0.000001);
    }

    public function testToolRoutingAgentStopsWhenTokenBudgetExceeded(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 7, 'total_tokens' => 15],
                    'choices' => [[
                        'message' => ['content' => '{"type":"final","content":"too late"}'],
                    ]],
                ];
            }
        };

        $model = new OpenAIToolRoutingModel('test-key', http: $http);
        $agent = new ToolRoutingAgent($model, [new MathTool()], budget: new AgentBudget(maxTokens: 10));
        $result = $agent->chat('hello');

        self::assertSame('token_budget_exceeded', $result['stop_reason']);
        self::assertSame(15, $result['usage']['total_tokens']);
        self::assertSame(15, $result['budget']['usage']['total_tokens']);
    }

    public function testNativeToolCallRoundTripMessagesAreSentBackToProvider(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<int, array<string, mixed>> */
            public array $bodies = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->bodies[] = $jsonBody;
                if (count($this->bodies) === 1) {
                    return [
                        'choices' => [[
                            'message' => [
                                'tool_calls' => [[
                                    'id' => 'call_math_1',
                                    'type' => 'function',
                                    'function' => ['name' => 'math', 'arguments' => '{"expression":"2+3"}'],
                                ]],
                            ],
                        ]],
                    ];
                }

                return [
                    'choices' => [[
                        'message' => ['content' => '{"type":"final","content":"done"}'],
                    ]],
                ];
            }
        };

        $model = new OpenAIToolRoutingModel('test-key', http: $http);
        $agent = new ToolRoutingAgent($model, [new MathTool()]);
        $result = $agent->chat('calculate 2+3');

        self::assertSame('done', $result['answer']);
        self::assertCount(2, $http->bodies);

        $secondMessages = $http->bodies[1]['messages'];
        $assistantToolMessage = null;
        $toolResultMessage = null;
        foreach ($secondMessages as $message) {
            if (($message['role'] ?? '') === 'assistant' && isset($message['tool_calls'])) {
                $assistantToolMessage = $message;
            }
            if (($message['role'] ?? '') === 'tool') {
                $toolResultMessage = $message;
            }
        }

        self::assertIsArray($assistantToolMessage);
        self::assertSame('call_math_1', $assistantToolMessage['tool_calls'][0]['id']);
        self::assertSame('math', $assistantToolMessage['tool_calls'][0]['function']['name']);
        self::assertIsArray($toolResultMessage);
        self::assertSame('call_math_1', $toolResultMessage['tool_call_id']);
        self::assertSame('call_math_1', $result['structured_tool_calls'][0]['provider_call_id']);
    }

    public function testAnthropicRoutingModelParsesNativeToolUse(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;
                return [
                    'usage' => ['input_tokens' => 12, 'output_tokens' => 8],
                    'content' => [[
                        'type' => 'tool_use',
                        'id' => 'toolu_math_1',
                        'name' => 'math',
                        'input' => ['expression' => '6*7'],
                    ]],
                ];
            }
        };

        $model = new AnthropicToolRoutingModel('test-key', http: $http, costPer1kTokens: 0.01);
        $decision = $model->respond(
            [['role' => 'user', 'content' => 'calculate 6*7']],
            [[
                'name' => 'math',
                'description' => 'Evaluate math.',
                'input_schema' => ['type' => 'object', 'required' => ['expression'], 'properties' => ['expression' => ['type' => 'string']]],
            ]]
        );

        self::assertSame('tool_call', $decision['type']);
        self::assertSame('math', $decision['tool']);
        self::assertSame('toolu_math_1', $decision['provider_call_id']);
        self::assertSame('math', $http->body['tools'][0]['name']);
        self::assertSame(20, $model->lastUsage()->totalTokens);
    }

    public function testOllamaRoutingModelSendsNativeToolsAndParsesToolCall(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;
                return [
                    'message' => [
                        'role' => 'assistant',
                        'content' => '',
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'math', 'arguments' => '{"expression":"9+1"}'],
                        ]],
                    ],
                ];
            }
        };

        $model = new OllamaToolRoutingModel(http: $http);
        $decision = $model->respond(
            [['role' => 'user', 'content' => 'calculate 9+1']],
            [[
                'name' => 'math',
                'description' => 'Evaluate math.',
                'input_schema' => ['type' => 'object', 'required' => ['expression'], 'properties' => ['expression' => ['type' => 'string']]],
            ]]
        );

        self::assertSame('tool_call', $decision['type']);
        self::assertSame('math', $decision['tool']);
        self::assertArrayHasKey('tools', $http->body);
        self::assertSame('call_1', $decision['provider_call_id']);
    }

    public function testOllamaRoutingModelFallsBackToJsonPromptWhenNativeToolsDisabled(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;
                return [
                    'message' => ['content' => '{"type":"final","content":"ok"}'],
                ];
            }
        };

        $model = new OllamaToolRoutingModel(http: $http, useNativeTools: false);
        $decision = $model->respond([['role' => 'user', 'content' => 'hello']], [['name' => 'math', 'description' => 'Math']]);

        self::assertSame('final', $decision['type']);
        self::assertArrayNotHasKey('tools', $http->body);
        self::assertStringContainsString('strict tool-routing controller', $http->body['messages'][0]['content']);
    }

    public function testProviderToolCallParserAcceptsObjectArguments(): void
    {
        $decision = ProviderToolCallParser::parseChatMessage([
            'tool_calls' => [[
                'id' => 'call_1',
                'type' => 'function',
                'function' => [
                    'name' => 'math',
                    'arguments' => ['expression' => '2+2'],
                ],
            ]],
        ]);

        self::assertSame('tool_call', $decision['type'] ?? null);
        self::assertSame('math', $decision['tool'] ?? null);
        self::assertSame('2+2', $decision['input']['expression'] ?? null);
    }

    public function testOllamaRoutingModelParsesObjectArgumentsFromCloud(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    'message' => [
                        'role' => 'assistant',
                        'content' => '',
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'math',
                                'arguments' => ['expression' => '9+1'],
                            ],
                        ]],
                    ],
                ];
            }
        };

        $model = new OllamaToolRoutingModel(http: $http);
        $decision = $model->respond([['role' => 'user', 'content' => 'calculate 9+1']], []);

        self::assertSame('tool_call', $decision['type']);
        self::assertSame('9+1', $decision['input']['expression']);
    }

    public function testOllamaRoutingModelSendsBearerAuthWhenApiKeyConfigured(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, string> */
            public array $headers = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->headers = $headers;

                return ['message' => ['content' => '{"type":"final","content":"ok"}']];
            }
        };

        $model = new OllamaToolRoutingModel(http: $http, apiKey: 'cloud-secret');
        $model->respond([['role' => 'user', 'content' => 'hello']], []);

        self::assertSame('Bearer cloud-secret', $http->headers['Authorization'] ?? null);
    }

    public function testOllamaRoutingModelSendsToolHistoryArgumentsAsObjects(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;

                return ['message' => ['content' => '{"type":"final","content":"done"}']];
            }
        };

        $model = new OllamaToolRoutingModel(http: $http);
        $model->respond([
            ['role' => 'user', 'content' => 'calculate 2+3'],
            [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [[
                    'id' => 'call_math_1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'math',
                        'arguments' => '{"expression":"2+3"}',
                    ],
                ]],
            ],
            ['role' => 'tool', 'tool_call_id' => 'call_math_1', 'content' => '{"result":5}'],
        ], [[
            'name' => 'math',
            'description' => 'Evaluate math.',
            'input_schema' => ['type' => 'object', 'properties' => ['expression' => ['type' => 'string']]],
        ]]);

        $assistantMessage = null;
        foreach ($http->body['messages'] as $message) {
            if (($message['role'] ?? '') === 'assistant' && isset($message['tool_calls'])) {
                $assistantMessage = $message;
            }
        }

        self::assertIsArray($assistantMessage);
        $arguments = $assistantMessage['tool_calls'][0]['function']['arguments'];
        self::assertIsArray($arguments);
        self::assertSame('2+3', $arguments['expression']);
    }

    public function testOllamaRoutingModelStringifiesArrayMessageContent(): void
    {
        $http = new class () implements HttpTransportInterface {
            /** @var array<string, mixed> */
            public array $body = [];

            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                $this->body = $jsonBody;

                return ['message' => ['content' => '{"type":"final","content":"ok"}']];
            }
        };

        $model = new OllamaToolRoutingModel(http: $http);
        $model->respond([
            ['role' => 'user', 'content' => ['text' => 'hello']],
        ], [['name' => 'math', 'description' => 'Math']]);

        $userMessage = null;
        foreach ($http->body['messages'] as $message) {
            if (($message['role'] ?? '') === 'user') {
                $userMessage = $message;
            }
        }

        self::assertIsArray($userMessage);
        self::assertIsString($userMessage['content']);
        self::assertStringContainsString('hello', $userMessage['content']);
    }

    public function testOllamaRoutingModelSurfacesProviderErrors(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return ['error' => 'model not found'];
            }
        };

        $model = new OllamaToolRoutingModel(http: $http);

        $this->expectException(\ML\IDEA\Exceptions\SerializationException::class);
        $this->expectExceptionMessage('Ollama request failed');

        $model->respond([['role' => 'user', 'content' => 'hello']], []);
    }
}
