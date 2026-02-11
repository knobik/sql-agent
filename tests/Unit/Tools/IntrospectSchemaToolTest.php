<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Services\ConnectionRegistry;
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

    it('excludes denied tables from listing via connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'description' => 'Main database.',
                'denied_tables' => ['test_users'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(connection: 'main'), true);

        expect($result['tables'])->not->toContain('test_users');
    });

    it('rejects introspection of denied table via connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'description' => 'Main database.',
                'denied_tables' => ['test_users'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect(fn () => $tool(table_name: 'test_users', connection: 'main'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('hides columns from inspected table via connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'description' => 'Main database.',
                'hidden_columns' => [
                    'test_users' => ['email'],
                ],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(table_name: 'test_users', connection: 'main'), true);

        $columnNames = array_column($result['columns'], 'name');

        expect($columnNames)->toContain('id');
        expect($columnNames)->toContain('name');
        expect($columnNames)->not->toContain('email');
    });
});

describe('IntrospectSchemaTool multi-connection mode', function () {
    it('registers connection enum parameter when multi-mode is active', function () {
        config()->set('sql-agent.database.connections', [
            'crm' => [
                'connection' => 'testing',
                'label' => 'CRM DB',
                'description' => 'CRM data.',
            ],
            'analytics' => [
                'connection' => 'testing',
                'label' => 'Analytics DB',
                'description' => 'Analytics data.',
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect($tool->parameters())->toHaveKey('connection');
        expect($tool->parametersAsArray()['connection']['enum'])->toContain('crm');
        expect($tool->parametersAsArray()['connection']['enum'])->toContain('analytics');
    });

    it('lists tables on specified connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main DB',
                'description' => 'Main database.',
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(connection: 'main'), true);

        expect($result)->toHaveKey('tables');
        expect($result['tables'])->toContain('test_users');
    });

    it('inspects table on specified connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main DB',
                'description' => 'Main database.',
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(table_name: 'test_users', connection: 'main'), true);

        expect($result['table'])->toBe('test_users');
        expect($result['columns'])->not->toBeEmpty();
    });

    it('enforces per-connection denied tables in listing', function () {
        config()->set('sql-agent.database.connections', [
            'restricted' => [
                'connection' => 'testing',
                'label' => 'Restricted DB',
                'description' => 'Restricted.',
                'denied_tables' => ['test_users'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(connection: 'restricted'), true);

        expect($result['tables'])->not->toContain('test_users');
    });

    it('rejects introspection of per-connection denied table', function () {
        config()->set('sql-agent.database.connections', [
            'restricted' => [
                'connection' => 'testing',
                'label' => 'Restricted DB',
                'description' => 'Restricted.',
                'denied_tables' => ['test_users'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect(fn () => $tool(table_name: 'test_users', connection: 'restricted'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('hides per-connection columns from inspected table', function () {
        config()->set('sql-agent.database.connections', [
            'secured' => [
                'connection' => 'testing',
                'label' => 'Secured DB',
                'description' => 'Secured.',
                'hidden_columns' => [
                    'test_users' => ['email'],
                ],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(table_name: 'test_users', connection: 'secured'), true);

        $columnNames = array_column($result['columns'], 'name');

        expect($columnNames)->toContain('id');
        expect($columnNames)->toContain('name');
        expect($columnNames)->not->toContain('email');
    });

    it('uses per-connection rules instead of global rules', function () {
        // Global rules deny test_users, but per-connection rules allow everything
        config()->set('sql-agent.sql.denied_tables', ['test_users']);
        config()->set('sql-agent.database.connections', [
            'open' => [
                'connection' => 'testing',
                'label' => 'Open DB',
                'description' => 'No restrictions.',
                'allowed_tables' => [],
                'denied_tables' => [],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = json_decode($tool(connection: 'open'), true);

        expect($result['tables'])->toContain('test_users');
    });
});
