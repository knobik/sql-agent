<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Knobik\SqlAgent\Data\TableSchema;
use Knobik\SqlAgent\Models\TableMetadata;

class SemanticModelLoader
{
    public function __construct(
        protected TableAccessControl $tableAccessControl,
    ) {}

    /**
     * Load table metadata from the configured source.
     *
     * @return Collection<int, TableSchema>
     */
    public function load(?string $connection = null, ?string $connectionName = null): Collection
    {
        $source = config('sql-agent.knowledge.source');

        $tables = match ($source) {
            'files' => $this->loadFromFiles($connection),
            'database' => $this->loadFromDatabase($connection),
            default => throw new \InvalidArgumentException("Unknown knowledge source: {$source}"),
        };

        return $this->applyAccessControl($tables, $connectionName);
    }

    /**
     * Format loaded table metadata as a prompt string.
     */
    public function format(?string $connection = null, ?string $connectionName = null): string
    {
        $tables = $this->load($connection, $connectionName);

        if ($tables->isEmpty()) {
            return 'No table metadata available.';
        }

        return $tables
            ->map(fn (TableSchema $table) => $table->toPromptString())
            ->implode("\n\n---\n\n");
    }

    /**
     * Load table metadata from JSON files.
     *
     * @return Collection<int, TableSchema>
     */
    protected function loadFromFiles(?string $connection = null): Collection
    {
        $path = config('sql-agent.knowledge.path').'/tables';

        if (! File::isDirectory($path)) {
            return collect();
        }

        $files = File::glob("{$path}/*.json");

        return collect($files)
            ->map(fn (string $file) => $this->parseJsonFile($file))
            ->filter()
            ->filter(fn (TableSchema $table, $key) => $this->matchesConnection($table, $connection))
            ->values();
    }

    /**
     * Load table metadata from the database.
     *
     * @return Collection<int, TableSchema>
     */
    protected function loadFromDatabase(?string $connection = null): Collection
    {
        $query = TableMetadata::query();

        if ($connection !== null) {
            $query->forConnection($connection);
        }

        return $query->get()->map(fn (TableMetadata $model) => $this->modelToTableSchema($model));
    }

    /**
     * Parse a JSON file into a TableSchema.
     */
    protected function parseJsonFile(string $filePath): ?TableSchema
    {
        try {
            $data = json_decode(File::get($filePath), true, 512, JSON_THROW_ON_ERROR);

            return $this->arrayToTableSchema($data);
        } catch (\JsonException $e) {
            report($e);

            return null;
        }
    }

    /**
     * Convert an array to a TableSchema DTO.
     */
    protected function arrayToTableSchema(array $data): TableSchema
    {
        $columns = $data['columns'] ?? $data['table_columns'] ?? [];
        $relationships = $data['relationships'] ?? [];

        return new TableSchema(
            tableName: $data['table'] ?? $data['table_name'] ?? '',
            description: $data['description'] ?? $data['table_description'] ?? null,
            columns: $columns,
            relationships: $relationships,
            dataQualityNotes: $data['data_quality_notes'] ?? [],
            useCases: $data['use_cases'] ?? [],
            connection: $data['connection'] ?? null,
        );
    }

    /**
     * Convert a TableMetadata model to a TableSchema DTO.
     */
    protected function modelToTableSchema(TableMetadata $model): TableSchema
    {
        return new TableSchema(
            tableName: $model->table_name,
            description: $model->description,
            columns: $model->columns ?? [],
            relationships: $model->relationships ?? [],
            dataQualityNotes: $model->data_quality_notes ?? [],
        );
    }

    /**
     * Check if a table belongs to the specified connection.
     *
     * When a connection is specified, only tables with a matching `connection`
     * field in their JSON file are included. Tables without a `connection`
     * field default to "default" and are included for all connections.
     */
    protected function matchesConnection(TableSchema $table, ?string $connection): bool
    {
        if ($connection === null) {
            return true;
        }

        $tableConnection = $table->connection ?? 'default';

        return $tableConnection === $connection || $tableConnection === 'default';
    }

    /**
     * Filter tables and columns through access control.
     *
     * @param  Collection<int, TableSchema>  $tables
     * @return Collection<int, TableSchema>
     */
    protected function applyAccessControl(Collection $tables, ?string $connectionName = null): Collection
    {
        return $tables
            ->filter(fn (TableSchema $table) => $this->tableAccessControl->isTableAllowed($table->tableName, $connectionName))
            ->map(function (TableSchema $table) use ($connectionName) {
                $filteredColumns = $this->tableAccessControl->filterColumns($table->tableName, $table->columns, $connectionName);

                if ($filteredColumns === $table->columns) {
                    return $table;
                }

                return new TableSchema(
                    tableName: $table->tableName,
                    description: $table->description,
                    columns: $filteredColumns,
                    relationships: $table->relationships,
                    dataQualityNotes: $table->dataQualityNotes,
                    useCases: $table->useCases,
                );
            })
            ->values();
    }

    /**
     * Get a single table schema by name.
     */
    public function getTable(string $tableName, ?string $connection = null): ?TableSchema
    {
        return $this->load($connection)->first(
            fn (TableSchema $table) => $table->tableName === $tableName
        );
    }

    /**
     * Get table names from loaded metadata.
     *
     * @return array<string>
     */
    public function getTableNames(?string $connection = null): array
    {
        return $this->load($connection)
            ->pluck('tableName')
            ->all();
    }
}
