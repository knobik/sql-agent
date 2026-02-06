<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Knobik\SqlAgent\Enums\LearningCategory;
use Knobik\SqlAgent\Support\TextAnalyzer;

class ErrorAnalyzer
{
    /**
     * Error patterns mapped to categories.
     */
    protected array $patterns = [
        // Schema-related errors
        '/column.*not found/i' => LearningCategory::SchemaFix,
        '/unknown column/i' => LearningCategory::SchemaFix,
        '/table.*not found/i' => LearningCategory::SchemaFix,
        '/unknown table/i' => LearningCategory::SchemaFix,
        '/doesn\'t exist/i' => LearningCategory::SchemaFix,
        '/does not exist/i' => LearningCategory::SchemaFix,
        '/no such table/i' => LearningCategory::SchemaFix,
        '/relation.*does not exist/i' => LearningCategory::SchemaFix,
        '/undefined column/i' => LearningCategory::SchemaFix,

        // Type-related errors
        '/type.*mismatch/i' => LearningCategory::TypeError,
        '/cannot convert/i' => LearningCategory::TypeError,
        '/invalid.*type/i' => LearningCategory::TypeError,
        '/incompatible types/i' => LearningCategory::TypeError,
        '/conversion failed/i' => LearningCategory::TypeError,
        '/invalid input syntax/i' => LearningCategory::TypeError,

        // Query pattern errors
        '/syntax error/i' => LearningCategory::QueryPattern,
        '/unexpected/i' => LearningCategory::QueryPattern,
        '/parse error/i' => LearningCategory::QueryPattern,
        '/mismatched input/i' => LearningCategory::QueryPattern,

        // Data quality errors
        '/data.*truncat/i' => LearningCategory::DataQuality,
        '/out of range/i' => LearningCategory::DataQuality,
        '/duplicate.*key/i' => LearningCategory::DataQuality,
        '/constraint.*violation/i' => LearningCategory::DataQuality,
        '/null.*constraint/i' => LearningCategory::DataQuality,
        '/division by zero/i' => LearningCategory::DataQuality,
    ];

    /**
     * Analyze a SQL error and return structured analysis.
     */
    public function analyze(string $sql, string $error): array
    {
        return [
            'category' => $this->categorize($error),
            'title' => $this->generateTitle($error),
            'description' => $this->generateDescription($sql, $error),
            'tables' => $this->extractTableNames($sql),
        ];
    }

    /**
     * Categorize an error message.
     */
    public function categorize(string $error): LearningCategory
    {
        foreach ($this->patterns as $pattern => $category) {
            if (preg_match($pattern, $error)) {
                return $category;
            }
        }

        return LearningCategory::BusinessLogic;
    }

    /**
     * Extract table names from a SQL query.
     */
    public function extractTableNames(string $sql): array
    {
        return TextAnalyzer::extractTablesFromSql($sql);
    }

    /**
     * Generate a concise title from an error message.
     */
    public function generateTitle(string $error): string
    {
        // Remove SQL state codes like "SQLSTATE[42S02]"
        $error = preg_replace('/SQLSTATE\[[^\]]+\]/', '', $error);

        // Remove driver prefixes like "[HY000]" or "[1054]"
        $error = preg_replace('/\[[A-Z0-9]+\]/', '', $error);

        // Remove "General error:" or similar prefixes
        $error = preg_replace('/^(General error|PDO Exception|SQL Error):\s*/i', '', $error);

        // Clean up extra whitespace
        $error = trim(preg_replace('/\s+/', ' ', $error));

        // Truncate to max 100 characters
        if (mb_strlen($error) > 100) {
            $error = mb_substr($error, 0, 97).'...';
        }

        return $error ?: 'SQL Error';
    }

    /**
     * Generate a description for the learning entry.
     */
    protected function generateDescription(string $sql, string $error): string
    {
        $category = $this->categorize($error);
        $tables = $this->extractTableNames($sql);

        $description = "SQL execution failed with error: {$error}\n\n";
        $description .= "Original query:\n```sql\n{$sql}\n```\n\n";

        if (! empty($tables)) {
            $description .= 'Tables involved: '.implode(', ', $tables)."\n\n";
        }

        $description .= "Category: {$category->label()}\n";
        $description .= 'This learning was auto-generated from a SQL error.';

        return $description;
    }

    /**
     * Extract column name from error message if present.
     */
    public function extractColumnName(string $error): ?string
    {
        // Match patterns like "Unknown column 'column_name'" or "column 'column_name' not found"
        if (preg_match('/(?:unknown column|column)\s*[\'"`]([^\'"`]+)[\'"`]/i', $error, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract table name from error message if present.
     */
    public function extractTableNameFromError(string $error): ?string
    {
        // Match patterns like "Table 'database.table' doesn't exist"
        if (preg_match('/table\s*[\'"`](?:[^.]+\.)?([^\'"`]+)[\'"`]/i', $error, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
