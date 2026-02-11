<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Knobik\SqlAgent\Models\QueryPattern;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;
use RuntimeException;

class SaveQueryTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('save_validated_query')
            ->for('Save a validated query pattern to the knowledge base. Use this when you have successfully executed a SQL query that correctly answers a user question. This helps future queries by providing proven patterns.')
            ->withStringParameter('name', 'A short, descriptive name for the query pattern (max 100 characters).')
            ->withStringParameter('question', 'The natural language question this query answers.')
            ->withStringParameter('sql', 'The validated SQL query that correctly answers the question.')
            ->withStringParameter('summary', 'A brief summary of what the query does and what data it returns.')
            ->withArrayParameter('tables_used', 'List of table names used in the query.', new StringSchema('table', 'A table name'))
            ->withStringParameter('data_quality_notes', 'Optional: Notes about data quality issues, edge cases, or important considerations for this query.', required: false)
            ->using($this);
    }

    public function __invoke(
        string $name,
        string $question,
        string $sql,
        string $summary,
        array $tables_used,
        ?string $data_quality_notes = null,
    ): string {
        $name = trim($name);
        $question = trim($question);
        $sql = trim($sql);
        $summary = trim($summary);
        $dataQualityNotes = $data_quality_notes !== null ? trim($data_quality_notes) : null;

        if (empty($name)) {
            throw new RuntimeException('Name is required.');
        }

        if (strlen($name) > 100) {
            throw new RuntimeException('Name must be 100 characters or less.');
        }

        if (empty($question)) {
            throw new RuntimeException('Question is required.');
        }

        if (empty($sql)) {
            throw new RuntimeException('SQL is required.');
        }

        if (empty($summary)) {
            throw new RuntimeException('Summary is required.');
        }

        if (empty($tables_used)) {
            throw new RuntimeException('Tables used must be a non-empty array.');
        }

        $sqlUpper = strtoupper(trim($sql));
        $allowedStatements = config('sql-agent.sql.allowed_statements');
        $startsWithAllowed = false;

        foreach ($allowedStatements as $statement) {
            if (str_starts_with($sqlUpper, $statement)) {
                $startsWithAllowed = true;
                break;
            }
        }

        if (! $startsWithAllowed) {
            throw new RuntimeException(
                'SQL must be a '.implode(' or ', $allowedStatements).' statement.'
            );
        }

        $tablesUsed = array_values(array_filter(array_map(function ($table) {
            return is_string($table) ? trim($table) : null;
        }, $tables_used)));

        if (empty($tablesUsed)) {
            throw new RuntimeException('Tables used must contain at least one valid table name.');
        }

        $existing = QueryPattern::search($question)->first();
        if ($existing && strtolower($existing->question) === strtolower($question)) {
            throw new RuntimeException("A query pattern with a similar question already exists: '{$existing->name}'");
        }

        $queryPattern = QueryPattern::create([
            'name' => $name,
            'question' => $question,
            'sql' => $sql,
            'summary' => $summary,
            'tables_used' => $tablesUsed,
            'data_quality_notes' => $dataQualityNotes ?: null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Query pattern saved successfully.',
            'pattern_id' => $queryPattern->id,
            'name' => $queryPattern->name,
            'tables_used' => $queryPattern->tables_used,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
