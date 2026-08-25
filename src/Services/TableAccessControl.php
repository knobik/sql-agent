<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

class TableAccessControl
{
    public function __construct(
        protected ConnectionRegistry $connectionRegistry,
    ) {}

    /**
     * Filter a list of table names through allowed/denied rules.
     *
     * @param  array<string>  $tables
     * @return array<string>
     */
    public function filterTables(array $tables, ?string $connectionName = null): array
    {
        return array_values(array_filter($tables, fn (string $table) => $this->isTableAllowed($table, $connectionName)));
    }

    /**
     * Check if a table is allowed based on allowed/denied config.
     * Denied takes precedence over allowed.
     */
    public function isTableAllowed(string $table, ?string $connectionName = null): bool
    {
        [$allowedTables, $deniedTables] = $this->resolveTableRules($connectionName);

        if (in_array($table, $deniedTables, true)) {
            return false;
        }

        if (empty($allowedTables)) {
            return true;
        }

        return in_array($table, $allowedTables, true);
    }

    /**
     * Table names that must not be disclosed on this connection.
     *
     * Combines the configured deny list with any candidate the allow list
     * excludes, so callers can scrub references to tables the agent cannot see.
     *
     * @param  array<string>  $candidates  table names that may be referenced
     * @return array<string>
     */
    public function getDisallowedTables(array $candidates = [], ?string $connectionName = null): array
    {
        [, $deniedTables] = $this->resolveTableRules($connectionName);

        $disallowed = $deniedTables;

        foreach ($candidates as $candidate) {
            if (! $this->isTableAllowed($candidate, $connectionName)) {
                $disallowed[] = $candidate;
            }
        }

        return array_values(array_unique($disallowed));
    }

    /**
     * Drop the descriptions that name a table the agent may not access.
     *
     * Relationships and data quality notes are free text, so a restricted table
     * can otherwise surface through the documentation of a permitted one.
     *
     * @param  array<string>  $descriptions
     * @param  array<string>  $disallowedTables
     * @return array<string>
     */
    public function filterDescriptions(array $descriptions, array $disallowedTables): array
    {
        if ($disallowedTables === []) {
            return $descriptions;
        }

        return array_values(array_filter(
            $descriptions,
            fn (string $description) => ! $this->mentionsTable($description, $disallowedTables),
        ));
    }

    /**
     * Whether a piece of text names any of the given tables.
     *
     * Matched on word boundaries so "users" does not match "user_id".
     *
     * @param  array<string>  $tables
     */
    protected function mentionsTable(string $text, array $tables): bool
    {
        foreach ($tables as $table) {
            if ($table !== '' && preg_match('/\b'.preg_quote($table, '/').'\b/i', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter columns for a table, removing hidden ones.
     *
     * @param  array<string, mixed>  $columns  column name => description/info
     * @return array<string, mixed>
     */
    public function filterColumns(string $table, array $columns, ?string $connectionName = null): array
    {
        $hidden = $this->getHiddenColumns($table, $connectionName);

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
    public function getHiddenColumns(string $table, ?string $connectionName = null): array
    {
        $hiddenColumns = $this->resolveHiddenColumns($connectionName);

        return $hiddenColumns[$table] ?? [];
    }

    /**
     * Resolve allowed/denied table rules for a connection.
     *
     * @return array{0: array<string>, 1: array<string>}
     */
    protected function resolveTableRules(?string $connectionName): array
    {
        if ($connectionName !== null) {
            $config = $this->connectionRegistry->getConnection($connectionName);

            return [$config->allowedTables, $config->deniedTables];
        }

        return [[], []];
    }

    /**
     * Resolve hidden columns config for a connection.
     *
     * @return array<string, array<string>>
     */
    protected function resolveHiddenColumns(?string $connectionName): array
    {
        if ($connectionName !== null) {
            return $this->connectionRegistry->getConnection($connectionName)->hiddenColumns;
        }

        return [];
    }
}
