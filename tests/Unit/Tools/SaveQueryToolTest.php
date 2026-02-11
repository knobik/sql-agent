<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Tools\SaveQueryTool;
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

describe('SaveQueryTool', function () {
    it('extends Prism Tool', function () {
        $tool = new SaveQueryTool;

        expect($tool)->toBeInstanceOf(Tool::class);
    });

    it('saves a query pattern', function () {
        $tool = new SaveQueryTool;

        $result = json_decode($tool(
            name: 'User Count',
            question: 'How many users are there?',
            sql: 'SELECT COUNT(*) as count FROM users',
            summary: 'Counts total users in the system',
            tables_used: ['users'],
        ), true);

        expect($result['success'])->toBeTrue();
        expect($result['pattern_id'])->toBeInt();
        expect($result['name'])->toBe('User Count');

        $pattern = QueryPattern::find($result['pattern_id']);
        expect($pattern->question)->toBe('How many users are there?');
        expect($pattern->tables_used)->toBe(['users']);
    });

    it('saves with data quality notes', function () {
        $tool = new SaveQueryTool;

        $result = json_decode($tool(
            name: 'Active Users',
            question: 'How many active users?',
            sql: 'SELECT COUNT(*) FROM users WHERE active = 1',
            summary: 'Counts active users',
            tables_used: ['users'],
            data_quality_notes: 'Some users may have NULL active status',
        ), true);

        expect($result['success'])->toBeTrue();

        $pattern = QueryPattern::find($result['pattern_id']);
        expect($pattern->data_quality_notes)->toBe('Some users may have NULL active status');
    });

    it('requires name', function () {
        $tool = new SaveQueryTool;

        expect(fn () => $tool(
            name: '',
            question: 'How many users?',
            sql: 'SELECT COUNT(*) FROM users',
            summary: 'Counts users',
            tables_used: ['users'],
        ))->toThrow(RuntimeException::class, 'Name is required');
    });

    it('requires question', function () {
        $tool = new SaveQueryTool;

        expect(fn () => $tool(
            name: 'User Count',
            question: '',
            sql: 'SELECT COUNT(*) FROM users',
            summary: 'Counts users',
            tables_used: ['users'],
        ))->toThrow(RuntimeException::class, 'Question is required');
    });

    it('requires sql', function () {
        $tool = new SaveQueryTool;

        expect(fn () => $tool(
            name: 'User Count',
            question: 'How many users?',
            sql: '',
            summary: 'Counts users',
            tables_used: ['users'],
        ))->toThrow(RuntimeException::class, 'SQL is required');
    });

    it('requires summary', function () {
        $tool = new SaveQueryTool;

        expect(fn () => $tool(
            name: 'User Count',
            question: 'How many users?',
            sql: 'SELECT COUNT(*) FROM users',
            summary: '',
            tables_used: ['users'],
        ))->toThrow(RuntimeException::class, 'Summary is required');
    });

    it('requires tables_used', function () {
        $tool = new SaveQueryTool;

        expect(fn () => $tool(
            name: 'User Count',
            question: 'How many users?',
            sql: 'SELECT COUNT(*) FROM users',
            summary: 'Counts users',
            tables_used: [],
        ))->toThrow(RuntimeException::class, 'Tables used');
    });

    it('validates name length', function () {
        $tool = new SaveQueryTool;

        expect(fn () => $tool(
            name: str_repeat('a', 101),
            question: 'How many users?',
            sql: 'SELECT COUNT(*) FROM users',
            summary: 'Counts users',
            tables_used: ['users'],
        ))->toThrow(RuntimeException::class, '100 characters');
    });

    it('only allows SELECT or WITH statements', function () {
        $tool = new SaveQueryTool;

        expect(fn () => $tool(
            name: 'Bad Query',
            question: 'Delete all users?',
            sql: 'DELETE FROM users',
            summary: 'Deletes users',
            tables_used: ['users'],
        ))->toThrow(RuntimeException::class, 'SELECT or WITH');
    });

    it('accepts WITH statements', function () {
        $tool = new SaveQueryTool;

        $result = json_decode($tool(
            name: 'Complex Query',
            question: 'Get users with orders?',
            sql: 'WITH user_orders AS (SELECT * FROM orders) SELECT * FROM users JOIN user_orders ON users.id = user_orders.user_id',
            summary: 'Gets users with orders',
            tables_used: ['users', 'orders'],
        ), true);

        expect($result['success'])->toBeTrue();
    });

    it('has correct name', function () {
        $tool = new SaveQueryTool;

        expect($tool->name())->toBe('save_validated_query');
    });

    it('has correct parameters', function () {
        $tool = new SaveQueryTool;

        expect($tool->hasParameters())->toBeTrue();
        expect($tool->parameters())->toHaveKey('name');
        expect($tool->parameters())->toHaveKey('question');
        expect($tool->parameters())->toHaveKey('sql');
        expect($tool->parameters())->toHaveKey('summary');
        expect($tool->parameters())->toHaveKey('tables_used');
        expect($tool->parameters())->toHaveKey('data_quality_notes');
        expect($tool->requiredParameters())->toContain('name');
        expect($tool->requiredParameters())->toContain('question');
        expect($tool->requiredParameters())->toContain('sql');
        expect($tool->requiredParameters())->toContain('summary');
        expect($tool->requiredParameters())->toContain('tables_used');
    });

});
