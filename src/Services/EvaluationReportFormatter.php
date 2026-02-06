<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Console\Command;
use Knobik\SqlAgent\Data\EvaluationReport;
use Knobik\SqlAgent\Data\TestResult;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class EvaluationReportFormatter
{
    public function displayResults(Command $command, EvaluationReport $report, bool $detailed = false): void
    {
        // Build results table
        $rows = [];
        foreach ($report->results as $result) {
            $row = [
                $result->getStatusEmoji(),
                $result->testCaseName,
                $result->category,
                number_format($result->duration, 2).'s',
            ];

            // Add LLM score if grading was used
            if ($report->usedLlmGrader && $result->gradeResult !== null) {
                $row[] = number_format($result->gradeResult->score * 100, 0).'%';
            } elseif ($report->usedLlmGrader) {
                $row[] = '-';
            }

            // Add result match if golden SQL was used
            if ($report->usedGoldenSql) {
                if ($result->resultMatch === true) {
                    $row[] = '✓';
                } elseif ($result->resultMatch === false) {
                    $row[] = '✗';
                } else {
                    $row[] = '-';
                }
            }

            $rows[] = $row;
        }

        // Build headers
        $headers = ['', 'Test', 'Category', 'Duration'];
        if ($report->usedLlmGrader) {
            $headers[] = 'LLM Score';
        }
        if ($report->usedGoldenSql) {
            $headers[] = 'Results';
        }

        table($headers, $rows);

        // Show verbose details for failed tests
        if ($detailed) {
            $failedTests = $report->getFailedTests();
            if ($failedTests->isNotEmpty()) {
                $command->newLine();
                info('Failed Test Details:');
                info('===================');

                foreach ($failedTests as $result) {
                    $this->displayFailedTestDetails($command, $result);
                }
            }
        }
    }

    public function displaySummary(Command $command, EvaluationReport $report): void
    {
        $command->newLine();
        info('Summary');
        info('=======');

        $passRate = number_format($report->getPassRate(), 1);
        $passColor = $report->getPassRate() >= 80 ? 'green' : ($report->getPassRate() >= 50 ? 'yellow' : 'red');

        $command->line("Total: {$report->getTotalTests()} tests");
        $command->line("<fg=green>Passed:</> {$report->getPassedCount()}");
        $command->line("<fg=red>Failed:</> {$report->getFailedCount()}");
        $command->line("<fg=yellow>Errors:</> {$report->getErrorCount()}");
        $command->line("<fg={$passColor}>Pass Rate:</> {$passRate}%");
        $command->line('Duration: '.number_format($report->totalDuration, 2).'s');

        if ($report->usedLlmGrader && $report->getAverageLlmScore() !== null) {
            $avgScore = number_format($report->getAverageLlmScore() * 100, 1);
            $command->line("Avg LLM Score: {$avgScore}%");
        }

        // Category breakdown
        $categoryStats = $report->getCategoryStats();
        if (count($categoryStats) > 1) {
            $command->newLine();
            info('By Category:');
            foreach ($categoryStats as $category => $stats) {
                $catPassRate = number_format($stats['pass_rate'], 0);
                $command->line("  {$category}: {$stats['passed']}/{$stats['total']} ({$catPassRate}%)");
            }
        }
    }

    protected function displayFailedTestDetails(Command $command, TestResult $result): void
    {
        $command->newLine();
        $command->line("<fg=red>✗</> <fg=white;options=bold>{$result->testCaseName}</>");
        $command->line("  Question: {$result->question}");

        if ($result->error !== null) {
            $command->line("  <fg=red>Error:</> {$result->error}");
        }

        if (! empty($result->missingStrings)) {
            $command->line('  <fg=yellow>Missing strings:</> '.implode(', ', $result->missingStrings));
        }

        if ($result->gradeResult !== null && ! $result->gradeResult->passed) {
            $command->line("  <fg=yellow>LLM reasoning:</> {$result->gradeResult->reasoning}");
        }

        if ($result->resultMatch === false && $result->resultExplanation !== null) {
            $command->line("  <fg=yellow>Result mismatch:</> {$result->resultExplanation}");
        }

        if ($result->response !== null) {
            $truncatedResponse = strlen($result->response) > 200
                ? substr($result->response, 0, 200).'...'
                : $result->response;
            $command->line("  Response: {$truncatedResponse}");
        }

        if ($result->sql !== null) {
            $command->line("  SQL: {$result->sql}");
        }
    }
}
