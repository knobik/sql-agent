<?php

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SetupPgvectorCommand extends Command
{
    protected $signature = 'sql-agent:setup-pgvector';

    protected $description = 'Set up the pgvector embeddings table on the configured connection';

    public function handle(): int
    {
        if (! class_exists(\Pgvector\Laravel\Vector::class)) {
            $this->error('The pgvector/pgvector package is not installed. Install it with: composer require pgvector/pgvector');

            return self::FAILURE;
        }

        $connection = config('sql-agent.search.drivers.pgvector.connection');

        if (! $connection) {
            $this->error('No pgvector connection configured.');
            $this->line('Set SQL_AGENT_EMBEDDINGS_CONNECTION in your .env file and add the connection to config/database.php.');

            return self::FAILURE;
        }

        $driverName = Schema::connection($connection)->getConnection()->getDriverName();

        if ($driverName !== 'pgsql') {
            $this->error("The [{$connection}] connection uses the [{$driverName}] driver, but pgvector requires PostgreSQL.");

            return self::FAILURE;
        }

        if (Schema::connection($connection)->hasTable('sql_agent_embeddings')) {
            $this->info('The sql_agent_embeddings table already exists — skipping.');
            $this->newLine();
            $this->info('pgvector is ready!');
            $this->line('  Next step: php artisan sql-agent:generate-embeddings');

            return self::SUCCESS;
        }

        // Publish the pgvector embeddings migration
        $this->info('Publishing pgvector migration...');
        $this->call('vendor:publish', [
            '--tag' => 'sql-agent-pgvector-migrations',
            '--force' => true,
        ]);

        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        } else {
            $this->newLine();
            $this->line('Run <comment>php artisan migrate</comment> to create the embeddings table.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('pgvector setup complete!');
        $this->line('  Next step: php artisan sql-agent:generate-embeddings');

        return self::SUCCESS;
    }
}
