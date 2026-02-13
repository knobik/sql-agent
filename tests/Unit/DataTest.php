<?php

use Knobik\SqlAgent\Data\BusinessRuleData;
use Knobik\SqlAgent\Data\Context;
use Knobik\SqlAgent\Data\QueryPatternData;
use Knobik\SqlAgent\Data\TableSchema;
use Knobik\SqlAgent\Enums\BusinessRuleType;

describe('TableSchema', function () {
    it('can be created', function () {
        $schema = new TableSchema(
            tableName: 'users',
            description: 'User accounts',
            columns: [
                'id' => 'Primary key, bigint',
                'name' => "User's full name, varchar",
            ],
            relationships: [
                'Has many posts (posts.user_id -> users.id)',
            ],
        );

        expect($schema->tableName)->toBe('users');
        expect($schema->columns)->toHaveCount(2);
        expect($schema->relationships)->toHaveCount(1);
    });

    it('generates prompt string', function () {
        $schema = new TableSchema(
            tableName: 'users',
            description: 'User accounts',
            columns: [
                'id' => 'Primary key, bigint',
            ],
            dataQualityNotes: ['Email is lowercase'],
        );

        $prompt = $schema->toPromptString();

        expect($prompt)->toContain('## Table: users');
        expect($prompt)->toContain('User accounts');
        expect($prompt)->toContain('### Columns:');
        expect($prompt)->toContain('- id: Primary key, bigint');
        expect($prompt)->toContain('### Data Quality Notes:');
        expect($prompt)->toContain('Email is lowercase');
    });

    it('can get column names', function () {
        $schema = new TableSchema(
            tableName: 'users',
            columns: [
                'id' => 'Primary key, bigint',
                'name' => "User's full name, varchar",
            ],
        );

        expect($schema->getColumnNames())->toBe(['id', 'name']);
    });

    it('can check if column exists', function () {
        $schema = new TableSchema(
            tableName: 'users',
            columns: [
                'id' => 'Primary key, bigint',
            ],
        );

        expect($schema->hasColumn('id'))->toBeTrue();
        expect($schema->hasColumn('email'))->toBeFalse();
    });

    it('can get column description', function () {
        $schema = new TableSchema(
            tableName: 'users',
            columns: [
                'id' => 'Primary key, bigint',
                'name' => "User's full name",
            ],
        );

        expect($schema->getColumn('id'))->toBe('Primary key, bigint');
        expect($schema->getColumn('name'))->toBe("User's full name");
        expect($schema->getColumn('email'))->toBeNull();
    });

    it('renders relationships in prompt', function () {
        $schema = new TableSchema(
            tableName: 'users',
            columns: [
                'id' => 'Primary key',
            ],
            relationships: [
                'Has many posts (posts.user_id -> users.id)',
                'Has many comments (comments.user_id -> users.id)',
            ],
        );

        $prompt = $schema->toPromptString();

        expect($prompt)->toContain('### Relationships:');
        expect($prompt)->toContain('- Has many posts (posts.user_id -> users.id)');
        expect($prompt)->toContain('- Has many comments (comments.user_id -> users.id)');
    });

    it('renders use cases in prompt', function () {
        $schema = new TableSchema(
            tableName: 'users',
            columns: [
                'id' => 'Primary key',
            ],
            useCases: ['User authentication', 'Profile management'],
        );

        $prompt = $schema->toPromptString();

        expect($prompt)->toContain('### Use Cases:');
        expect($prompt)->toContain('- User authentication');
        expect($prompt)->toContain('- Profile management');
    });
});

