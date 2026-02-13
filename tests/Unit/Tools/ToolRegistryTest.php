<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Agent\ToolRegistry;
use Knobik\SqlAgent\Tools\RunSqlTool;
use Knobik\SqlAgent\Tools\SaveLearningTool;
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

describe('ToolRegistry', function () {
    it('can register a tool', function () {
        $registry = new ToolRegistry;
        $tool = new RunSqlTool;

        $registry->register($tool);

        expect($registry->has('run_sql'))->toBeTrue();
        expect($registry->get('run_sql'))->toBe($tool);
    });

    it('can register multiple tools', function () {
        $registry = new ToolRegistry;

        $registry->registerMany([
            new RunSqlTool,
            new SaveLearningTool,
        ]);

        expect($registry->has('run_sql'))->toBeTrue();
        expect($registry->has('save_learning'))->toBeTrue();
    });

    it('returns all tool names', function () {
        $registry = new ToolRegistry;
        $registry->registerMany([
            new RunSqlTool,
            new SaveLearningTool,
        ]);

        $names = $registry->names();

        expect($names)->toContain('run_sql');
        expect($names)->toContain('save_learning');
    });

    it('returns all tools', function () {
        $registry = new ToolRegistry;
        $registry->registerMany([
            new RunSqlTool,
            new SaveLearningTool,
        ]);

        $tools = $registry->all();

        expect($tools)->toHaveCount(2);
        expect($tools[0])->toBeInstanceOf(Tool::class);
    });

    it('throws exception for unregistered tool', function () {
        $registry = new ToolRegistry;

        $registry->get('non_existent');
    })->throws(InvalidArgumentException::class, "Tool 'non_existent' is not registered.");

    it('silently overwrites on register by default', function () {
        $registry = new ToolRegistry;
        $tool1 = new RunSqlTool;
        $tool2 = new RunSqlTool;

        $registry->register($tool1);
        $registry->register($tool2);

        expect($registry->get('run_sql'))->toBe($tool2);
    });

    it('throws when registering duplicate in strict mode', function () {
        $registry = new ToolRegistry;
        $registry->register(new RunSqlTool);

        $registry->registerStrict(new RunSqlTool);
    })->throws(InvalidArgumentException::class, "Tool 'run_sql' is already registered.");

    it('registers in strict mode when no duplicate exists', function () {
        $registry = new ToolRegistry;
        $registry->registerStrict(new RunSqlTool);

        expect($registry->has('run_sql'))->toBeTrue();
    });
});
