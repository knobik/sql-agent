<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class Context extends Data
{
    public function __construct(
        public string $semanticModel,
        public string $businessRules,
        /** @var Collection<int, QueryPatternData> */
        public Collection $queryPatterns,
        /** @var Collection<int, mixed> */
        public Collection $learnings,
        /** @var Collection<int, array<string, mixed>> */
        public ?Collection $customKnowledge = null,
    ) {
        $this->customKnowledge ??= collect();
    }

    public function toPromptString(): string
    {
        $sections = [];

        // Layer 1: Semantic Model
        if ($this->semanticModel) {
            $sections[] = $this->formatSection('DATABASE SCHEMA', $this->semanticModel);
        }

        // Layer 2: Business Rules
        if ($this->businessRules) {
            $sections[] = $this->formatSection('BUSINESS RULES & DEFINITIONS', $this->businessRules);
        }

        // Layer 3: Query Patterns
        if ($this->queryPatterns->isNotEmpty()) {
            $patterns = $this->queryPatterns
                ->map(fn (QueryPatternData $p) => $p->toPromptString())
                ->implode("\n");
            $sections[] = $this->formatSection('SIMILAR QUERY EXAMPLES', $patterns);
        }

        // Layer 4: Learnings
        if ($this->learnings->isNotEmpty()) {
            $learnings = $this->learnings
                ->map(fn ($l) => "- {$l['title']}: {$l['description']}")
                ->implode("\n");
            $sections[] = $this->formatSection('RELEVANT LEARNINGS', $learnings);
        }

        // Layer 5: Custom Knowledge
        if ($this->customKnowledge->isNotEmpty()) {
            $knowledge = $this->customKnowledge
                ->map(function (array $item) {
                    $parts = [];
                    foreach ($item as $key => $value) {
                        if ($value !== null && $value !== '') {
                            $parts[] = "{$key}: {$value}";
                        }
                    }

                    return '- '.implode(' | ', $parts);
                })
                ->implode("\n");
            $sections[] = $this->formatSection('ADDITIONAL KNOWLEDGE', $knowledge);
        }

        return implode("\n\n", $sections);
    }

    protected function formatSection(string $title, string $content): string
    {
        return "# {$title}\n\n{$content}";
    }

    public function hasQueryPatterns(): bool
    {
        return $this->queryPatterns->isNotEmpty();
    }

    public function hasLearnings(): bool
    {
        return $this->learnings->isNotEmpty();
    }

    public function getQueryPatternCount(): int
    {
        return $this->queryPatterns->count();
    }

    public function getLearningCount(): int
    {
        return $this->learnings->count();
    }

    public function isEmpty(): bool
    {
        return empty($this->semanticModel)
            && empty($this->businessRules)
            && $this->queryPatterns->isEmpty()
            && $this->learnings->isEmpty()
            && $this->customKnowledge->isEmpty();
    }
}
