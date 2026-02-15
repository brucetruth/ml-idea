<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\ToolInterface;

final class DbQueryTool implements ToolInterface
{
    /**
     * @param array<int, string> $allowedTables
     * @param array<string, array<int, string>> $allowedColumnsByTable
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly array $allowedTables = [],
        private readonly bool $readOnly = true,
        private readonly int $maxRows = 200,
        private readonly array $allowedColumnsByTable = [],
        private readonly int $maxExecutionMs = 2500,
        private readonly ?string $auditLogPath = null,
    ) {
    }

    public function name(): string
    {
        return 'db_query';
    }

    public function description(): string
    {
        return 'Executes parameterized SQL allowed table_names include ['.json_encode($this->allowedTables).'], user might misspell table names, so understand user question and match with the required tables, tables access policy is currently: '.$this->readOnly.' you are allowed to pre-run other queries to understand table structures. Input: {"sql":"SELECT ... WHERE id = :id", "params":{"id":1}}';
    }

    public function invoke(array $input): string
    {
        $sql = isset($input['sql']) ? trim((string) $input['sql']) : '';
        if ($sql === '') {
            return 'DbQueryTool: missing sql.';
        }

        /** @var array<string, mixed> $params */
        $params = isset($input['params']) && is_array($input['params']) ? $input['params'] : [];

        try {
            $this->guardSql($sql);

            $start = microtime(true);
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new InvalidArgumentException('Failed to prepare SQL statement.');
            }

            if (!$stmt->execute($params)) {
                throw new InvalidArgumentException('SQL execution failed.');
            }

            if ($stmt->columnCount() === 0) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                $this->guardDuration($durationMs);
                $this->audit($sql, $params, true, $durationMs, $stmt->rowCount(), null);

                return json_encode([
                    'ok' => true,
                    'mode' => $this->readOnly ? 'read_only' : 'read_write',
                    'affected_rows' => $stmt->rowCount(),
                    'duration_ms' => $durationMs,
                ], JSON_THROW_ON_ERROR);
            }

            $rows = [];
            $truncated = false;
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $rows[] = $row;
                if (count($rows) > $this->maxRows) {
                    array_pop($rows);
                    $truncated = true;
                    break;
                }
            }

            $columns = $rows === [] ? [] : array_keys($rows[0]);
            $this->guardSelectedColumns($columns, $sql);

            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->guardDuration($durationMs);
            $this->audit($sql, $params, true, $durationMs, count($rows), $truncated);

            return json_encode([
                'ok' => true,
                'mode' => $this->readOnly ? 'read_only' : 'read_write',
                'columns' => $columns,
                'row_count' => count($rows),
                'truncated' => $truncated,
                'duration_ms' => $durationMs,
                'rows' => $rows,
            ], JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->audit($sql, $params, false, null, null, null, $e->getMessage());
            return 'DbQueryTool error: ' . $e->getMessage();
        }
    }

    private function guardSql(string $sql): void
    {
        if (str_contains($sql, ';')) {
            throw new InvalidArgumentException('Multiple SQL statements are not allowed.');
        }

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));

        if ($this->readOnly) {
            $isReadOp = str_starts_with($normalized, 'select ')
                || str_starts_with($normalized, 'with ')
                || str_starts_with($normalized, 'pragma table_info(');

            if (!$isReadOp) {
                throw new InvalidArgumentException('Read-only DbQueryTool accepts only SELECT/WITH queries.');
            }

            $denied = [' insert ', ' update ', ' delete ', ' drop ', ' alter ', ' create ', ' truncate ', ' replace ', ' attach ', ' detach ', ' grant ', ' revoke '];
            foreach ($denied as $keyword) {
                if (str_contains(' ' . $normalized . ' ', $keyword)) {
                    throw new InvalidArgumentException(sprintf('Forbidden keyword in read-only query: %s', trim($keyword)));
                }
            }
        }

        if ($this->allowedTables !== []) {
            $tables = $this->extractTableNames($normalized);
            foreach ($tables as $table) {
                if (!in_array($table, $this->allowedTables, true)) {
                    throw new InvalidArgumentException(sprintf('Table not allowed: %s', $table));
                }
            }
        }

        if ($this->allowedColumnsByTable !== []) {
            $this->guardRequestedColumns($normalized);
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractTableNames(string $sql): array
    {
        preg_match_all('/\b(?:from|join|update|into)\s+([a-zA-Z_][a-zA-Z0-9_]*)\b/', $sql, $m);
        /** @var array<int, string> $tables */
        $tables = $m[1];
        return array_values(array_unique($tables));
    }

    private function guardDuration(int $durationMs): void
    {
        if ($durationMs > $this->maxExecutionMs) {
            throw new InvalidArgumentException(sprintf('Query exceeded execution budget: %d ms > %d ms', $durationMs, $this->maxExecutionMs));
        }
    }

    /**
     * @param array<int, string> $returnedColumns
     */
    private function guardSelectedColumns(array $returnedColumns, string $sql): void
    {
        if ($this->allowedColumnsByTable === [] || $returnedColumns === []) {
            return;
        }

        $tables = $this->extractTableNames(strtolower($sql));
        if (count($tables) !== 1) {
            return;
        }

        $table = $tables[0];
        $allowed = $this->allowedColumnsByTable[$table] ?? null;
        if ($allowed === null) {
            return;
        }

        foreach ($returnedColumns as $column) {
            if (!in_array($column, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('Column not allowed in result: %s', $column));
            }
        }
    }

    private function guardRequestedColumns(string $normalizedSql): void
    {
        if (!str_starts_with($normalizedSql, 'select ') && !str_starts_with($normalizedSql, 'with ')) {
            return;
        }

        if (preg_match('/\bselect\s+(.*?)\s+from\s+([a-zA-Z_][a-zA-Z0-9_]*)\b/', $normalizedSql, $m) !== 1) {
            return;
        }

        $selectClause = trim($m[1]);
        $table = trim($m[2]);
        $allowed = $this->allowedColumnsByTable[$table] ?? null;
        if ($allowed === null) {
            return;
        }

        if ($selectClause === '*') {
            throw new InvalidArgumentException('Wildcard select is not allowed with column allow-list policy.');
        }

        $parts = array_map('trim', explode(',', $selectClause));
        foreach ($parts as $part) {
            $column = $part;
            if (str_contains($column, '.')) {
                $segments = explode('.', $column);
                $column = trim((string) end($segments));
            }

            if (preg_match('/\bas\s+([a-zA-Z_][a-zA-Z0-9_]*)$/', $column, $aliasMatch) === 1) {
                $column = trim(str_replace($aliasMatch[0], '', $column));
            }

            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column) !== 1) {
                continue;
            }

            if (!in_array($column, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('Requested column not allowed: %s', $column));
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function audit(string $sql, array $params, bool $ok, ?int $durationMs, ?int $rowCount, ?bool $truncated, ?string $error = null): void
    {
        if ($this->auditLogPath === null || $this->auditLogPath === '') {
            return;
        }

        $event = [
            'timestamp' => date('c'),
            'tool' => 'db_query',
            'ok' => $ok,
            'mode' => $this->readOnly ? 'read_only' : 'read_write',
            'sql' => $sql,
            'params' => $params,
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'truncated' => $truncated,
            'error' => $error,
        ];

        DbQueryAuditLogger::append($this->auditLogPath, $event);
    }
}
