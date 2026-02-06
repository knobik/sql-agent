<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use RuntimeException;

class IntrospectSchemaTool extends BaseTool
{
    protected ?string $connection = null;

    public function __construct(
        protected SchemaIntrospector $introspector
    ) {}

    public function name(): string
    {
        return 'introspect_schema';
    }

    public function description(): string
    {
        return 'Get detailed schema information about database tables. Can inspect a specific table or list all available tables.';
    }

    protected function schema(): array
    {
        return $this->objectSchema([
            'table_name' => $this->stringProperty(
                'Optional: The name of a specific table to inspect. If not provided, lists all tables.'
            ),
            'include_sample_data' => $this->booleanProperty(
                'Whether to include sample data from the table (up to 3 rows). This data is for understanding the schema only - never use it directly in responses to the user.',
                false
            ),
        ]);
    }

    /**
     * Set the database connection to use.
     */
    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    protected function handle(array $parameters): mixed
    {
        $tableName = $parameters['table_name'] ?? null;
        $includeSampleData = $parameters['include_sample_data'] ?? false;
        $connection = $this->connection ?? config('sql-agent.database.connection');

        if ($tableName) {
            return $this->inspectTable($tableName, $includeSampleData, $connection);
        }

        return $this->listTables($connection);
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
        // Check if table exists
        if (! $this->introspector->tableExists($tableName, $connection)) {
            $available = $this->introspector->getTableNames($connection);

            throw new RuntimeException(
                "Table '{$tableName}' does not exist. Available tables: ".implode(', ', $available)
            );
        }

        $schema = $this->introspector->introspectTable($tableName, $connection);

        if ($schema === null) {
            throw new RuntimeException("Could not introspect table '{$tableName}'.");
        }

        $result = [
            'table' => $schema->tableName,
            'description' => $schema->description,
            'columns' => $schema->columns->map(fn ($col) => [
                'name' => $col->name,
                'type' => $col->type,
                'nullable' => $col->nullable,
                'primary_key' => $col->isPrimaryKey,
                'foreign_key' => $col->isForeignKey,
                'references' => $col->isForeignKey ? "{$col->foreignTable}.{$col->foreignColumn}" : null,
                'default' => $col->defaultValue,
                'description' => $col->description,
            ])->toArray(),
            'relationships' => $schema->relationships->map(fn ($rel) => [
                'type' => $rel->type,
                'related_table' => $rel->relatedTable,
                'foreign_key' => $rel->foreignKey,
                'local_key' => $rel->localKey,
            ])->toArray(),
        ];

        if ($includeSampleData) {
            $result['sample_data'] = $this->getSampleData($tableName, $connection);
        }

        return $result;
    }

    protected function getSampleData(string $tableName, ?string $connection): array
    {
        $rows = DB::connection($connection)
            ->table($tableName)
            ->limit(3)
            ->get();

        return $rows->map(fn ($row) => (array) $row)->toArray();
    }
}
