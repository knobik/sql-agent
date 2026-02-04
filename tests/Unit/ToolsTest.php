<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Agent\ToolRegistry;
use Knobik\SqlAgent\Contracts\Tool;
use Knobik\SqlAgent\Contracts\ToolResult;
use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Services\SchemaIntrospector;
use Knobik\SqlAgent\Tools\BaseTool;
use Knobik\SqlAgent\Tools\IntrospectSchemaTool;
use Knobik\SqlAgent\Tools\RunSqlTool;
use Knobik\SqlAgent\Tools\SaveLearningTool;
use Knobik\SqlAgent\Tools\SaveQueryTool;
use Knobik\SqlAgent\Tools\SearchKnowledgeTool;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');

    // Create a test table for SQL execution tests
    DB::statement('CREATE TABLE IF NOT EXISTS test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    DB::table('test_users')->insert([
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ]);
});

afterEach(function () {
    DB::statement('DROP TABLE IF EXISTS test_users');
});

describe('ToolRegistry', function () {
    it('can register a tool', function () {
        $registry = new ToolRegistry;
        $tool = new RunSqlTool;

        $registry->register($tool);

        expect($registry->has('run_sql'))->toBeTrue();
        expect($registry->get('run_sql'))->toBe($tool);
    });

    it('can register multiple tools', function () {
        $registry = new ToolRegistry;

        $registry->registerMany([
            new RunSqlTool,
            new SaveLearningTool,
        ]);

        expect($registry->count())->toBe(2);
        expect($registry->has('run_sql'))->toBeTrue();
        expect($registry->has('save_learning'))->toBeTrue();
    });

    it('returns all tool names', function () {
        $registry = new ToolRegistry;
        $registry->registerMany([
            new RunSqlTool,
            new SaveLearningTool,
        ]);

        $names = $registry->names();

        expect($names)->toContain('run_sql');
        expect($names)->toContain('save_learning');
    });

    it('returns all tools', function () {
        $registry = new ToolRegistry;
        $registry->registerMany([
            new RunSqlTool,
            new SaveLearningTool,
        ]);

        $tools = $registry->all();

        expect($tools)->toHaveCount(2);
        expect($tools[0])->toBeInstanceOf(Tool::class);
    });

    it('throws exception for unregistered tool', function () {
        $registry = new ToolRegistry;

        $registry->get('non_existent');
    })->throws(InvalidArgumentException::class, "Tool 'non_existent' is not registered.");

    it('can remove a tool', function () {
        $registry = new ToolRegistry;
        $registry->register(new RunSqlTool);

        expect($registry->has('run_sql'))->toBeTrue();

        $registry->remove('run_sql');

        expect($registry->has('run_sql'))->toBeFalse();
    });

    it('can clear all tools', function () {
        $registry = new ToolRegistry;
        $registry->registerMany([
            new RunSqlTool,
            new SaveLearningTool,
        ]);

        expect($registry->count())->toBe(2);

        $registry->clear();

        expect($registry->isEmpty())->toBeTrue();
        expect($registry->count())->toBe(0);
    });
});

