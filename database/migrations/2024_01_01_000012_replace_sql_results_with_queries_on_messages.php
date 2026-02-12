<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->table('sql_agent_messages', function (Blueprint $table) {
            $table->dropColumn(['sql', 'results']);
        });

        Schema::connection($this->getConnection())->table('sql_agent_messages', function (Blueprint $table) {
            $table->json('queries')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('sql_agent_messages', function (Blueprint $table) {
            $table->dropColumn('queries');
        });

        Schema::connection($this->getConnection())->table('sql_agent_messages', function (Blueprint $table) {
            $table->text('sql')->nullable()->after('content');
            $table->json('results')->nullable()->after('sql');
        });
    }

    public function getConnection(): ?string
    {
        return config('sql-agent.database.storage_connection');
    }
};
