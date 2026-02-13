<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Tools\RunSqlTool;
use Prism\Prism\Tool;

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

describe('RunSqlTool', function () {
    it('extends Prism Tool', function () {
        $tool = new RunSqlTool;

        expect($tool)->toBeInstanceOf(Tool::class);
    });

    it('executes valid SELECT queries', function () {
        $tool = new RunSqlTool;

        $result = json_decode($tool('SELECT * FROM test_users'), true);

        expect($result['rows'])->toHaveCount(2);
        expect($result['row_count'])->toBe(2);
    });

    it('sets lastSql and lastResults on success', function () {
        $tool = new RunSqlTool;

        $tool('SELECT * FROM test_users');

        expect($tool->getLastSql())->toBe('SELECT * FROM test_users');
        expect($tool->getLastResults())->toHaveCount(2);
    });

    it('executes WITH statements', function () {
        $tool = new RunSqlTool;

        $result = json_decode($tool('WITH user_names AS (SELECT name FROM test_users) SELECT * FROM user_names'), true);

        expect($result['rows'])->toHaveCount(2);
    });

    it('rejects empty SQL', function () {
        $tool = new RunSqlTool;

        expect(fn () => $tool(''))->toThrow(RuntimeException::class, 'empty');
    });

    it('rejects INSERT statements', function () {
        $tool = new RunSqlTool;

        expect(fn () => $tool("INSERT INTO test_users (name) VALUES ('Test')"))->toThrow(RuntimeException::class, 'Only');
    });

    it('rejects DROP statements', function () {
        $tool = new RunSqlTool;

        expect(fn () => $tool('DROP TABLE test_users'))->toThrow(RuntimeException::class, 'Only');
    });

    it('rejects SELECT with DELETE keyword', function () {
        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM test_users; DELETE FROM test_users'))->toThrow(RuntimeException::class);
    });

    it('rejects UPDATE statements', function () {
        $tool = new RunSqlTool;

        expect(fn () => $tool("UPDATE test_users SET name = 'Test'"))->toThrow(RuntimeException::class);
    });

    it('has correct name and description', function () {
        $tool = new RunSqlTool;

        expect($tool->name())->toBe('run_sql');
        expect($tool->description())->toContain('Execute');
    });

    it('has correct parameters', function () {
        $tool = new RunSqlTool;

        expect($tool->hasParameters())->toBeTrue();
        expect($tool->parameters())->toHaveKey('sql');
        expect($tool->requiredParameters())->toContain('sql');
    });

    it('accumulates executedQueries across calls', function () {
        $tool = new RunSqlTool;

        $tool('SELECT * FROM test_users');
        $tool('SELECT name FROM test_users WHERE id = 1');

        expect($tool->getExecutedQueries())->toHaveCount(2);
        expect($tool->getExecutedQueries()[0]['sql'])->toBe('SELECT * FROM test_users');
        expect($tool->getExecutedQueries()[0]['connection'])->toBeNull();
        expect($tool->getExecutedQueries()[1]['sql'])->toBe('SELECT name FROM test_users WHERE id = 1');
    });

    it('can set and get question', function () {
        $tool = new RunSqlTool;

        $tool->setQuestion('How many users?');

        expect($tool->getQuestion())->toBe('How many users?');
    });

    it('rejects queries on denied tables via connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'description' => 'Main database.',
                'denied_tables' => ['test_users'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM test_users', 'main'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('rejects queries on tables not in allowed list via connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'description' => 'Main database.',
                'allowed_tables' => ['other_table'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM test_users', 'main'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('allows queries on allowed tables via connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'description' => 'Main database.',
                'allowed_tables' => ['test_users'],
                'denied_tables' => [],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        $result = json_decode($tool('SELECT * FROM test_users', 'main'), true);

        expect($result['rows'])->toHaveCount(2);
    });

    it('rejects queries with denied tables in JOIN via connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main',
                'description' => 'Main database.',
                'denied_tables' => ['test_users'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM orders JOIN test_users ON orders.user_id = test_users.id', 'main'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });
});

describe('RunSqlTool multi-connection mode', function () {
    it('registers connection enum parameter when multi-mode is active', function () {
        config()->set('sql-agent.database.connections', [
            'sales' => [
                'connection' => 'testing',
                'label' => 'Sales DB',
                'description' => 'Sales data.',
            ],
            'analytics' => [
                'connection' => 'testing',
                'label' => 'Analytics DB',
                'description' => 'Analytics data.',
            ],
        ]);

        // ConnectionRegistry is a singleton, so we need to flush the cached state
        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        expect($tool->parameters())->toHaveKey('connection');
        expect($tool->parametersAsArray()['connection']['enum'])->toContain('sales');
        expect($tool->parametersAsArray()['connection']['enum'])->toContain('analytics');
    });

    it('executes query on specified connection', function () {
        config()->set('sql-agent.database.connections', [
            'main' => [
                'connection' => 'testing',
                'label' => 'Main DB',
                'description' => 'Main database.',
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        $result = json_decode($tool('SELECT * FROM test_users', 'main'), true);

        expect($result['rows'])->toHaveCount(2);
        expect($result['row_count'])->toBe(2);
    });

    it('enforces per-connection denied tables', function () {
        config()->set('sql-agent.database.connections', [
            'restricted' => [
                'connection' => 'testing',
                'label' => 'Restricted DB',
                'description' => 'Restricted.',
                'denied_tables' => ['test_users'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM test_users', 'restricted'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('enforces per-connection allowed tables', function () {
        config()->set('sql-agent.database.connections', [
            'limited' => [
                'connection' => 'testing',
                'label' => 'Limited DB',
                'description' => 'Limited.',
                'allowed_tables' => ['other_table'],
            ],
        ]);

        app()->forgetInstance(ConnectionRegistry::class);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM test_users', 'limited'))
            ->toThrow(RuntimeException::class, 'Access denied');
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

        $tool = new RunSqlTool;

        $result = json_decode($tool('SELECT * FROM test_users', 'open'), true);

        expect($result['rows'])->toHaveCount(2);
    });
});
