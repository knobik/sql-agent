<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Knobik\SqlAgent\Contracts\FullTextSearchStrategy;
use Knobik\SqlAgent\Support\TextAnalyzer;

/**
 * PostgreSQL to_tsvector/to_tsquery full-text search strategy.
 */
class PostgresFullTextStrategy implements FullTextSearchStrategy
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
    ) {}

    public function apply(Builder $query, string $searchTerm, array $columns, int $limit): Builder
    {
        $searchTerm = TextAnalyzer::prepareSearchTerm($searchTerm);

        if (empty($searchTerm)) {
            return $query->limit($limit);
        }

        $language = $this->config['language'] ?? 'english';

        // Build tsvector from columns
        $tsvectorParts = array_map(
            fn ($column) => "coalesce({$column}, '')",
            $columns
        );
        $tsvectorExpression = "to_tsvector('{$language}', ".implode(" || ' ' || ", $tsvectorParts).')';

        // Build tsquery with OR between words for broader matching
        $tsqueryExpression = "plainto_tsquery('{$language}', ?)";

        return $query
            ->selectRaw("*, ts_rank({$tsvectorExpression}, {$tsqueryExpression}) as search_score", [$searchTerm, $searchTerm])
            ->whereRaw("{$tsvectorExpression} @@ {$tsqueryExpression}", [$searchTerm])
            ->orderByDesc('search_score')
            ->limit($limit);
    }

    public function getName(): string
    {
        return 'postgres_fulltext';
    }
}
