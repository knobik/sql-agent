<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

class GradeResult
{
    public function __construct(
        public bool $passed,
        public float $score,      // 0.0 - 1.0
        public string $reasoning,
    ) {}

    public static function fromLlmResponse(string $response, ?float $passThreshold = null): self
    {
        // Use config threshold or default to 0.6
        $threshold = $passThreshold ?? config('sql-agent.evaluation.pass_threshold');

        // Parse structured response from LLM
        // Expected format:
        // SCORE: 0.8
        // PASSED: true
        // REASONING: ...

        $score = 0.0;
        $passed = false;
        $reasoning = '';

        // Extract score
        if (preg_match('/SCORE:\s*([\d.]+)/i', $response, $matches)) {
            $score = min(1.0, max(0.0, (float) $matches[1]));
        }

        // Extract passed
        if (preg_match('/PASSED:\s*(true|false|yes|no)/i', $response, $matches)) {
            $passed = in_array(strtolower($matches[1]), ['true', 'yes']);
        }

        // Extract reasoning - everything after REASONING:
        if (preg_match('/REASONING:\s*(.+)$/is', $response, $matches)) {
            $reasoning = trim($matches[1]);
        }

        // Fallback: if no structured format, try to infer from content
        if (empty($reasoning)) {
            $reasoning = $response;
            // If score wasn't found, try to infer based on positive/negative words
            if ($score === 0.0) {
                $positiveWords = ['correct', 'accurate', 'good', 'excellent', 'matches'];
                $negativeWords = ['incorrect', 'wrong', 'missing', 'failed', 'error'];

                $lowerResponse = strtolower($response);
                $positiveCount = 0;
                $negativeCount = 0;

                foreach ($positiveWords as $word) {
                    $positiveCount += substr_count($lowerResponse, $word);
                }
                foreach ($negativeWords as $word) {
                    $negativeCount += substr_count($lowerResponse, $word);
                }

                if ($positiveCount + $negativeCount > 0) {
                    $score = $positiveCount / ($positiveCount + $negativeCount);
                    $passed = $score >= $threshold;
                }
            }
        }

        return new self(
            passed: $passed,
            score: $score,
            reasoning: $reasoning,
        );
    }

    public function getScorePercentage(): float
    {
        return $this->score * 100;
    }

    public function getScoreLabel(): string
    {
        return match (true) {
            $this->score >= 0.9 => 'Excellent',
            $this->score >= 0.7 => 'Good',
            $this->score >= 0.5 => 'Fair',
            default => 'Poor',
        };
    }
}