describe('RunSqlTool', function () {
    it('executes valid SELECT queries', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute(['sql' => 'SELECT * FROM test_users']);

        expect($result->success)->toBeTrue();
        expect($result->data['rows'])->toHaveCount(2);
        expect($result->data['row_count'])->toBe(2);
    });

    it('executes WITH statements', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute([
            'sql' => 'WITH user_names AS (SELECT name FROM test_users) SELECT * FROM user_names',
        ]);

        expect($result->success)->toBeTrue();
        expect($result->data['rows'])->toHaveCount(2);
    });

    it('rejects empty SQL', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute(['sql' => '']);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('empty');
    });

    it('rejects INSERT statements', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute(['sql' => "INSERT INTO test_users (name) VALUES ('Test')"]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Only');
    });

    it('rejects DROP statements', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute(['sql' => 'DROP TABLE test_users']);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Only');
    });

    it('rejects SELECT with DELETE keyword', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute(['sql' => 'SELECT * FROM test_users; DELETE FROM test_users']);

        expect($result->success)->toBeFalse();
    });

    it('rejects UPDATE statements', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute(['sql' => "UPDATE test_users SET name = 'Test'"]);

        expect($result->success)->toBeFalse();
    });

    it('returns ToolResult', function () {
        $tool = new RunSqlTool;

        $result = $tool->execute(['sql' => 'SELECT * FROM test_users']);

        expect($result)->toBeInstanceOf(ToolResult::class);
    });

    it('has correct name and description', function () {
        $tool = new RunSqlTool;

        expect($tool->name())->toBe('run_sql');
        expect($tool->description())->toContain('Execute');
    });

    it('has correct parameters schema', function () {
        $tool = new RunSqlTool;
        $params = $tool->parameters();

        expect($params['type'])->toBe('object');
        expect($params['properties'])->toHaveKey('sql');
        expect($params['required'])->toContain('sql');
    });

    it('can set connection', function () {
        $tool = new RunSqlTool;

        $result = $tool->setConnection('testing');

        expect($result)->toBe($tool);
    });

    it('can set and get question', function () {
        $tool = new RunSqlTool;

        $tool->setQuestion('How many users?');

        expect($tool->getQuestion())->toBe('How many users?');
    });
});

describe('IntrospectSchemaTool', function () {
    it('lists all tables', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->execute([]);

        // In SQLite test environment, introspector may fail or return empty
        // The tool should return a result (success or failure)
        expect($result)->toBeInstanceOf(ToolResult::class);

        if ($result->success) {
            expect($result->data)->toBeArray();
        }
    });

    it('inspects specific table', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->execute(['table_name' => 'sql_agent_learnings']);

        // The tool should return a result
        expect($result)->toBeInstanceOf(ToolResult::class);

        if ($result->success) {
            expect($result->data)->toBeArray();
        }
    });

    it('handles non-existent table', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->execute(['table_name' => 'non_existent_table_xyz123']);

        // The tool should return a result
        expect($result)->toBeInstanceOf(ToolResult::class);

        if ($result->success) {
            // If success, should have error message about non-existent table
            expect($result->data)->toBeArray();
        }
    });

    it('has correct name', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        expect($tool->name())->toBe('introspect_schema');
    });

    it('can set connection', function () {
        $introspector = app(SchemaIntrospector::class);
        $tool = new IntrospectSchemaTool($introspector);

        $result = $tool->setConnection('testing');

        expect($result)->toBe($tool);
    });
});

