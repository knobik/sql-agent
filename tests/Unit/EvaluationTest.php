<?php

use Knobik\SqlAgent\Contracts\Agent;
use Knobik\SqlAgent\Contracts\AgentResponse;
use Knobik\SqlAgent\Data\EvaluationReport;
use Knobik\SqlAgent\Data\GradeResult;
use Knobik\SqlAgent\Data\TestResult;
use Knobik\SqlAgent\Llm\LlmManager;
use Knobik\SqlAgent\Services\EvaluationRunner;
use Knobik\SqlAgent\Services\LlmGrader;

describe('GradeResult', function () {
    it('can be created', function () {
        $result = new GradeResult(
            passed: true,
            score: 0.85,
            reasoning: 'The response is accurate and complete.',
        );

        expect($result->passed)->toBeTrue();
        expect($result->score)->toBe(0.85);
        expect($result->reasoning)->toBe('The response is accurate and complete.');
    });

    it('calculates score percentage', function () {
        $result = new GradeResult(
            passed: true,
            score: 0.75,
            reasoning: 'Good response.',
        );

        expect($result->getScorePercentage())->toBe(75.0);
    });

    it('returns correct score label', function () {
        expect((new GradeResult(true, 0.95, ''))->getScoreLabel())->toBe('Excellent');
        expect((new GradeResult(true, 0.75, ''))->getScoreLabel())->toBe('Good');
        expect((new GradeResult(false, 0.55, ''))->getScoreLabel())->toBe('Fair');
        expect((new GradeResult(false, 0.3, ''))->getScoreLabel())->toBe('Poor');
    });

    it('parses structured LLM response', function () {
        $response = <<<'RESPONSE'
            SCORE: 0.8
            PASSED: true
            REASONING: The response correctly identifies Hamilton as the winner with 11 wins.
            RESPONSE;

        $result = GradeResult::fromLlmResponse($response);

        expect($result->passed)->toBeTrue();
        expect($result->score)->toBe(0.8);
        expect($result->reasoning)->toContain('Hamilton');
    });

    it('handles lowercase passed value', function () {
        $response = "SCORE: 0.9\nPASSED: yes\nREASONING: Correct.";
        $result = GradeResult::fromLlmResponse($response);

        expect($result->passed)->toBeTrue();
    });

    it('clamps score to valid range', function () {
        $response = "SCORE: 1.5\nPASSED: true\nREASONING: Over the limit.";
        $result = GradeResult::fromLlmResponse($response);

        expect($result->score)->toBe(1.0);
    });
});

describe('TestResult', function () {
    it('can be created', function () {
        $result = new TestResult(
            testCaseId: 1,
            testCaseName: 'race_winner_2019',
            question: 'Who won the most races in 2019?',
            category: 'basic',
            status: TestResult::STATUS_PASS,
            duration: 2.5,
            response: 'Hamilton won 11 races in 2019.',
            sql: 'SELECT name, COUNT(*) FROM race_wins...',
        );

        expect($result->testCaseId)->toBe(1);
        expect($result->isPassed())->toBeTrue();
        expect($result->isFailed())->toBeFalse();
        expect($result->isError())->toBeFalse();
    });

    it('creates pass result from execution', function () {
        $agentResponse = new AgentResponse(
            answer: 'Hamilton won 11 races in 2019.',
            sql: 'SELECT name, COUNT(*) FROM race_wins...',
            results: [['name' => 'Hamilton', 'wins' => 11]],
        );

        $result = TestResult::fromExecution(
            testCaseId: 1,
            testCaseName: 'race_winner_2019',
            question: 'Who won the most races in 2019?',
            category: 'basic',
            duration: 2.5,
            agentResponse: $agentResponse,
            missingStrings: [],
        );

        expect($result->isPassed())->toBeTrue();
        expect($result->response)->toBe('Hamilton won 11 races in 2019.');
    });

    it('creates fail result when strings are missing', function () {
        $agentResponse = new AgentResponse(
            answer: 'Someone won many races in 2019.',
        );

        $result = TestResult::fromExecution(
            testCaseId: 1,
            testCaseName: 'race_winner_2019',
            question: 'Who won the most races in 2019?',
            category: 'basic',
            duration: 2.5,
            agentResponse: $agentResponse,
            missingStrings: ['Hamilton', '11'],
        );

        expect($result->isFailed())->toBeTrue();
        expect($result->missingStrings)->toBe(['Hamilton', '11']);
    });

    it('creates error result from exception', function () {
        $result = TestResult::fromError(
            testCaseId: 1,
            testCaseName: 'race_winner_2019',
            question: 'Who won the most races in 2019?',
            category: 'basic',
            duration: 1.0,
            error: 'Connection failed',
        );

        expect($result->isError())->toBeTrue();
        expect($result->error)->toBe('Connection failed');
    });

    it('returns correct status emoji', function () {
        expect((new TestResult(1, 'test', 'q', 'cat', TestResult::STATUS_PASS, 1.0))->getStatusEmoji())->toBe('✓');
        expect((new TestResult(1, 'test', 'q', 'cat', TestResult::STATUS_FAIL, 1.0))->getStatusEmoji())->toBe('✗');
        expect((new TestResult(1, 'test', 'q', 'cat', TestResult::STATUS_ERROR, 1.0))->getStatusEmoji())->toBe('⚠');
    });
});

