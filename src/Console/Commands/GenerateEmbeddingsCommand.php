<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Embeddings\EmbeddingGenerator;
use Knobik\SqlAgent\Embeddings\TextSerializer;
use Knobik\SqlAgent\Models\Embedding;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;

class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'sql-agent:generate-embeddings
                            {--model= : Only generate for a specific model (query_patterns or learnings)}
                            {--force : Regenerate embeddings even if they already exist}
                            {--batch-size=50 : Number of records to process per batch}';

    protected $description = 'Generate vector embeddings for existing knowledge base records';

    /**
     * @var array<string, class-string<Model&Searchable>>
     */
    protected array $modelMapping = [
        'query_patterns' => QueryPattern::class,
        'learnings' => Learning::class,
    ];

    public function handle(EmbeddingGenerator $generator, TextSerializer $serializer): int
    {
        if (! class_exists(\Pgvector\Laravel\Vector::class)) {
            $this->error('The pgvector/pgvector package is not installed. Install it with: composer require pgvector/pgvector');

            return self::FAILURE;
        }

        $connection = config('sql-agent.search.drivers.pgvector.connection');

        if (! $connection) {
            $this->error('No embeddings connection configured. Set SQL_AGENT_EMBEDDINGS_CONNECTION in your .env file.');

            return self::FAILURE;
        }

        $driverName = Schema::connection($connection)->getConnection()->getDriverName();

        if ($driverName !== 'pgsql') {
            $this->error("The embeddings connection '{$connection}' must be a PostgreSQL connection (got '{$driverName}').");

            return self::FAILURE;
        }

        $modelFilter = $this->option('model');
        $force = (bool) $this->option('force');
        $batchSize = (int) $this->option('batch-size');

        if ($modelFilter && ! isset($this->modelMapping[$modelFilter])) {
            $this->error("Unknown model: {$modelFilter}. Available: ".implode(', ', array_keys($this->modelMapping)));

            return self::FAILURE;
        }

        $models = $modelFilter
            ? [$modelFilter => $this->modelMapping[$modelFilter]]
            : $this->modelMapping;

        foreach ($models as $name => $modelClass) {
            $this->processModel($name, $modelClass, $generator, $serializer, $force, $batchSize);
        }

        $this->newLine();
        $this->info('Embedding generation complete.');

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model&Searchable>  $modelClass
     */
    protected function processModel(
        string $name,
        string $modelClass,
        EmbeddingGenerator $generator,
        TextSerializer $serializer,
        bool $force,
        int $batchSize,
    ): void {
        $total = $modelClass::count();

        if ($total === 0) {
            $this->warn("No {$name} records found. Skipping.");

            return;
        }

        $this->info("Processing {$total} {$name}...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created = 0;
        $skipped = 0;

        $modelClass::query()->chunkById($batchSize, function ($records) use (
            $generator, $serializer, $force, &$created, &$skipped, $bar
        ) {
            $textsToEmbed = [];
            $recordsToEmbed = [];

            /** @var Model&Searchable $record */
            foreach ($records as $record) {
                if (! $force) {
                    $existing = Embedding::forModel($record)->first();

                    if ($existing) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }
                }

                $text = $serializer->serialize($record);

                if ($text === '') {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $textsToEmbed[] = $text;
                $recordsToEmbed[] = $record;
            }

            if ($textsToEmbed === []) {
                return;
            }

            // Batch embed for efficiency
            $vectors = $generator->embedBatch($textsToEmbed);

            foreach ($recordsToEmbed as $i => $record) {
                Embedding::updateOrCreate(
                    [
                        'embeddable_type' => $record->getMorphClass(),
                        'embeddable_id' => $record->getKey(),
                    ],
                    [
                        'embedding' => $vectors[$i],
                        'content_hash' => hash('sha256', $textsToEmbed[$i]),
                    ]
                );

                $created++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->line("  Created: {$created}, Skipped: {$skipped}");
    }
}
