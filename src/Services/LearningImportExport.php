<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Support\UserResolver;

class LearningImportExport
{
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
                'user_id' => app(UserResolver::class)->id(),
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * Check if data would create a duplicate.
     */
    public function isDuplicate(array $data): bool
    {
        if (Learning::where('title', $data['title'])->exists()) {
            return true;
        }

        if (! empty($data['sql'])) {
            $hash = md5($data['sql']);

            return Learning::whereNotNull('sql')
                ->get(['sql'])
                ->contains(fn (Learning $l) => md5($l->sql) === $hash);
        }

        return false;
    }
}
