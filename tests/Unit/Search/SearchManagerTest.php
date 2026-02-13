<?php

use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Search\Drivers\DatabaseSearchDriver;
use Knobik\SqlAgent\Search\Drivers\NullSearchDriver;
use Knobik\SqlAgent\Search\Drivers\PgvectorSearchDriver;
use Knobik\SqlAgent\Search\SearchManager;

beforeEach(function () {
    $this->manager = app(SearchManager::class);
});

test('search manager is registered as singleton', function () {
    $manager1 = app(SearchManager::class);
    $manager2 = app(SearchManager::class);

    expect($manager1)->toBe($manager2);
});

test('search driver interface is bound', function () {
    $driver = app(SearchDriver::class);

    expect($driver)->toBeInstanceOf(SearchDriver::class);
});

test('default driver is database', function () {
    expect($this->manager->getDefaultDriver())->toBe('database');
});

test('can create database driver', function () {
    $driver = $this->manager->driver('database');

    expect($driver)->toBeInstanceOf(DatabaseSearchDriver::class);
});

test('can create null driver', function () {
    $driver = $this->manager->driver('null');

    expect($driver)->toBeInstanceOf(NullSearchDriver::class);
});

test('manager implements search driver interface', function () {
    expect($this->manager)->toBeInstanceOf(SearchDriver::class);
});

test('can change default driver via config', function () {
    config(['sql-agent.search.default' => 'null']);

    $manager = new SearchManager(app());

    expect($manager->getDefaultDriver())->toBe('null');
});

test('search method is proxied to current driver', function () {
    config(['sql-agent.search.default' => 'null']);

    $manager = new SearchManager(app());
    $results = $manager->search('test query', 'query_patterns', 5);

    // NullSearchDriver returns empty collection
    expect($results)->toBeEmpty();
});

test('searchMultiple method is proxied to current driver', function () {
    config(['sql-agent.search.default' => 'null']);

    $manager = new SearchManager(app());
    $results = $manager->searchMultiple('test query', ['query_patterns', 'learnings'], 5);

    expect($results)->toBeEmpty();
});

test('index method is proxied to current driver', function () {
    config(['sql-agent.search.default' => 'null']);

    $manager = new SearchManager(app());

    // NullSearchDriver index() is a no-op, should not throw
    expect(fn () => $manager->index((object) ['id' => 1]))->not->toThrow(Exception::class);
});

test('delete method is proxied to current driver', function () {
    config(['sql-agent.search.default' => 'null']);

    $manager = new SearchManager(app());

    // NullSearchDriver delete() is a no-op, should not throw
    expect(fn () => $manager->delete((object) ['id' => 1]))->not->toThrow(Exception::class);
});

test('can create pgvector driver', function () {
    $driver = $this->manager->driver('pgvector');

    expect($driver)->toBeInstanceOf(PgvectorSearchDriver::class);
});

test('getRegisteredIndexes returns default indexes for database driver', function () {
    $indexes = $this->manager->getRegisteredIndexes();

    expect($indexes)->toContain('query_patterns');
    expect($indexes)->toContain('learnings');
});

test('getRegisteredIndexes returns empty array for null driver', function () {
    config(['sql-agent.search.default' => 'null']);

    $manager = new SearchManager(app());
    $indexes = $manager->getRegisteredIndexes();

    expect($indexes)->toBe([]);
});

test('getCustomIndexes returns empty when no custom indexes configured', function () {
    $customIndexes = $this->manager->getCustomIndexes();

    expect($customIndexes)->toBe([]);
});

test('getCustomIndexes excludes built-in indexes', function () {
    config(['sql-agent.search.drivers.database.index_mapping' => [
        'my_custom_index' => \Knobik\SqlAgent\Models\QueryPattern::class,
    ]]);

    $manager = new SearchManager(app());
    $customIndexes = $manager->getCustomIndexes();

    expect($customIndexes)->toBe(['my_custom_index']);
});

test('getRegisteredIndexes includes custom indexes', function () {
    config(['sql-agent.search.drivers.database.index_mapping' => [
        'my_custom_index' => \Knobik\SqlAgent\Models\QueryPattern::class,
    ]]);

    $manager = new SearchManager(app());
    $indexes = $manager->getRegisteredIndexes();

    expect($indexes)->toContain('query_patterns');
    expect($indexes)->toContain('learnings');
    expect($indexes)->toContain('my_custom_index');
});
