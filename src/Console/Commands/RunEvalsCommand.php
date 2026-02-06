<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Console\Commands;

use Illuminate\Console\Command;
use Knobik\SqlAgent\Data\EvaluationReport;
use Knobik\SqlAgent\Database\Seeders\TestCaseSeeder;
use Knobik\SqlAgent\Services\EvaluationReportFormatter;
use Knobik\SqlAgent\Services\EvaluationRunner;

use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
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

    protected EvaluationRunner $evaluationRunner;

    public function handle(EvaluationRunner $evaluationRunner, EvaluationReportFormatter $formatter): int
    {
        $this->evaluationRunner = $evaluationRunner;

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
            $detailed = (bool) $this->option('detailed') || (bool) $this->option('verbose');
            $formatter->displayResults($this, $report, $detailed);
            $formatter->displaySummary($this, $report);
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
