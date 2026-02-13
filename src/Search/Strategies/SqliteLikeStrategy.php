<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Knobik\SqlAgent\Contracts\FullTextSearchStrategy;
use Knobik\SqlAgent\Support\TextAnalyzer;

/**
 * SQLite and fallback LIKE-based search strategy.
 */
class SqliteLikeStrategy implements FullTextSearchStrategy
{
    public function apply(Builder $query, string $searchTerm, array $columns, int $limit): Builder
    {
        $keywords = TextAnalyzer::extractKeywords($searchTerm);

        if (empty($keywords)) {
            return $query->limit($limit);
        }

        // Build a scoring expression using CASE statements
        ['expression' => $scoreExpression, 'bindings' => $scoreBindings] = $this->buildScoreExpression($columns, $keywords);

        return $query
            ->selectRaw('*, ('.$scoreExpression.') as search_score', $scoreBindings)
            ->where(function ($q) use ($keywords, $columns) {
                foreach ($keywords as $keyword) {
                    $term = '%'.strtolower($keyword).'%';
                    $q->where(function ($inner) use ($term, $columns) {
                        foreach ($columns as $column) {
                            $inner->orWhereRaw("LOWER({$column}) LIKE ?", [$term]);
                        }
                    });
                }
            })
            ->orderByDesc('search_score')
            ->limit($limit);
    }

    public function getName(): string
    {
        return 'sqlite_like';
    }

    /**
     * Build a SQL score expression using CASE statements for keyword matching.
     *
     * @param  array<string>  $columns
     * @param  array<string>  $keywords
     * @return array{expression: string, bindings: array<string>}
     */
    protected function buildScoreExpression(array $columns, array $keywords): array
    {
        $cases = [];
        $bindings = [];

        foreach ($keywords as $keyword) {
            $term = '%'.strtolower($keyword).'%';
            foreach ($columns as $column) {
                $cases[] = "CASE WHEN LOWER({$column}) LIKE ? THEN 1 ELSE 0 END";
                $bindings[] = $term;
            }
        }

        return [
            'expression' => implode(' + ', $cases),
            'bindings' => $bindings,
        ];
    }
}
