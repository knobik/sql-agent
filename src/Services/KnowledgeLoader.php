<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Facades\File;
use Knobik\SqlAgent\Enums\BusinessRuleType;
use Knobik\SqlAgent\Models\BusinessRule;
use Knobik\SqlAgent\Models\QueryPattern;
use Knobik\SqlAgent\Models\TableMetadata;

class KnowledgeLoader
{
    /**
     * Truncate all knowledge tables.
     */
    public function truncateAll(): void
    {
        TableMetadata::truncate();
        BusinessRule::truncate();
        QueryPattern::truncate();
    }

    /**
     * Load table metadata from JSON files.
     */
    public function loadTables(string $path): int
    {
        if (! File::isDirectory($path)) {
            return 0;
        }

        $files = File::glob("{$path}/*.json");
        $count = 0;

        foreach ($files as $file) {
            if ($this->loadTableFile($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Load a single table metadata file.
     */
    protected function loadTableFile(string $filePath): bool
    {
        try {
            $data = json_decode(File::get($filePath), true, 512, JSON_THROW_ON_ERROR);

            $tableName = $data['table_name'] ?? null;
            if (! $tableName) {
                return false;
            }

            $connection = $data['connection'] ?? 'default';

            // Convert columns format if needed
            $columns = $data['table_columns'] ?? $data['columns'] ?? [];

            TableMetadata::updateOrCreate(
                [
                    'connection' => $connection,
                    'table_name' => $tableName,
                ],
                [
                    'description' => $data['table_description'] ?? $data['description'] ?? null,
                    'columns' => $columns,
                    'relationships' => $data['relationships'] ?? [],
                    'data_quality_notes' => $data['data_quality_notes'] ?? [],
                ]
            );

            return true;
        } catch (\JsonException $e) {
            report($e);

            return false;
        }
    }

    /**
     * Load business rules from JSON files.
     */
    public function loadBusinessRules(string $path): int
    {
        if (! File::isDirectory($path)) {
            return 0;
        }

        $files = File::glob("{$path}/*.json");
        $count = 0;

        foreach ($files as $file) {
            $count += $this->loadBusinessRulesFile($file);
        }

        return $count;
    }

    /**
     * Load business rules from a single file.
     */
    protected function loadBusinessRulesFile(string $filePath): int
    {
        try {
            $data = json_decode(File::get($filePath), true, 512, JSON_THROW_ON_ERROR);
            $count = 0;

            // Load metrics
            foreach ($data['metrics'] ?? [] as $metric) {
                BusinessRule::updateOrCreate(
                    [
                        'type' => BusinessRuleType::Metric,
                        'name' => $metric['name'],
                    ],
                    [
                        'description' => $metric['definition'] ?? $metric['description'] ?? '',
                        'conditions' => [
                            'table' => $metric['table'] ?? null,
                            'calculation' => $metric['calculation'] ?? null,
                        ],
                    ]
                );
                $count++;
            }

            // Load business rules
            foreach ($data['business_rules'] ?? $data['rules'] ?? [] as $index => $rule) {
                if (is_string($rule)) {
                    BusinessRule::updateOrCreate(
                        [
                            'type' => BusinessRuleType::Rule,
                            'name' => "Rule #{$index}",
                        ],
                        [
                            'description' => $rule,
                            'conditions' => [],
                        ]
                    );
                } else {
                    BusinessRule::updateOrCreate(
                        [
                            'type' => BusinessRuleType::Rule,
                            'name' => $rule['name'] ?? "Rule #{$index}",
                        ],
                        [
                            'description' => $rule['description'] ?? $rule['rule'] ?? '',
                            'conditions' => [
                                'tables_affected' => $rule['tables_affected'] ?? [],
                            ],
                        ]
                    );
                }
                $count++;
            }

            // Load gotchas
            foreach ($data['common_gotchas'] ?? $data['gotchas'] ?? [] as $index => $gotcha) {
                if (is_string($gotcha)) {
                    BusinessRule::updateOrCreate(
                        [
                            'type' => BusinessRuleType::Gotcha,
                            'name' => "Gotcha #{$index}",
                        ],
                        [
                            'description' => $gotcha,
                            'conditions' => [],
                        ]
                    );
                } else {
                    BusinessRule::updateOrCreate(
                        [
                            'type' => BusinessRuleType::Gotcha,
                            'name' => $gotcha['issue'] ?? $gotcha['name'] ?? "Gotcha #{$index}",
                        ],
                        [
                            'description' => $gotcha['description'] ?? $gotcha['issue'] ?? '',
                            'conditions' => [
                                'tables_affected' => $gotcha['tables_affected'] ?? [],
                                'solution' => $gotcha['solution'] ?? null,
                            ],
                        ]
                    );
                }
                $count++;
            }

            return $count;
        } catch (\JsonException $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Load query patterns from SQL and JSON files.
     */
    public function loadQueryPatterns(string $path): int
    {
        if (! File::isDirectory($path)) {
            return 0;
        }

        $count = 0;

        // Load SQL files
        $sqlFiles = File::glob("{$path}/*.sql");
        foreach ($sqlFiles as $file) {
            $count += $this->loadQueryPatternsSqlFile($file);
        }

        // Load JSON files
        $jsonFiles = File::glob("{$path}/*.json");
        foreach ($jsonFiles as $file) {
            $count += $this->loadQueryPatternsJsonFile($file);
        }

        return $count;
    }

    /**
     * Load query patterns from a SQL file.
     */
    protected function loadQueryPatternsSqlFile(string $filePath): int
    {
        $content = File::get($filePath);
        $count = 0;

        // Pattern format:
        // -- <query name>name</query name>
        // -- <query description>
        // -- description text
        // -- </query description>
        // -- <query>
        // SELECT ...
        // -- </query>

        $regex = '/--\s*<query\s+name>([^<]+)<\/query\s+name>\s*(?:--\s*<query\s+description>\s*([\s\S]*?)--\s*<\/query\s+description>\s*)?--\s*<query>\s*([\s\S]*?)--\s*<\/query>/i';

        if (preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[1]);
                $description = isset($match[2]) ? trim(preg_replace('/^--\s*/m', '', $match[2])) : '';
                $sql = trim($match[3]);

                // Extract tables from SQL
                $tablesUsed = $this->extractTablesFromSql($sql);

                QueryPattern::updateOrCreate(
                    ['name' => $name],
                    [
                        'question' => $description,
                        'sql' => $sql,
                        'summary' => $description,
                        'tables_used' => $tablesUsed,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Load query patterns from a JSON file.
     */
    protected function loadQueryPatternsJsonFile(string $filePath): int
    {
        try {
            $data = json_decode(File::get($filePath), true, 512, JSON_THROW_ON_ERROR);
            $count = 0;

            $patterns = $data['patterns'] ?? $data['queries'] ?? [$data];

            foreach ($patterns as $pattern) {
                if (! isset($pattern['name']) && ! isset($pattern['question'])) {
                    continue;
                }

                $name = $pattern['name'] ?? $pattern['question'] ?? 'Query';
                $sql = $pattern['sql'] ?? $pattern['query'] ?? '';

                QueryPattern::updateOrCreate(
                    ['name' => $name],
                    [
                        'question' => $pattern['question'] ?? $pattern['name'] ?? '',
                        'sql' => $sql,
                        'summary' => $pattern['summary'] ?? $pattern['description'] ?? null,
                        'tables_used' => $pattern['tables_used'] ?? $pattern['tables'] ?? $this->extractTablesFromSql($sql),
                        'data_quality_notes' => $pattern['data_quality_notes'] ?? $pattern['notes'] ?? null,
                    ]
                );
                $count++;
            }

            return $count;
        } catch (\JsonException $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Extract table names from SQL.
     *
     * @return array<string>
     */
    protected function extractTablesFromSql(string $sql): array
    {
        $tables = [];

        // Match FROM clause
        if (preg_match_all('/\bFROM\s+([`"\[]?[\w]+[`"\]]?)/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Match JOIN clauses
        if (preg_match_all('/\bJOIN\s+([`"\[]?[\w]+[`"\]]?)/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Clean up table names (remove quotes)
        $tables = array_map(fn ($t) => trim($t, '`"[]'), $tables);

        return array_values(array_unique($tables));
    }

    /**
     * Load all knowledge from the configured path.
     */
    public function loadAll(?string $basePath = null): array
    {
        $basePath = $basePath ?? config('sql-agent.knowledge.path');

        return [
            'tables' => $this->loadTables($basePath.'/tables'),
            'business_rules' => $this->loadBusinessRules($basePath.'/business'),
            'query_patterns' => $this->loadQueryPatterns($basePath.'/queries'),
        ];
    }
}
