<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Knobik\SqlAgent\Data\EvaluationReport;
use Knobik\SqlAgent\Data\TestResult;
use Knobik\SqlAgent\Database\Seeders\TestCaseSeeder;
use Knobik\SqlAgent\Services\EvaluationRunner;

use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class RunEvalsCommand extends Command
{
    protected $signature = 'sql-agent:eval
                            {--category= : Test category (basic, aggregation, data_quality, complex, edge_case)}
                            {--llm-grader : Use LLM grading}
                            {--golden-sql : Compare against golden SQL results}
                            {--connection= : Database connection}
                            {--detailed : Show detailed output for failed tests}
                            {--json : Output as JSON}
                            {--html= : Generate HTML report at path}
                            {--seed : Seed test cases before running}';

    protected $description = 'Run evaluation tests';

    public function __construct(
        protected EvaluationRunner $evaluationRunner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Seed test cases if requested
        if ($this->option('seed')) {
            info('Seeding test cases...');
            $seeder = new TestCaseSeeder;
            $seeder->run();
            info('Test cases seeded successfully.');
        }

        // Validate category
        $category = $this->option('category');
        if ($category !== null) {
            $availableCategories = $this->evaluationRunner->getCategories();
            if (! in_array($category, $availableCategories, true)) {
                warning("Invalid category: {$category}");
                warning('Available categories: '.implode(', ', $availableCategories));

                return self::FAILURE;
            }
        }

        // Check test case count
        $testCaseCount = $this->evaluationRunner->getTestCaseCount($category);
        if ($testCaseCount === 0) {
            warning('No test cases found.');
            warning('Run with --seed to seed test cases first.');

            return self::FAILURE;
        }

        // Display mode info
        $this->displayModeInfo($category, $testCaseCount);

        // Run evaluation with progress bar
        $report = $this->runEvaluation($category);

        // Output results
        if ($this->option('json')) {
            $this->line($report->toJson());
        } else {
            $this->displayResults($report);
            $this->displaySummary($report);
        }

        // Generate HTML report if requested
        $htmlPath = $this->option('html');
        if ($htmlPath !== null) {
            $this->generateHtmlReport($report, $htmlPath);
        }

        // Return appropriate exit code
        return $report->getPassRate() >= 100.0 ? self::SUCCESS : self::FAILURE;
    }

    protected function displayModeInfo(?string $category, int $testCaseCount): void
    {
        info('SQL Agent Evaluation');
        info('====================');
        info("Test cases: {$testCaseCount}");

        if ($category !== null) {
            info("Category: {$category}");
        } else {
            info('Category: all');
        }

        $modes = ['String matching'];
        if ($this->option('llm-grader')) {
            $modes[] = 'LLM grading';
        }
        if ($this->option('golden-sql')) {
            $modes[] = 'Golden SQL comparison';
        }
        info('Modes: '.implode(', ', $modes));
        info('');
    }

    protected function runEvaluation(?string $category): EvaluationReport
    {
        $useLlmGrader = (bool) $this->option('llm-grader');
        $useGoldenSql = (bool) $this->option('golden-sql');
        $connection = $this->option('connection');

        // Get test case count for progress bar
        $testCaseCount = $this->evaluationRunner->getTestCaseCount($category);

        // Use Laravel Prompts progress function
        $results = collect();

        progress(
            label: 'Running evaluations',
            steps: $testCaseCount,
            callback: function ($step, $progress) use ($category, $useLlmGrader, $useGoldenSql, $connection, &$results) {
                // We need to run all at once since we don't have test case iteration
                if ($step === 0) {
                    $report = $this->evaluationRunner->run(
                        category: $category,
                        useLlmGrader: $useLlmGrader,
                        useGoldenSql: $useGoldenSql,
                        connection: $connection,
                    );
                    $results = $report;
                }
            },
            hint: 'This may take a while...'
        );

        // Actually run all tests if progress didn't work properly
        if ($results instanceof EvaluationReport) {
            return $results;
        }

        return $this->evaluationRunner->run(
            category: $category,
            useLlmGrader: $useLlmGrader,
            useGoldenSql: $useGoldenSql,
            connection: $connection,
        );
    }

    protected function displayResults(EvaluationReport $report): void
    {
        $isVerbose = $this->option('detailed') || $this->option('verbose');

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
        if ($isVerbose) {
            $failedTests = $report->getFailedTests();
            if ($failedTests->isNotEmpty()) {
                $this->newLine();
                info('Failed Test Details:');
                info('===================');

                foreach ($failedTests as $result) {
                    $this->displayFailedTestDetails($result);
                }
            }
        }
    }

    protected function displayFailedTestDetails(TestResult $result): void
    {
        $this->newLine();
        $this->line("<fg=red>✗</> <fg=white;options=bold>{$result->testCaseName}</>");
        $this->line("  Question: {$result->question}");

        if ($result->error !== null) {
            $this->line("  <fg=red>Error:</> {$result->error}");
        }

        if (! empty($result->missingStrings)) {
            $this->line('  <fg=yellow>Missing strings:</> '.implode(', ', $result->missingStrings));
        }

        if ($result->gradeResult !== null && ! $result->gradeResult->passed) {
            $this->line("  <fg=yellow>LLM reasoning:</> {$result->gradeResult->reasoning}");
        }

        if ($result->resultMatch === false && $result->resultExplanation !== null) {
            $this->line("  <fg=yellow>Result mismatch:</> {$result->resultExplanation}");
        }

        if ($result->response !== null) {
            $truncatedResponse = strlen($result->response) > 200
                ? substr($result->response, 0, 200).'...'
                : $result->response;
            $this->line("  Response: {$truncatedResponse}");
        }

        if ($result->sql !== null) {
            $this->line("  SQL: {$result->sql}");
        }
    }

    protected function displaySummary(EvaluationReport $report): void
    {
        $this->newLine();
        info('Summary');
        info('=======');

        $passRate = number_format($report->getPassRate(), 1);
        $passColor = $report->getPassRate() >= 80 ? 'green' : ($report->getPassRate() >= 50 ? 'yellow' : 'red');

        $this->line("Total: {$report->getTotalTests()} tests");
        $this->line("<fg=green>Passed:</> {$report->getPassedCount()}");
        $this->line("<fg=red>Failed:</> {$report->getFailedCount()}");
        $this->line("<fg=yellow>Errors:</> {$report->getErrorCount()}");
        $this->line("<fg={$passColor}>Pass Rate:</> {$passRate}%");
        $this->line('Duration: '.number_format($report->totalDuration, 2).'s');

        if ($report->usedLlmGrader && $report->getAverageLlmScore() !== null) {
            $avgScore = number_format($report->getAverageLlmScore() * 100, 1);
            $this->line("Avg LLM Score: {$avgScore}%");
        }

        // Category breakdown
        $categoryStats = $report->getCategoryStats();
        if (count($categoryStats) > 1) {
            $this->newLine();
            info('By Category:');
            foreach ($categoryStats as $category => $stats) {
                $catPassRate = number_format($stats['pass_rate'], 0);
                $this->line("  {$category}: {$stats['passed']}/{$stats['total']} ({$catPassRate}%)");
            }
        }
    }

    protected function generateHtmlReport(EvaluationReport $report, string $path): void
    {
        $html = $report->toHtml();

        // Ensure directory exists
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $html);
        info("HTML report generated: {$path}");
    }
}
