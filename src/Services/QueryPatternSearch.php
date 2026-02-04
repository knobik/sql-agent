<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Knobik\SqlAgent\Data\QueryPatternData;
use Knobik\SqlAgent\Models\QueryPattern;

class QueryPatternSearch
{
    protected int $defaultLimit = 3;

    /**
     * Search for query patterns similar to the given question.
     *
     * @return Collection<int, QueryPatternData>
     */
    public function search(string $question, ?int $limit = null): Collection
    {
        $limit = $limit ?? $this->defaultLimit;
        $source = config('sql-agent.knowledge.source', 'files');

        return match ($source) {
            'files' => $this->searchFiles($question, $limit),
            'database' => $this->searchDatabase($question, $limit),
            default => throw new \InvalidArgumentException("Unknown knowledge source: {$source}"),
        };
    }

    /**
     * Search query patterns from files.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function searchFiles(string $question, int $limit): Collection
    {
        $patterns = $this->loadAllFromFiles();

        if ($patterns->isEmpty()) {
            return collect();
        }

        // Simple keyword-based similarity search
        $questionWords = $this->extractKeywords($question);

        return $patterns
            ->map(fn (QueryPatternData $pattern) => [
                'pattern' => $pattern,
                'score' => $this->calculateSimilarity($questionWords, $pattern),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->filter(fn (array $item) => $item['score'] > 0)
            ->pluck('pattern');
    }

    /**
     * Search query patterns from the database.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function searchDatabase(string $question, int $limit): Collection
    {
        $driver = config('database.connections.'.config('sql-agent.database.storage_connection').'.driver');

        // Use full-text search for MySQL
        if ($driver === 'mysql') {
            return $this->searchWithFullText($question, $limit);
        }

        // Fall back to LIKE-based search
        return $this->searchWithLike($question, $limit);
    }

    /**
     * Search using MySQL full-text index.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function searchWithFullText(string $question, int $limit): Collection
    {
        $searchTerm = $this->prepareFullTextSearchTerm($question);

        if (empty($searchTerm)) {
            return QueryPattern::query()
                ->limit($limit)
                ->get()
                ->map(fn (QueryPattern $model) => $this->modelToQueryPatternData($model));
        }

        return QueryPattern::query()
            ->selectRaw('*, MATCH(name, question, summary) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance', [$searchTerm])
            ->whereRaw('MATCH(name, question, summary) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchTerm])
            ->orderByDesc('relevance')
            ->limit($limit)
            ->get()
            ->map(fn (QueryPattern $model) => $this->modelToQueryPatternData($model));
    }

    /**
     * Search using LIKE queries.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function searchWithLike(string $question, int $limit): Collection
    {
        $keywords = $this->extractKeywords($question);

        if (empty($keywords)) {
            return QueryPattern::query()
                ->limit($limit)
                ->get()
                ->map(fn (QueryPattern $model) => $this->modelToQueryPatternData($model));
        }

        $query = QueryPattern::query();

        foreach ($keywords as $keyword) {
            $term = '%'.strtolower($keyword).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(question) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(summary) LIKE ?', [$term]);
            });
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (QueryPattern $model) => $this->modelToQueryPatternData($model));
    }

    /**
     * Load all query patterns from files.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function loadAllFromFiles(): Collection
    {
        $path = config('sql-agent.knowledge.path').'/queries';

        if (! File::isDirectory($path)) {
            return collect();
        }

        $sqlFiles = File::glob("{$path}/*.sql");
        $jsonFiles = File::glob("{$path}/*.json");

        $patterns = collect();

        foreach ($sqlFiles as $file) {
            $patterns = $patterns->merge($this->parseSqlFile($file));
        }

        foreach ($jsonFiles as $file) {
            $patterns = $patterns->merge($this->parseJsonFile($file));
        }

        return $patterns;
    }

    /**
     * Parse a .sql file containing query patterns.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function parseSqlFile(string $filePath): Collection
    {
        $content = File::get($filePath);
        $patterns = collect();

        // Pattern format:
        // -- <query name>name</query name>
        // -- <query description>
        // -- description text
        // -- </query description>
        // -- <query>
        // SELECT ...
        // -- </query>

        $regex = '/--\s*<query\s+name>([^<]+)<\/query\s+name>\s*(?:--\s*<query\s+description>\s*([\s\S]*?)--\s*<\/query\s+description>\s*)?--\s*<query>\s*([\s\S]*?)--\s*<\/query>/i';

        if (preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[1]);
                $description = isset($match[2]) ? trim(preg_replace('/^--\s*/m', '', $match[2])) : '';
                $sql = trim($match[3]);

