<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Events\SqlErrorOccurred;
use Prism\Prism\Tool;
use RuntimeException;
use Throwable;

class RunSqlTool extends Tool
{
    protected ?string $connection = null;

    protected ?string $question = null;

    public ?string $lastSql = null;

    public ?array $lastResults = null;

    public function __construct()
    {
        $allowed = implode(', ', config('sql-agent.sql.allowed_statements'));

        $this
            ->as('run_sql')
            ->for("Execute a SQL query against the database. Only {$allowed} statements are allowed. Returns query results as JSON.")
            ->withStringParameter('sql', "The SQL query to execute. Must be a {$allowed} statement.")
            ->using($this);
    }

    public function __invoke(string $sql): string
    {
        $sql = trim($sql);

        if (empty($sql)) {
            throw new RuntimeException('SQL query cannot be empty.');
        }

        $this->validateSql($sql);

        $connection = $this->connection ?? config('sql-agent.database.connection');
        $maxRows = config('sql-agent.sql.max_rows');

        try {
            $results = DB::connection($connection)->select($sql);
        } catch (Throwable $e) {
            if ($this->question !== null) {
                SqlErrorOccurred::dispatch(
                    $sql,
                    $e->getMessage(),
                    $this->question,
                    $connection,
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

    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
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

    protected function validateSql(string $sql): void
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
    }
}
