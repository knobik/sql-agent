<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

class TableAccessControl
{
    /**
     * Filter a list of table names through allowed/denied rules.
     *
     * @param  array<string>  $tables
     * @return array<string>
     */
    public function filterTables(array $tables): array
    {
        return array_values(array_filter($tables, fn (string $table) => $this->isTableAllowed($table)));
    }

    /**
     * Check if a table is allowed based on allowed/denied config.
     * Denied takes precedence over allowed.
     */
    public function isTableAllowed(string $table): bool
    {
        $deniedTables = config('sql-agent.sql.denied_tables');

        if (in_array($table, $deniedTables, true)) {
            return false;
        }

        $allowedTables = config('sql-agent.sql.allowed_tables');

        if (empty($allowedTables)) {
            return true;
        }

        return in_array($table, $allowedTables, true);
    }

    /**
     * Filter columns for a table, removing hidden ones.
     *
     * @param  array<string, mixed>  $columns  column name => description/info
     * @return array<string, mixed>
     */
    public function filterColumns(string $table, array $columns): array
    {
        $hidden = $this->getHiddenColumns($table);

        if (empty($hidden)) {
            return $columns;
        }

        return array_diff_key($columns, array_flip($hidden));
    }

    /**
     * Get the list of hidden columns for a table.
     *
     * @return array<string>
     */
    public function getHiddenColumns(string $table): array
    {
        $hiddenColumns = config('sql-agent.sql.hidden_columns');

        return $hiddenColumns[$table] ?? [];
    }
}
