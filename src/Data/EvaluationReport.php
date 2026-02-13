<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class EvaluationReport
{
    public function __construct(
        /** @var Collection<int, TestResult> */
        public Collection $results,
        public float $totalDuration,
        public DateTimeImmutable $completedAt,
        public ?string $category = null,
        public bool $usedLlmGrader = false,
        public bool $usedGoldenSql = false,
    ) {}

    public function getTotalTests(): int
    {
        return $this->results->count();
    }

    public function getPassedCount(): int
    {
        return $this->results->filter(fn (TestResult $r) => $r->isPassed())->count();
    }

    public function getFailedCount(): int
    {
        return $this->results->filter(fn (TestResult $r) => $r->isFailed())->count();
    }

    public function getErrorCount(): int
    {
        return $this->results->filter(fn (TestResult $r) => $r->isError())->count();
    }

    public function getPassRate(): float
    {
        $total = $this->getTotalTests();
        if ($total === 0) {
            return 0.0;
        }

        return ($this->getPassedCount() / $total) * 100;
    }

    public function getAverageDuration(): float
    {
        if ($this->results->isEmpty()) {
            return 0.0;
        }

        return $this->results->avg('duration') ?? 0.0;
    }

    public function getAverageLlmScore(): ?float
    {
        if (! $this->usedLlmGrader) {
            return null;
        }

        $resultsWithGrade = $this->results->filter(fn (TestResult $r) => $r->gradeResult !== null);

        if ($resultsWithGrade->isEmpty()) {
            return null;
        }

        return $resultsWithGrade->avg(fn (TestResult $r) => $r->gradeResult->score);
    }

    /**
     * @return Collection<string, Collection<int, TestResult>>
     */
    public function getResultsByCategory(): Collection
    {
        return $this->results->groupBy('category');
    }

    /**
     * @return array<string, array{total: int, passed: int, failed: int, error: int, pass_rate: float}>
     */
    public function getCategoryStats(): array
    {
        $stats = [];

        foreach ($this->getResultsByCategory() as $category => $results) {
            $total = $results->count();
            $passed = $results->filter(fn (TestResult $r) => $r->isPassed())->count();
            $failed = $results->filter(fn (TestResult $r) => $r->isFailed())->count();
            $error = $results->filter(fn (TestResult $r) => $r->isError())->count();

            $stats[$category] = [
                'total' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'error' => $error,
                'pass_rate' => $total > 0 ? ($passed / $total) * 100 : 0.0,
            ];
        }

        return $stats;
    }

    /**
     * @return Collection<int, TestResult>
     */
    public function getFailedTests(): Collection
    {
        return $this->results->filter(fn (TestResult $r) => $r->isFailed() || $r->isError());
    }

    public function toJson($options = 0): string
    {
        return json_encode([
            'summary' => [
                'total_tests' => $this->getTotalTests(),
                'passed' => $this->getPassedCount(),
                'failed' => $this->getFailedCount(),
                'errors' => $this->getErrorCount(),
                'pass_rate' => round($this->getPassRate(), 2),
                'total_duration' => round($this->totalDuration, 2),
                'average_duration' => round($this->getAverageDuration(), 2),
                'average_llm_score' => $this->getAverageLlmScore() !== null
                    ? round($this->getAverageLlmScore(), 2)
                    : null,
                'completed_at' => $this->completedAt->format('Y-m-d H:i:s'),
                'category' => $this->category,
                'used_llm_grader' => $this->usedLlmGrader,
                'used_golden_sql' => $this->usedGoldenSql,
            ],
            'category_stats' => $this->getCategoryStats(),
            'results' => $this->results->map(fn (TestResult $r) => [
                'test_case_id' => $r->testCaseId,
                'name' => $r->testCaseName,
                'question' => $r->question,
                'category' => $r->category,
                'status' => $r->status,
                'duration' => round($r->duration, 2),
                'response' => $r->response,
                'sql' => $r->sql,
                'missing_strings' => $r->missingStrings,
                'grade_result' => $r->gradeResult ? [
                    'passed' => $r->gradeResult->passed,
                    'score' => round($r->gradeResult->score, 2),
                    'reasoning' => $r->gradeResult->reasoning,
                ] : null,
                'result_match' => $r->resultMatch,
                'result_explanation' => $r->resultExplanation,
                'error' => $r->error,
            ])->toArray(),
        ], $options | JSON_PRETTY_PRINT);
    }

    public function toHtml(): string
    {
        return View::make('sql-agent::reports.evaluation', [
            'report' => $this,
        ])->render();
    }
}
