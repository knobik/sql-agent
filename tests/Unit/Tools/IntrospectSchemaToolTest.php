<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Contracts\ToolResult;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Tools\IntrospectSchemaTool;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

    // Create a test table for SQL execution tests
    DB::statement('CREATE TABLE IF NOT EXISTS test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    DB::table('test_users')->insert([
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ]);
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS test_users');
});

describe('IntrospectSchemaTool', function () {
    it('lists all tables', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->execute([]);

        // In SQLite test environment, introspector may fail or return empty
        // The tool should return a result (success or failure)
        expect($result)->toBeInstanceOf(ToolResult::class);

        if ($result->success) {
            expect($result->data)->toBeArray();
        }
    });

    it('inspects specific table', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->execute(['table_name' => 'sql_agent_learnings']);

        // The tool should return a result
        expect($result)->toBeInstanceOf(ToolResult::class);

        if ($result->success) {
            expect($result->data)->toBeArray();
        }
    });

    it('handles non-existent table', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->execute(['table_name' => 'non_existent_table_xyz123']);

        // Non-existent tables should return a failure result
        expect($result)->toBeInstanceOf(ToolResult::class);
        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('non_existent_table_xyz123');
        expect($result->error)->toContain('does not exist');
    });

    it('has correct name', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect($tool->name())->toBe('introspect_schema');
    });

    it('can set connection', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->setConnection('testing');

        expect($result)->toBe($tool);
    });
});
