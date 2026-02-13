<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Data\TableSchema;
use Knobik\SqlAgent\Models\TableMetadata;

class SemanticModelLoader
{
    public function __construct(
        protected TableAccessControl $tableAccessControl,
    ) {}

    /**
     * Load table metadata from the database.
     *
     * @return Collection<int, TableSchema>
     */
    public function load(?string $connection = null, ?string $connectionName = null): Collection
    {
        $query = TableMetadata::query();

        if ($connection !== null) {
            $query->forConnection($connection);
        }

        $tables = $query->get()->map(fn (TableMetadata $model) => $this->modelToTableSchema($model));

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
