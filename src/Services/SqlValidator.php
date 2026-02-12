<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use RuntimeException;

class SqlValidator
{
    public function __construct(
        protected TableAccessControl $tableAccessControl,
    ) {}

    public function validate(string $sql, ?string $connectionName = null): void
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
     * @return array<string>
     */
    protected function extractTableNames(string $sql): array
    {
        $tables = [];

        $pattern = '/\b(?:FROM|JOIN|INTO|UPDATE)\s+([`\[\"]?)(\w+(?:\.\w+)?)\1/i';
        if (preg_match_all($pattern, $sql, $matches)) {
            foreach ($matches[2] as $match) {
                $parts = explode('.', $match);
                $tables[] = end($parts);
            }
        }

        return array_unique($tables);
    }
}
