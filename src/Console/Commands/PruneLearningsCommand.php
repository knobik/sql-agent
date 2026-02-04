<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Knobik\SqlAgent\Services\LearningMachine;

class PruneLearningsCommand extends Command
{
    protected $signature = 'sql-agent:prune-learnings
                            {--days= : Remove learnings older than this many days (default from config)}
                            {--duplicates : Only remove duplicate learnings}
                            {--include-used : Also remove learnings that have been used}
                            {--dry-run : Show what would be removed without actually removing}';

    protected $description = 'Remove old or duplicate learnings';

    public function handle(LearningMachine $learningMachine): int
    {
        // Use config value as default when --days is not provided
        $daysOption = $this->option('days');
        $days = $daysOption !== null
            ? (int) $daysOption
            : config('sql-agent.learning.prune_after_days', 90);

        $duplicatesOnly = (bool) $this->option('duplicates');
        $includeUsed = (bool) $this->option('include-used');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
            $this->newLine();
        }

        if ($duplicatesOnly) {
            return $this->handleDuplicates($learningMachine, $dryRun);
        }

        return $this->handleOldLearnings($learningMachine, $days, $includeUsed, $dryRun);
    }

    protected function handleDuplicates(LearningMachine $learningMachine, bool $dryRun): int
    {
        $this->info('Finding duplicate learnings...');

        $duplicates = $learningMachine->findDuplicates();

        if ($duplicates->isEmpty()) {
            $this->components->info('No duplicates found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$duplicates->count()} duplicate(s):");
        $this->newLine();

        $headers = ['ID', 'Title', 'Category', 'Created'];
        $rows = $duplicates->map(fn ($l) => [
            $l->id,
            mb_substr($l->title, 0, 50) . (mb_strlen($l->title) > 50 ? '...' : ''),
            $l->category->label(),
            $l->created_at->format('Y-m-d H:i'),
        ])->all();

        $this->table($headers, $rows);
        $this->newLine();

        if ($dryRun) {
            $this->warn("Would remove {$duplicates->count()} duplicate(s).");

            return self::SUCCESS;
        }

        if (! $this->confirm("Remove {$duplicates->count()} duplicate(s)?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $removed = $learningMachine->removeDuplicates();
        $this->components->info("Removed {$removed} duplicate(s).");

        return self::SUCCESS;
    }

    protected function handleOldLearnings(
        LearningMachine $learningMachine,
        int $days,
        bool $includeUsed,
        bool $dryRun,
    ): int {
        $keepUsed = ! $includeUsed;
        $cutoffDate = now()->subDays($days);

        $this->info("Finding learnings older than {$days} days (before {$cutoffDate->format('Y-m-d')})...");

        if ($keepUsed) {
            $this->line('  Keeping learnings that have been used');
        }

        $this->newLine();

        if ($dryRun) {
            // For dry run, we need to count what would be affected
            $builder = \Knobik\SqlAgent\Models\Learning::where('created_at', '<', $cutoffDate);

            if ($keepUsed) {
                $builder->whereNull('metadata->last_used_at');
            }

            $count = $builder->count();

            if ($count === 0) {
                $this->components->info('No learnings found matching criteria.');

                return self::SUCCESS;
            }

            $this->warn("Would remove {$count} learning(s).");

            return self::SUCCESS;
        }

        $removed = $learningMachine->prune($days, $keepUsed);

        if ($removed === 0) {
            $this->components->info('No learnings found matching criteria.');

            return self::SUCCESS;
        }

        $this->components->info("Removed {$removed} learning(s).");

        return self::SUCCESS;
    }
}
