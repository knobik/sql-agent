<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Services\LearningMachine;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
    $this->learningMachine = app(LearningMachine::class);
});

describe('save', function () {
    it('creates a learning entry', function () {
        $learning = $this->learningMachine->save(
            title: 'Test Learning',
            description: 'Test description',
            category: LearningCategory::SchemaFix,
            sql: 'SELECT * FROM users',
        );

        expect($learning)->toBeInstanceOf(Learning::class);
        expect($learning->title)->toBe('Test Learning');
        expect($learning->description)->toBe('Test description');
        expect($learning->category)->toBe(LearningCategory::SchemaFix);
        expect($learning->sql)->toBe('SELECT * FROM users');
    });

    it('stores metadata', function () {
        $learning = $this->learningMachine->save(
            title: 'Test',
            description: 'Desc',
            category: LearningCategory::QueryPattern,
            metadata: ['source' => 'test'],
        );

        expect($learning->metadata)->toBe(['source' => 'test']);
    });
});

describe('shouldAutoLearn', function () {
    it('returns true when both configs are enabled', function () {
        config([
            'sql-agent.learning.enabled' => true,
            'sql-agent.learning.auto_save_errors' => true,
        ]);

        expect($this->learningMachine->shouldAutoLearn())->toBeTrue();
    });

    it('returns false when learning is disabled', function () {
        config(['sql-agent.learning.enabled' => false]);

        expect($this->learningMachine->shouldAutoLearn())->toBeFalse();
    });

    it('returns false when auto save is disabled', function () {
        config(['sql-agent.learning.auto_save_errors' => false]);

        expect($this->learningMachine->shouldAutoLearn())->toBeFalse();
    });
});

describe('learnFromError', function () {
    it('creates learning from sql error', function () {
        config([
            'sql-agent.learning.enabled' => true,
            'sql-agent.learning.auto_save_errors' => true,
        ]);

        $learning = $this->learningMachine->learnFromError(
            sql: 'SELECT * FROM nonexistent_table',
            error: "Table 'nonexistent_table' doesn't exist",
            question: 'Show me all data',
        );

        expect($learning)->toBeInstanceOf(Learning::class);
        expect($learning->metadata['source'])->toBe('auto_learned');
        expect($learning->metadata['original_question'])->toBe('Show me all data');
    });

    it('returns null when auto learning is disabled', function () {
        config(['sql-agent.learning.enabled' => false]);

        $learning = $this->learningMachine->learnFromError(
            sql: 'SELECT 1',
            error: 'error',
            question: 'test',
        );

        expect($learning)->toBeNull();
    });

    it('skips when similar learning exists', function () {
        config([
            'sql-agent.learning.enabled' => true,
            'sql-agent.learning.auto_save_errors' => true,
        ]);

        // Create first learning
        $this->learningMachine->learnFromError(
            sql: 'SELECT * FROM missing',
            error: "Table 'missing' doesn't exist",
            question: 'test',
        );

        // Try to create similar
        $second = $this->learningMachine->learnFromError(
            sql: 'SELECT * FROM missing',
            error: "Table 'missing' doesn't exist",
            question: 'test again',
        );

        expect($second)->toBeNull();
    });
});

describe('findSimilar', function () {
    it('finds learnings with matching tables', function () {
        $this->learningMachine->save(
            title: 'Users query fix',
            description: 'Fix for users table',
            category: LearningCategory::SchemaFix,
            sql: 'SELECT * FROM users WHERE active = 1',
        );

        $similar = $this->learningMachine->findSimilar('SELECT name FROM users');

        expect($similar)->toHaveCount(1);
    });

    it('returns empty for sql without tables', function () {
        $similar = $this->learningMachine->findSimilar('SELECT 1');

        expect($similar)->toBeEmpty();
    });
});
