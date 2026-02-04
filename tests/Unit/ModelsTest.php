<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Enums\BusinessRuleType;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\BusinessRule;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Models\TableMetadata;
use Knobik\SqlAgent\Models\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

describe('TableMetadata', function () {
    it('can be created', function () {
        $table = TableMetadata::create([
            'connection' => 'default',
            'table_name' => 'users',
            'description' => 'User accounts table',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'description' => 'Primary key'],
                ['name' => 'name', 'type' => 'varchar', 'description' => 'User name'],
            ],
            'relationships' => [
                ['type' => 'hasMany', 'related_table' => 'posts', 'foreign_key' => 'user_id'],
            ],
            'data_quality_notes' => ['Email is always lowercase'],
        ]);

        expect($table->id)->toBeInt();
        expect($table->table_name)->toBe('users');
        expect($table->columns)->toBeArray();
        expect($table->columns)->toHaveCount(2);
        expect($table->relationships)->toBeArray();
        expect($table->data_quality_notes)->toBeArray();
    });

    it('can get column names', function () {
        $table = TableMetadata::create([
            'table_name' => 'users',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'email', 'type' => 'varchar'],
            ],
        ]);

        expect($table->getColumnNames())->toBe(['id', 'name', 'email']);
    });

    it('can get specific column', function () {
        $table = TableMetadata::create([
            'table_name' => 'users',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'description' => 'Primary key'],
                ['name' => 'name', 'type' => 'varchar', 'description' => 'User name'],
            ],
        ]);

        $column = $table->getColumn('name');
        expect($column)->toBeArray();
        expect($column['type'])->toBe('varchar');
        expect($column['description'])->toBe('User name');
    });

    it('scopes by connection', function () {
        TableMetadata::create(['connection' => 'mysql', 'table_name' => 'users']);
        TableMetadata::create(['connection' => 'pgsql', 'table_name' => 'users']);

        expect(TableMetadata::forConnection('mysql')->count())->toBe(1);
        expect(TableMetadata::forConnection('pgsql')->count())->toBe(1);
    });
});

describe('BusinessRule', function () {
    it('can be created with metric type', function () {
        $rule = BusinessRule::create([
            'type' => BusinessRuleType::Metric,
            'name' => 'Active User',
            'description' => 'User logged in within 30 days',
            'conditions' => ['calculation' => 'WHERE last_login > NOW() - INTERVAL 30 DAY'],
        ]);

        expect($rule->type)->toBe(BusinessRuleType::Metric);
        expect($rule->isMetric())->toBeTrue();
        expect($rule->isRule())->toBeFalse();
        expect($rule->isGotcha())->toBeFalse();
    });

    it('scopes by type', function () {
        BusinessRule::create(['type' => BusinessRuleType::Metric, 'name' => 'M1', 'description' => 'D1']);
        BusinessRule::create(['type' => BusinessRuleType::Rule, 'name' => 'R1', 'description' => 'D2']);
        BusinessRule::create(['type' => BusinessRuleType::Gotcha, 'name' => 'G1', 'description' => 'D3']);

        expect(BusinessRule::metrics()->count())->toBe(1);
        expect(BusinessRule::rules()->count())->toBe(1);
        expect(BusinessRule::gotchas()->count())->toBe(1);
    });

    it('can get tables affected', function () {
        $rule = BusinessRule::create([
            'type' => BusinessRuleType::Gotcha,
            'name' => 'Soft deletes',
            'description' => 'Check deleted_at',
            'conditions' => ['tables_affected' => ['users', 'posts']],
        ]);

        expect($rule->getTablesAffected())->toBe(['users', 'posts']);
    });
});

describe('QueryPattern', function () {
    it('implements Searchable interface', function () {
        $pattern = new QueryPattern;

        expect($pattern)->toBeInstanceOf(\Knobik\SqlAgent\Contracts\Searchable::class);
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

describe('Learning', function () {
    it('implements Searchable interface', function () {
        $learning = new Learning;

        expect($learning)->toBeInstanceOf(\Knobik\SqlAgent\Contracts\Searchable::class);
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

describe('Conversation', function () {
    it('can be created', function () {
        $conversation = Conversation::create([
            'title' => 'Test conversation',
            'connection' => 'default',
        ]);

        expect($conversation->title)->toBe('Test conversation');
    });

    it('has many messages', function () {
        $conversation = Conversation::create(['title' => 'Test']);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => 'Hello',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Hi there',
        ]);

        expect($conversation->messages()->count())->toBe(2);
    });

    it('can generate title from first message', function () {
        $conversation = Conversation::create([]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => 'How many users do we have?',
        ]);

        expect($conversation->generateTitle())->toBe('How many users do we have?');
    });
});

describe('Message', function () {
    it('can be created', function () {
        $conversation = Conversation::create([]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::User,
            'content' => 'Test message',
        ]);

        expect($message->role)->toBe(MessageRole::User);
        expect($message->isFromUser())->toBeTrue();
    });

    it('can have sql and results', function () {
        $conversation = Conversation::create([]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Assistant,
            'content' => 'Here are the results',
            'sql' => 'SELECT * FROM users',
            'results' => [['id' => 1, 'name' => 'John']],
        ]);

        expect($message->hasSql())->toBeTrue();
        expect($message->hasResults())->toBeTrue();
        expect($message->getResultCount())->toBe(1);
    });

    it('scopes by role', function () {
        $conversation = Conversation::create([]);
        Message::create(['conversation_id' => $conversation->id, 'role' => MessageRole::User, 'content' => 'Q']);
        Message::create(['conversation_id' => $conversation->id, 'role' => MessageRole::Assistant, 'content' => 'A']);

        expect(Message::fromUser()->count())->toBe(1);
        expect(Message::fromAssistant()->count())->toBe(1);
    });
});

describe('TestCase', function () {
    it('can be created', function () {
        $testCase = TestCase::create([
            'name' => 'user_count_test',
            'question' => 'How many users?',
            'expected_values' => ['count' => 10],
            'golden_sql' => 'SELECT COUNT(*) as count FROM users',
        ]);

        expect($testCase->name)->toBe('user_count_test');
        expect($testCase->hasGoldenSql())->toBeTrue();
        expect($testCase->hasExpectedValues())->toBeTrue();
    });

    it('can match expected values', function () {
        $testCase = TestCase::create([
            'name' => 'test',
            'question' => 'Test?',
            'expected_values' => ['count' => 10, 'name' => 'John'],
        ]);

        expect($testCase->matchesExpectedValues(['count' => 10, 'name' => 'John']))->toBeTrue();
        expect($testCase->matchesExpectedValues(['count' => 5, 'name' => 'John']))->toBeFalse();
    });
});
