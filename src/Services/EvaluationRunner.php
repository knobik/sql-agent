<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Contracts\Agent;
use Knobik\SqlAgent\Data\EvaluationReport;
use Knobik\SqlAgent\Data\TestResult;
use Knobik\SqlAgent\Exceptions\TestTimeoutException;
use Knobik\SqlAgent\Models\TestCase;
use Throwable;

class EvaluationRunner
{
    public function __construct(
        protected Agent $agent,
        protected LlmGrader $llmGrader,
    ) {}

    public function run(
        ?string $category = null,
        bool $useLlmGrader = false,
        bool $useGoldenSql = false,
        ?string $connection = null,
    ): EvaluationReport {
        $startTime = microtime(true);
        $results = collect();

        // Get test cases
        $query = TestCase::query();
        if ($category !== null) {
            $query->ofCategory($category);
        }
        $testCases = $query->get();

        foreach ($testCases as $testCase) {
            $results->push(
                $this->runTestCase($testCase, $useLlmGrader, $useGoldenSql, $connection)
            );
        }

        $totalDuration = microtime(true) - $startTime;

        return new EvaluationReport(
            results: $results,
            totalDuration: $totalDuration,
            completedAt: new DateTimeImmutable,
            category: $category,
            usedLlmGrader: $useLlmGrader,
            usedGoldenSql: $useGoldenSql,
        );
    }

    public function runTestCase(
        TestCase $testCase,
        bool $useLlmGrader = false,
        bool $useGoldenSql = false,
        ?string $connection = null,
    ): TestResult {
        $startTime = microtime(true);
        $timeout = config('sql-agent.evaluation.timeout', 60);

        try {
            // Run the agent
            $agentResponse = $this->agent->run($testCase->question, $connection);
            $duration = microtime(true) - $startTime;

            // Check if we've exceeded the timeout
            if ($duration > $timeout) {
                throw new TestTimeoutException(
                    "Test case exceeded timeout of {$timeout} seconds (took {$duration}s)"
                );
            }

            // String matching (always runs)
            $expectedStrings = $testCase->expected_values ?? [];
            $missingStrings = $this->checkExpectedStrings(
                $agentResponse->answer,
                $expectedStrings
            );

            // LLM grading (optional)
            $gradeResult = null;
            if ($useLlmGrader) {
                $goldenResult = null;
                if ($useGoldenSql && $testCase->hasGoldenSql()) {
                    $goldenResult = $this->executeGoldenSql($testCase->golden_sql, $connection);
                }

                $gradeResult = $this->llmGrader->grade(
                    question: $testCase->question,
                    response: $agentResponse->answer,
                    expectedStrings: $expectedStrings,
                    goldenResult: $goldenResult,
                );
            }

            // Result comparison (optional)
            $resultMatch = null;
            $resultExplanation = null;
            if ($useGoldenSql && $testCase->hasGoldenSql() && $agentResponse->hasResults()) {
                $goldenResult = $this->executeGoldenSql($testCase->golden_sql, $connection);
                if ($goldenResult !== null) {
                    $comparison = $this->llmGrader->compareResults(
                        expected: $goldenResult,
                        actual: $agentResponse->results,
                    );
                    $resultMatch = $comparison['matches'];
                    $resultExplanation = $comparison['explanation'];
                }
            }

            return TestResult::fromExecution(
                testCaseId: $testCase->id,
                testCaseName: $testCase->name,
                question: $testCase->question,
                category: $testCase->category,
                duration: $duration,
                agentResponse: $agentResponse,
                missingStrings: $missingStrings,
                gradeResult: $gradeResult,
                resultMatch: $resultMatch,
                resultExplanation: $resultExplanation,
            );
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;

            return TestResult::fromError(
                testCaseId: $testCase->id,
                testCaseName: $testCase->name,
                question: $testCase->question,
                category: $testCase->category,
                duration: $duration,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Get all available categories.
     *
     * @return array<string>
     */
    public function getCategories(): array
    {
        return TestCase::query()
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->toArray();
    }

    public function getTestCaseCount(?string $category = null): int
    {
        $query = TestCase::query();
        if ($category !== null) {
            $query->ofCategory($category);
        }

        return $query->count();
    }

    /**
     * Check which expected strings are missing from the response.
     * Uses case-insensitive substring matching.
     *
     * @param  array<string>  $expectedStrings
     * @return array<string>
     */
    protected function checkExpectedStrings(string $response, array $expectedStrings): array
    {
        $missing = [];
        $lowerResponse = strtolower($response);

        foreach ($expectedStrings as $expected) {
            if (! str_contains($lowerResponse, strtolower($expected))) {
                $missing[] = $expected;
            }
        }

        return $missing;
    }

    /**
     * Execute golden SQL and return results.
     *
     * @return array<array<string, mixed>>|null
     */
    protected function executeGoldenSql(string $sql, ?string $connection): ?array
    {
        try {
            $conn = $connection ?? config('sql-agent.database.connection');
            $results = DB::connection($conn)->select($sql);

            // Convert to array of arrays
            return array_map(fn ($row) => (array) $row, $results);
        } catch (Throwable $e) {
            // Golden SQL may have database-specific syntax that doesn't work
            // Return null to skip result comparison
            return null;
        }
    }
}
