<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Knobik\SqlAgent\Contracts\FullTextSearchStrategy;
use Knobik\SqlAgent\Support\TextAnalyzer;

/**
 * SQL Server CONTAINSTABLE full-text search strategy.
 */
class SqlServerFullTextStrategy implements FullTextSearchStrategy
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
    ) {}

    public function apply(Builder $query, string $searchTerm, array $columns, int $limit): Builder
    {
        $searchTerm = $this->prepareSearchTerm($searchTerm);

        if (empty($searchTerm)) {
            return $query->limit($limit);
        }

        $table = $query->getModel()->getTable();
        $primaryKey = $query->getModel()->getKeyName();
        $columnList = implode(', ', $columns);

        // Use CONTAINSTABLE for ranked results
        return $query
            ->selectRaw("{$table}.*, ft.[RANK] as search_score")
            ->join(
                \DB::raw("CONTAINSTABLE({$table}, ({$columnList}), ?) as ft"),
                "{$table}.{$primaryKey}",
                '=',
                'ft.[KEY]'
            )
            ->addBinding($searchTerm, 'join')
            ->orderByDesc('search_score')
            ->limit($limit);
    }

    public function getName(): string
    {
        return 'sqlserver_fulltext';
    }

    /**
     * Prepare the search term for SQL Server full-text search.
     * Uses FORMSOF and OR for broader matching.
     *
     * Safety: keywords are interpolated into FORMSOF() expressions. This is safe because
     * TextAnalyzer::extractKeywords() only passes through [a-zA-Z0-9] characters.
     * If that filter changes, this method must sanitize keywords.
     */
    protected function prepareSearchTerm(string $term): string
    {
        $keywords = TextAnalyzer::extractKeywords($term);

        if (empty($keywords)) {
            return '';
        }

        // Build FORMSOF expressions for each keyword for inflectional matching
        $expressions = array_map(
            fn ($keyword) => "FORMSOF(INFLECTIONAL, \"{$keyword}\")",
            $keywords
        );

        return implode(' OR ', $expressions);
    }
}
