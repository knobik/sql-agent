<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        expect($tool->lastSql)->toBe('SELECT * FROM test_users');
        expect($tool->lastResults)->toHaveCount(2);
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

    it('can set connection', function () {
        $tool = new RunSqlTool;

        $result = $tool->setConnection('testing');

        expect($result)->toBe($tool);
    });

    it('can set and get question', function () {
        $tool = new RunSqlTool;

        $tool->setQuestion('How many users?');

        expect($tool->getQuestion())->toBe('How many users?');
    });

    it('rejects queries on denied tables', function () {
        config()->set('sql-agent.sql.denied_tables', ['test_users']);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM test_users'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('rejects queries on tables not in allowed list', function () {
        config()->set('sql-agent.sql.allowed_tables', ['other_table']);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM test_users'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });

    it('allows queries on allowed tables', function () {
        config()->set('sql-agent.sql.allowed_tables', ['test_users']);
        config()->set('sql-agent.sql.denied_tables', []);

        $tool = new RunSqlTool;

        $result = json_decode($tool('SELECT * FROM test_users'), true);

        expect($result['rows'])->toHaveCount(2);
    });

    it('rejects queries with denied tables in JOIN', function () {
        config()->set('sql-agent.sql.denied_tables', ['test_users']);

        $tool = new RunSqlTool;

        expect(fn () => $tool('SELECT * FROM orders JOIN test_users ON orders.user_id = test_users.id'))
            ->toThrow(RuntimeException::class, 'Access denied');
    });
});
