<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Knobik\SqlAgent\Contracts\FullTextSearchStrategy;
use Knobik\SqlAgent\Support\TextAnalyzer;

/**
 * MySQL MATCH...AGAINST full-text search strategy.
 */
class MysqlFullTextStrategy implements FullTextSearchStrategy
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

        $mode = $this->config['mode'] ?? 'NATURAL LANGUAGE MODE';
        $columnList = implode(', ', $columns);

        return $query
            ->selectRaw("*, MATCH({$columnList}) AGAINST(? IN {$mode}) as search_score", [$searchTerm])
            ->whereRaw("MATCH({$columnList}) AGAINST(? IN {$mode})", [$searchTerm])
            ->orderByDesc('search_score')
            ->limit($limit);
    }

    public function getName(): string
    {
        return 'mysql_fulltext';
    }
}
