<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

class QueryPatternData
{
    public function __construct(
        public string $name,
        public string $question,
        public string $sql,
        public ?string $summary = null,
        /** @var array<string> */
        public array $tablesUsed = [],
        public ?string $dataQualityNotes = null,
    ) {}

    public function toPromptString(): string
    {
        $output = "### {$this->name}\n";
        $output .= "**Question:** {$this->question}\n";

        if ($this->summary) {
            $output .= "**Summary:** {$this->summary}\n";
        }

        $output .= "```sql\n{$this->sql}\n```\n";

        if ($this->tablesUsed) {
            $tables = implode(', ', $this->tablesUsed);
            $output .= "Tables used: {$tables}\n";
        }

        if ($this->dataQualityNotes) {
            $output .= "Note: {$this->dataQualityNotes}\n";
        }

        return $output;
    }

    public function usesTable(string $tableName): bool
    {
        return in_array($tableName, $this->tablesUsed, true);
    }
}
