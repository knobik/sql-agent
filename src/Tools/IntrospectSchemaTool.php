<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Services\TableAccessControl;
use Prism\Prism\Tool;
use RuntimeException;
use Throwable;

class IntrospectSchemaTool extends Tool
{
    protected ?string $connection = null;

    protected TableAccessControl $tableAccessControl;

    public function __construct(
        protected SchemaIntrospector $introspector,
    ) {
        $this->tableAccessControl = app(TableAccessControl::class);

        $this
            ->as('introspect_schema')
            ->for('Get detailed schema information about database tables. Can inspect a specific table or list all available tables.')
            ->withStringParameter('table_name', 'Optional: The name of a specific table to inspect. If not provided, lists all tables.', required: false)
            ->withBooleanParameter('include_sample_data', 'Whether to include sample data from the table (up to 3 rows). This data is for understanding the schema only - never use it directly in responses to the user.', required: false)
            ->using($this);
    }

    public function __invoke(?string $table_name = null, bool $include_sample_data = false): string
    {
        $connection = $this->connection ?? config('sql-agent.database.connection');

        if ($table_name) {
            return json_encode(
                $this->inspectTable($table_name, $include_sample_data, $connection),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        return json_encode(
            $this->listTables($connection),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    protected function listTables(?string $connection): array
    {
        $tableNames = $this->introspector->getTableNames($connection);

        return [
            'tables' => $tableNames,
            'count' => count($tableNames),
        ];
    }

    protected function inspectTable(string $tableName, bool $includeSampleData, ?string $connection): array
    {
        if (! $this->tableAccessControl->isTableAllowed($tableName)) {
            throw new RuntimeException(
                "Access denied: table '{$tableName}' is restricted."
            );
        }

        if (! $this->introspector->tableExists($tableName, $connection)) {
            $available = $this->introspector->getTableNames($connection);

            throw new RuntimeException(
                "Table '{$tableName}' does not exist. Available tables: ".implode(', ', $available)
            );
        }

        $schemaBuilder = Schema::connection($connection);

        $dbColumns = $schemaBuilder->getColumns($tableName);
        $indexes = $schemaBuilder->getIndexes($tableName);
        $foreignKeys = $this->getForeignKeys($tableName, $connection);

        $primaryKeyColumns = $this->getPrimaryKeyColumns($indexes);
        $foreignKeyMap = $this->buildForeignKeyMap($foreignKeys);

        $hiddenColumns = $this->tableAccessControl->getHiddenColumns($tableName);

        $columns = [];
        foreach ($dbColumns as $column) {
            $columnName = $column['name'];

            if (in_array($columnName, $hiddenColumns, true)) {
                continue;
            }

            $fkInfo = $foreignKeyMap[$columnName] ?? null;

            $columns[] = [
                'name' => $columnName,
                'type' => $column['type_name'],
                'nullable' => $column['nullable'],
                'primary_key' => in_array($columnName, $primaryKeyColumns),
                'foreign_key' => $fkInfo !== null,
                'references' => $fkInfo !== null ? "{$fkInfo['table']}.{$fkInfo['column']}" : null,
                'default' => $this->formatDefaultValue($column['default'] ?? null),
                'description' => $column['comment'] ?? null,
            ];
        }

        $relationships = [];
        foreach ($foreignKeys as $fk) {
            $relationships[] = [
                'type' => 'belongsTo',
                'related_table' => $fk['foreign_table'],
                'foreign_key' => $fk['columns'][0] ?? '',
                'local_key' => $fk['foreign_columns'][0] ?? 'id',
            ];
        }

        $tableComment = $this->getTableComment($tableName, $connection);

        $result = [
            'table' => $tableName,
            'description' => $tableComment,
            'columns' => $columns,
            'relationships' => $relationships,
        ];

        if ($includeSampleData) {
            $result['sample_data'] = $this->getSampleData($tableName, $connection);
        }

        return $result;
    }

    /**
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

    protected function getForeignKeys(string $tableName, ?string $connection): array
    {
        try {
            return Schema::connection($connection)->getForeignKeys($tableName);
        } catch (Throwable) {
            return [];
        }
    }

    protected function getTableComment(string $tableName, ?string $connection): ?string
    {
        try {
            $tables = Schema::connection($connection)->getTables();
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

    protected function getSampleData(string $tableName, ?string $connection): array
    {
        $hiddenColumns = $this->tableAccessControl->getHiddenColumns($tableName);

        $rows = DB::connection($connection)
            ->table($tableName)
            ->limit(3)
            ->get();

        return $rows->map(function ($row) use ($hiddenColumns) {
            $data = (array) $row;

            return array_diff_key($data, array_flip($hiddenColumns));
        })->toArray();
    }
}
