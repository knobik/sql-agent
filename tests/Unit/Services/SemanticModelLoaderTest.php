<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\SemanticModelLoader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

    config()->set('sql-agent.knowledge.source', 'files');

    // Create a temporary knowledge directory with table files
    $this->knowledgePath = sys_get_temp_dir().'/sql-agent-test-knowledge-'.uniqid();
    $tablesPath = $this->knowledgePath.'/tables';
    File::makeDirectory($tablesPath, 0755, true);

    File::put("{$tablesPath}/users.json", json_encode([
        'table' => 'users',
        'description' => 'User accounts',
        'columns' => [
            'id' => 'integer, Primary key',
            'name' => 'string',
            'email' => 'string',
            'password' => 'string, hashed',
        ],
        'relationships' => [],
    ]));

    File::put("{$tablesPath}/orders.json", json_encode([
        'table' => 'orders',
        'description' => 'Customer orders',
        'columns' => [
            'id' => 'integer, Primary key',
            'user_id' => 'integer, FK → users.id',
            'total' => 'decimal',
        ],
        'relationships' => ['belongsTo users via user_id → users.id'],
    ]));

    File::put("{$tablesPath}/secrets.json", json_encode([
        'table' => 'secrets',
        'description' => 'Secret data',
        'columns' => [
            'id' => 'integer',
            'api_key' => 'string',
        ],
        'relationships' => [],
    ]));

    config()->set('sql-agent.knowledge.path', $this->knowledgePath);
});

afterEach(function () {
    File::deleteDirectory($this->knowledgePath);
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