describe('BusinessRuleData', function () {
    it('can be created', function () {
        $rule = new BusinessRuleData(
            name: 'Active User',
            description: 'Logged in within 30 days',
            type: BusinessRuleType::Metric,
            calculation: 'WHERE last_login > NOW() - INTERVAL 30 DAY',
        );

        expect($rule->name)->toBe('Active User');
        expect($rule->isMetric())->toBeTrue();
    });

    it('generates prompt string for metric', function () {
        $rule = new BusinessRuleData(
            name: 'Active User',
            description: 'Logged in within 30 days',
            type: BusinessRuleType::Metric,
            table: 'users',
            calculation: 'WHERE last_login > NOW()',
        );

        $prompt = $rule->toPromptString();

        expect($prompt)->toContain('Active User');
        expect($prompt)->toContain('Table: users');
        expect($prompt)->toContain('Calculation:');
    });

    it('generates prompt string for gotcha', function () {
        $rule = new BusinessRuleData(
            name: 'Soft deletes',
            description: 'Users are soft deleted',
            type: BusinessRuleType::Gotcha,
            tablesAffected: ['users'],
            solution: 'Add WHERE deleted_at IS NULL',
        );

        $prompt = $rule->toPromptString();

        expect($prompt)->toContain('Soft deletes');
        expect($prompt)->toContain('Affected tables:');
        expect($prompt)->toContain('Solution:');
    });
});

describe('QueryPatternData', function () {
    it('can be created', function () {
        $pattern = new QueryPatternData(
            name: 'active_users',
            question: 'How many active users?',
            sql: 'SELECT COUNT(*) FROM users',
            summary: 'Count active users',
            tablesUsed: ['users'],
        );

        expect($pattern->name)->toBe('active_users');
        expect($pattern->tablesUsed)->toBe(['users']);
    });

    it('generates prompt string', function () {
        $pattern = new QueryPatternData(
            name: 'active_users',
            question: 'How many active users?',
            sql: 'SELECT COUNT(*) FROM users',
            summary: 'Count active users',
            tablesUsed: ['users'],
        );

        $prompt = $pattern->toPromptString();

        expect($prompt)->toContain('### active_users');
        expect($prompt)->toContain('**Question:**');
        expect($prompt)->toContain('```sql');
        expect($prompt)->toContain('Tables used: users');
    });

    it('can check if uses table', function () {
        $pattern = new QueryPatternData(
            name: 'test',
            question: 'Test',
            sql: 'SELECT',
            tablesUsed: ['users', 'posts'],
        );

        expect($pattern->usesTable('users'))->toBeTrue();
        expect($pattern->usesTable('comments'))->toBeFalse();
    });
});

describe('Context', function () {
    it('can be created', function () {
        $context = new Context(
            semanticModel: 'Table info',
            businessRules: 'Rules info',
            queryPatterns: collect(),
            learnings: collect(),

        );

        expect($context->semanticModel)->toBe('Table info');
        expect($context->businessRules)->toBe('Rules info');
    });

    it('generates prompt string', function () {
        $pattern = new QueryPatternData(
            name: 'test',
            question: 'Test?',
            sql: 'SELECT 1',
        );

        $context = new Context(
            semanticModel: 'Schema info here',
            businessRules: 'Rules here',
            queryPatterns: collect([$pattern]),
            learnings: collect([['title' => 'Learning 1', 'description' => 'Desc 1']]),
        );

        $prompt = $context->toPromptString();

        expect($prompt)->toContain('# DATABASE SCHEMA');
        expect($prompt)->toContain('Schema info here');
        expect($prompt)->toContain('# BUSINESS RULES');
        expect($prompt)->toContain('# SIMILAR QUERY EXAMPLES');
        expect($prompt)->toContain('# RELEVANT LEARNINGS');
    });

    it('detects empty context', function () {
        $emptyContext = new Context(
            semanticModel: '',
            businessRules: '',
            queryPatterns: collect(),
            learnings: collect(),

        );

        expect($emptyContext->isEmpty())->toBeTrue();

        $nonEmptyContext = new Context(
            semanticModel: 'Some schema',
            businessRules: '',
            queryPatterns: collect(),
            learnings: collect(),

        );

        expect($nonEmptyContext->isEmpty())->toBeFalse();
    });

    it('counts patterns and learnings', function () {
        $context = new Context(
            semanticModel: '',
            businessRules: '',
            queryPatterns: collect([
                new QueryPatternData(name: 'p1', question: 'q1', sql: 's1'),
                new QueryPatternData(name: 'p2', question: 'q2', sql: 's2'),
            ]),
            learnings: collect([
                ['title' => 'L1', 'description' => 'D1'],
            ]),

        );

        expect($context->getQueryPatternCount())->toBe(2);
        expect($context->getLearningCount())->toBe(1);
    });
});
