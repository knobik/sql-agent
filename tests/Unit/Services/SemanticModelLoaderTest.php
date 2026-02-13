<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Models\TableMetadata;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\SemanticModelLoader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

    // Create test table metadata in the database
    TableMetadata::create([
        'connection' => 'default',
        'table_name' => 'users',
        'description' => 'User accounts',
        'columns' => [
            'id' => 'integer, Primary key',
            'name' => 'string',
            'email' => 'string',
            'password' => 'string, hashed',
        ],
        'relationships' => [],
    ]);

    TableMetadata::create([
        'connection' => 'default',
        'table_name' => 'orders',
        'description' => 'Customer orders',
        'columns' => [
            'id' => 'integer, Primary key',
            'user_id' => 'integer, FK → users.id',
            'total' => 'decimal',
        ],
        'relationships' => ['belongsTo users via user_id → users.id'],
    ]);

    TableMetadata::create([
        'connection' => 'default',
        'table_name' => 'secrets',
        'description' => 'Secret data',
        'columns' => [
            'id' => 'integer',
            'api_key' => 'string',
        ],
        'relationships' => [],
    ]);
});

describe('SemanticModelLoader with TableAccessControl', function () {
    it('excludes denied tables from loaded metadata', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'denied_tables' => ['secrets'],
            ],
        ]);
        app()->forgetInstance(ConnectionRegistry::class);

        $loader = app(SemanticModelLoader::class);

        $tables = $loader->load(connectionName: 'main');
        $tableNames = $tables->pluck('tableName')->all();

        expect($tableNames)->toContain('users');
        expect($tableNames)->toContain('orders');
        expect($tableNames)->not->toContain('secrets');
    });

    it('only includes allowed tables', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'allowed_tables' => ['users'],
            ],
        ]);
        app()->forgetInstance(ConnectionRegistry::class);

        $loader = app(SemanticModelLoader::class);

        $tables = $loader->load(connectionName: 'main');
        $tableNames = $tables->pluck('tableName')->all();

        expect($tableNames)->toBe(['users']);
    });

    it('hides columns from loaded metadata', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'hidden_columns' => [
                    'users' => ['password'],
                ],
            ],
        ]);
        app()->forgetInstance(ConnectionRegistry::class);

        $loader = app(SemanticModelLoader::class);

        $tables = $loader->load(connectionName: 'main');
        $usersTable = $tables->first(fn ($t) => $t->tableName === 'users');

        expect($usersTable)->not->toBeNull();
        expect($usersTable->getColumnNames())->toContain('id');
        expect($usersTable->getColumnNames())->toContain('name');
        expect($usersTable->getColumnNames())->toContain('email');
        expect($usersTable->getColumnNames())->not->toContain('password');
    });

    it('does not modify tables without hidden columns', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'hidden_columns' => [
                    'users' => ['password'],
                ],
            ],
        ]);
        app()->forgetInstance(ConnectionRegistry::class);

        $loader = app(SemanticModelLoader::class);

        $tables = $loader->load(connectionName: 'main');
        $ordersTable = $tables->first(fn ($t) => $t->tableName === 'orders');

        expect($ordersTable)->not->toBeNull();
        expect($ordersTable->getColumnNames())->toBe(['id', 'user_id', 'total']);
    });
});
