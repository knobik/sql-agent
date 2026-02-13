<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Tools\SearchKnowledgeTool;
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

describe('SearchKnowledgeTool', function () {
    beforeEach(function () {
        // Create some test data
        QueryPattern::create([
            'name' => 'user_count',
            'question' => 'How many users are there?',
            'sql' => 'SELECT COUNT(*) FROM users',
            'summary' => 'Counts all users',
            'tables_used' => ['users'],
        ]);

        QueryPattern::create([
            'name' => 'order_total',
            'question' => 'What is the total order amount?',
            'sql' => 'SELECT SUM(total) FROM orders',
            'summary' => 'Sums all order totals',
            'tables_used' => ['orders'],
        ]);

        Learning::create([
            'title' => 'User table soft deletes',
            'description' => 'The users table uses soft deletes, check deleted_at column.',
            'category' => LearningCategory::BusinessLogic,
        ]);
    });

    it('extends Prism Tool', function () {
        $tool = app(SearchKnowledgeTool::class);

        expect($tool)->toBeInstanceOf(Tool::class);
    });

    it('searches query patterns', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = json_decode($tool(query: 'users', type: 'query_patterns'), true);

        expect($result['query_patterns'])->toHaveCount(1);
        expect($result['query_patterns'][0]['name'])->toBe('user_count');
    });

    it('searches learnings', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = json_decode($tool(query: 'soft deletes', type: 'learnings'), true);

        expect($result['learnings'])->toHaveCount(1);
        expect($result['learnings'][0]['title'])->toBe('User table soft deletes');
    });

    it('searches all by default', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = json_decode($tool(query: 'user'), true);

        expect($result)->toHaveKey('query_patterns');
        expect($result)->toHaveKey('learnings');
        expect($result['total_found'])->toBeGreaterThan(0);
    });

    it('rejects empty query', function () {
        $tool = app(SearchKnowledgeTool::class);

        expect(fn () => $tool(query: ''))->toThrow(RuntimeException::class, 'empty');
    });

    it('respects limit parameter', function () {
        // Add more patterns
        for ($i = 1; $i <= 10; $i++) {
            QueryPattern::create([
                'name' => "pattern_{$i}",
                'question' => "Question about users {$i}",
                'sql' => 'SELECT * FROM users',
                'summary' => "Pattern {$i}",
                'tables_used' => ['users'],
            ]);
        }

        $tool = app(SearchKnowledgeTool::class);

        $result = json_decode($tool(query: 'users', type: 'query_patterns', limit: 3), true);

        expect($result['query_patterns'])->toHaveCount(3);
    });

    it('has correct name', function () {
        $tool = app(SearchKnowledgeTool::class);

        expect($tool->name())->toBe('search_knowledge');
    });

    it('has correct parameters', function () {
        $tool = app(SearchKnowledgeTool::class);

        expect($tool->hasParameters())->toBeTrue();
        expect($tool->parameters())->toHaveKey('query');
        expect($tool->parameters())->toHaveKey('type');
        expect($tool->parameters())->toHaveKey('limit');
        expect($tool->requiredParameters())->toContain('query');
    });

    it('includes registered indexes in type enum', function () {
        $tool = app(SearchKnowledgeTool::class);

        $params = $tool->parameters();
        $typeSchema = $params['type'];

        // The enum schema should contain the registered indexes
        $json = json_encode($typeSchema);

        expect($json)->toContain('all');
        expect($json)->toContain('query_patterns');
        expect($json)->toContain('learnings');
    });

    it('falls back to all for invalid type', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = json_decode($tool(query: 'user', type: 'nonexistent_index'), true);

        expect($result)->toHaveKey('query_patterns');
        expect($result)->toHaveKey('learnings');
    });

    it('skips learnings when learning is disabled', function () {
        config(['sql-agent.learning.enabled' => false]);

        $tool = app(SearchKnowledgeTool::class);

        $result = json_decode($tool(query: 'user', type: 'all'), true);

        expect($result['learnings'])->toBeEmpty();
    });
});
