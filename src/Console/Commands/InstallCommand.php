<?php

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'sql-agent:install
                            {--force : Overwrite existing files}';

    protected $description = 'Install the SqlAgent package';

    public function handle(): int
    {
        $this->info('Installing SqlAgent...');

        // Publish config
        $this->call('vendor:publish', [
            '--tag' => 'sql-agent-config',
            '--force' => $this->option('force'),
        ]);

        // Publish Prism config for LLM provider credentials
        $this->call('vendor:publish', [
            '--tag' => 'prism-config',
            '--force' => $this->option('force'),
        ]);

        // Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'sql-agent-migrations',
            '--force' => $this->option('force'),
        ]);

        // Create knowledge directory before migrations so it exists even if migrations fail
        $knowledgePath = resource_path('sql-agent/knowledge');
        if (! is_dir($knowledgePath)) {
            mkdir($knowledgePath, 0755, true);
            mkdir($knowledgePath.'/tables', 0755, true);
            mkdir($knowledgePath.'/queries', 0755, true);
            mkdir($knowledgePath.'/business', 0755, true);
            $this->info("Created knowledge directory at: {$knowledgePath}");
        }

        // Ask to run migrations
        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->info('SqlAgent installed successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('  1. Configure your LLM provider credentials in config/prism.php');
        $this->line('  2. Set SQL_AGENT_LLM_PROVIDER and SQL_AGENT_LLM_MODEL in .env');
        $this->line('  3. Add table metadata to resources/sql-agent/knowledge/tables/');
        $this->line('  4. Run: php artisan sql-agent:load-knowledge');
        $this->newLine();
        $this->line('  Note: The web UI requires an auth middleware with a "login" route.');
        $this->line('  Either install auth scaffolding or change the middleware in config/sql-agent.php.');

        return self::SUCCESS;
    }
}
