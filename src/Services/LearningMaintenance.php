<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;

class LearningMaintenance
{
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
     * Find duplicate learnings using database-level grouping.
     */
    public function findDuplicates(): Collection
    {
        // Find titles that have more than one entry
        $duplicateTitles = Learning::query()
            ->select('title')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('title')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('title');

        if ($duplicateTitles->isEmpty()) {
            return collect();
        }

        // For each duplicate title, keep the oldest (first created) and return the rest
        $duplicates = collect();

        foreach ($duplicateTitles as $title) {
            $entries = Learning::where('title', $title)
                ->orderBy('created_at')
                ->get();

            // Skip the first entry (keep it), collect the rest as duplicates
            $duplicates = $duplicates->merge($entries->slice(1));
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
     * Get statistics about learnings using aggregate queries.
     */
    public function getStats(): array
    {
        $total = Learning::count();

        $byCategory = [];
        $categoryCounts = Learning::query()
            ->select('category')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category');

        foreach (LearningCategory::cases() as $category) {
            $byCategory[$category->value] = (int) ($categoryCounts[$category->value] ?? 0);
        }

        $recentCount = Learning::where('created_at', '>=', now()->subDays(7))->count();

        $autoLearnedCount = Learning::where('metadata', 'LIKE', '%"source":"auto_learned"%')
            ->orWhere('metadata', 'LIKE', '%"source": "auto_learned"%')
            ->count();

        return [
            'total' => $total,
            'by_category' => $byCategory,
            'recent_7_days' => $recentCount,
            'auto_learned' => $autoLearnedCount,
            'manual' => $total - $autoLearnedCount,
        ];
    }
}
