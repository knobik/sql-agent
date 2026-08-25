<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Drivers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Embeddings\EmbeddingGenerator;
use Knobik\SqlAgent\Embeddings\TextSerializer;
use Knobik\SqlAgent\Models\Embedding;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Models\TableMetadata;
use Knobik\SqlAgent\Search\SearchResult;
use Pgvector\Laravel\Distance;
use RuntimeException;

class PgvectorSearchDriver implements SearchDriver
{
    /**
     * Default index to model class mapping.
     *
     * @var array<string, class-string<Model&Searchable>>
     */
    protected array $defaultIndexMapping = [
        'query_patterns' => QueryPattern::class,
        'learnings' => Learning::class,
        'table_metadata' => TableMetadata::class,
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected EmbeddingGenerator $embeddingGenerator,
        protected TextSerializer $textSerializer,
        protected array $config = [],
    ) {}

    /**
     * @return Collection<int, SearchResult>
     */
    public function search(string $query, string $index, int $limit = 10): Collection
    {
        $modelClass = $this->resolveModelClass($index);
        $morphClass = (new $modelClass)->getMorphClass();

        $queryVector = $this->embeddingGenerator->embed($query);

        $embeddings = Embedding::query()
            ->where('embeddable_type', $morphClass)
            ->nearestNeighbors('embedding', $queryVector, $this->getDistance())
            ->limit($limit)
            ->get();

        return $embeddings->map(function (Embedding $embedding) use ($index, $modelClass) {
            $distance = $embedding->getAttribute('neighbor_distance') ?? 1.0;
            $score = $this->distanceToScore((float) $distance);

            // Load the source model from its own connection
            $sourceModel = $modelClass::find($embedding->embeddable_id);

            if (! $sourceModel) {
                return null;
            }

            return SearchResult::fromModel($sourceModel, $index, $score);
        })->filter()->values();
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

        return $results
            ->sortByDesc(fn (SearchResult $result) => $result->score)
            ->take($limit)
            ->values();
    }

    public function index(mixed $model): void
    {
        if (! ($model instanceof Model) || ! ($model instanceof Searchable)) {
            return;
        }

        $text = $this->textSerializer->serialize($model);

        if ($text === '') {
            return;
        }

        $contentHash = hash('sha256', $text);

        // Check if embedding already exists and content hasn't changed
        $existing = Embedding::forModel($model)->first();

        if ($existing && $existing->content_hash === $contentHash) {
            return;
        }

        $vector = $this->embeddingGenerator->embed($text);

        Embedding::updateOrCreate(
            [
                'embeddable_type' => $model->getMorphClass(),
                'embeddable_id' => $model->getKey(),
            ],
            [
                'embedding' => $vector,
                'content_hash' => $contentHash,
            ]
        );
    }

    public function delete(mixed $model): void
    {
        if (! ($model instanceof Model)) {
            return;
        }

        Embedding::forModel($model)->delete();
    }

    /**
     * Convert distance to a similarity score (0.0 to 1.0).
     */
    protected function distanceToScore(float $distance): float
    {
        $metric = $this->config['distance_metric'] ?? 'cosine';

        return match ($metric) {
            'cosine' => max(0.0, 1.0 - $distance),
            'inner_product' => max(0.0, $distance), // Already negated by pgvector
            'l2' => 1.0 / (1.0 + $distance),
            default => max(0.0, 1.0 - $distance),
        };
    }

    /**
     * Get the pgvector distance enum from config.
     */
    protected function getDistance(): Distance
    {
        $metric = $this->config['distance_metric'] ?? 'cosine';

        return match ($metric) {
            'l2' => Distance::L2,
            'inner_product' => Distance::InnerProduct,
            default => Distance::Cosine,
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
