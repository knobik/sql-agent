<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Tools\IntrospectSchemaTool;
use Prism\Prism\Tool;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

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
    it('extends Prism Tool', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect($tool)->toBeInstanceOf(Tool::class);
    });

    it('lists all tables', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(), true);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('tables');
        expect($result)->toHaveKey('count');
    });

    it('inspects specific table', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(table_name: 'sql_agent_learnings'), true);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('table');
        expect($result['table'])->toBe('sql_agent_learnings');
        expect($result)->toHaveKey('columns');
    });

    it('handles non-existent table', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect(fn () => $tool(table_name: 'non_existent_table_xyz123'))
            ->toThrow(RuntimeException::class, 'does not exist');
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

    it('excludes denied tables from listing', function () {
        config()->set('sql-agent.sql.denied_tables', ['test_users']);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(), true);

        expect($result['tables'])->not->toContain('test_users');
    });

    it('rejects introspection of denied table', function () {
        config()->set('sql-agent.sql.denied_tables', ['test_users']);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect(fn () => $tool(table_name: 'test_users'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('hides columns from inspected table', function () {
        config()->set('sql-agent.sql.hidden_columns', [
            'test_users' => ['email'],
        ]);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(table_name: 'test_users'), true);

        $columnNames = array_column($result['columns'], 'name');

        expect($columnNames)->toContain('id');
        expect($columnNames)->toContain('name');
        expect($columnNames)->not->toContain('email');
    });
});
