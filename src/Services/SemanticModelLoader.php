<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Data\TableSchema;
use Knobik\SqlAgent\Models\TableMetadata;
use Knobik\SqlAgent\Search\SearchManager;
use Knobik\SqlAgent\Search\SearchResult;
use Throwable;

class SemanticModelLoader
{
    public function __construct(
        protected TableAccessControl $tableAccessControl,
        protected SearchManager $searchManager,
        protected ConnectionRegistry $connectionRegistry,
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
        return $this->formatTables($this->load($connection, $connectionName));
    }

    /**
     * Format only the table metadata relevant to a question.
     *
     * Falls back to the full model whenever retrieval produces nothing, since an
     * empty schema is worse than an oversized one.
     */
    public function formatRelevant(string $question, ?string $connection = null, ?string $connectionName = null): string
    {
        $all = $this->load($connection, $connectionName);

        if ($all->isEmpty()) {
            return 'No table metadata available.';
        }

        $selected = $this->selectRelevant($question, $all, $connection);

        if ($selected->isEmpty()) {
            return $this->formatTables($all);
        }

        $output = $this->formatTables($selected);

        if (config('sql-agent.schema.rag.include_table_list')) {
            $omitted = $all
                ->reject(fn (TableSchema $table) => $selected->contains(
                    fn (TableSchema $chosen) => $chosen->tableName === $table->tableName
                ))
                ->map(fn (TableSchema $table) => $table->tableName)
                ->values();

            if ($omitted->isNotEmpty()) {
                $output .= "\n\n---\n\n### Other tables on this connection\n"
                    ."Metadata for these was not included. Use `introspect_schema` if you need one of them:\n"
                    .$omitted->implode(', ');
            }
        }

        return $output;
    }

    /**
     * Pick the tables to describe for a question.
     *
     * @param  Collection<int, TableSchema>  $all
     * @return Collection<int, TableSchema>
     */
    protected function selectRelevant(string $question, Collection $all, ?string $connection): Collection
    {
        $names = $this->searchTableNames($question, $connection);

        foreach (config('sql-agent.schema.rag.always_include') as $name) {
            $names[] = $name;
        }

        $names = $this->expandThroughRelationships($names, $all);

        return $all
            ->filter(fn (TableSchema $table) => in_array($table->tableName, $names, true))
            ->values();
    }

    /**
     * Retrieve table names for the question through the search driver.
     *
     * @return array<string>
     */
    protected function searchTableNames(string $question, ?string $connection): array
    {
        $limit = (int) config('sql-agent.schema.rag.limit');

        // The search index spans every connection, so over-fetch and then keep
        // only the hits belonging to the connection being described.
        $connectionCount = max(1, count($this->connectionRegistry->all()));

        try {
            $results = $this->searchManager->search($question, 'table_metadata', $limit * $connectionCount);
        } catch (Throwable) {
            // Retrieval is an optimisation; failing it must fall back to the
            // full semantic model rather than break the request.
            return [];
        }

        return $results
            ->filter(fn (SearchResult $result) => $connection === null
                || $result->model->getAttribute('connection') === $connection)
            ->map(fn (SearchResult $result) => (string) $result->model->getAttribute('table_name'))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Add the tables referenced by the relationships of the selected tables.
     *
     * Retrieval matches a question against table descriptions, which regularly
     * misses the join targets those tables point at.
     *
     * @param  array<string>  $names
     * @param  Collection<int, TableSchema>  $all
     * @return array<string>
     */
    protected function expandThroughRelationships(array $names, Collection $all): array
    {
        if (! config('sql-agent.schema.rag.expand_relationships') || $names === []) {
            return array_values(array_unique($names));
        }

        $known = $all->map(fn (TableSchema $table) => $table->tableName)->all();
        $selected = array_values(array_unique($names));
        $depth = (int) config('sql-agent.schema.rag.expansion_depth');

        for ($round = 0; $round < $depth; $round++) {
            $added = [];

            foreach ($selected as $name) {
                $table = $all->first(fn (TableSchema $candidate) => $candidate->tableName === $name);

                if (! $table) {
                    continue;
                }

                $text = implode(' ', $table->relationships);

                foreach ($known as $candidate) {
                    if (in_array($candidate, $selected, true) || in_array($candidate, $added, true)) {
                        continue;
                    }

                    if (preg_match('/\b'.preg_quote($candidate, '/').'\b/i', $text) === 1) {
                        $added[] = $candidate;
                    }
                }
            }

            if ($added === []) {
                break;
            }

            $selected = array_merge($selected, $added);
        }

        return $selected;
    }

    /**
     * @param  Collection<int, TableSchema>  $tables
     */
    protected function formatTables(Collection $tables): string
    {
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
