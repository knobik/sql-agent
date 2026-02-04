<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Drivers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Knobik\SqlAgent\Contracts\FullTextSearchStrategy;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Search\SearchResult;
use Knobik\SqlAgent\Search\Strategies\MysqlFullTextStrategy;
use Knobik\SqlAgent\Search\Strategies\PostgresFullTextStrategy;
use Knobik\SqlAgent\Search\Strategies\SqliteLikeStrategy;
use Knobik\SqlAgent\Search\Strategies\SqlServerFullTextStrategy;
use RuntimeException;

/**
 * Database full-text search driver with auto-detection of database type.
 */
class DatabaseSearchDriver implements SearchDriver
{
    /**
     * Default index to model class mapping.
     *
     * @var array<string, class-string<Model&Searchable>>
     */
    protected array $defaultIndexMapping = [
        'query_patterns' => QueryPattern::class,
        'learnings' => Learning::class,
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
    ) {}

    /**
     * @return Collection<int, SearchResult>
     */
    public function search(string $query, string $index, int $limit = 10): Collection
    {
        $modelClass = $this->resolveModelClass($index);
        /** @var Model&Searchable $model */
        $model = new $modelClass;

        $columns = $model->getSearchableColumns();
        $strategy = $this->getStrategy($model);

        $queryBuilder = $model->newQuery();
        $strategy->apply($queryBuilder, $query, $columns, $limit);

        return $queryBuilder->get()->map(function (Model $resultModel) use ($index) {
            $score = $resultModel->getAttribute('search_score') ?? 0.0;

            return SearchResult::fromModel($resultModel, $index, (float) $score);
        });
    }

    /**
     * Search across multiple indexes.
     *
     * @param  array<string>  $indexes
     * @return Collection<int, SearchResult>
     */
    public function searchMultiple(string $query, array $indexes, int $limit = 10): Collection
    {
        $results = collect();

        foreach ($indexes as $index) {
            $indexResults = $this->search($query, $index, $limit);
            $results = $results->merge($indexResults);
        }

        // Sort by score and limit total results
        return $results
            ->sortByDesc(fn (SearchResult $result) => $result->score)
            ->take($limit)
            ->values();
    }

    public function index(mixed $model): void
    {
        // Database search doesn't require explicit indexing
        // The data is searchable directly from the database
    }

    public function delete(mixed $model): void
    {
        // Database search doesn't require explicit deletion from index
        // Deleting from the database removes the model from search
    }

    /**
     * Get the appropriate search strategy based on the database driver.
     */
    protected function getStrategy(Model $model): FullTextSearchStrategy
    {
        $driverName = $model->getConnection()->getDriverName();

        return match ($driverName) {
            'mysql', 'mariadb' => new MysqlFullTextStrategy($this->config['mysql'] ?? []),
            'pgsql' => new PostgresFullTextStrategy($this->config['pgsql'] ?? []),
            'sqlsrv' => new SqlServerFullTextStrategy($this->config['sqlsrv'] ?? []),
            default => new SqliteLikeStrategy,
        };
    }

    /**
     * Resolve the model class for a given index name.
     *
     * @return class-string<Model&Searchable>
     */
    protected function resolveModelClass(string $index): string
    {
        $customMapping = $this->config['index_mapping'] ?? [];
        $mapping = array_merge($this->defaultIndexMapping, $customMapping);

        if (! isset($mapping[$index])) {
            throw new RuntimeException("Unknown search index: {$index}. Available indexes: ".implode(', ', array_keys($mapping)));
        }

        $class = $mapping[$index];

        if (! is_a($class, Model::class, true)) {
            throw new RuntimeException("Index {$index} must map to an Eloquent Model class.");
        }

        if (! is_a($class, Searchable::class, true)) {
            throw new RuntimeException("Model {$class} must implement the Searchable interface.");
        }

        return $class;
    }

    /**
     * Get the index mapping.
     *
     * @return array<string, class-string<Model&Searchable>>
     */
    public function getIndexMapping(): array
    {
        return array_merge(
            $this->defaultIndexMapping,
            $this->config['index_mapping'] ?? []
        );
    }
}
