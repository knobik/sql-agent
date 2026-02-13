<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

class TestResult
{
    public const STATUS_PASS = 'pass';

    public const STATUS_FAIL = 'fail';

    public const STATUS_ERROR = 'error';

    public function __construct(
        public int $testCaseId,
        public string $testCaseName,
        public string $question,
        public string $category,
        public string $status,           // 'pass', 'fail', 'error'
        public float $duration,
        public ?string $response = null,
        public ?string $sql = null,
        public ?array $results = null,
        public ?array $missingStrings = null,
        public ?GradeResult $gradeResult = null,
        public ?bool $resultMatch = null,
        public ?string $resultExplanation = null,
        public ?string $error = null,
    ) {}

    public static function fromExecution(
        int $testCaseId,
        string $testCaseName,
        string $question,
        string $category,
        float $duration,
        AgentResponse $agentResponse,
        array $missingStrings = [],
        ?GradeResult $gradeResult = null,
        ?bool $resultMatch = null,
        ?string $resultExplanation = null,
    ): self {
        // Determine status based on available checks
        $status = self::STATUS_PASS;

        // Check for agent errors
        if (! $agentResponse->isSuccess()) {
            $status = self::STATUS_ERROR;
        }

        // String matching is always run - missing strings means failure
        if (count($missingStrings) > 0) {
            $status = self::STATUS_FAIL;
        }

        // LLM grader result
        if ($gradeResult !== null && ! $gradeResult->passed) {
            $status = self::STATUS_FAIL;
        }

        // Result comparison
        if ($resultMatch === false) {
            $status = self::STATUS_FAIL;
        }

        return new self(
            testCaseId: $testCaseId,
            testCaseName: $testCaseName,
            question: $question,
            category: $category,
            status: $status,
            duration: $duration,
            response: $agentResponse->answer,
            sql: $agentResponse->sql,
            results: $agentResponse->results,
            missingStrings: $missingStrings,
            gradeResult: $gradeResult,
            resultMatch: $resultMatch,
            resultExplanation: $resultExplanation,
            error: $agentResponse->error,
        );
    }

    public static function fromError(
        int $testCaseId,
        string $testCaseName,
        string $question,
        string $category,
        float $duration,
        string $error,
    ): self {
        return new self(
            testCaseId: $testCaseId,
            testCaseName: $testCaseName,
            question: $question,
            category: $category,
            status: self::STATUS_ERROR,
            duration: $duration,
            error: $error,
        );
    }

    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAIL;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function getStatusEmoji(): string
    {
        return match ($this->status) {
            self::STATUS_PASS => '✓',
            self::STATUS_FAIL => '✗',
            self::STATUS_ERROR => '⚠',
            default => '?',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PASS => 'Pass',
            self::STATUS_FAIL => 'Fail',
            self::STATUS_ERROR => 'Error',
            default => 'Unknown',
        };
    }
}