describe('EvaluationReport', function () {
    beforeEach(function () {
        $this->results = collect([
            new TestResult(1, 'test1', 'q1', 'basic', TestResult::STATUS_PASS, 1.0),
            new TestResult(2, 'test2', 'q2', 'basic', TestResult::STATUS_PASS, 1.5),
            new TestResult(3, 'test3', 'q3', 'aggregation', TestResult::STATUS_FAIL, 2.0),
            new TestResult(4, 'test4', 'q4', 'aggregation', TestResult::STATUS_ERROR, 0.5),
        ]);

        $this->report = new EvaluationReport(
            results: $this->results,
            totalDuration: 5.0,
            completedAt: new DateTimeImmutable('2024-01-15 10:30:00'),
            category: null,
            usedLlmGrader: false,
            usedGoldenSql: false,
        );
    });

    it('calculates total tests', function () {
        expect($this->report->getTotalTests())->toBe(4);
    });

    it('calculates passed count', function () {
        expect($this->report->getPassedCount())->toBe(2);
    });

    it('calculates failed count', function () {
        expect($this->report->getFailedCount())->toBe(1);
    });

    it('calculates error count', function () {
        expect($this->report->getErrorCount())->toBe(1);
    });

    it('calculates pass rate', function () {
        expect($this->report->getPassRate())->toBe(50.0);
    });

    it('calculates average duration', function () {
        expect($this->report->getAverageDuration())->toBe(1.25);
    });

    it('returns null for average LLM score when not used', function () {
        expect($this->report->getAverageLlmScore())->toBeNull();
    });

    it('groups results by category', function () {
        $byCategory = $this->report->getResultsByCategory();

        expect($byCategory->keys()->toArray())->toBe(['basic', 'aggregation']);
        expect($byCategory->get('basic'))->toHaveCount(2);
        expect($byCategory->get('aggregation'))->toHaveCount(2);
    });

    it('calculates category stats', function () {
        $stats = $this->report->getCategoryStats();

        expect($stats['basic']['total'])->toBe(2);
        expect($stats['basic']['passed'])->toBe(2);
        expect((float) $stats['basic']['pass_rate'])->toBe(100.0);

        expect($stats['aggregation']['total'])->toBe(2);
        expect($stats['aggregation']['passed'])->toBe(0);
        expect((float) $stats['aggregation']['pass_rate'])->toBe(0.0);
    });

    it('gets failed tests', function () {
        $failed = $this->report->getFailedTests();

        expect($failed)->toHaveCount(2);
        expect($failed->pluck('testCaseName')->toArray())->toBe(['test3', 'test4']);
    });

    it('generates JSON output', function () {
        $json = $this->report->toJson();
        $data = json_decode($json, true);

        expect($data['summary']['total_tests'])->toBe(4);
        expect($data['summary']['passed'])->toBe(2);
        expect((float) $data['summary']['pass_rate'])->toBe(50.0);
        expect($data['results'])->toHaveCount(4);
    });

    it('handles empty results', function () {
        $emptyReport = new EvaluationReport(
            results: collect(),
            totalDuration: 0.0,
            completedAt: new DateTimeImmutable,
        );

        expect($emptyReport->getTotalTests())->toBe(0);
        expect($emptyReport->getPassRate())->toBe(0.0);
        expect($emptyReport->getAverageDuration())->toBe(0.0);
    });
});

describe('LlmGrader', function () {
    it('can compare empty results', function () {
        $llmManager = Mockery::mock(LlmManager::class);
        $grader = new LlmGrader($llmManager);

        $comparison = $grader->compareResults([], []);

        expect($comparison['matches'])->toBeTrue();
        expect($comparison['explanation'])->toBe('Both results are empty');
    });

    it('detects missing expected data', function () {
        $llmManager = Mockery::mock(LlmManager::class);
        $grader = new LlmGrader($llmManager);

        $comparison = $grader->compareResults(
            expected: [['name' => 'Hamilton', 'wins' => '11']],
            actual: [],
        );

        expect($comparison['matches'])->toBeFalse();
        expect($comparison['explanation'])->toContain('empty');
    });

    it('detects value mismatch in single row', function () {
        $llmManager = Mockery::mock(LlmManager::class);
        $grader = new LlmGrader($llmManager);

        $comparison = $grader->compareResults(
            expected: [['name' => 'Hamilton', 'wins' => '11']],
            actual: [['name' => 'Verstappen', 'wins' => '9']],
        );

        expect($comparison['matches'])->toBeFalse();
        expect($comparison['explanation'])->toContain('Mismatch');
    });

    it('matches when values are correct', function () {
        $llmManager = Mockery::mock(LlmManager::class);
        $grader = new LlmGrader($llmManager);

        $comparison = $grader->compareResults(
            expected: [['name' => 'Hamilton', 'wins' => '11']],
            actual: [['name' => 'Hamilton', 'wins' => '11']],
        );

        expect($comparison['matches'])->toBeTrue();
    });

    it('is case insensitive', function () {
        $llmManager = Mockery::mock(LlmManager::class);
        $grader = new LlmGrader($llmManager);

        $comparison = $grader->compareResults(
            expected: [['name' => 'HAMILTON']],
            actual: [['name' => 'hamilton']],
        );

        expect($comparison['matches'])->toBeTrue();
    });
});

describe('EvaluationRunner', function () {
    it('can be instantiated', function () {
        $agent = Mockery::mock(Agent::class);
        $grader = Mockery::mock(LlmGrader::class);

        $runner = new EvaluationRunner($agent, $grader);

        expect($runner)->toBeInstanceOf(EvaluationRunner::class);
    });
});
