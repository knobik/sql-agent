<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Search\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Knobik\SqlAgent\Contracts\FullTextSearchStrategy;

/**
 * SQLite and fallback LIKE-based search strategy.
 */
class SqliteLikeStrategy implements FullTextSearchStrategy
{
    /**
     * Common stop words to filter out from search terms.
     *
     * @var array<string>
     */
    protected array $stopWords = [
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
        'am', 'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves',
        'you', 'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his',
        'himself', 'she', 'her', 'hers', 'herself', 'it', 'its', 'itself',
        'they', 'them', 'their', 'theirs', 'themselves', 'show', 'get', 'find',
        'list', 'give', 'tell', 'many', 'much',
    ];

    public function apply(Builder $query, string $searchTerm, array $columns, int $limit): Builder
    {
        $keywords = $this->extractKeywords($searchTerm);

        if (empty($keywords)) {
            return $query->limit($limit);
        }

        // Build a scoring expression using CASE statements
        $scoreExpression = $this->buildScoreExpression($columns, $keywords);

        return $query
            ->selectRaw('*, ('.$scoreExpression.') as search_score')
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
     * Extract keywords from a search term.
     *
     * @return array<string>
     */
    protected function extractKeywords(string $text): array
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        return array_values(array_filter(
            $words,
            fn (string $word) => strlen($word) > 2 && ! in_array($word, $this->stopWords)
        ));
    }

    /**
     * Build a SQL score expression using CASE statements for keyword matching.
     *
     * @param  array<string>  $columns
     * @param  array<string>  $keywords
     */
    protected function buildScoreExpression(array $columns, array $keywords): string
    {
        $cases = [];

        foreach ($keywords as $keyword) {
            $term = '%'.strtolower($keyword).'%';
            foreach ($columns as $column) {
                $cases[] = "CASE WHEN LOWER({$column}) LIKE '{$term}' THEN 1 ELSE 0 END";
            }
        }

        return implode(' + ', $cases);
    }
}