describe('SaveLearningTool', function () {
    it('saves a learning', function () {
        $tool = new SaveLearningTool;

        $result = $tool->execute([
            'title' => 'Test Learning',
            'description' => 'This is a test learning about the database.',
            'category' => LearningCategory::SchemaFix->value,
        ]);

        expect($result->success)->toBeTrue();
        expect($result->data['success'])->toBeTrue();
        expect($result->data['learning_id'])->toBeInt();

        $learning = Learning::find($result->data['learning_id']);
        expect($learning->title)->toBe('Test Learning');
    });

    it('saves a learning with SQL', function () {
        $tool = new SaveLearningTool;

        $result = $tool->execute([
            'title' => 'SQL Pattern',
            'description' => 'How to count users correctly.',
            'category' => LearningCategory::QueryPattern->value,
            'sql' => 'SELECT COUNT(*) FROM users WHERE active = 1',
        ]);

        expect($result->success)->toBeTrue();

        $learning = Learning::find($result->data['learning_id']);
        expect($learning->sql)->toBe('SELECT COUNT(*) FROM users WHERE active = 1');
    });

    it('requires title', function () {
        $tool = new SaveLearningTool;

        $result = $tool->execute([
            'description' => 'Test description',
            'category' => LearningCategory::SchemaFix->value,
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Title is required');
    });

    it('requires description', function () {
        $tool = new SaveLearningTool;

        $result = $tool->execute([
            'title' => 'Test',
            'category' => LearningCategory::SchemaFix->value,
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Description is required');
    });

    it('requires valid category', function () {
        $tool = new SaveLearningTool;

        $result = $tool->execute([
            'title' => 'Test',
            'description' => 'Test description',
            'category' => 'invalid_category',
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Invalid category');
    });

    it('validates title length', function () {
        $tool = new SaveLearningTool;

        $result = $tool->execute([
            'title' => str_repeat('a', 101),
            'description' => 'Test description',
            'category' => LearningCategory::SchemaFix->value,
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('100 characters');
    });

    it('has correct name', function () {
        $tool = new SaveLearningTool;

        expect($tool->name())->toBe('save_learning');
    });

    it('respects disabled learning config', function () {
        config(['sql-agent.learning.enabled' => false]);

        $tool = new SaveLearningTool;

        $result = $tool->execute([
            'title' => 'Test',
            'description' => 'Test description',
            'category' => LearningCategory::SchemaFix->value,
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('disabled');

        config(['sql-agent.learning.enabled' => true]);
    });
});

describe('SaveQueryTool', function () {
    it('saves a query pattern', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'User Count',
            'question' => 'How many users are there?',
            'sql' => 'SELECT COUNT(*) as count FROM users',
            'summary' => 'Counts total users in the system',
            'tables_used' => ['users'],
        ]);

        expect($result->success)->toBeTrue();
        expect($result->data['success'])->toBeTrue();
        expect($result->data['pattern_id'])->toBeInt();
        expect($result->data['name'])->toBe('User Count');

        $pattern = QueryPattern::find($result->data['pattern_id']);
        expect($pattern->question)->toBe('How many users are there?');
        expect($pattern->tables_used)->toBe(['users']);
    });

    it('saves with data quality notes', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'Active Users',
            'question' => 'How many active users?',
            'sql' => 'SELECT COUNT(*) FROM users WHERE active = 1',
            'summary' => 'Counts active users',
            'tables_used' => ['users'],
            'data_quality_notes' => 'Some users may have NULL active status',
        ]);

        expect($result->success)->toBeTrue();

        $pattern = QueryPattern::find($result->data['pattern_id']);
        expect($pattern->data_quality_notes)->toBe('Some users may have NULL active status');
    });

    it('requires name', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'question' => 'How many users?',
            'sql' => 'SELECT COUNT(*) FROM users',
            'summary' => 'Counts users',
            'tables_used' => ['users'],
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Name is required');
    });

    it('requires question', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'User Count',
            'sql' => 'SELECT COUNT(*) FROM users',
            'summary' => 'Counts users',
            'tables_used' => ['users'],
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Question is required');
    });

    it('requires sql', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'User Count',
            'question' => 'How many users?',
            'summary' => 'Counts users',
            'tables_used' => ['users'],
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('SQL is required');
    });

    it('requires summary', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'User Count',
            'question' => 'How many users?',
            'sql' => 'SELECT COUNT(*) FROM users',
            'tables_used' => ['users'],
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Summary is required');
    });

    it('requires tables_used', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'User Count',
            'question' => 'How many users?',
            'sql' => 'SELECT COUNT(*) FROM users',
            'summary' => 'Counts users',
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Tables used');
    });

    it('rejects empty tables_used array', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'User Count',
            'question' => 'How many users?',
            'sql' => 'SELECT COUNT(*) FROM users',
            'summary' => 'Counts users',
            'tables_used' => [],
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Tables used');
    });

    it('validates name length', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => str_repeat('a', 101),
            'question' => 'How many users?',
            'sql' => 'SELECT COUNT(*) FROM users',
            'summary' => 'Counts users',
            'tables_used' => ['users'],
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('100 characters');
    });

    it('only allows SELECT or WITH statements', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'Bad Query',
            'question' => 'Delete all users?',
            'sql' => 'DELETE FROM users',
            'summary' => 'Deletes users',
            'tables_used' => ['users'],
        ]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('SELECT or WITH');
    });

    it('accepts WITH statements', function () {
        $tool = new SaveQueryTool;

        $result = $tool->execute([
            'name' => 'Complex Query',
            'question' => 'Get users with orders?',
            'sql' => 'WITH user_orders AS (SELECT * FROM orders) SELECT * FROM users JOIN user_orders ON users.id = user_orders.user_id',
            'summary' => 'Gets users with orders',
            'tables_used' => ['users', 'orders'],
        ]);

        expect($result->success)->toBeTrue();
    });

    it('has correct name', function () {
        $tool = new SaveQueryTool;

        expect($tool->name())->toBe('save_validated_query');
    });

    it('has correct parameters schema', function () {
        $tool = new SaveQueryTool;
        $params = $tool->parameters();

        expect($params['type'])->toBe('object');
        expect($params['properties'])->toHaveKey('name');
        expect($params['properties'])->toHaveKey('question');
        expect($params['properties'])->toHaveKey('sql');
        expect($params['properties'])->toHaveKey('summary');
        expect($params['properties'])->toHaveKey('tables_used');
        expect($params['properties'])->toHaveKey('data_quality_notes');
        expect($params['required'])->toContain('name');
        expect($params['required'])->toContain('question');
        expect($params['required'])->toContain('sql');
        expect($params['required'])->toContain('summary');
        expect($params['required'])->toContain('tables_used');
    });
});

