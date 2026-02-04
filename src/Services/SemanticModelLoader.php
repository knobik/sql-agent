<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Knobik\SqlAgent\Data\ColumnInfo;
use Knobik\SqlAgent\Data\RelationshipInfo;
use Knobik\SqlAgent\Data\TableSchema;
use Knobik\SqlAgent\Models\TableMetadata;

class SemanticModelLoader
{
    /**
     * Load table metadata from the configured source.
     *
     * @return Collection<int, TableSchema>
     */
    public function load(?string $connection = null): Collection
    {
        $source = config('sql-agent.knowledge.source', 'files');

        return match ($source) {
            'files' => $this->loadFromFiles($connection),
            'database' => $this->loadFromDatabase($connection),
            default => throw new \InvalidArgumentException("Unknown knowledge source: {$source}"),
        };
    }

    /**
     * Format loaded table metadata as a prompt string.
     */
    public function format(?string $connection = null): string
    {
        $tables = $this->load($connection);

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
        $columns = collect($data['table_columns'] ?? $data['columns'] ?? [])
            ->map(fn (array $col) => new ColumnInfo(
                name: $col['name'],
                type: $col['type'],
                description: $col['description'] ?? null,
                nullable: $col['nullable'] ?? true,
                isPrimaryKey: $col['is_primary_key'] ?? $col['primary_key'] ?? false,
                isForeignKey: $col['is_foreign_key'] ?? $col['foreign_key'] ?? false,
                foreignTable: $col['foreign_table'] ?? null,
                foreignColumn: $col['foreign_column'] ?? null,
                defaultValue: $col['default_value'] ?? $col['default'] ?? null,
            ));

        $relationships = collect($data['relationships'] ?? [])
            ->map(fn (array $rel) => new RelationshipInfo(
                type: $rel['type'],
                relatedTable: $rel['related_table'],
                foreignKey: $rel['foreign_key'],
                localKey: $rel['local_key'] ?? null,
                pivotTable: $rel['pivot_table'] ?? null,
                description: $rel['description'] ?? null,
            ));

        return new TableSchema(
            tableName: $data['table_name'],
            description: $data['table_description'] ?? $data['description'] ?? null,
            columns: $columns,
            relationships: $relationships,
            dataQualityNotes: $data['data_quality_notes'] ?? [],
            useCases: $data['use_cases'] ?? [],
        );
    }

    /**
     * Convert a TableMetadata model to a TableSchema DTO.
     */
    protected function modelToTableSchema(TableMetadata $model): TableSchema
    {
        $columns = collect($model->columns ?? [])
            ->map(fn (array $col) => new ColumnInfo(
                name: $col['name'],
                type: $col['type'],
                description: $col['description'] ?? null,
                nullable: $col['nullable'] ?? true,
                isPrimaryKey: $col['is_primary_key'] ?? $col['primary_key'] ?? false,
                isForeignKey: $col['is_foreign_key'] ?? $col['foreign_key'] ?? false,
                foreignTable: $col['foreign_table'] ?? null,
                foreignColumn: $col['foreign_column'] ?? null,
                defaultValue: $col['default_value'] ?? $col['default'] ?? null,
            ));

        $relationships = collect($model->relationships ?? [])
            ->map(fn (array $rel) => new RelationshipInfo(
                type: $rel['type'],
                relatedTable: $rel['related_table'],
                foreignKey: $rel['foreign_key'],
                localKey: $rel['local_key'] ?? null,
                pivotTable: $rel['pivot_table'] ?? null,
                description: $rel['description'] ?? null,
            ));

        return new TableSchema(
            tableName: $model->table_name,
            description: $model->description,
            columns: $columns,
            relationships: $relationships,
            dataQualityNotes: $model->data_quality_notes ?? [],
        );
    }

    /**
     * Check if a table belongs to the specified connection.
     */
    protected function matchesConnection(TableSchema $table, ?string $connection): bool
    {
        if ($connection === null) {
            return true;
        }

        // For file-based loading, we don't filter by connection
        // (connection info would need to be in the JSON if filtering is needed)
        return true;
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
