<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Support;

class TextAnalyzer
{
    /**
     * Common English stop words to filter out from keyword extraction.
     *
     * @var array<int, string>
     */
    protected static array $stopWords = [
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

    /**
     * Extract meaningful keywords from text, filtering out stop words and short words.
     *
     * @return array<int, string>
     */
    public static function extractKeywords(string $text): array
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn (string $word) => strlen($word) > 2 && ! in_array($word, static::$stopWords)
        ));
    }

    /**
     * Extract table names from a SQL query.
     *
     * Matches tables after FROM, JOIN, UPDATE, and INTO clauses.
     *
     * @return array<int, string>
     */
    public static function extractTablesFromSql(string $sql): array
    {
        $tables = [];

        // Match table names after FROM
        if (preg_match_all('/\bFROM\s+[`"\[]?(\w+)[`"\]]?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Match table names after JOIN
        if (preg_match_all('/\bJOIN\s+[`"\[]?(\w+)[`"\]]?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Match table names after UPDATE
        if (preg_match_all('/\bUPDATE\s+[`"\[]?(\w+)[`"\]]?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Match table names after INTO
        if (preg_match_all('/\bINTO\s+[`"\[]?(\w+)[`"\]]?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        return array_values(array_unique($tables));
    }

    /**
     * Prepare a full-text search term from a question by extracting keywords.
     */
    public static function prepareSearchTerm(string $question): string
    {
        return implode(' ', static::extractKeywords($question));
    }
}
