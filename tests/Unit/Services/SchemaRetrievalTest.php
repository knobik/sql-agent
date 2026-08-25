<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Models\TableMetadata;
use Knobik\SqlAgent\Services\ContextBuilder;
use Knobik\SqlAgent\Services\SemanticModelLoader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

    TableMetadata::create([
        'connection' => 'testing',
        'table_name' => 'orders',
        'description' => 'Purchase orders placed in the shop',
        'columns' => ['id' => 'integer', 'user_id' => 'integer', 'total' => 'decimal'],
        'relationships' => ['Belongs to users via user_id -> users.id'],
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
        'table_name' => 'audit_logs',
        'description' => 'Internal change trail',
        'columns' => ['id' => 'integer', 'action' => 'string'],
        'relationships' => [],
    ]);

    config()->set('sql-agent.schema.mode', 'rag');
    config()->set('sql-agent.schema.rag.limit', 5);
    config()->set('sql-agent.schema.rag.always_include', []);
    config()->set('sql-agent.schema.rag.expand_relationships', true);
    config()->set('sql-agent.schema.rag.expansion_depth', 1);
    config()->set('sql-agent.schema.rag.include_table_list', false);
});

test('only the tables matching the question are described', function () {
    config()->set('sql-agent.schema.rag.expand_relationships', false);

    $schema = app(SemanticModelLoader::class)->formatRelevant('orders', 'testing');

    expect($schema)->toContain('## Table: orders')
        ->and($schema)->not->toContain('## Table: audit_logs')
        ->and($schema)->not->toContain('## Table: users');
});

test('relationship targets are pulled in alongside the matched tables', function () {
    $schema = app(SemanticModelLoader::class)->formatRelevant('orders', 'testing');

    expect($schema)->toContain('## Table: orders')
        ->and($schema)->toContain('## Table: users')
        ->and($schema)->not->toContain('## Table: audit_logs');
});

test('always_include tables are described regardless of the question', function () {
    config()->set('sql-agent.schema.rag.always_include', ['audit_logs']);

    $schema = app(SemanticModelLoader::class)->formatRelevant('orders', 'testing');

    expect($schema)->toContain('## Table: audit_logs');
});

test('the omitted tables are listed by name so the agent knows they exist', function () {
    config()->set('sql-agent.schema.rag.include_table_list', true);
    config()->set('sql-agent.schema.rag.expand_relationships', false);

    $schema = app(SemanticModelLoader::class)->formatRelevant('orders', 'testing');

    expect($schema)->toContain('Other tables on this connection')
        ->and($schema)->toContain('introspect_schema')
        ->and($schema)->toContain('audit_logs')
        ->and($schema)->not->toContain('## Table: audit_logs');
});

test('an unmatched question falls back to the full semantic model', function () {
    $schema = app(SemanticModelLoader::class)->formatRelevant('quarterly headcount forecast', 'testing');

    expect($schema)->toContain('## Table: orders')
        ->and($schema)->toContain('## Table: users')
        ->and($schema)->toContain('## Table: audit_logs');
});

test('a failing search driver falls back to the full semantic model', function () {
    config()->set('sql-agent.search.default', 'pgvector');
    config()->set('sql-agent.search.drivers.pgvector.connection', null);

    $schema = app(SemanticModelLoader::class)->formatRelevant('orders', 'testing');

    expect($schema)->toContain('## Table: orders')
        ->and($schema)->toContain('## Table: audit_logs');
});

test('tables the access control denies are never retrieved', function () {
    config()->set('sql-agent.database.connections.default', [
        'connection' => 'testing',
        'label' => 'Database',
        'description' => 'Test database.',
        'denied_tables' => ['users'],
    ]);
    config()->set('sql-agent.schema.rag.include_table_list', true);

    $schema = app(SemanticModelLoader::class)->formatRelevant('orders', 'testing', 'default');

    $omittedList = substr($schema, (int) strpos($schema, 'Other tables on this connection'));

    // Note: relationship prose on a permitted table can still name a denied
    // table. That predates retrieval and applies to the full model too.
    expect($schema)->toContain('## Table: orders')
        ->and($schema)->not->toContain('## Table: users')
        ->and($omittedList)->toContain('audit_logs')
        ->and($omittedList)->not->toContain('users');
});

test('full mode describes every table regardless of the question', function () {
    config()->set('sql-agent.schema.mode', 'full');
    config()->set('sql-agent.database.connections.default', [
        'connection' => 'testing',
        'label' => 'Database',
        'description' => 'Test database.',
    ]);

    $context = app(ContextBuilder::class)->build('orders');

    expect($context->semanticModel)->toContain('## Table: audit_logs')
        ->and($context->semanticModelIsDynamic)->toBeFalse()
        ->and($context->toStaticPromptString())->toContain('DATABASE SCHEMA');
});

test('rag mode retrieves the schema and marks it as question-dependent', function () {
    config()->set('sql-agent.database.connections.default', [
        'connection' => 'testing',
        'label' => 'Database',
        'description' => 'Test database.',
    ]);

    $context = app(ContextBuilder::class)->build('orders');

    expect($context->semanticModel)->toContain('## Table: orders')
        ->and($context->semanticModel)->not->toContain('## Table: audit_logs')
        ->and($context->semanticModelIsDynamic)->toBeTrue()
        ->and($context->toDynamicPromptString())->toContain('DATABASE SCHEMA');
});

test('table metadata is not leaked into the additional knowledge section', function () {
    expect(app(\Knobik\SqlAgent\Search\SearchManager::class)->getCustomIndexes())
        ->not->toContain('table_metadata');
});

test('table metadata is searchable by name and description', function () {
    $results = app(\Knobik\SqlAgent\Search\SearchManager::class)
        ->search('orders', 'table_metadata', 5);

    expect($results)->toHaveCount(1)
        ->and($results->first()->model->table_name)->toBe('orders');
});

test('search_knowledge leaves table metadata out of an all search', function () {
    $results = json_decode(app(\Knobik\SqlAgent\Tools\SearchKnowledgeTool::class)('orders'), true);

    expect($results)->not->toHaveKey('table_metadata')
        ->and($results)->toHaveKey('query_patterns');
});

test('search_knowledge returns table metadata when asked for it', function () {
    $results = json_decode(
        app(\Knobik\SqlAgent\Tools\SearchKnowledgeTool::class)('orders', 'table_metadata'),
        true,
    );

    expect($results['table_metadata'])->toHaveCount(1)
        ->and($results['table_metadata'][0]['table'])->toBe('orders')
        ->and($results['table_metadata'][0]['columns'])->toContain('total (decimal)');
});
