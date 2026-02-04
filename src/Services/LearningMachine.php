<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Events\LearningCreated;
use Knobik\SqlAgent\Models\Learning;

class LearningMachine
{
    protected const AUTO_LEARN_CACHE_KEY = 'sql_agent_auto_learnings_today';

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
            'user_id' => auth()->id(),
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

        // Check if we've hit the daily limit
        if (! $this->canAutoLearnToday()) {
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

        $this->incrementAutoLearnCount();

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
     * Export learnings to an array.
     */
    public function export(?LearningCategory $category = null): array
    {
        $builder = Learning::query();

        if ($category !== null) {
            $builder->ofCategory($category);
        }

        return $builder->get()
            ->map(fn (Learning $learning) => [
                'title' => $learning->title,
                'description' => $learning->description,
                'category' => $learning->category->value,
                'sql' => $learning->sql,
                'metadata' => $learning->metadata,
                'created_at' => $learning->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Import learnings from an array.
     */
    public function import(array $learnings, bool $skipDuplicates = true): int
    {
        $imported = 0;

        foreach ($learnings as $data) {
            if ($skipDuplicates && $this->isDuplicate($data)) {
                continue;
            }

            Learning::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => LearningCategory::from($data['category']),
                'sql' => $data['sql'] ?? null,
                'metadata' => array_merge(
                    $data['metadata'] ?? [],
                    ['imported_at' => now()->toIso8601String()],
                ),
                'user_id' => auth()->id(),
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * Prune old learnings.
     */
    public function prune(?int $daysOld = null, bool $keepUsed = true): int
    {
        $daysOld ??= config('sql-agent.learning.prune_after_days', 90);
        $cutoffDate = now()->subDays($daysOld);

        $builder = Learning::where('created_at', '<', $cutoffDate);

        if ($keepUsed) {
            // Keep learnings that have been used (have usage metadata)
            $builder->whereNull('metadata->last_used_at');
        }

        return $builder->delete();
    }

    /**
     * Find duplicate learnings.
     */
    public function findDuplicates(): Collection
    {
        // Group by title hash and SQL hash to find duplicates
        $learnings = Learning::all();
        $groups = [];

        foreach ($learnings as $learning) {
            $key = $this->generateDuplicateKey($learning);
            $groups[$key][] = $learning;
        }

        // Return groups with more than one entry
        $duplicates = collect();
        foreach ($groups as $group) {
            if (count($group) > 1) {
                // Keep the first one, mark the rest as duplicates
                array_shift($group);
                foreach ($group as $duplicate) {
                    $duplicates->push($duplicate);
                }
            }
        }

        return $duplicates;
    }

    /**
     * Remove duplicate learnings.
     */
    public function removeDuplicates(): int
    {
        $duplicates = $this->findDuplicates();
        $count = $duplicates->count();

        foreach ($duplicates as $duplicate) {
            $duplicate->delete();
        }

        return $count;
    }

    /**
     * Get statistics about learnings.
     */
    public function getStats(): array
    {
        $learnings = Learning::all();

        $byCategory = [];
        foreach (LearningCategory::cases() as $category) {
            $byCategory[$category->value] = $learnings
                ->where('category', $category)
                ->count();
        }

        $recentCount = $learnings
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $autoLearnedCount = $learnings
            ->filter(fn ($l) => ($l->metadata['source'] ?? null) === 'auto_learned')
            ->count();

        return [
            'total' => $learnings->count(),
            'by_category' => $byCategory,
            'recent_7_days' => $recentCount,
            'auto_learned' => $autoLearnedCount,
            'manual' => $learnings->count() - $autoLearnedCount,
        ];
    }

    /**
     * Check if we can auto-learn today (rate limiting).
     */
    protected function canAutoLearnToday(): bool
    {
        $maxPerDay = config('sql-agent.learning.max_auto_learnings_per_day', 50);
        $count = Cache::get(self::AUTO_LEARN_CACHE_KEY, 0);

        return $count < $maxPerDay;
    }

    /**
     * Increment the auto-learn counter for today.
     */
    protected function incrementAutoLearnCount(): void
    {
        $count = Cache::get(self::AUTO_LEARN_CACHE_KEY, 0);
        Cache::put(
            self::AUTO_LEARN_CACHE_KEY,
            $count + 1,
            now()->endOfDay(),
        );
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

    /**
     * Check if data would create a duplicate.
     */
    protected function isDuplicate(array $data): bool
    {
        $titleMatch = Learning::where('title', $data['title'])->exists();

        if ($titleMatch) {
            return true;
        }

        if (! empty($data['sql'])) {
            return Learning::whereRaw("MD5(sql) = ?", [md5($data['sql'])])->exists();
        }

        return false;
    }

    /**
     * Generate a key for duplicate detection.
     */
    protected function generateDuplicateKey(Learning $learning): string
    {
        $parts = [
            md5($learning->title),
        ];

        if ($learning->sql) {
            $parts[] = md5($learning->sql);
        }

        return implode(':', $parts);
    }
}
