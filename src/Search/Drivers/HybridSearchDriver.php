<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Drivers;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Search\SearchResult;
use Throwable;

/**
 * Hybrid search driver that uses Scout as primary with database fallback.
 */
class HybridSearchDriver implements SearchDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected SearchDriver $primaryDriver,
        protected SearchDriver $fallbackDriver,
        protected array $config = [],
    ) {}

    /**
     * @return Collection<int, SearchResult>
     */
    public function search(string $query, string $index, int $limit = 10): Collection
    {
        try {
            $primaryResults = $this->primaryDriver->search($query, $index, $limit);

            if ($this->shouldMergeResults()) {
                $fallbackResults = $this->fallbackDriver->search($query, $index, $limit);

                return $this->mergeResults($primaryResults, $fallbackResults, $limit);
            }

            return $primaryResults;
        } catch (Throwable $e) {
            // Fall back to database search if primary fails
            return $this->fallbackDriver->search($query, $index, $limit);
        }
    }

    /**
     * Search across multiple indexes.
     *
     * @param  array<string>  $indexes
     * @return Collection<int, SearchResult>
     */
    public function searchMultiple(string $query, array $indexes, int $limit = 10): Collection
    {
        try {
            $primaryResults = $this->primaryDriver->searchMultiple($query, $indexes, $limit);

            if ($this->shouldMergeResults()) {
                $fallbackResults = $this->fallbackDriver->searchMultiple($query, $indexes, $limit);

                return $this->mergeResults($primaryResults, $fallbackResults, $limit);
            }

            return $primaryResults;
        } catch (Throwable $e) {
            return $this->fallbackDriver->searchMultiple($query, $indexes, $limit);
        }
    }

    public function index(mixed $model): void
    {
        // Index in both drivers
        $this->primaryDriver->index($model);
        $this->fallbackDriver->index($model);
    }

    public function delete(mixed $model): void
    {
        // Delete from both drivers
        $this->primaryDriver->delete($model);
        $this->fallbackDriver->delete($model);
    }

    /**
     * Check if results should be merged from both drivers.
     */
    protected function shouldMergeResults(): bool
    {
        return $this->config['merge_results'] ?? false;
    }

    /**
     * Merge results from primary and fallback drivers.
     *
     * @param  Collection<int, SearchResult>  $primary
     * @param  Collection<int, SearchResult>  $fallback
     * @return Collection<int, SearchResult>
     */
    protected function mergeResults(Collection $primary, Collection $fallback, int $limit): Collection
    {
        // Track seen model IDs to avoid duplicates
        $seenIds = [];
        $merged = collect();

        // Add primary results first (higher priority)
        foreach ($primary as $result) {
            $modelId = $result->model->getKey();
            $indexKey = $result->index.':'.$modelId;

            if (! isset($seenIds[$indexKey])) {
                $seenIds[$indexKey] = true;
                $merged->push($result);
            }
        }

        // Add fallback results that weren't in primary
        foreach ($fallback as $result) {
            $modelId = $result->model->getKey();
            $indexKey = $result->index.':'.$modelId;

            if (! isset($seenIds[$indexKey])) {
                $seenIds[$indexKey] = true;
                // Reduce score slightly for fallback results
                $merged->push(new SearchResult(
                    $result->model,
                    $result->score * 0.8,
                    $result->index,
                ));
            }
        }

        return $merged
            ->sortByDesc(fn (SearchResult $result) => $result->score)
            ->take($limit)
            ->values();
    }

    /**
     * Get the primary driver.
     */
    public function getPrimaryDriver(): SearchDriver
    {
        return $this->primaryDriver;
    }

    /**
     * Get the fallback driver.
     */
    public function getFallbackDriver(): SearchDriver
    {
        return $this->fallbackDriver;
    }
}