                // Extract tables from SQL
                $tablesUsed = $this->extractTablesFromSql($sql);

                $patterns->push(new QueryPatternData(
                    name: $name,
                    question: $description,
                    sql: $sql,
                    summary: $description,
                    tablesUsed: $tablesUsed,
                ));
            }
        }

        return $patterns;
    }

    /**
     * Parse a JSON file containing query patterns.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function parseJsonFile(string $filePath): Collection
    {
        try {
            $data = json_decode(File::get($filePath), true, 512, JSON_THROW_ON_ERROR);
            $patterns = collect();

            foreach ($data['patterns'] ?? $data['queries'] ?? [$data] as $pattern) {
                if (! isset($pattern['name']) && ! isset($pattern['question'])) {
                    continue;
                }

                $patterns->push(new QueryPatternData(
                    name: $pattern['name'] ?? $pattern['question'] ?? 'Query',
                    question: $pattern['question'] ?? $pattern['name'] ?? '',
                    sql: $pattern['sql'] ?? $pattern['query'] ?? '',
                    summary: $pattern['summary'] ?? $pattern['description'] ?? null,
                    tablesUsed: $pattern['tables_used'] ?? $pattern['tables'] ?? [],
                    dataQualityNotes: $pattern['data_quality_notes'] ?? $pattern['notes'] ?? null,
                ));
            }

            return $patterns;
        } catch (\JsonException $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Convert a QueryPattern model to a QueryPatternData DTO.
     */
    protected function modelToQueryPatternData(QueryPattern $model): QueryPatternData
    {
        return new QueryPatternData(
            name: $model->name,
            question: $model->question,
            sql: $model->sql,
            summary: $model->summary,
            tablesUsed: $model->tables_used ?? [],
            dataQualityNotes: $model->data_quality_notes,
        );
    }

    /**
     * Extract keywords from a question for similarity matching.
     *
     * @return array<string>
     */
    protected function extractKeywords(string $text): array
    {
        // Common stop words to filter out
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
            'am', 'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves',
            'you', 'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his',
            'himself', 'she', 'her', 'hers', 'herself', 'it', 'its', 'itself',
            'they', 'them', 'their', 'theirs', 'themselves', 'show', 'get', 'find',
            'list', 'give', 'tell', 'many', 'much',
        ];

        $words = preg_split('/[^a-zA-Z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn (string $word) => strlen($word) > 2 && ! in_array($word, $stopWords)
        ));
    }

    /**
     * Calculate similarity score between keywords and a pattern.
     */
    protected function calculateSimilarity(array $keywords, QueryPatternData $pattern): float
    {
        if (empty($keywords)) {
            return 0;
        }

        $patternText = strtolower($pattern->name.' '.$pattern->question.' '.($pattern->summary ?? ''));
        $patternWords = $this->extractKeywords($patternText);

        if (empty($patternWords)) {
            return 0;
        }

        $matches = 0;
        foreach ($keywords as $keyword) {
            foreach ($patternWords as $patternWord) {
                // Exact match
                if ($keyword === $patternWord) {
                    $matches += 2;
                }
                // Partial match (contains)
                elseif (str_contains($patternWord, $keyword) || str_contains($keyword, $patternWord)) {
                    $matches += 1;
                }
            }
        }

        return $matches / max(count($keywords), count($patternWords));
    }

    /**
     * Prepare a search term for MySQL full-text search.
     */
    protected function prepareFullTextSearchTerm(string $question): string
    {
        $keywords = $this->extractKeywords($question);

        return implode(' ', $keywords);
    }

    /**
     * Extract table names from SQL.
     *
     * @return array<string>
     */
    protected function extractTablesFromSql(string $sql): array
    {
        $tables = [];

        // Match FROM clause
        if (preg_match_all('/\bFROM\s+([`"\[]?[\w]+[`"\]]?)/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Match JOIN clauses
        if (preg_match_all('/\bJOIN\s+([`"\[]?[\w]+[`"\]]?)/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Clean up table names (remove quotes)
        $tables = array_map(fn ($t) => trim($t, '`"[]'), $tables);

        return array_unique($tables);
    }

    /**
     * Get all query patterns without search.
     *
     * @return Collection<int, QueryPatternData>
     */
    public function all(): Collection
    {
        $source = config('sql-agent.knowledge.source', 'files');

        return match ($source) {
            'files' => $this->loadAllFromFiles(),
            'database' => QueryPattern::all()->map(fn (QueryPattern $model) => $this->modelToQueryPatternData($model)),
            default => collect(),
        };
    }

    /**
     * Set the default limit for search results.
     */
    public function setDefaultLimit(int $limit): self
    {
        $this->defaultLimit = $limit;

        return $this;
    }
}
