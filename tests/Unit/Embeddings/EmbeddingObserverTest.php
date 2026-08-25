<?php

use Illuminate\Database\Eloquent\Model;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Embeddings\EmbeddingObserver;
use Knobik\SqlAgent\Search\Drivers\PgvectorSearchDriver;

beforeEach(function () {
    $this->driver = Mockery::mock(PgvectorSearchDriver::class);
    $this->observer = new EmbeddingObserver($this->driver);
});

test('created indexes the model', function () {
    $model = Mockery::mock(Model::class, Searchable::class);
    $model->shouldReceive('getSearchableColumns')->andReturn(['title']);
    $model->shouldReceive('toSearchableArray')->andReturn(['title' => 'test']);

    $this->driver->shouldReceive('index')->with($model)->once();

    $this->observer->created($model);
});

test('created skips non-searchable models', function () {
    $model = Mockery::mock(Model::class);

    $this->driver->shouldNotReceive('index');

    $this->observer->created($model);
});

test('created catches exceptions silently', function () {
    $model = Mockery::mock(Model::class, Searchable::class);
    $model->shouldReceive('getSearchableColumns')->andReturn(['title']);

    $this->driver->shouldReceive('index')->andThrow(new RuntimeException('Embedding failed'));

    // Should not throw
    $this->observer->created($model);

    expect(true)->toBeTrue();
});

test('updated re-indexes when searchable columns changed', function () {
    $model = Mockery::mock(Model::class, Searchable::class);
    $model->shouldReceive('getSearchableColumns')->andReturn(['title', 'description']);
    $model->shouldReceive('getDirty')->andReturn(['title' => 'new title']);

    $this->driver->shouldReceive('index')->with($model)->once();

    $this->observer->updated($model);
});

test('updated skips when no searchable columns changed', function () {
    $model = Mockery::mock(Model::class, Searchable::class);
    $model->shouldReceive('getSearchableColumns')->andReturn(['title', 'description']);
    $model->shouldReceive('getDirty')->andReturn(['sql' => 'SELECT 1']);

    $this->driver->shouldNotReceive('index');

    $this->observer->updated($model);
});

test('deleted removes the embedding', function () {
    $model = Mockery::mock(Model::class);

    $this->driver->shouldReceive('delete')->with($model)->once();

    $this->observer->deleted($model);
});

test('deleted catches exceptions silently', function () {
    $model = Mockery::mock(Model::class);

    $this->driver->shouldReceive('delete')->andThrow(new RuntimeException('Delete failed'));

    // Should not throw
    $this->observer->deleted($model);

    expect(true)->toBeTrue();
});
