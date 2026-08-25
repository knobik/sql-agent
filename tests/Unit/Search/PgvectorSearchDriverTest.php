<?php

use Illuminate\Database\Eloquent\Model;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Embeddings\EmbeddingGenerator;
use Knobik\SqlAgent\Embeddings\TextSerializer;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Search\Drivers\PgvectorSearchDriver;

beforeEach(function () {
    $this->generator = Mockery::mock(EmbeddingGenerator::class);
    $this->serializer = new TextSerializer;
});

test('throws exception for unknown index', function () {
    $driver = new PgvectorSearchDriver($this->generator, $this->serializer, []);

    expect(fn () => $driver->search('test', 'unknown_index', 5))
        ->toThrow(RuntimeException::class, 'Unknown search index: unknown_index');
});

test('get index mapping returns default mapping', function () {
    $driver = new PgvectorSearchDriver($this->generator, $this->serializer, []);
    $mapping = $driver->getIndexMapping();

    expect($mapping)->toHaveKey('query_patterns');
    expect($mapping)->toHaveKey('learnings');
    expect($mapping['query_patterns'])->toBe(QueryPattern::class);
    expect($mapping['learnings'])->toBe(Learning::class);
});

test('can configure custom index mapping', function () {
    $driver = new PgvectorSearchDriver($this->generator, $this->serializer, [
        'index_mapping' => [
            'custom' => QueryPattern::class,
        ],
    ]);

    $mapping = $driver->getIndexMapping();

    expect($mapping)->toHaveKey('custom');
    expect($mapping['custom'])->toBe(QueryPattern::class);
});

test('index skips non-model objects', function () {
    $driver = new PgvectorSearchDriver($this->generator, $this->serializer, []);

    // Should not throw
    $driver->index((object) ['id' => 1]);

    expect(true)->toBeTrue();
});

test('delete skips non-model objects', function () {
    $driver = new PgvectorSearchDriver($this->generator, $this->serializer, []);

    // Should not throw
    $driver->delete((object) ['id' => 1]);

    expect(true)->toBeTrue();
});

test('index skips when serialized text is empty', function () {
    $serializer = Mockery::mock(TextSerializer::class);
    $serializer->shouldReceive('serialize')->andReturn('');

    $driver = new PgvectorSearchDriver($this->generator, $serializer, []);

    // Create a mock that implements both Model and Searchable
    $model = Mockery::mock(Model::class, Searchable::class);
    $model->shouldReceive('toSearchableArray')->andReturn([]);
    $model->shouldReceive('getSearchableColumns')->andReturn([]);

    // Should not call embed since text is empty
    $this->generator->shouldNotReceive('embed');

    $driver->index($model);
});
