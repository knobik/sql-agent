<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search;

use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Embeddings\EmbeddingGenerator;
use Knobik\SqlAgent\Embeddings\TextSerializer;
use Knobik\SqlAgent\Search\Drivers\DatabaseSearchDriver;
use Knobik\SqlAgent\Search\Drivers\NullSearchDriver;
use Knobik\SqlAgent\Search\Drivers\PgvectorSearchDriver;

/**
 * Search manager for managing search drivers.
 *
 * @mixin SearchDriver
 */
class SearchManager extends Manager implements SearchDriver
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('sql-agent.search.default', 'database');
    }

    /**
     * Create a database search driver instance.
     */
    public function createDatabaseDriver(): DatabaseSearchDriver
    {
        $config = $this->config->get('sql-agent.search.drivers.database', []);

        return new DatabaseSearchDriver($config);
    }

    /**
     * Create a pgvector search driver instance.
     */
    public function createPgvectorDriver(): PgvectorSearchDriver
    {
        if (! class_exists(\Pgvector\Laravel\Vector::class)) {
            throw new \RuntimeException(
                'The pgvector search driver requires the pgvector/pgvector package. Install it with: composer require pgvector/pgvector'
            );
        }

        $config = $this->config->get('sql-agent.search.drivers.pgvector', []);

        return new PgvectorSearchDriver(
            $this->container->make(EmbeddingGenerator::class),
            $this->container->make(TextSerializer::class),
            $config,
        );
    }

    /**
     * Create a null search driver instance.
     */
    public function createNullDriver(): NullSearchDriver
    {
        return new NullSearchDriver;
    }

    /**
     * Proxy search call to the current driver.
     *
     * @return Collection<int, SearchResult>
     */
    public function search(string $query, string $index, int $limit = 10): Collection
    {
        return $this->driver()->search($query, $index, $limit);
    }

    /**
     * Proxy searchMultiple call to the current driver.
     *
     * @param  array<string>  $indexes
     * @return Collection<int, SearchResult>
     */
    public function searchMultiple(string $query, array $indexes, int $limit = 10): Collection
    {
        $driver = $this->driver();

        if (method_exists($driver, 'searchMultiple')) {
            return $driver->searchMultiple($query, $indexes, $limit);
        }

        // Fall back to calling search for each index
        $results = collect();
        foreach ($indexes as $index) {
            $results = $results->merge($driver->search($query, $index, $limit));
        }

        return $results
            ->sortByDesc(fn (SearchResult $result) => $result->score)
            ->take($limit)
            ->values();
    }

    /**
     * Proxy index call to the current driver.
     */
    public function index(mixed $model): void
    {
        $this->driver()->index($model);
    }

    /**
     * Proxy delete call to the current driver.
     */
    public function delete(mixed $model): void
    {
        $this->driver()->delete($model);
    }

    /**
     * Get all registered index names from the current driver.
     *
     * @return array<string>
     */
    public function getRegisteredIndexes(): array
    {
        $driver = $this->driver();

        if (method_exists($driver, 'getIndexMapping')) {
            return array_keys($driver->getIndexMapping());
        }

        return [];
    }

    /**
     * Get custom indexes (excluding built-in query_patterns and learnings).
     *
     * @return array<string>
     */
    public function getCustomIndexes(): array
    {
        $builtIn = ['query_patterns', 'learnings'];

        return array_values(array_diff($this->getRegisteredIndexes(), $builtIn));
    }
}