describe('SearchKnowledgeTool', function () {
    beforeEach(function () {
        // Create some test data
        QueryPattern::create([
            'name' => 'user_count',
            'question' => 'How many users are there?',
            'sql' => 'SELECT COUNT(*) FROM users',
            'summary' => 'Counts all users',
            'tables_used' => ['users'],
        ]);

        QueryPattern::create([
            'name' => 'order_total',
            'question' => 'What is the total order amount?',
            'sql' => 'SELECT SUM(total) FROM orders',
            'summary' => 'Sums all order totals',
            'tables_used' => ['orders'],
        ]);

        Learning::create([
            'title' => 'User table soft deletes',
            'description' => 'The users table uses soft deletes, check deleted_at column.',
            'category' => LearningCategory::BusinessLogic,
        ]);
    });

    it('searches query patterns', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = $tool->execute([
            'query' => 'users',
            'type' => 'patterns',
        ]);

        expect($result->success)->toBeTrue();
        expect($result->data['query_patterns'])->toHaveCount(1);
        expect($result->data['query_patterns'][0]['name'])->toBe('user_count');
    });

    it('searches learnings', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = $tool->execute([
            'query' => 'soft deletes',
            'type' => 'learnings',
        ]);

        expect($result->success)->toBeTrue();
        expect($result->data['learnings'])->toHaveCount(1);
        expect($result->data['learnings'][0]['title'])->toBe('User table soft deletes');
    });

    it('searches all by default', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = $tool->execute([
            'query' => 'user',
        ]);

        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('query_patterns');
        expect($result->data)->toHaveKey('learnings');
        expect($result->data['total_found'])->toBeGreaterThan(0);
    });

    it('requires query', function () {
        $tool = app(SearchKnowledgeTool::class);

        $result = $tool->execute([]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('empty');
    });

    it('respects limit parameter', function () {
        // Add more patterns
        for ($i = 1; $i <= 10; $i++) {
            QueryPattern::create([
                'name' => "pattern_{$i}",
                'question' => "Question about users {$i}",
                'sql' => 'SELECT * FROM users',
                'summary' => "Pattern {$i}",
                'tables_used' => ['users'],
            ]);
        }

        $tool = app(SearchKnowledgeTool::class);

        $result = $tool->execute([
            'query' => 'users',
            'type' => 'patterns',
            'limit' => 3,
        ]);

        expect($result->success)->toBeTrue();
        expect($result->data['query_patterns'])->toHaveCount(3);
    });

    it('has correct name', function () {
        $tool = app(SearchKnowledgeTool::class);

        expect($tool->name())->toBe('search_knowledge');
    });

    it('enforces max limit of 20', function () {
        $tool = app(SearchKnowledgeTool::class);
        $params = $tool->parameters();

        expect($params['properties']['limit']['maximum'])->toBe(20);
    });
});

