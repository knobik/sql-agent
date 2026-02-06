<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Events\LearningCreated;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Support\UserResolver;

class LearningMachine
{
    public function __construct(
        protected ErrorAnalyzer $errorAnalyzer,
    ) {}

    /**
     * Save a new learning entry.
     */
    public function save(
        string $title,
        string $description,
        LearningCategory $category,
        ?string $sql = null,
        array $metadata = [],
    ): Learning {
        $learning = Learning::create([
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'sql' => $sql,
            'metadata' => $metadata,
            'user_id' => app(UserResolver::class)->id(),
        ]);

        LearningCreated::dispatch($learning);

        return $learning;
    }

    /**
     * Search for learnings matching a query.
     */
    public function search(string $query, int $limit = 5, ?LearningCategory $category = null): Collection
    {
        $builder = Learning::search($query);

        if ($category !== null) {
            $builder->ofCategory($category);
        }

        return $builder->limit($limit)->get();
    }

    /**
     * Find similar learnings based on SQL content.
     */
    public function findSimilar(string $sql, int $limit = 3): Collection
    {
        $tables = $this->errorAnalyzer->extractTableNames($sql);

        if (empty($tables)) {
            return collect();
        }

        // Search by table names in the SQL column
        return Learning::where(function ($query) use ($tables) {
            foreach ($tables as $table) {
                $query->orWhere('sql', 'LIKE', "%{$table}%");
            }
        })
            ->limit($limit)
            ->get();
    }

    /**
     * Learn from a SQL error automatically.
     */
    public function learnFromError(string $sql, string $error, string $question): ?Learning
    {
        if (! $this->shouldAutoLearn()) {
            return null;
        }

        // Check for existing similar learning
        if ($this->hasSimilarLearning($sql, $error)) {
            return null;
        }

        $analysis = $this->errorAnalyzer->analyze($sql, $error);

        $learning = $this->save(
            title: $analysis['title'],
            description: $analysis['description'],
            category: $analysis['category'],
            sql: $sql,
            metadata: [
                'original_question' => $question,
                'error_message' => $error,
                'source' => 'auto_learned',
                'tables' => $analysis['tables'],
            ],
        );

        return $learning;
    }

    /**
     * Check if auto-learning is enabled.
     */
    public function shouldAutoLearn(): bool
    {
        return config('sql-agent.learning.enabled', true)
            && config('sql-agent.learning.auto_save_errors', true);
    }

    /**
     * Check if a similar learning already exists.
     */
    protected function hasSimilarLearning(string $sql, string $error): bool
    {
        $title = $this->errorAnalyzer->generateTitle($error);

        // Check title match
        if (Learning::where('title', $title)->exists()) {
            return true;
        }

        // Check if exact SQL already exists
        return Learning::where('sql', $sql)->exists();
    }
}
