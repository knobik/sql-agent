<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Events\SqlErrorOccurred;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\TableAccessControl;
use Prism\Prism\Tool;
use RuntimeException;
use Throwable;

class RunSqlTool extends Tool
{
    protected ?string $question = null;

    protected TableAccessControl $tableAccessControl;

    protected ConnectionRegistry $connectionRegistry;

    public ?string $lastSql = null;

    public ?array $lastResults = null;

    public function __construct()
    {
        $this->tableAccessControl = app(TableAccessControl::class);
        $this->connectionRegistry = app(ConnectionRegistry::class);

        $allowed = implode(', ', config('sql-agent.sql.allowed_statements'));

        $this
            ->as('run_sql')
            ->for("Execute a SQL query against the database. Only {$allowed} statements are allowed. Returns query results as JSON.")
            ->withStringParameter('sql', "The SQL query to execute. Must be a {$allowed} statement.")
            ->withEnumParameter(
                'connection',
                'The database connection to query.',
                $this->connectionRegistry->getConnectionNames(),
            )
            ->using($this);
    }

    public function __invoke(string $sql, ?string $connection = null): string
    {
        $sql = trim($sql);

        if (empty($sql)) {
            throw new RuntimeException('SQL query cannot be empty.');
        }

        $this->validateSql($sql, $connection);

        $resolvedConnection = $this->resolveConnection($connection);
        $maxRows = config('sql-agent.sql.max_rows');

        try {
            $results = DB::connection($resolvedConnection)->select($sql);
        } catch (Throwable $e) {
            if ($this->question !== null) {
                SqlErrorOccurred::dispatch(
                    $sql,
                    $e->getMessage(),
                    $this->question,
                    $resolvedConnection,
                );
            }

            throw $e;
        }

        $rows = array_map(fn ($row) => (array) $row, $results);

        $totalRows = count($rows);
        $rows = array_slice($rows, 0, $maxRows);

        $this->lastSql = $sql;
        $this->lastResults = $rows;

        return json_encode([
            'rows' => $rows,
            'row_count' => count($rows),
            'total_rows' => $totalRows,
            'truncated' => $totalRows > $maxRows,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function setQuestion(?string $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    protected function resolveConnection(?string $logicalName): ?string
    {
        return $this->connectionRegistry->resolveConnection($logicalName);
    }

    protected function validateSql(string $sql, ?string $connectionName = null): void
    {
        $sqlUpper = strtoupper(trim($sql));

        $allowedStatements = config('sql-agent.sql.allowed_statements');
        $startsWithAllowed = false;

        foreach ($allowedStatements as $statement) {
            if (str_starts_with($sqlUpper, $statement)) {
                $startsWithAllowed = true;
                break;
            }
        }

        if (! $startsWithAllowed) {
            throw new RuntimeException(
                'Only '.implode(' and ', $allowedStatements).' statements are allowed.'
            );
        }

        $forbiddenKeywords = config('sql-agent.sql.forbidden_keywords');

        foreach ($forbiddenKeywords as $keyword) {
            $pattern = '/\b'.preg_quote($keyword, '/').'\b/i';
            if (preg_match($pattern, $sql)) {
                throw new RuntimeException(
                    "Forbidden SQL keyword detected: {$keyword}. This query cannot be executed."
                );
            }
        }

        $withoutStrings = preg_replace("/'[^']*'/", '', $sql);
        $withoutStrings = preg_replace('/"[^"]*"/', '', $withoutStrings);

        if (substr_count($withoutStrings, ';') > 1) {
            throw new RuntimeException('Multiple SQL statements are not allowed.');
        }

        $this->validateTableAccess($withoutStrings, $connectionName);
    }

    /**
     * Extract table names from SQL and validate access.
     */
    protected function validateTableAccess(string $sql, ?string $connectionName = null): void
    {
        $tables = $this->extractTableNames($sql);

        foreach ($tables as $table) {
            if (! $this->tableAccessControl->isTableAllowed($table, $connectionName)) {
                throw new RuntimeException(
                    "Access denied: table '{$table}' is restricted and cannot be queried."
                );
            }
        }
    }

    /**
     * Extract table names from SQL (best-effort regex).
     *
     * @return array<string>
     */
    protected function extractTableNames(string $sql): array
    {
        $tables = [];

        // Match FROM table, JOIN table, INTO table, UPDATE table patterns
        // Handles optional schema prefix (schema.table) and backtick/bracket quoting
        $pattern = '/\b(?:FROM|JOIN|INTO|UPDATE)\s+([`\[\"]?)(\w+(?:\.\w+)?)\1/i';
        if (preg_match_all($pattern, $sql, $matches)) {
            foreach ($matches[2] as $match) {
                // Strip schema prefix if present
                $parts = explode('.', $match);
                $tables[] = end($parts);
            }
        }

        return array_unique($tables);
    }
}