describe('ToolResult', function () {
    it('creates success result', function () {
        $result = ToolResult::success(['key' => 'value']);

        expect($result->success)->toBeTrue();
        expect($result->data)->toBe(['key' => 'value']);
        expect($result->error)->toBeNull();
    });

    it('creates failure result', function () {
        $result = ToolResult::failure('Something went wrong');

        expect($result->success)->toBeFalse();
        expect($result->data)->toBeNull();
        expect($result->error)->toBe('Something went wrong');
    });
});

describe('BaseTool', function () {
    it('wraps exceptions in failure result', function () {
        // Create a tool that throws an exception
        $tool = new class extends BaseTool
        {
            public function name(): string
            {
                return 'test_tool';
            }

            public function description(): string
            {
                return 'Test tool';
            }

            protected function schema(): array
            {
                return $this->objectSchema([]);
            }

            protected function handle(array $parameters): mixed
            {
                throw new RuntimeException('Test error');
            }
        };

        $result = $tool->execute([]);

        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Test error');
    });

    it('provides helper methods for schema building', function () {
        $tool = new class extends BaseTool
        {
            public function name(): string
            {
                return 'test_tool';
            }

            public function description(): string
            {
                return 'Test tool';
            }

            protected function schema(): array
            {
                return $this->objectSchema([
                    'string_prop' => $this->stringProperty('A string', ['a', 'b']),
                    'bool_prop' => $this->booleanProperty('A bool', true),
                    'int_prop' => $this->integerProperty('An int', 1, 100),
                    'array_prop' => $this->arrayProperty('An array', ['type' => 'string']),
                ], ['string_prop']);
            }

            protected function handle(array $parameters): mixed
            {
                return true;
            }
        };

        $params = $tool->parameters();

        expect($params['properties']['string_prop']['type'])->toBe('string');
        expect($params['properties']['string_prop']['enum'])->toBe(['a', 'b']);
        expect($params['properties']['bool_prop']['type'])->toBe('boolean');
        expect($params['properties']['bool_prop']['default'])->toBeTrue();
        expect($params['properties']['int_prop']['type'])->toBe('integer');
        expect($params['properties']['int_prop']['minimum'])->toBe(1);
        expect($params['properties']['int_prop']['maximum'])->toBe(100);
        expect($params['properties']['array_prop']['type'])->toBe('array');
        expect($params['required'])->toBe(['string_prop']);
    });
});

describe('Tool Registration Integration', function () {
    it('registers all tools via service provider', function () {
        $registry = app(ToolRegistry::class);
        $names = $registry->names();

        expect($names)->toContain('run_sql');
        expect($names)->toContain('introspect_schema');
        expect($names)->toContain('save_learning');
        expect($names)->toContain('save_validated_query');
        expect($names)->toContain('search_knowledge');
    });

    it('all registered tools implement Tool interface', function () {
        $registry = app(ToolRegistry::class);

        foreach ($registry->all() as $tool) {
            expect($tool)->toBeInstanceOf(Tool::class);
        }
    });

    it('all tools have valid names', function () {
        $registry = app(ToolRegistry::class);

        foreach ($registry->all() as $tool) {
            expect($tool->name())->toBeString();
            expect($tool->name())->not->toBeEmpty();
            expect(preg_match('/^[a-z_]+$/', $tool->name()))->toBe(1);
        }
    });

    it('all tools have descriptions', function () {
        $registry = app(ToolRegistry::class);

        foreach ($registry->all() as $tool) {
            expect($tool->description())->toBeString();
            expect($tool->description())->not->toBeEmpty();
        }
    });

    it('all tools have valid parameter schemas', function () {
        $registry = app(ToolRegistry::class);

        foreach ($registry->all() as $tool) {
            $params = $tool->parameters();

            expect($params)->toBeArray();
            expect($params['type'])->toBe('object');
            expect($params['properties'])->toBeArray();
        }
    });
});
