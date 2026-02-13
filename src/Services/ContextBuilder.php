<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Data\Context;
use Knobik\SqlAgent\Data\QueryPatternData;
use Knobik\SqlAgent\Search\SearchManager;
use Knobik\SqlAgent\Search\SearchResult;

class ContextBuilder
{
    public function __construct(
        protected SemanticModelLoader $semanticLoader,
        protected BusinessRulesLoader $rulesLoader,
        protected SearchManager $searchManager,
        protected ConnectionRegistry $connectionRegistry,
    ) {}

    /**
     * Build the complete context for a question.
     */
    public function build(string $question): Context
    {
        return new Context(
            semanticModel: $this->buildSemanticModel(),
            businessRules: $this->rulesLoader->format(),
            queryPatterns: $this->searchQueryPatterns($question),
            learnings: $this->searchLearnings($question),
            customKnowledge: $this->searchCustomIndexes($question),
        );
    }

    /**
     * Build context with custom options.
     *
     * @internal
     */
    public function buildWithOptions(
        string $question,
        bool $includeSemanticModel = true,
        bool $includeBusinessRules = true,
        bool $includeQueryPatterns = true,
        bool $includeLearnings = true,
        int $queryPatternLimit = 3,
        int $learningLimit = 5,
    ): Context {
        return new Context(
            semanticModel: $includeSemanticModel ? $this->buildSemanticModel() : '',
            businessRules: $includeBusinessRules ? $this->rulesLoader->format() : '',
            queryPatterns: $includeQueryPatterns ? $this->searchQueryPatterns($question, $queryPatternLimit) : collect(),
            learnings: $includeLearnings ? $this->searchLearnings($question, $learningLimit) : collect(),
            customKnowledge: $this->searchCustomIndexes($question),
        );
    }

    /**
     * Build minimal context (just schema, no search).
     *
     * @internal
     */
    public function buildMinimal(): Context
    {
        return new Context(
            semanticModel: $this->buildSemanticModel(),
            businessRules: $this->rulesLoader->format(),
            queryPatterns: collect(),
            learnings: collect(),
        );
    }

    /**
     * Search for query patterns via SearchManager.
     *
     * @return Collection<int, QueryPatternData>
     */
    protected function searchQueryPatterns(string $question, int $limit = 3): Collection
    {
        return $this->searchManager->search($question, 'query_patterns', $limit)
            ->map(fn (SearchResult $result) => new QueryPatternData(
                name: $result->model->getAttribute('name'),
                question: $result->model->getAttribute('question'),
                sql: $result->model->getAttribute('sql'),
                summary: $result->model->getAttribute('summary'),
                tablesUsed: $result->model->getAttribute('tables_used') ?? [],
                dataQualityNotes: $result->model->getAttribute('data_quality_notes'),
            ));
    }

    /**
     * Search for relevant learnings via SearchManager.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchLearnings(string $question, int $limit = 5): Collection
    {
        if (! config('sql-agent.learning.enabled')) {
            return collect();
        }

        return $this->searchManager->search($question, 'learnings', $limit) // @phpstan-ignore return.type
            ->map(fn (SearchResult $result) => [
                'title' => $result->model->getAttribute('title'),
                'description' => $result->model->getAttribute('description'),
                'category' => $result->model->getAttribute('category')?->value,
                'sql' => $result->model->getAttribute('sql'),
            ]);
    }

    /**
     * Search custom indexes for additional knowledge.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchCustomIndexes(string $question, int $limit = 5): Collection
    {
        $customIndexes = $this->searchManager->getCustomIndexes();

        if (empty($customIndexes)) {
            return collect();
        }

        return $this->searchManager->searchMultiple($question, $customIndexes, $limit)
            ->map(function (SearchResult $result) {
                /** @var \Illuminate\Database\Eloquent\Model&\Knobik\SqlAgent\Contracts\Searchable $model */
                $model = $result->model;

                return $model->toSearchableArray();
            });
    }

    /**
     * Build semantic model across all configured connections.
     */
    protected function buildSemanticModel(): string
    {
        $sections = [];

        foreach ($this->connectionRegistry->all() as $name => $config) {
            $semantic = $this->semanticLoader->format($config->connection, $name);
            if ($semantic && $semantic !== 'No table metadata available.') {
                $sections[] = "## Connection: {$name} ({$config->label})\n{$config->description}\n\n{$semantic}";
            }
        }

        return implode("\n\n---\n\n", $sections) ?: 'No table metadata available.';
    }

    /**
     * Get the semantic model loader.
     *
     * @internal
     */
    public function getSemanticLoader(): SemanticModelLoader
    {
        return $this->semanticLoader;
    }

    /**
     * Get the business rules loader.
     *
     * @internal
     */
    public function getRulesLoader(): BusinessRulesLoader
    {
        return $this->rulesLoader;
    }
}
