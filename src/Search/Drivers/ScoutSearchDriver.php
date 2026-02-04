<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Drivers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Search\SearchResult;
use Laravel\Scout\Searchable as ScoutSearchable;
use RuntimeException;

/**
 * Laravel Scout search driver for external search engines.
 */
class ScoutSearchDriver implements SearchDriver
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

        $this->ensureScoutSearchable($modelClass);

        /** @var Model&ScoutSearchable $model */
        $model = new $modelClass;

        $results = $model::search($query)
            ->take($limit)
            ->get();

        return $results->map(function (Model $resultModel, int $key) use ($index, $limit) {
            // Scout doesn't always provide relevance scores, so we calculate a position-based score
            $score = ($limit - $key) / $limit;

            return SearchResult::fromModel($resultModel, $index, $score);
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
        if (! $model instanceof Model) {
            throw new RuntimeException('Model must be an Eloquent Model instance.');
        }

        $this->ensureScoutSearchable(get_class($model));

        /** @var Model&ScoutSearchable $model */
        $model->searchable();
    }

    public function delete(mixed $model): void
    {
        if (! $model instanceof Model) {
            throw new RuntimeException('Model must be an Eloquent Model instance.');
        }

        $this->ensureScoutSearchable(get_class($model));

        /** @var Model&ScoutSearchable $model */
        $model->unsearchable();
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

        return $class;
    }

    /**
     * Ensure the model uses Laravel Scout's Searchable trait.
     *
     * @param  class-string  $modelClass
     */
    protected function ensureScoutSearchable(string $modelClass): void
    {
        if (! in_array(ScoutSearchable::class, class_uses_recursive($modelClass))) {
            throw new RuntimeException(
                "Model {$modelClass} must use the Laravel Scout Searchable trait to use the Scout search driver."
            );
        }
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
