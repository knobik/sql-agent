<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Services\SchemaIntrospector;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

    DB::statement('CREATE TABLE IF NOT EXISTS test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT)');
    DB::statement('CREATE TABLE IF NOT EXISTS test_orders (id INTEGER PRIMARY KEY, user_id INTEGER, total REAL)');
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS test_orders');
    DB::statement('DROP TABLE IF EXISTS test_users');
});

describe('SchemaIntrospector with TableAccessControl', function () {
    it('excludes denied tables from getTableNames', function () {
        config()->set('sql-agent.sql.denied_tables', ['test_users']);

        $introspector = app(SchemaIntrospector::class);

        $tables = $introspector->getTableNames();

        expect($tables)->not->toContain('test_users');
        expect($tables)->toContain('test_orders');
    });

    it('excludes tables not in allowed list from getTableNames', function () {
        config()->set('sql-agent.sql.allowed_tables', ['test_orders']);

        $introspector = app(SchemaIntrospector::class);

        $tables = $introspector->getTableNames();

        expect($tables)->not->toContain('test_users');
        expect($tables)->toContain('test_orders');
    });

    it('returns null when introspecting denied table', function () {
        config()->set('sql-agent.sql.denied_tables', ['test_users']);

        $introspector = app(SchemaIntrospector::class);

        $result = $introspector->introspectTable('test_users');

        expect($result)->toBeNull();
    });

    it('hides columns from introspected table', function () {
        config()->set('sql-agent.sql.hidden_columns', [
            'test_users' => ['password'],
        ]);

        $introspector = app(SchemaIntrospector::class);

        $schema = $introspector->introspectTable('test_users');

        expect($schema)->not->toBeNull();
        expect($schema->getColumnNames())->toContain('id');
        expect($schema->getColumnNames())->toContain('name');
        expect($schema->getColumnNames())->toContain('email');
        expect($schema->getColumnNames())->not->toContain('password');
    });

    it('excludes denied tables from getAllTables', function () {
        config()->set('sql-agent.sql.denied_tables', ['test_users']);

        $introspector = app(SchemaIntrospector::class);

        $tables = $introspector->getAllTables();
        $tableNames = $tables->pluck('tableName')->all();

        expect($tableNames)->not->toContain('test_users');
        expect($tableNames)->toContain('test_orders');
    });
});
