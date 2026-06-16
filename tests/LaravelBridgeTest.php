<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Laravel\Support\AgentTracerFactory;
use ML\IDEA\Laravel\Support\RoutingModelFactory;
use ML\IDEA\Laravel\ToolRoutingAgentManager;
use ML\IDEA\RAG\Agents\NoOpAgentTracer;
use ML\IDEA\RAG\Agents\RecordingAgentTracer;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class LaravelBridgeTest extends TestCase
{
    public function testRoutingModelFactoryCreatesHeuristicDriver(): void
    {
        $model = RoutingModelFactory::make(['driver' => 'heuristic']);

        self::assertInstanceOf(HeuristicToolRoutingModel::class, $model);
    }

    public function testAgentTracerFactoryCreatesRecordingDriver(): void
    {
        $tracer = AgentTracerFactory::make(['driver' => 'recording']);

        self::assertInstanceOf(RecordingAgentTracer::class, $tracer);
    }

    public function testAgentTracerFactoryDefaultsToNoOp(): void
    {
        $tracer = AgentTracerFactory::make(['driver' => 'noop']);

        self::assertInstanceOf(NoOpAgentTracer::class, $tracer);
    }

    public function testAgentRunLoggerFactoryCreatesJsonlDriver(): void
    {
        $path = sys_get_temp_dir() . '/mlidea_laravel_log_' . uniqid('', true) . '.jsonl';
        $logger = \ML\IDEA\Laravel\Support\AgentRunLoggerFactory::make(
            ['driver' => 'jsonl', 'path' => $path],
        );

        self::assertInstanceOf(\ML\IDEA\RAG\Agents\JsonlAgentRunLogger::class, $logger);
    }

    public function testManagerLogsAgentRunsWhenJsonlDriverEnabled(): void
    {
        $path = sys_get_temp_dir() . '/mlidea_laravel_log_' . uniqid('', true) . '.jsonl';

        $manager = new ToolRoutingAgentManager(
            static fn (string $class): object => new $class(),
            [
                'model' => ['driver' => 'heuristic'],
                'agent' => ['name' => 'TestAgent', 'max_iterations' => 4],
                'tools' => [MathTool::class],
                'store' => ['driver' => 'none'],
                'tracing' => ['driver' => 'noop'],
                'logging' => ['driver' => 'jsonl', 'path' => $path],
                'logging_path' => $path,
            ],
        );

        $manager->chat('calculate 3+4');

        self::assertFileExists($path);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim((string) file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('TestAgent', $decoded['agent_name']);
        self::assertSame('final', $decoded['stop_reason']);

        @unlink($path);
    }

    public function testDatabaseAgentRunLoggerInsertsRow(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            self::markTestSkipped('illuminate/database required for database logger test');
        }

        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $connection = $capsule->getConnection();

        $connection->getSchemaBuilder()->create('agent_runs', static function ($table): void {
            $table->string('id', 32)->primary();
            $table->timestamp('logged_at');
            $table->string('agent_name');
            $table->string('session_id', 120)->nullable();
            $table->text('user_message')->nullable();
            $table->boolean('resume')->default(false);
            $table->text('answer');
            $table->string('stop_reason', 64);
            $table->unsignedSmallInteger('iterations')->default(0);
            $table->text('tool_calls');
            $table->text('decisions');
            $table->text('usage');
            $table->text('budget');
            $table->text('telemetry')->nullable();
            $table->text('pending_approval')->nullable();
            $table->timestamps();
        });

        $manager = new ToolRoutingAgentManager(
            static fn (string $class): object => new $class(),
            [
                'model' => ['driver' => 'heuristic'],
                'agent' => ['name' => 'DbLogAgent', 'max_iterations' => 4],
                'tools' => [MathTool::class],
                'store' => ['driver' => 'none'],
                'tracing' => ['driver' => 'noop'],
                'logging' => ['driver' => 'database', 'table' => 'agent_runs'],
            ],
            $connection,
        );

        $manager->chat('calculate 5+5');

        $row = $connection->table('agent_runs')->first();
        self::assertNotNull($row);
        self::assertSame('DbLogAgent', $row->agent_name);
        self::assertSame('final', $row->stop_reason);
        self::assertStringContainsString('10', $row->answer);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $row->logged_at,
            'logged_at must be stored in MySQL-compatible Y-m-d H:i:s format',
        );
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $row->created_at,
        );
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $row->updated_at,
        );
    }

    public function testDatabaseAgentRunLoggerInsertsRowOnMysqlWhenConfigured(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            self::markTestSkipped('illuminate/database required for database logger test');
        }

        $host = getenv('MLIDEA_MYSQL_HOST') ?: getenv('MYSQL_HOST');
        $database = getenv('MLIDEA_MYSQL_DATABASE') ?: getenv('MYSQL_DATABASE');
        $username = getenv('MLIDEA_MYSQL_USERNAME') ?: getenv('MYSQL_USERNAME');
        $password = getenv('MLIDEA_MYSQL_PASSWORD') ?: getenv('MYSQL_PASSWORD');

        if ($host === false || $host === '' || $database === false || $database === '') {
            self::markTestSkipped('Set MLIDEA_MYSQL_HOST and MLIDEA_MYSQL_DATABASE to run MySQL integration test');
        }

        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int) (getenv('MLIDEA_MYSQL_PORT') ?: getenv('MYSQL_PORT') ?: 3306),
            'database' => $database,
            'username' => $username !== false ? $username : 'root',
            'password' => $password !== false ? $password : '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $connection = $capsule->getConnection();

        $table = 'agent_runs_mlidea_test_' . bin2hex(random_bytes(4));
        $connection->getSchemaBuilder()->create($table, static function ($table): void {
            $table->string('id', 32)->primary();
            $table->timestamp('logged_at');
            $table->string('agent_name');
            $table->string('session_id', 120)->nullable();
            $table->text('user_message')->nullable();
            $table->boolean('resume')->default(false);
            $table->text('answer');
            $table->string('stop_reason', 64);
            $table->unsignedSmallInteger('iterations')->default(0);
            $table->text('tool_calls');
            $table->text('decisions');
            $table->text('usage');
            $table->text('budget');
            $table->text('telemetry')->nullable();
            $table->text('pending_approval')->nullable();
            $table->timestamps();
        });

        try {
            $manager = new ToolRoutingAgentManager(
                static fn (string $class): object => new $class(),
                [
                    'model' => ['driver' => 'heuristic'],
                    'agent' => ['name' => 'MysqlDbLogAgent', 'max_iterations' => 4],
                    'tools' => [MathTool::class],
                    'store' => ['driver' => 'none'],
                    'tracing' => ['driver' => 'noop'],
                    'logging' => ['driver' => 'database', 'table' => $table],
                ],
                $connection,
            );

            $manager->chat('calculate 6+6');

            $row = $connection->table($table)->first();
            self::assertNotNull($row);
            self::assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                (string) $row->logged_at,
            );
        } finally {
            $connection->getSchemaBuilder()->dropIfExists($table);
        }
    }

    public function testManagerDispatchesAgentRunCompletedEvent(): void
    {
        $dispatched = [];
        $dispatcher = new class ($dispatched) {
            /** @param array<int, object> $dispatched */
            public function __construct(private array &$dispatched)
            {
            }

            public function dispatch(object $event): void
            {
                $this->dispatched[] = $event;
            }
        };

        $manager = new ToolRoutingAgentManager(
            static fn (string $class): object => new $class(),
            [
                'model' => ['driver' => 'heuristic'],
                'agent' => ['name' => 'EventAgent', 'max_iterations' => 4],
                'tools' => [MathTool::class],
                'store' => ['driver' => 'none'],
                'tracing' => ['driver' => 'noop'],
                'logging' => ['driver' => 'noop'],
                'events' => ['enabled' => true],
            ],
            null,
            $dispatcher,
        );

        $manager->chat('calculate 1+1');

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(\ML\IDEA\Laravel\Events\AgentRunCompleted::class, $dispatched[0]);
    }

    public function testManagerBuildsAgentAndRunsChat(): void
    {
        $manager = new ToolRoutingAgentManager(
            static fn (string $class): object => new $class(),
            [
                'model' => ['driver' => 'heuristic'],
                'agent' => ['name' => 'TestAgent', 'max_iterations' => 4],
                'tools' => [MathTool::class],
                'store' => ['driver' => 'file', 'path' => sys_get_temp_dir() . '/mlidea_laravel_test'],
                'tracing' => ['driver' => 'recording'],
                'context' => ['enabled' => true, 'max_messages' => 12, 'max_tool_output_chars' => 1000],
            ],
        );

        $result = $manager->chat('calculate 3+4');

        self::assertSame('final', $result['stop_reason']);
        self::assertArrayHasKey('telemetry', $result);
        self::assertStringContainsString('7', $result['answer']);
    }

    public function testManagerRegistersHandoffAgentsOnBuiltAgent(): void
    {
        $specialistModel = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'from specialist'];
            }
        };

        $manager = new ToolRoutingAgentManager(
            static fn (string $class): object => new $class(),
            [
                'model' => ['driver' => 'heuristic'],
                'tools' => [],
                'store' => ['driver' => 'none'],
                'tracing' => ['driver' => 'noop'],
            ],
        );

        $manager->registerHandoff(
            'worker',
            new ToolRoutingAgent($specialistModel, []),
            'Worker agent',
        );

        $agent = $manager->make();
        self::assertNotNull($agent->handoffRegistry());
        self::assertSame(['worker'], $agent->handoffRegistry()->names());
    }

    public function testManagerRegisterToolOverridesConfigToolWithSameName(): void
    {
        $customMath = new class () implements ToolInterface, \ML\IDEA\RAG\Contracts\ToolSchemaInterface {
            public function name(): string { return 'math'; }
            public function description(): string { return 'Custom math.'; }
            public function invoke(array $input): string { return '{"result":99}'; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function examples(): array { return []; }
            public function riskLevel(): string { return 'low'; }
        };

        $manager = new ToolRoutingAgentManager(
            static fn (string $class): object => new $class(),
            [
                'model' => ['driver' => 'heuristic'],
                'tools' => [MathTool::class],
                'store' => ['driver' => 'none'],
                'tracing' => ['driver' => 'noop'],
            ],
        );

        $manager->registerTool($customMath);
        self::assertSame(['math'], $manager->toolNames());

        $agent = $manager->make();
        $result = $agent->chat('ignored');
        self::assertSame('final', $result['stop_reason']);
    }

    public function testAgentApprovalContextBuildsReviewPayloadFromPausedResult(): void
    {
        $model = new class () implements \ML\IDEA\RAG\Contracts\ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'tool_call', 'tool' => 'risky', 'input' => ['order_id' => 101]]
                    : ['type' => 'final', 'content' => 'done'];
            }
        };

        $tool = new class () implements ToolInterface, \ML\IDEA\RAG\Contracts\ToolSchemaInterface {
            public function name(): string { return 'risky'; }
            public function description(): string { return 'Risky.'; }
            public function invoke(array $input): string { return '{"ok":true}'; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function examples(): array { return []; }
            public function riskLevel(): string { return 'high'; }
        };

        $manager = new ToolRoutingAgentManager(
            static fn (string $class): object => new $class(),
            [
                'model' => ['driver' => 'heuristic'],
                'tools' => [],
                'store' => ['driver' => 'none'],
                'tracing' => ['driver' => 'noop'],
            ],
        );

        $agent = new \ML\IDEA\RAG\Agents\ToolRoutingAgent(
            $model,
            [$tool],
            toolExecutor: new \ML\IDEA\RAG\Agents\ToolExecutor(
                new \ML\IDEA\RAG\Agents\ToolInputValidator(),
                new \ML\IDEA\RAG\Agents\AgentPolicy(pauseForApproval: true),
            ),
        );

        $paused = $agent->chat('run risky');
        $context = $manager->approvalContextFromResult($paused);

        self::assertNotNull($context);
        self::assertNotSame('', $context->summary);
        self::assertStringContainsString('risky', $context->recommendedAction);
        self::assertArrayHasKey('summary', $context->toReviewPayload());
        self::assertArrayHasKey('tools_used', $context->toReviewPayload());

        $stored = $context->toStorage();
        $restored = \ML\IDEA\Laravel\Support\AgentApprovalContext::fromStorage($stored);
        self::assertSame($context->approvalToken, $restored->approvalToken);
    }
}
