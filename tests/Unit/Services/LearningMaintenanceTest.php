<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Services\LearningMaintenance;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
    $this->maintenance = app(LearningMaintenance::class);
});

function createLearningAt(array $attributes, Carbon $createdAt): Learning
{
    $learning = Learning::create($attributes);
    $learning->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $learning->fresh();
}

describe('prune', function () {
    it('removes old learnings', function () {
        createLearningAt([
            'title' => 'Old',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
        ], now()->subDays(100));

        createLearningAt([
            'title' => 'Recent',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
        ], now()->subDays(5));

        $removed = $this->maintenance->prune(daysOld: 30);

        expect($removed)->toBe(1);
        expect(Learning::count())->toBe(1);
        expect(Learning::first()->title)->toBe('Recent');
    });

    it('uses config default for days', function () {
        config(['sql-agent.learning.prune_after_days' => 10]);

        createLearningAt([
            'title' => 'Semi-old',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
        ], now()->subDays(15));

        $removed = $this->maintenance->prune();

        expect($removed)->toBe(1);
    });

    it('keeps used learnings when keepUsed is true', function () {
        createLearningAt([
            'title' => 'Used',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
            'metadata' => ['last_used_at' => now()->toIso8601String()],
        ], now()->subDays(100));

        $removed = $this->maintenance->prune(daysOld: 30, keepUsed: true);

        expect($removed)->toBe(0);
        expect(Learning::count())->toBe(1);
    });

    it('removes used learnings when keepUsed is false', function () {
        createLearningAt([
            'title' => 'Used',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
            'metadata' => ['last_used_at' => now()->toIso8601String()],
        ], now()->subDays(100));

        $removed = $this->maintenance->prune(daysOld: 30, keepUsed: false);

        expect($removed)->toBe(1);
    });
});

describe('findDuplicates', function () {
    it('finds learnings with duplicate titles', function () {
        Learning::create([
            'title' => 'Duplicate Title',
            'description' => 'desc 1',
            'category' => LearningCategory::SchemaFix,
        ]);
        Learning::create([
            'title' => 'Duplicate Title',
            'description' => 'desc 2',
            'category' => LearningCategory::SchemaFix,
        ]);
        Learning::create([
            'title' => 'Unique Title',
            'description' => 'desc 3',
            'category' => LearningCategory::SchemaFix,
        ]);

        $duplicates = $this->maintenance->findDuplicates();

        expect($duplicates)->toHaveCount(1);
        expect($duplicates->first()->description)->toBe('desc 2');
    });

    it('returns empty when no duplicates', function () {
        Learning::create([
            'title' => 'Unique 1',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
        ]);
        Learning::create([
            'title' => 'Unique 2',
            'description' => 'desc',
            'category' => LearningCategory::QueryPattern,
        ]);

        $duplicates = $this->maintenance->findDuplicates();

        expect($duplicates)->toBeEmpty();
    });
});

describe('removeDuplicates', function () {
    it('removes duplicate learnings and returns count', function () {
        Learning::create([
            'title' => 'Dup',
            'description' => 'first',
            'category' => LearningCategory::SchemaFix,
        ]);
        Learning::create([
            'title' => 'Dup',
            'description' => 'second',
            'category' => LearningCategory::SchemaFix,
        ]);
        Learning::create([
            'title' => 'Dup',
            'description' => 'third',
            'category' => LearningCategory::SchemaFix,
        ]);

        $removed = $this->maintenance->removeDuplicates();

        expect($removed)->toBe(2);
        expect(Learning::count())->toBe(1);
        expect(Learning::first()->description)->toBe('first');
    });
});

describe('getStats', function () {
    it('returns correct statistics', function () {
        Learning::create([
            'title' => 'Schema Fix',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
            'metadata' => ['source' => 'auto_learned'],
        ]);
        Learning::create([
            'title' => 'Query Pattern',
            'description' => 'desc',
            'category' => LearningCategory::QueryPattern,
        ]);
        createLearningAt([
            'title' => 'Old Learning',
            'description' => 'desc',
            'category' => LearningCategory::SchemaFix,
        ], now()->subDays(30));

        $stats = $this->maintenance->getStats();

        expect($stats['total'])->toBe(3);
        expect($stats['by_category']['schema_fix'])->toBe(2);
        expect($stats['by_category']['query_pattern'])->toBe(1);
        expect($stats['recent_7_days'])->toBe(2);
        expect($stats['auto_learned'])->toBe(1);
        expect($stats['manual'])->toBe(2);
    });

    it('returns zeros for empty database', function () {
        $stats = $this->maintenance->getStats();

        expect($stats['total'])->toBe(0);
        expect($stats['auto_learned'])->toBe(0);
        expect($stats['manual'])->toBe(0);
    });
});
