<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

describe('Learning', function () {
    it('implements Searchable interface', function () {
        $learning = new Learning;

        expect($learning)->toBeInstanceOf(Searchable::class);
    });

    it('can be created', function () {
        $learning = Learning::create([
            'title' => 'Type casting issue',
            'description' => 'UUID columns need explicit casting',
            'category' => LearningCategory::TypeError,
            'sql' => 'SELECT CAST(id AS CHAR) FROM users',
        ]);

        expect($learning->title)->toBe('Type casting issue');
        expect($learning->category)->toBe(LearningCategory::TypeError);
    });

    it('can scope by category', function () {
        Learning::create(['title' => 'L1', 'description' => 'D1', 'category' => LearningCategory::TypeError]);
        Learning::create(['title' => 'L2', 'description' => 'D2', 'category' => LearningCategory::SchemaFix]);

        expect(Learning::ofCategory(LearningCategory::TypeError)->count())->toBe(1);
    });

    it('can scope global learnings', function () {
        Learning::create(['title' => 'L1', 'description' => 'D1', 'user_id' => null]);
        Learning::create(['title' => 'L2', 'description' => 'D2', 'user_id' => 1]);

        expect(Learning::global()->count())->toBe(1);
    });
});
