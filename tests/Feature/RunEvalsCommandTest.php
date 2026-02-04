<?php

use Knobik\SqlAgent\Contracts\Agent;
use Knobik\SqlAgent\Contracts\AgentResponse;
use Knobik\SqlAgent\Database\Seeders\TestCaseSeeder;
use Knobik\SqlAgent\Models\TestCase;

beforeEach(function () {
    // Set a fake API key before the service provider boots
    config(['sql-agent.llm.drivers.openai.api_key' => 'test-key-for-tests']);

    // Run migrations
    $this->artisan('migrate', ['--database' => 'testing']);
});

describe('RunEvalsCommand', function () {
    it('shows warning when no test cases exist', function () {
        $this->artisan('sql-agent:eval')
            ->expectsOutputToContain('No test cases found')
            ->assertFailed();
    });

    it('seeds test cases with --seed flag', function () {
        expect(TestCase::count())->toBe(0);

        // Mock the agent to prevent actual LLM calls
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('run')
            ->andReturn(new AgentResponse(answer: 'Test response'));

        $this->app->instance(Agent::class, $agent);

        $this->artisan('sql-agent:eval', ['--seed' => true])
            ->expectsOutputToContain('Seeding test cases')
            ->expectsOutputToContain('Test cases seeded successfully');

        expect(TestCase::count())->toBe(18);
    });

    it('validates category option', function () {
        // First seed test cases
        (new TestCaseSeeder)->run();

        $this->artisan('sql-agent:eval', ['--category' => 'invalid_category'])
            ->expectsOutputToContain('Invalid category')
            ->assertFailed();
    });

    it('filters by category', function () {
        // Seed test cases
        (new TestCaseSeeder)->run();

        // Mock the agent
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('run')
            ->andReturn(new AgentResponse(answer: 'Hamilton won with 11 races'));

        $this->app->instance(Agent::class, $agent);

        $this->artisan('sql-agent:eval', ['--category' => 'basic'])
            ->expectsOutputToContain('Category: basic');
    });

    it('outputs JSON when requested', function () {
        // Seed test cases
        (new TestCaseSeeder)->run();

        // Mock the agent
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('run')
            ->andReturn(new AgentResponse(answer: 'Hamilton won with 11 races'));

        $this->app->instance(Agent::class, $agent);

        // Run with JSON output - may fail tests but should output JSON format
        // The command returns exit code 1 when tests fail, so we just check the output contains JSON
        $this->artisan('sql-agent:eval', ['--json' => true, '--category' => 'basic'])
            ->expectsOutputToContain('summary');
    });

    it('generates HTML report when requested', function () {
        // Seed test cases
        (new TestCaseSeeder)->run();

        // Mock the agent
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('run')
            ->andReturn(new AgentResponse(answer: 'Hamilton won with 11 races'));

        $this->app->instance(Agent::class, $agent);

        $htmlPath = storage_path('app/test-eval-report.html');

        // Ensure directory exists
        if (! is_dir(dirname($htmlPath))) {
            mkdir(dirname($htmlPath), 0755, true);
        }

        $this->artisan('sql-agent:eval', ['--html' => $htmlPath])
            ->expectsOutputToContain('HTML report generated');

        expect(file_exists($htmlPath))->toBeTrue();

        // Clean up
        if (file_exists($htmlPath)) {
            unlink($htmlPath);
        }
    });

    it('runs with LLM grader mode', function () {
        // Seed test cases
        (new TestCaseSeeder)->run();

        // Mock the agent
        $agent = Mockery::mock(Agent::class);
        $agent->shouldReceive('run')
            ->andReturn(new AgentResponse(
                answer: 'Hamilton won with 11 races',
                sql: 'SELECT name, COUNT(*) FROM race_wins',
                results: [['name' => 'Hamilton', 'wins' => 11]],
            ));

        $this->app->instance(Agent::class, $agent);

        $this->artisan('sql-agent:eval', ['--llm-grader' => true])
            ->expectsOutputToContain('LLM grading');
    });
});

describe('TestCaseSeeder', function () {
    it('creates all 18 test cases', function () {
        (new TestCaseSeeder)->run();

        expect(TestCase::count())->toBe(18);
    });

    it('creates test cases in correct categories', function () {
        (new TestCaseSeeder)->run();

        $categories = TestCase::distinct()->pluck('category')->sort()->values();

        expect($categories->toArray())->toBe([
            'aggregation',
            'basic',
            'complex',
            'data_quality',
            'edge_case',
        ]);
    });

    it('sets expected_values as arrays', function () {
        (new TestCaseSeeder)->run();

        $testCase = TestCase::where('name', 'race_winner_2019')->first();

        expect($testCase->expected_values)->toBeArray();
        expect($testCase->expected_values)->toContain('Hamilton');
        expect($testCase->expected_values)->toContain('11');
    });

    it('includes golden SQL where applicable', function () {
        (new TestCaseSeeder)->run();

        $withGoldenSql = TestCase::withGoldenSql()->count();
        $withoutGoldenSql = TestCase::whereNull('golden_sql')->count();

        expect($withGoldenSql)->toBeGreaterThan(0);
        expect($withoutGoldenSql)->toBeGreaterThan(0); // Some edge cases don't have golden SQL
    });

    it('can update existing test cases', function () {
        (new TestCaseSeeder)->run();
        $initialCount = TestCase::count();

        // Run seeder again
        (new TestCaseSeeder)->run();

        // Should not duplicate
        expect(TestCase::count())->toBe($initialCount);
    });
});
