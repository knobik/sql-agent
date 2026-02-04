<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search;

use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Search\Drivers\DatabaseSearchDriver;
use Knobik\SqlAgent\Search\Drivers\HybridSearchDriver;
use Knobik\SqlAgent\Search\Drivers\NullSearchDriver;
use Knobik\SqlAgent\Search\Drivers\ScoutSearchDriver;

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
     * Create a Scout search driver instance.
     */
    public function createScoutDriver(): ScoutSearchDriver
    {
        $config = $this->config->get('sql-agent.search.drivers.scout', []);

        return new ScoutSearchDriver($config);
    }

    /**
     * Create a hybrid search driver instance.
     */
    public function createHybridDriver(): HybridSearchDriver
    {
        $config = $this->config->get('sql-agent.search.drivers.hybrid', []);

        $primaryName = $config['primary'] ?? 'scout';
        $fallbackName = $config['fallback'] ?? 'database';

        // Create driver instances directly to avoid recursion
        $primaryDriver = $this->createDriverInstance($primaryName);
        $fallbackDriver = $this->createDriverInstance($fallbackName);

        return new HybridSearchDriver($primaryDriver, $fallbackDriver, $config);
    }

    /**
     * Create a null search driver instance.
     */
    public function createNullDriver(): NullSearchDriver
    {
        return new NullSearchDriver;
    }

    /**
     * Create a driver instance by name without storing it.
     */
    protected function createDriverInstance(string $name): SearchDriver
    {
        $method = 'create'.ucfirst($name).'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        // Default to database driver
        return $this->createDatabaseDriver();
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
}
