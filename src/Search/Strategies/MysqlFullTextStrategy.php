<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Knobik\SqlAgent\Contracts\FullTextSearchStrategy;

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
        $searchTerm = $this->prepareSearchTerm($searchTerm);

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

    /**
     * Prepare the search term for MySQL full-text search.
     */
    protected function prepareSearchTerm(string $term): string
    {
        // Extract keywords and filter stop words
        $keywords = $this->extractKeywords($term);

        return implode(' ', $keywords);
    }

    /**
     * Extract keywords from a search term.
     *
     * @return array<string>
     */
    protected function extractKeywords(string $text): array
    {
        $stopWords = [
            'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'must', 'can', 'to', 'of', 'in', 'for',
            'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during',
            'before', 'after', 'above', 'below', 'between', 'under', 'again',
            'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why',
            'how', 'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such',
            'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too',
            'very', 'just', 'and', 'but', 'if', 'or', 'because', 'until', 'while',
            'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those',
            'show', 'get', 'find', 'list', 'give', 'tell', 'many', 'much',
        ];

        $words = preg_split('/[^a-zA-Z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        return array_values(array_filter(
            $words,
            fn (string $word) => strlen($word) > 2 && ! in_array($word, $stopWords)
        ));
    }
}
