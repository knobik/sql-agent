<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Services\TableAccessControl;
use Prism\Prism\Tool;
use RuntimeException;

class IntrospectSchemaTool extends Tool
{
    protected TableAccessControl $tableAccessControl;

    protected ConnectionRegistry $connectionRegistry;

    public function __construct(
        protected SchemaIntrospector $introspector,
    ) {
        $this->tableAccessControl = app(TableAccessControl::class);
        $this->connectionRegistry = app(ConnectionRegistry::class);

        $this
            ->as('introspect_schema')
            ->for('Get detailed schema information about database tables. Can inspect a specific table or list all available tables.')
            ->withStringParameter('table_name', 'Optional: The name of a specific table to inspect. If not provided, lists all tables.', required: false)
            ->withBooleanParameter('include_sample_data', 'Whether to include sample data from the table (up to 3 rows). This data is for understanding the schema only - never use it directly in responses to the user.', required: false)
            ->withEnumParameter(
                'connection',
                'The database connection to inspect.',
                $this->connectionRegistry->getConnectionNames(),
            )
            ->using($this);
    }

    public function __invoke(?string $table_name = null, bool $include_sample_data = false, ?string $connection = null): string
    {
        $resolvedConnection = $this->resolveConnection($connection);

        if ($table_name) {
            return json_encode(
                $this->inspectTable($table_name, $include_sample_data, $resolvedConnection, $connection),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        return json_encode(
            $this->listTables($resolvedConnection, $connection),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    protected function resolveConnection(?string $logicalName): ?string
    {
        return $this->connectionRegistry->resolveConnection($logicalName);
    }

    protected function listTables(?string $connection, ?string $connectionName = null): array
    {
        $tableNames = $this->introspector->getTableNames($connection, $connectionName);

        return [
            'tables' => $tableNames,
            'count' => count($tableNames),
        ];
    }

    protected function inspectTable(string $tableName, bool $includeSampleData, ?string $connection, ?string $connectionName = null): array
    {
        if (! $this->tableAccessControl->isTableAllowed($tableName, $connectionName)) {
            throw new RuntimeException(
                "Access denied: table '{$tableName}' is restricted."
            );
        }

        if (! $this->introspector->tableExists($tableName, $connection)) {
            $available = $this->introspector->getTableNames($connection, $connectionName);

            throw new RuntimeException(
                "Table '{$tableName}' does not exist. Available tables: ".implode(', ', $available)
            );
        }

        $schemaBuilder = Schema::connection($connection);

        $dbColumns = $schemaBuilder->getColumns($tableName);
        $indexes = $schemaBuilder->getIndexes($tableName);
        $foreignKeys = $this->introspector->getForeignKeys($tableName, $connection);

        $primaryKeyColumns = $this->introspector->getPrimaryKeyColumns($indexes);
        $foreignKeyMap = $this->introspector->buildForeignKeyMap($foreignKeys);

        $hiddenColumns = $this->tableAccessControl->getHiddenColumns($tableName, $connectionName);

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
                'default' => $this->introspector->formatDefaultValue($column['default'] ?? null),
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

        $tableComment = $this->introspector->getTableComment($tableName, $connection);

        $result = [
            'table' => $tableName,
            'description' => $tableComment,
            'columns' => $columns,
            'relationships' => $relationships,
        ];

        if ($includeSampleData) {
            $result['sample_data'] = $this->getSampleData($tableName, $connection, $connectionName);
        }

        return $result;
    }

    protected function getSampleData(string $tableName, ?string $connection, ?string $connectionName = null): array
    {
        $hiddenColumns = $this->tableAccessControl->getHiddenColumns($tableName, $connectionName);

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
