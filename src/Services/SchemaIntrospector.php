<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Knobik\SqlAgent\Data\ColumnInfo;
use Knobik\SqlAgent\Data\RelationshipInfo;
use Knobik\SqlAgent\Data\TableSchema;
use Throwable;

class SchemaIntrospector
{
    /**
     * Get the schema for all tables in the connection.
     *
     * @return Collection<int, TableSchema>
     */
    public function getAllTables(?string $connection = null): Collection
    {
        $connection = $this->resolveConnection($connection);

        try {
            $tableNames = $this->getTableNames($connection);
        } catch (Throwable $e) {
            report($e);

            return collect();
        }

        return collect($tableNames)
            ->map(fn (string $tableName) => $this->introspectTable($tableName, $connection))
            ->filter();
    }

    /**
     * Introspect a single table.
     */
    public function introspectTable(string $tableName, ?string $connection = null): ?TableSchema
    {
        $connection = $this->resolveConnection($connection);

        try {
            if (! $this->tableExists($tableName, $connection)) {
                return null;
            }

            return $this->buildTableSchema($tableName, $connection);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Get relevant schema for a question by extracting potential table names.
     */
    public function getRelevantSchema(string $question, ?string $connection = null): ?string
    {
        $connection = $this->resolveConnection($connection);

        // Extract potential table names from the question
        $potentialTables = $this->extractPotentialTableNames($question, $connection);

        if (empty($potentialTables)) {
            return null;
        }

        $schemas = collect($potentialTables)
            ->map(fn (string $tableName) => $this->introspectTable($tableName, $connection))
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
    protected function buildTableSchema(string $tableName, ?string $connection): TableSchema
    {
        $schemaBuilder = Schema::connection($connection);

        $columns = $schemaBuilder->getColumns($tableName);
        $indexes = $schemaBuilder->getIndexes($tableName);
        $foreignKeys = $this->getForeignKeys($tableName, $connection);

        // Find primary key columns
        $primaryKeyColumns = $this->getPrimaryKeyColumns($indexes);

        // Build foreign key lookup
        $foreignKeyMap = $this->buildForeignKeyMap($foreignKeys);

        // Build column info collection
        $columnInfos = collect($columns)->map(function (array $column) use ($primaryKeyColumns, $foreignKeyMap) {
            $columnName = $column['name'];
            $fkInfo = $foreignKeyMap[$columnName] ?? null;

            return new ColumnInfo(
                name: $columnName,
                type: $column['type_name'] ?? $column['type'] ?? 'unknown',
                description: $column['comment'] ?? null,
                nullable: $column['nullable'] ?? true,
                isPrimaryKey: in_array($columnName, $primaryKeyColumns),
                isForeignKey: $fkInfo !== null,
                foreignTable: $fkInfo['table'] ?? null,
                foreignColumn: $fkInfo['column'] ?? null,
                defaultValue: $this->formatDefaultValue($column['default'] ?? null),
            );
        });

        // Build relationships from foreign keys
        $relationships = collect($foreignKeys)->map(fn (array $fk) => new RelationshipInfo(
            type: 'belongsTo',
            relatedTable: $fk['foreign_table'],
            foreignKey: $fk['columns'][0] ?? '',
            localKey: $fk['foreign_columns'][0] ?? 'id',
        ));

        // Try to get table comment
        $tableComment = $this->getTableComment($tableName, $connection);

        return new TableSchema(
            tableName: $tableName,
            description: $tableComment,
            columns: $columnInfos,
            relationships: $relationships,
        );
    }

    /**
     * Get primary key columns from indexes.
     *
     * @return array<string>
     */
    protected function getPrimaryKeyColumns(array $indexes): array
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
    protected function buildForeignKeyMap(array $foreignKeys): array
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
    protected function getForeignKeys(string $tableName, ?string $connection): array
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
    protected function getTableComment(string $tableName, ?string $connection): ?string
    {
        try {
            $tables = Schema::connection($connection)->getTables();

            foreach ($tables as $table) {
                if (($table['name'] ?? '') === $tableName) {
                    return $table['comment'] ?? null;
                }
            }
        } catch (Throwable) {
            // Table comments not supported or error occurred
        }

        return null;
    }

    /**
     * Format default value for display.
     */
    protected function formatDefaultValue(mixed $default): ?string
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
    protected function extractPotentialTableNames(string $question, ?string $connection = null): array
    {
        $connection = $this->resolveConnection($connection);

        try {
            $allTables = $this->getTableNames($connection);
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

            // Singular/plural match
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
    public function getTableNames(?string $connection = null): array
    {
        $connection = $this->resolveConnection($connection);

        try {
            $tables = Schema::connection($connection)->getTables();

            return array_map(fn (array $table) => $table['name'], $tables);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Check if a table exists.
     */
    public function tableExists(string $tableName, ?string $connection = null): bool
    {
        $connection = $this->resolveConnection($connection);

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
    public function format(?string $connection = null): string
    {
        $tables = $this->getAllTables($connection);

        if ($tables->isEmpty()) {
            return 'No tables found in the database.';
        }

        return $tables
            ->map(fn (TableSchema $table) => $table->toPromptString())
            ->implode("\n\n---\n\n");
    }

    /**
     * Resolve the connection name.
     */
    protected function resolveConnection(?string $connection): ?string
    {
        return $connection ?? config('sql-agent.database.connection');
    }
}
