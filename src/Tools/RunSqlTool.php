<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Events\SqlErrorOccurred;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\SqlValidator;
use Prism\Prism\Tool;
use RuntimeException;
use Throwable;

class RunSqlTool extends Tool
{
    protected ?string $question = null;

    protected SqlValidator $sqlValidator;

    protected ConnectionRegistry $connectionRegistry;

    private ?string $lastSql = null;

    private ?array $lastResults = null;

    private array $executedQueries = [];

    public function __construct()
    {
        $this->sqlValidator = app(SqlValidator::class);
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
        $this->executedQueries[] = [
            'sql' => $sql,
            'connection' => $connection,
        ];

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

    public function getLastSql(): ?string
    {
        return $this->lastSql;
    }

    public function getLastResults(): ?array
    {
        return $this->lastResults;
    }

    /**
     * @return array<int, array{sql: string, connection: ?string}>
     */
    public function getExecutedQueries(): array
    {
        return $this->executedQueries;
    }

    public function reset(): void
    {
        $this->lastSql = null;
        $this->lastResults = null;
        $this->executedQueries = [];
    }

    protected function resolveConnection(?string $logicalName): ?string
    {
        return $this->connectionRegistry->resolveConnection($logicalName);
    }

    protected function validateSql(string $sql, ?string $connectionName = null): void
    {
        $this->sqlValidator->validate($sql, $connectionName);
    }
}
