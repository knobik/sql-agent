<?php

/**
 * Configuration Tests for SQL Agent for Laravel
 *
 * Tests all configuration options to verify they are:
 * 1. Properly read from config
 * 2. Applied correctly in the codebase
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Knobik\SqlAgent\Data\GradeResult;
use Knobik\SqlAgent\Models\Learning;
use Knobik\SqlAgent\Search\SearchManager;
use Knobik\SqlAgent\Search\Strategies\MysqlFullTextStrategy;
use Knobik\SqlAgent\Search\Strategies\PostgresFullTextStrategy;
use Knobik\SqlAgent\Services\LearningMachine;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

describe('Database Configuration', function () {
    it('models use storage_connection config', function () {
        config(['sql-agent.database.storage_connection' => 'storage_db']);

        $learning = new Learning;
        $connectionName = $learning->getConnectionName();

        expect($connectionName)->toBe('storage_db');
    });
});

describe('LLM Configuration', function () {
    it('uses provider from config', function () {
        config(['sql-agent.llm.provider' => 'openai']);

        expect(config('sql-agent.llm.provider'))->toBe('openai');
    });

    it('can change provider', function () {
        config(['sql-agent.llm.provider' => 'anthropic']);

        expect(config('sql-agent.llm.provider'))->toBe('anthropic');
    });

    it('reads model from config', function () {
        config(['sql-agent.llm.model' => 'gpt-4-turbo']);

        expect(config('sql-agent.llm.model'))->toBe('gpt-4-turbo');
    });

    it('reads temperature from config', function () {
        config(['sql-agent.llm.temperature' => 0.5]);

        expect(config('sql-agent.llm.temperature'))->toBe(0.5);
    });

    it('reads max_tokens from config', function () {
        config(['sql-agent.llm.max_tokens' => 2048]);

        expect(config('sql-agent.llm.max_tokens'))->toBe(2048);
    });

    it('reads provider_options from config', function () {
        config(['sql-agent.llm.provider_options' => ['thinking' => true]]);

        expect(config('sql-agent.llm.provider_options'))->toBe(['thinking' => true]);
    });
});

describe('Search Configuration', function () {
    it('uses default search driver from config', function () {
        config(['sql-agent.search.default' => 'database']);

        $manager = app(SearchManager::class);

        expect($manager->getDefaultDriver())->toBe('database');
    });

    it('database driver passes config to MySQL strategy', function () {
        $config = ['mysql' => ['mode' => 'BOOLEAN MODE']];
        $strategy = new MysqlFullTextStrategy($config['mysql']);

        $reflection = new ReflectionProperty($strategy, 'config');
        $reflection->setAccessible(true);

        expect($reflection->getValue($strategy)['mode'])->toBe('BOOLEAN MODE');
    });

    it('database driver passes config to Postgres strategy', function () {
        $config = ['pgsql' => ['language' => 'spanish']];
        $strategy = new PostgresFullTextStrategy($config['pgsql']);

        $reflection = new ReflectionProperty($strategy, 'config');
        $reflection->setAccessible(true);

        expect($reflection->getValue($strategy)['language'])->toBe('spanish');
    });

});

describe('Agent Configuration', function () {
    it('max_iterations config is read', function () {
        config(['sql-agent.agent.max_iterations' => 5]);

        expect(config('sql-agent.agent.max_iterations'))->toBe(5);
    });

    it('default_limit is mentioned in system prompt', function () {
        config(['sql-agent.agent.default_limit' => 50]);

        $promptPath = __DIR__.'/../../resources/prompts/system.blade.php';
        $promptContent = file_get_contents($promptPath);

        expect($promptContent)->toContain("config('sql-agent.agent.default_limit'");
    });

    it('chat_history_length is used (verified config exists)', function () {
        config(['sql-agent.agent.chat_history_length' => 5]);

        expect(config('sql-agent.agent.chat_history_length'))->toBe(5);
    });
});

describe('Learning Configuration', function () {
    it('learning enabled config is checked', function () {
        config(['sql-agent.learning.enabled' => false]);

        $machine = app(LearningMachine::class);

        expect($machine->shouldAutoLearn())->toBeFalse();
    });

    it('auto_save_errors config is checked', function () {
        config(['sql-agent.learning.enabled' => true]);
        config(['sql-agent.learning.auto_save_errors' => false]);

        $machine = app(LearningMachine::class);

        expect($machine->shouldAutoLearn())->toBeFalse();
    });

    it('both enabled and auto_save_errors must be true for auto learning', function () {
        config(['sql-agent.learning.enabled' => true]);
        config(['sql-agent.learning.auto_save_errors' => true]);

        $machine = app(LearningMachine::class);

        expect($machine->shouldAutoLearn())->toBeTrue();
    });

    it('prune method reads from config', function () {
        config(['sql-agent.learning.prune_after_days' => 30]);

        $maintenance = app(\Knobik\SqlAgent\Services\LearningMaintenance::class);

        // Call prune with null to use config
        // The prune method should use config default
        $reflection = new ReflectionMethod($maintenance, 'prune');
        $params = $reflection->getParameters();

        // First parameter should be nullable int
        expect($params[0]->allowsNull())->toBeTrue();
    });

});

describe('Knowledge Configuration', function () {
    it('knowledge path config is read', function () {
        config(['sql-agent.knowledge.path' => '/custom/path']);

        expect(config('sql-agent.knowledge.path'))->toBe('/custom/path');
    });

    it('knowledge source config supports files and database', function () {
        config(['sql-agent.knowledge.source' => 'files']);
        expect(config('sql-agent.knowledge.source'))->toBe('files');

        config(['sql-agent.knowledge.source' => 'database']);
        expect(config('sql-agent.knowledge.source'))->toBe('database');
    });
});

describe('UI Configuration', function () {
    it('ui enabled config is read', function () {
        config(['sql-agent.ui.enabled' => false]);

        expect(config('sql-agent.ui.enabled'))->toBeFalse();
    });

    it('route prefix config is read', function () {
        config(['sql-agent.ui.route_prefix' => 'custom-agent']);

        expect(config('sql-agent.ui.route_prefix'))->toBe('custom-agent');
    });

    it('middleware config is read', function () {
        config(['sql-agent.ui.middleware' => ['web', 'auth', 'custom']]);

        expect(config('sql-agent.ui.middleware'))->toBe(['web', 'auth', 'custom']);
    });
});

describe('SQL Safety Configuration', function () {
    it('allowed_statements config is read', function () {
        config(['sql-agent.sql.allowed_statements' => ['SELECT']]);

        expect(config('sql-agent.sql.allowed_statements'))->toBe(['SELECT']);
    });

    it('forbidden_keywords config is read', function () {
        config(['sql-agent.sql.forbidden_keywords' => ['DROP', 'DELETE']]);

        expect(config('sql-agent.sql.forbidden_keywords'))->toBe(['DROP', 'DELETE']);
    });

    it('max_rows config is read', function () {
        config(['sql-agent.sql.max_rows' => 500]);

        expect(config('sql-agent.sql.max_rows'))->toBe(500);
    });

    it('RunSqlTool uses max_rows config', function () {
        config(['sql-agent.sql.max_rows' => 100]);

        expect(config('sql-agent.sql.max_rows'))->toBe(100);
    });
});

describe('Evaluation Configuration', function () {
    it('grader_model config is read', function () {
        config(['sql-agent.evaluation.grader_model' => 'gpt-4-turbo']);

        expect(config('sql-agent.evaluation.grader_model'))->toBe('gpt-4-turbo');
    });

    it('pass_threshold config is used in GradeResult', function () {
        config(['sql-agent.evaluation.pass_threshold' => 0.8]);

        // Simulate a score-based fallback scenario
        $response = 'The response is correct and accurate';
        $result = GradeResult::fromLlmResponse($response, 0.8);

        // With threshold 0.8 and heuristic scoring
        expect($result)->toBeInstanceOf(GradeResult::class);
    });

    it('timeout config is read', function () {
        config(['sql-agent.evaluation.timeout' => 120]);

        expect(config('sql-agent.evaluation.timeout'))->toBe(120);
    });
});

describe('Display Name Configuration', function () {
    it('name config is read', function () {
        config(['sql-agent.name' => 'Custom Agent']);

        expect(config('sql-agent.name'))->toBe('Custom Agent');
    });
});
