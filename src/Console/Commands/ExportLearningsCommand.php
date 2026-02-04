<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Services\LearningMachine;

class ExportLearningsCommand extends Command
{
    protected $signature = 'sql-agent:export-learnings
                            {file? : The output file path (default: storage/app/sql-agent-learnings.json)}
                            {--category= : Filter by category (type_error, schema_fix, query_pattern, data_quality, business_logic)}';

    protected $description = 'Export learnings to a JSON file';

    public function handle(LearningMachine $learningMachine): int
    {
        $file = $this->argument('file') ?? storage_path('app/sql-agent-learnings.json');
        $categoryValue = $this->option('category');

        $category = null;
        if ($categoryValue) {
            $category = LearningCategory::tryFrom($categoryValue);
            if ($category === null) {
                $this->error("Invalid category: {$categoryValue}");
                $this->info('Valid categories: '.implode(', ', array_map(
                    fn ($c) => $c->value,
                    LearningCategory::cases(),
                )));

                return self::FAILURE;
            }
        }

        $this->info('Exporting learnings...');

        $learnings = $learningMachine->export($category);

        if (empty($learnings)) {
            $this->warn('No learnings found to export.');

            return self::SUCCESS;
        }

        $data = [
            'exported_at' => now()->toIso8601String(),
            'count' => count($learnings),
            'category_filter' => $category?->value,
            'learnings' => $learnings,
        ];

        $directory = dirname($file);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->components->info("Exported {$data['count']} learnings to: {$file}");

        if ($category) {
            $this->line("  Filtered by category: {$category->label()}");
        }

        return self::SUCCESS;
    }
}
