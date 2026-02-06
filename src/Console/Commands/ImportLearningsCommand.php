<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Knobik\SqlAgent\Services\LearningImportExport;

class ImportLearningsCommand extends Command
{
    protected $signature = 'sql-agent:import-learnings
                            {file : The JSON file to import from}
                            {--skip-duplicates : Skip learnings that already exist (default behavior)}
                            {--force : Import all learnings, including duplicates}';

    protected $description = 'Import learnings from a JSON file';

    public function handle(LearningImportExport $importExport): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON in file: '.json_last_error_msg());

            return self::FAILURE;
        }

        $learnings = $data['learnings'] ?? $data;

        if (! is_array($learnings)) {
            $this->error("Invalid file format. Expected 'learnings' array or array of learnings.");

            return self::FAILURE;
        }

        if (empty($learnings)) {
            $this->warn('No learnings found in file.');

            return self::SUCCESS;
        }

        $total = count($learnings);
        $skipDuplicates = ! $this->option('force');

        $this->info("Importing {$total} learnings from: {$file}");

        if ($skipDuplicates) {
            $this->line('  Skipping duplicates (use --force to import all)');
        }

        $this->newLine();

        $imported = $importExport->import($learnings, $skipDuplicates);
        $skipped = $total - $imported;

        $this->components->info('Import complete!');
        $this->line("  Imported: {$imported}");

        if ($skipped > 0) {
            $this->line("  Skipped (duplicates): {$skipped}");
        }

        return self::SUCCESS;
    }
}
