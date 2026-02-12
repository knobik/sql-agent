<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create the sql_agent_embeddings table on the dedicated embeddings connection.
 * This migration only runs on PostgreSQL connections with pgvector installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = $this->getConnection();

        if (! $connection) {
            return;
        }

        $driverName = Schema::connection($connection)->getConnection()->getDriverName();

        if ($driverName !== 'pgsql') {
            return;
        }

        DB::connection($connection)->statement('CREATE EXTENSION IF NOT EXISTS vector');

        $dimensions = (int) config('sql-agent.search.drivers.pgvector.dimensions', 1536);

        Schema::connection($connection)->create('sql_agent_embeddings', function (Blueprint $table) use ($dimensions) {
            $table->id();
            $table->string('embeddable_type');
            $table->unsignedBigInteger('embeddable_id');
            $table->vector('embedding', $dimensions);
            $table->string('content_hash', 64);
            $table->timestamps();

            $table->unique(['embeddable_type', 'embeddable_id']);
        });

        // Add HNSW index for fast cosine similarity search
        DB::connection($connection)->statement(
            'CREATE INDEX sql_agent_embeddings_embedding_idx ON sql_agent_embeddings USING hnsw (embedding vector_cosine_ops)'
        );
    }

    public function down(): void
    {
        $connection = $this->getConnection();

        if (! $connection) {
            return;
        }

        $driverName = Schema::connection($connection)->getConnection()->getDriverName();

        if ($driverName !== 'pgsql') {
            return;
        }

        Schema::connection($connection)->dropIfExists('sql_agent_embeddings');
    }

    public function getConnection(): ?string
    {
        return config('sql-agent.search.drivers.pgvector.connection');
    }
};
