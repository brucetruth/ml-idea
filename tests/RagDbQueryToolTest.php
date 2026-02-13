<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Tools\DbQueryTool;
use PHPUnit\Framework\TestCase;

final class RagDbQueryToolTest extends TestCase
{
    public function testDbQueryToolCanRunReadOnlyParameterizedQuery(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('sqlite PDO driver is not available.');
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, customer TEXT, amount REAL)');
        $pdo->exec("INSERT INTO orders(customer, amount) VALUES ('Bruce', 120.50), ('Ava', 45.25)");

        $tool = new DbQueryTool($pdo, allowedTables: ['orders'], readOnly: true, maxRows: 50);
        $output = $tool->invoke([
            'sql' => 'SELECT customer, amount FROM orders WHERE amount > :min_amount',
            'params' => ['min_amount' => 100],
        ]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) $decoded['ok']);
        self::assertSame(1, $decoded['row_count']);
    }

    public function testDbQueryToolBlocksWriteQueryInReadOnlyMode(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('sqlite PDO driver is not available.');
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, name TEXT)');

        $tool = new DbQueryTool($pdo, allowedTables: ['events'], readOnly: true);
        $output = $tool->invoke(['sql' => "INSERT INTO events(name) VALUES ('x')"]);

        self::assertStringContainsString('Read-only DbQueryTool', $output);
    }

    public function testDbQueryToolColumnAllowListAndAuditLogging(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('sqlite PDO driver is not available.');
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, customer TEXT, amount REAL, secret_note TEXT)');
        $pdo->exec("INSERT INTO orders(customer, amount, secret_note) VALUES ('Bruce', 120.50, 'internal')");

        $logPath = sys_get_temp_dir() . '/ml_idea_db_query_audit_' . uniqid('', true) . '.log';

        $tool = new DbQueryTool(
            $pdo,
            allowedTables: ['orders'],
            readOnly: true,
            maxRows: 50,
            allowedColumnsByTable: ['orders' => ['id', 'customer', 'amount']],
            maxExecutionMs: 2500,
            auditLogPath: $logPath,
        );

        $okOutput = $tool->invoke(['sql' => 'SELECT id, customer, amount FROM orders']);
        /** @var array<string, mixed> $okDecoded */
        $okDecoded = json_decode($okOutput, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) $okDecoded['ok']);

        $badOutput = $tool->invoke(['sql' => 'SELECT * FROM orders']);
        self::assertStringContainsString('Wildcard select is not allowed', $badOutput);

        self::assertFileExists($logPath);
        $logRaw = file_get_contents($logPath);
        self::assertNotFalse($logRaw);
        self::assertStringContainsString('"tool":"db_query"', (string) $logRaw);

        @unlink($logPath);
    }
}
