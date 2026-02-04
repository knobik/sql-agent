<?php

use Knobik\SqlAgent\Contracts\SearchDriver;
use Knobik\SqlAgent\Search\Drivers\DatabaseSearchDriver;
use Knobik\SqlAgent\Search\Drivers\NullSearchDriver;
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
