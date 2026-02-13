<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Knobik\SqlAgent\Data\TableSchema;
use Throwable;

class SchemaIntrospector
{
    public function __construct(
        protected TableAccessControl $tableAccessControl,
    ) {}

    /**
     * Get the schema for all tables in the connection.
     *
     * @return Collection<int, TableSchema>
     */
    public function getAllTables(?string $connection = null, ?string $connectionName = null): Collection
    {
        try {
            $tableNames = $this->getTableNames($connection, $connectionName);
        } catch (Throwable $e) {
            report($e);

            return collect();
        }

        return collect($tableNames)
            ->map(fn (string $tableName) => $this->introspectTable($tableName, $connection, $connectionName))
            ->filter();
    }

    /**
     * Introspect a single table.
     */
    public function introspectTable(string $tableName, ?string $connection = null, ?string $connectionName = null): ?TableSchema
    {
        if (! $this->tableAccessControl->isTableAllowed($tableName, $connectionName)) {
            return null;
        }

        try {
            if (! $this->tableExists($tableName, $connection)) {
                return null;
            }

            return $this->buildTableSchema($tableName, $connection, $connectionName);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Get relevant schema for a question by extracting potential table names.
     */
    public function getRelevantSchema(string $question, ?string $connection = null, ?string $connectionName = null): ?string
    {
        // Extract potential table names from the question
        $potentialTables = $this->extractPotentialTableNames($question, $connection, $connectionName);

        if (empty($potentialTables)) {
            return null;
        }

        $schemas = collect($potentialTables)
            ->map(fn (string $tableName) => $this->introspectTable($tableName, $connection, $connectionName))
            ->filter()
            ->map(fn (TableSchema $schema) => $schema->toPromptString());

        if ($schemas->isEmpty()) {
            return null;
        }

        return $schemas->implode("\n\n---\n\n");
    }

    /**
     * Build a TableSchema from Laravel's schema information.
     */
    protected function buildTableSchema(string $tableName, ?string $connection, ?string $connectionName = null): TableSchema
    {
        $schemaBuilder = Schema::connection($connection);

        $dbColumns = $schemaBuilder->getColumns($tableName);
        $indexes = $schemaBuilder->getIndexes($tableName);
        $foreignKeys = $this->getForeignKeys($tableName, $connection);

        // Find primary key columns
        $primaryKeyColumns = $this->getPrimaryKeyColumns($indexes);

        // Build foreign key lookup
        $foreignKeyMap = $this->buildForeignKeyMap($foreignKeys);

        // Build simplified column descriptions
        $columns = [];
        foreach ($dbColumns as $column) {
            $columnName = $column['name'];
            $parts = [$column['type_name']];

            if (in_array($columnName, $primaryKeyColumns)) {
                $parts[] = 'Primary key';
            }

            $fkInfo = $foreignKeyMap[$columnName] ?? null;
            if ($fkInfo !== null) {
                $parts[] = "FK \u{2192} {$fkInfo['table']}.{$fkInfo['column']}";
            }

            if (! $column['nullable']) {
                $parts[] = 'NOT NULL';
            }

            $default = $this->formatDefaultValue($column['default'] ?? null);
            if ($default !== null) {
                $parts[] = "default: {$default}";
            }

            if (! empty($column['comment'])) {
                $parts[] = $column['comment'];
            }

            $columns[$columnName] = implode(', ', $parts);
        }

        // Build simplified relationship descriptions
        $relationships = [];
        foreach ($foreignKeys as $fk) {
            $localColumn = $fk['columns'][0] ?? '';
            $foreignTable = $fk['foreign_table'];
            $foreignColumn = $fk['foreign_columns'][0] ?? 'id';
            $relationships[] = "belongsTo {$foreignTable} via {$localColumn} \u{2192} {$foreignTable}.{$foreignColumn}";
        }

        // Try to get table comment
        $tableComment = $this->getTableComment($tableName, $connection);

        $columns = $this->tableAccessControl->filterColumns($tableName, $columns, $connectionName);

        return new TableSchema(
            tableName: $tableName,
            description: $tableComment,
            columns: $columns,
            relationships: $relationships,
        );
    }

    /**
     * Get primary key columns from indexes.
     *
     * @return array<string>
     */
    public function getPrimaryKeyColumns(array $indexes): array
    {
        foreach ($indexes as $index) {
            if ($index['primary'] ?? false) {
                return $index['columns'] ?? [];
            }
        }

        return [];
    }

    /**
     * Build a map of column names to their foreign key info.
     *
     * @return array<string, array{table: string, column: string}>
     */
    public function buildForeignKeyMap(array $foreignKeys): array
    {
        $map = [];

        foreach ($foreignKeys as $fk) {
            $localColumns = $fk['columns'] ?? [];
            $foreignColumns = $fk['foreign_columns'] ?? [];
            $foreignTable = $fk['foreign_table'] ?? null;

            foreach ($localColumns as $index => $columnName) {
                $map[$columnName] = [
                    'table' => $foreignTable,
                    'column' => $foreignColumns[$index] ?? 'id',
                ];
            }
        }

        return $map;
    }

    /**
     * Get foreign keys for a table.
     *
     * @return array<array{name: string, columns: array, foreign_table: string, foreign_columns: array}>
     */
    public function getForeignKeys(string $tableName, ?string $connection): array
    {
        try {
            return Schema::connection($connection)->getForeignKeys($tableName);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Get table comment if supported by the database.
     */
    public function getTableComment(string $tableName, ?string $connection): ?string
    {
        try {
            $tables = $this->getTablesForConnection($connection);
        } catch (Throwable) {
            return null;
        }

        foreach ($tables as $table) {
            if ($table['name'] === $tableName) {
                return $table['comment'] ?? null;
            }
        }

        return null;
    }

    /**
     * Format default value for display.
     */
    public function formatDefaultValue(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }

        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }

        return (string) $default;
    }

    /**
     * Extract potential table names from a question.
     *
     * @return array<string>
     */
    protected function extractPotentialTableNames(string $question, ?string $connection = null, ?string $connectionName = null): array
    {
        try {
            $allTables = $this->getTableNames($connection, $connectionName);
        } catch (Throwable) {
            return [];
        }

        $questionLower = strtolower($question);
        $potentialTables = [];

        foreach ($allTables as $tableName) {
            $tableNameLower = strtolower($tableName);

            // Direct match
            if (str_contains($questionLower, $tableNameLower)) {
                $potentialTables[] = $tableName;

                continue;
            }

            // Naive singular/plural match: strips trailing 's' only.
            // Does not handle 'ies', 'es', etc. Sufficient for common table names.
            $singular = rtrim($tableNameLower, 's');
            if (str_contains($questionLower, $singular)) {
                $potentialTables[] = $tableName;

                continue;
            }

            // Common variations
            $variations = [
                str_replace('_', ' ', $tableNameLower),
                str_replace('_', '', $tableNameLower),
            ];

            foreach ($variations as $variation) {
                if (str_contains($questionLower, $variation)) {
                    $potentialTables[] = $tableName;
                    break;
                }
            }
        }

        return array_unique($potentialTables);
    }

    /**
     * Get table names from the database.
     *
     * @return array<string>
     */
    public function getTableNames(?string $connection = null, ?string $connectionName = null): array
    {
        try {
            $tables = $this->getTablesForConnection($connection);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $tableNames = array_map(fn (array $table) => $table['name'], $tables);

        return $this->tableAccessControl->filterTables($tableNames, $connectionName);
    }

    /**
     * Check if a table exists.
     */
    public function tableExists(string $tableName, ?string $connection = null): bool
    {
        try {
            return Schema::connection($connection)->hasTable($tableName);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get column names for a table.
     *
     * @return array<string>
     */
    public function getColumnNames(string $tableName, ?string $connection = null): array
    {
        $schema = $this->introspectTable($tableName, $connection);

        return $schema ? $schema->getColumnNames() : [];
    }

    /**
     * Format all tables as a prompt string.
     */
    public function format(?string $connection = null, ?string $connectionName = null): string
    {
        $tables = $this->getAllTables($connection, $connectionName);

        if ($tables->isEmpty()) {
            return 'No tables found in the database.';
        }

        return $tables
            ->map(fn (TableSchema $table) => $table->toPromptString())
            ->implode("\n\n---\n\n");
    }

    /**
     * Get tables filtered to the connection's configured database.
     *
     * Laravel's Schema::getTables() may return tables from all databases on the same
     * server (observed on MySQL). This method filters results to only include tables
     * belonging to the connection's configured database.
     *
     * @return list<array<string, mixed>>
     */
    protected function getTablesForConnection(?string $connection): array
    {
        $tables = Schema::connection($connection)->getTables();

        $schemas = array_unique(array_filter(array_column($tables, 'schema')));
        if (count($schemas) <= 1) {
            return $tables;
        }

        $databaseName = DB::connection($connection)->getDatabaseName();

        return array_values(
            array_filter($tables, fn (array $table) => ($table['schema'] ?? null) === $databaseName)
        );
    }
}
