<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Models\QueryPattern;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

describe('QueryPattern', function () {
    it('implements Searchable interface', function () {
        $pattern = new QueryPattern;

        expect($pattern)->toBeInstanceOf(Searchable::class);
    });

    it('can be created', function () {
        $pattern = QueryPattern::create([
            'name' => 'active_users',
            'question' => 'How many active users?',
            'sql' => 'SELECT COUNT(*) FROM users WHERE active = 1',
            'summary' => 'Count active users',
            'tables_used' => ['users'],
        ]);

        expect($pattern->name)->toBe('active_users');
        expect($pattern->tables_used)->toBe(['users']);
    });

    it('can search by term', function () {
        QueryPattern::create(['name' => 'active_users', 'question' => 'Count active users', 'sql' => 'SELECT']);
        QueryPattern::create(['name' => 'posts_count', 'question' => 'Count posts', 'sql' => 'SELECT']);

        expect(QueryPattern::search('active')->count())->toBe(1);
        expect(QueryPattern::search('count')->count())->toBe(2);
    });

    it('returns searchable columns', function () {
        $pattern = new QueryPattern;

        expect($pattern->getSearchableColumns())->toBe(['name', 'question', 'summary']);
    });
});
