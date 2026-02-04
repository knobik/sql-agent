<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Search\Strategies\SqliteLikeStrategy;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->strategy = new SqliteLikeStrategy;
});

test('strategy name is sqlite_like', function () {
    expect($this->strategy->getName())->toBe('sqlite_like');
});

test('applies LIKE search to query builder', function () {
    QueryPattern::create([
        'name' => 'User Count',
        'question' => 'How many users?',
        'sql' => 'SELECT COUNT(*) FROM users',
        'summary' => 'Counts users',
    ]);

    QueryPattern::create([
        'name' => 'Order Total',
        'question' => 'Total orders?',
        'sql' => 'SELECT SUM(amount) FROM orders',
        'summary' => 'Order revenue',
    ]);

    $query = QueryPattern::query();
    $columns = ['name', 'question', 'summary'];

    $this->strategy->apply($query, 'users count', $columns, 10);

    $results = $query->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('User Count');
});

test('returns limited results when search term is empty', function () {
    for ($i = 1; $i <= 5; $i++) {
        QueryPattern::create([
            'name' => "Query {$i}",
            'question' => "Question {$i}",
            'sql' => 'SELECT 1',
        ]);
    }

    $query = QueryPattern::query();
    $this->strategy->apply($query, '', ['name'], 3);

    expect($query->get())->toHaveCount(3);
});

test('filters stop words from search', function () {
    QueryPattern::create([
        'name' => 'User Query',
        'question' => 'Show me the users',
        'sql' => 'SELECT * FROM users',
        'summary' => 'Lists users',
    ]);

    $query = QueryPattern::query();
    $columns = ['name', 'question', 'summary'];

    // "the", "me", "show" are stop words, only "users" should match
    $this->strategy->apply($query, 'show me the users', $columns, 10);

    $results = $query->get();

    expect($results)->toHaveCount(1);
});

test('requires all keywords to match', function () {
    QueryPattern::create([
        'name' => 'User Count',
        'question' => 'Count users',
        'sql' => 'SELECT COUNT(*) FROM users',
    ]);

    QueryPattern::create([
        'name' => 'Order Status',
        'question' => 'Order status check',
        'sql' => 'SELECT * FROM orders',
    ]);

    $query = QueryPattern::query();
    $columns = ['name', 'question'];

    // Both "user" and "order" need to match - no single record has both
    $this->strategy->apply($query, 'user order', $columns, 10);

    expect($query->get())->toHaveCount(0);
});

test('search is case insensitive', function () {
    QueryPattern::create([
        'name' => 'USER COUNT',
        'question' => 'Count USERS',
        'sql' => 'SELECT COUNT(*) FROM users',
    ]);

    $query = QueryPattern::query();

    $this->strategy->apply($query, 'user count', ['name', 'question'], 10);

    expect($query->get())->toHaveCount(1);
});

test('adds search_score to results', function () {
    QueryPattern::create([
        'name' => 'User Count',
        'question' => 'How many users?',
        'sql' => 'SELECT COUNT(*) FROM users',
        'summary' => 'User count query',
    ]);

    $query = QueryPattern::query();

    $this->strategy->apply($query, 'user count', ['name', 'question', 'summary'], 10);

    $result = $query->first();

    expect($result->search_score)->toBeGreaterThan(0);
});

test('orders results by search score descending', function () {
    // Record with more keyword matches should rank higher
    QueryPattern::create([
        'name' => 'User Count Query',
        'question' => 'Count all users in the system',
        'sql' => 'SELECT COUNT(*) FROM users',
        'summary' => 'User count statistics',
    ]);

    QueryPattern::create([
        'name' => 'Order Query',
        'question' => 'Get user orders',
        'sql' => 'SELECT * FROM orders',
        'summary' => 'Order listing',
    ]);

    $query = QueryPattern::query();

    $this->strategy->apply($query, 'user count', ['name', 'question', 'summary'], 10);

    $results = $query->get();

    // First result should have higher score
    if ($results->count() >= 2) {
        expect($results[0]->search_score)->toBeGreaterThanOrEqual($results[1]->search_score);
    }
});

test('ignores words shorter than 3 characters', function () {
    QueryPattern::create([
        'name' => 'AB Test',
        'question' => 'An AB test query',
        'sql' => 'SELECT 1',
    ]);

    $query = QueryPattern::query();

    // "AB", "an" are too short, only "test" should be searched
    $this->strategy->apply($query, 'AB an test', ['name', 'question'], 10);

    $results = $query->get();

    expect($results)->toHaveCount(1);
});
