<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Knobik\SqlAgent\Services\KnowledgeLoader;

class LoadKnowledgeCommand extends Command
{
    protected $signature = 'sql-agent:load-knowledge
                            {--recreate : Drop and recreate all knowledge}
                            {--tables : Load only table metadata}
                            {--rules : Load only business rules}
                            {--queries : Load only query patterns}
                            {--path= : Custom path to knowledge files}';

    protected $description = 'Load knowledge files into the database';

    public function handle(KnowledgeLoader $loader): int
    {
        $path = $this->option('path') ?? config('sql-agent.knowledge.path');

        if (! is_dir($path)) {
            $this->error("Knowledge path does not exist: {$path}");
            $this->info('You can create the directory structure with:');
            $this->line("  mkdir -p {$path}/tables {$path}/business {$path}/queries");

            return self::FAILURE;
        }

        // Handle --recreate flag
        if ($this->option('recreate')) {
            if ($this->confirm('This will delete all existing knowledge. Are you sure?', true)) {
                $this->warn('Truncating knowledge tables...');
                $loader->truncateAll();
                $this->info('Knowledge tables truncated.');
            } else {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $loadTables = ! $this->option('rules') && ! $this->option('queries');
        $loadRules = ! $this->option('tables') && ! $this->option('queries');
        $loadQueries = ! $this->option('tables') && ! $this->option('rules');

        // If specific flags are provided, only load those
        if ($this->option('tables') || $this->option('rules') || $this->option('queries')) {
            $loadTables = (bool) $this->option('tables');
            $loadRules = (bool) $this->option('rules');
            $loadQueries = (bool) $this->option('queries');
        }

        $this->info("Loading knowledge from: {$path}");
        $this->newLine();

        $results = [];

        // Load tables
        if ($loadTables) {
            $this->components->task('Loading table metadata', function () use ($loader, $path, &$results) {
                $results['tables'] = $loader->loadTables($path.'/tables');

                return true;
            });
            $this->line("  Loaded {$results['tables']} table metadata file(s)");
        }

        // Load business rules
        if ($loadRules) {
            $this->components->task('Loading business rules', function () use ($loader, $path, &$results) {
                $results['rules'] = $loader->loadBusinessRules($path.'/business');

                return true;
            });
            $this->line("  Loaded {$results['rules']} business rule(s)");
        }

        // Load query patterns
        if ($loadQueries) {
            $this->components->task('Loading query patterns', function () use ($loader, $path, &$results) {
                $results['queries'] = $loader->loadQueryPatterns($path.'/queries');

                return true;
            });
            $this->line("  Loaded {$results['queries']} query pattern(s)");
        }

        $this->newLine();
        $this->components->info('Knowledge loaded successfully!');

        // Show summary
        $total = array_sum($results);
        if ($total === 0) {
            $this->warn('No knowledge files were found. Make sure your files are in the correct format.');
            $this->newLine();
            $this->info('Expected directory structure:');
            $this->line("  {$path}/tables/*.json    - Table metadata");
            $this->line("  {$path}/business/*.json  - Business rules & metrics");
            $this->line("  {$path}/queries/*.sql    - Query patterns");
            $this->line("  {$path}/queries/*.json   - Query patterns (JSON format)");
        }

        return self::SUCCESS;
    }
}
