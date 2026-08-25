<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a full-text index to the table metadata table.
 *
 * Table metadata became searchable so the agent can retrieve only the tables
 * relevant to a question (`sql-agent.schema.mode` set to "rag"). SQLite falls
 * back to LIKE and needs no index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driverName = Schema::connection($this->getConnection())->getConnection()->getDriverName();

        match ($driverName) {
            'mysql', 'mariadb' => $this->createMysqlIndex(),
            'pgsql' => $this->createPostgresIndex(),
            'sqlsrv' => $this->createSqlServerIndex(),
            default => null,
        };
    }

    public function down(): void
    {
        $driverName = Schema::connection($this->getConnection())->getConnection()->getDriverName();

        match ($driverName) {
            'mysql', 'mariadb' => $this->dropMysqlIndex(),
            'pgsql' => $this->dropPostgresIndex(),
            'sqlsrv' => $this->dropSqlServerIndex(),
            default => null,
        };
    }

    protected function createMysqlIndex(): void
    {
        Schema::connection($this->getConnection())->table('sql_agent_table_metadata', function ($table) {
            $table->fullText(['table_name', 'description'], 'sql_agent_table_metadata_fulltext_idx');
        });
    }

    protected function dropMysqlIndex(): void
    {
        Schema::connection($this->getConnection())->table('sql_agent_table_metadata', function ($table) {
            $table->dropFullText('sql_agent_table_metadata_fulltext_idx');
        });
    }

    protected function createPostgresIndex(): void
    {
        DB::connection($this->getConnection())->statement("
            CREATE INDEX IF NOT EXISTS sql_agent_table_metadata_fulltext_idx
            ON sql_agent_table_metadata
            USING GIN (to_tsvector('english', coalesce(table_name, '') || ' ' || coalesce(description, '')))
        ");
    }

    protected function dropPostgresIndex(): void
    {
        DB::connection($this->getConnection())->statement('DROP INDEX IF EXISTS sql_agent_table_metadata_fulltext_idx');
    }

    protected function createSqlServerIndex(): void
    {
        $connection = $this->getConnection();

        DB::connection($connection)->statement("
            IF NOT EXISTS (SELECT 1 FROM sys.fulltext_catalogs WHERE name = 'sql_agent_catalog')
            BEGIN
                CREATE FULLTEXT CATALOG sql_agent_catalog AS DEFAULT
            END
        ");

        DB::connection($connection)->statement("
            IF NOT EXISTS (SELECT 1 FROM sys.fulltext_indexes WHERE object_id = OBJECT_ID('sql_agent_table_metadata'))
            BEGIN
                CREATE FULLTEXT INDEX ON sql_agent_table_metadata(table_name, description)
                KEY INDEX PK_sql_agent_table_metadata
                ON sql_agent_catalog
                WITH CHANGE_TRACKING AUTO
            END
        ");
    }

    protected function dropSqlServerIndex(): void
    {
        DB::connection($this->getConnection())->statement("
            IF EXISTS (SELECT 1 FROM sys.fulltext_indexes WHERE object_id = OBJECT_ID('sql_agent_table_metadata'))
            BEGIN
                DROP FULLTEXT INDEX ON sql_agent_table_metadata
            END
        ");
    }

    public function getConnection(): ?string
    {
        return config('sql-agent.database.storage_connection');
    }
};
