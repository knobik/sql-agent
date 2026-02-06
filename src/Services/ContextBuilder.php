<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Data\Context;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Support\TextAnalyzer;

class ContextBuilder
{
    public function __construct(
        protected SemanticModelLoader $semanticLoader,
        protected BusinessRulesLoader $rulesLoader,
        protected QueryPatternSearch $patternSearch,
        protected SchemaIntrospector $introspector,
    ) {}

    /**
     * Build the complete context for a question.
     */
    public function build(string $question, ?string $connection = null): Context
    {
        return new Context(
            semanticModel: $this->semanticLoader->format($connection),
            businessRules: $this->rulesLoader->format(),
            queryPatterns: $this->patternSearch->search($question),
            learnings: $this->searchLearnings($question),
            runtimeSchema: $this->introspector->getRelevantSchema($question, $connection),
        );
    }

    /**
     * Build context with custom options.
     */
    public function buildWithOptions(
        string $question,
        ?string $connection = null,
        bool $includeSemanticModel = true,
        bool $includeBusinessRules = true,
        bool $includeQueryPatterns = true,
        bool $includeLearnings = true,
        bool $includeRuntimeSchema = true,
        int $queryPatternLimit = 3,
        int $learningLimit = 5,
    ): Context {
        return new Context(
            semanticModel: $includeSemanticModel ? $this->semanticLoader->format($connection) : '',
            businessRules: $includeBusinessRules ? $this->rulesLoader->format() : '',
            queryPatterns: $includeQueryPatterns ? $this->patternSearch->search($question, $queryPatternLimit) : collect(),
            learnings: $includeLearnings ? $this->searchLearnings($question, $learningLimit) : collect(),
            runtimeSchema: $includeRuntimeSchema ? $this->introspector->getRelevantSchema($question, $connection) : null,
        );
    }

    /**
     * Build minimal context (just schema, no search).
     */
    public function buildMinimal(?string $connection = null): Context
    {
        return new Context(
            semanticModel: $this->semanticLoader->format($connection),
            businessRules: $this->rulesLoader->format(),
            queryPatterns: collect(),
            learnings: collect(),
            runtimeSchema: null,
        );
    }

    /**
     * Build context with runtime introspection only.
     */
    public function buildRuntimeOnly(string $question, ?string $connection = null): Context
    {
        return new Context(
            semanticModel: '',
            businessRules: '',
            queryPatterns: collect(),
            learnings: collect(),
            runtimeSchema: $this->introspector->getRelevantSchema($question, $connection),
        );
    }

    /**
     * Search for relevant learnings.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchLearnings(string $question, int $limit = 5): Collection
    {
        if (! config('sql-agent.learning.enabled', true)) {
            return collect();
        }

        $keywords = TextAnalyzer::extractKeywords($question);

        if (empty($keywords)) {
            return Learning::query()
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (Learning $l) => [
                    'title' => $l->title,
                    'description' => $l->description,
                    'category' => $l->category?->value,
                    'sql' => $l->sql,
                ]);
        }

        $query = Learning::query();

        foreach ($keywords as $keyword) {
            $term = '%'.strtolower($keyword).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        $results = $query->limit($limit)->get();

        // If strict keyword match returned nothing, try looser search
        if ($results->isEmpty()) {
            $query = Learning::query();
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $term = '%'.strtolower($keyword).'%';
                    $q->orWhereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
                }
            });
            $results = $query->limit($limit)->get();
        }

        return $results->map(fn (Learning $l) => [
            'title' => $l->title,
            'description' => $l->description,
            'category' => $l->category?->value,
            'sql' => $l->sql,
        ]);
    }

    /**
     * Get the semantic model loader.
     */
    public function getSemanticLoader(): SemanticModelLoader
    {
        return $this->semanticLoader;
    }

    /**
     * Get the business rules loader.
     */
    public function getRulesLoader(): BusinessRulesLoader
    {
        return $this->rulesLoader;
    }

    /**
     * Get the query pattern search service.
     */
    public function getPatternSearch(): QueryPatternSearch
    {
        return $this->patternSearch;
    }

    /**
     * Get the schema introspector.
     */
    public function getIntrospector(): SchemaIntrospector
    {
        return $this->introspector;
    }
}
