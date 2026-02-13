<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Search\SearchManager;
use Knobik\SqlAgent\Search\SearchResult;
use Prism\Prism\Tool;
use RuntimeException;

class SearchKnowledgeTool extends Tool
{
    public function __construct(
        protected SearchManager $searchManager,
    ) {
        $indexes = $this->searchManager->getRegisteredIndexes();
        $enumValues = ['all', ...$indexes];

        $this
            ->as('search_knowledge')
            ->for('Search the knowledge base for relevant query patterns and learnings. Use this to find similar queries, understand business logic, or discover past learnings about the database.')
            ->withStringParameter('query', 'The search query to find relevant knowledge.')
            ->withEnumParameter('type', "Filter results by index: 'all' (default) searches everything, or specify a specific index name.", $enumValues, required: false)
            ->withNumberParameter('limit', 'Maximum number of results to return.', required: false)
            ->using($this);
    }

    public function __invoke(string $query, string $type = 'all', int $limit = 5): string
    {
        $query = trim($query);

        if (empty($query)) {
            throw new RuntimeException('Search query cannot be empty.');
        }

        $registeredIndexes = $this->searchManager->getRegisteredIndexes();

        if ($type !== 'all' && ! in_array($type, $registeredIndexes)) {
            $type = 'all';
        }

        $limit = min($limit, 20);
        $results = [];

        if ($type === 'all') {
            foreach ($registeredIndexes as $index) {
                $results[$index] = $this->searchIndex($query, $index, $limit);
            }
        } else {
            $results[$type] = $this->searchIndex($query, $type, $limit);
        }

        $total = 0;
        foreach ($results as $indexResults) {
            $total += count($indexResults);
        }
        $results['total_found'] = $total;

        return json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Search a single index and format results.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function searchIndex(string $query, string $index, int $limit): array
    {
        // Guard: skip learnings when learning is disabled
        if ($index === 'learnings' && ! config('sql-agent.learning.enabled')) {
            return [];
        }

        $searchResults = $this->searchManager->search($query, $index, $limit);

        return match ($index) {
            'query_patterns' => $this->formatQueryPatterns($searchResults),
            'learnings' => $this->formatLearnings($searchResults),
            default => $this->formatCustomIndex($searchResults),
        };
    }

    /**
     * Format query pattern search results.
     *
     * @param  \Illuminate\Support\Collection<int, SearchResult>  $results
     * @return array<int, array<string, mixed>>
     */
    protected function formatQueryPatterns($results): array
    {
        return $results->map(fn (SearchResult $result) => [
            'name' => $result->model->getAttribute('name'),
            'question' => $result->model->getAttribute('question'),
            'sql' => $result->model->getAttribute('sql'),
            'summary' => $result->model->getAttribute('summary'),
            'tables_used' => $result->model->getAttribute('tables_used'),
            'relevance_score' => $result->score,
        ])->toArray();
    }

    /**
     * Format learning search results.
     *
     * @param  \Illuminate\Support\Collection<int, SearchResult>  $results
     * @return array<int, array<string, mixed>>
     */
    protected function formatLearnings($results): array
    {
        return $results->map(fn (SearchResult $result) => [
            'title' => $result->model->getAttribute('title'),
            'description' => $result->model->getAttribute('description'),
            'category' => $result->model->getAttribute('category')?->value,
            'sql' => $result->model->getAttribute('sql'),
            'relevance_score' => $result->score,
        ])->toArray();
    }

    /**
     * Format custom index search results using toSearchableArray().
     *
     * @param  \Illuminate\Support\Collection<int, SearchResult>  $results
     * @return array<int, array<string, mixed>>
     */
    protected function formatCustomIndex($results): array
    {
        return $results->map(function (SearchResult $result) {
            $data = $result->model instanceof Searchable
                ? $result->model->toSearchableArray()
                : [];
            $data['relevance_score'] = $result->score;

            return $data;
        })->toArray();
    }
}
