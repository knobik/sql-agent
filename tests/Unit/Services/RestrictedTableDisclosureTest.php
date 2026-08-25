<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Models\TableMetadata;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Services\SemanticModelLoader;
use Knobik\SqlAgent\Services\TableAccessControl;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

    TableMetadata::create([
        'connection' => 'testing',
        'table_name' => 'orders',
        'description' => 'Purchase orders',
        'columns' => ['id' => 'integer', 'user_id' => 'integer', 'total' => 'decimal'],
        'relationships' => [
            'Belongs to users via user_id -> users.id',
            'Has many order_items (order_items.order_id -> orders.id)',
        ],
        'data_quality_notes' => [
            'Rows whose users record was purged keep a dangling user_id',
            'total is stored in cents',
        ],
    ]);

    TableMetadata::create([
        'connection' => 'testing',
        'table_name' => 'users',
        'description' => 'Account holders',
        'columns' => ['id' => 'integer', 'email' => 'string'],
        'relationships' => [],
    ]);

    TableMetadata::create([
        'connection' => 'testing',
        'table_name' => 'order_items',
        'description' => 'Line items',
        'columns' => ['id' => 'integer', 'order_id' => 'integer'],
        'relationships' => [],
    ]);

    config()->set('sql-agent.schema.mode', 'full');
});

function denyTables(array $denied): void
{
    config()->set('sql-agent.database.connections.default', [
        'connection' => 'testing',
        'label' => 'Database',
        'description' => 'Test database.',
        'denied_tables' => $denied,
    ]);
}

function allowTables(array $allowed): void
{
    config()->set('sql-agent.database.connections.default', [
        'connection' => 'testing',
        'label' => 'Database',
        'description' => 'Test database.',
        'allowed_tables' => $allowed,
    ]);
}

test('a denied table is not named by the relationships of a permitted table', function () {
    denyTables(['users']);

    $schema = app(SemanticModelLoader::class)->format('testing', 'default');

    expect($schema)->toContain('## Table: orders')
        ->and($schema)->not->toContain('## Table: users')
        ->and($schema)->not->toContain('Belongs to users')
        ->and($schema)->not->toContain('users.id');
});

test('a denied table is not named by the data quality notes of a permitted table', function () {
    denyTables(['users']);

    $schema = app(SemanticModelLoader::class)->format('testing', 'default');

    expect($schema)->not->toContain('dangling user_id')
        ->and($schema)->toContain('total is stored in cents');
});

test('relationships to permitted tables survive', function () {
    denyTables(['users']);

    $schema = app(SemanticModelLoader::class)->format('testing', 'default');

    expect($schema)->toContain('Has many order_items');
});

test('nothing is scrubbed when no restrictions are configured', function () {
    config()->set('sql-agent.database.connections.default', [
        'connection' => 'testing',
        'label' => 'Database',
        'description' => 'Test database.',
    ]);

    $schema = app(SemanticModelLoader::class)->format('testing', 'default');

    expect($schema)->toContain('Belongs to users')
        ->and($schema)->toContain('dangling user_id');
});

test('an allow list scrubs the tables it leaves out', function () {
    allowTables(['orders']);

    $schema = app(SemanticModelLoader::class)->format('testing', 'default');

    expect($schema)->toContain('## Table: orders')
        ->and($schema)->not->toContain('Belongs to users')
        ->and($schema)->not->toContain('Has many order_items');
});

test('a table name is matched on word boundaries', function () {
    // "users" must not strike "user_id" on its own, or every mention of a
    // foreign key column would disappear with the table.
    expect(app(TableAccessControl::class)->filterDescriptions(
        ['Keyed by user_id', 'Belongs to users'],
        ['users'],
    ))->toBe(['Keyed by user_id']);
});

test('disallowed tables combine the deny list with allow list exclusions', function () {
    allowTables(['orders']);

    $disallowed = app(TableAccessControl::class)
        ->getDisallowedTables(['orders', 'users', 'order_items'], 'default');

    expect($disallowed)->toContain('users')
        ->and($disallowed)->toContain('order_items')
        ->and($disallowed)->not->toContain('orders');
});

test('introspection hides foreign keys that point at a denied table', function () {
    denyTables(['users']);

    app('db')->connection('testing')->statement('CREATE TABLE users (id integer primary key, email text)');
    app('db')->connection('testing')->statement(
        'CREATE TABLE orders (id integer primary key, user_id integer references users(id), total integer)'
    );

    $schema = app(SchemaIntrospector::class)->introspectTable('orders', 'testing', 'default');

    expect($schema->tableName)->toBe('orders')
        ->and(implode(' ', $schema->relationships))->not->toContain('users')
        ->and(implode(' ', $schema->columns))->not->toContain('users');
});

test('introspection keeps foreign keys that point at a permitted table', function () {
    config()->set('sql-agent.database.connections.default', [
        'connection' => 'testing',
        'label' => 'Database',
        'description' => 'Test database.',
    ]);

    app('db')->connection('testing')->statement('CREATE TABLE users (id integer primary key, email text)');
    app('db')->connection('testing')->statement(
        'CREATE TABLE orders (id integer primary key, user_id integer references users(id), total integer)'
    );

    $schema = app(SchemaIntrospector::class)->introspectTable('orders', 'testing', 'default');

    expect(implode(' ', $schema->relationships))->toContain('users');
});
