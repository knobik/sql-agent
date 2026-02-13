<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

class TableSchema
{
    public function __construct(
        public string $tableName,
        public ?string $description = null,
        /** @var array<string, string> column name => description */
        public array $columns = [],
        /** @var array<string> relationship descriptions */
        public array $relationships = [],
        /** @var array<string> */
        public array $dataQualityNotes = [],
        /** @var array<string> */
        public array $useCases = [],
        /** Connection name this table belongs to (used for file-based multi-connection filtering) */
        public ?string $connection = null,
    ) {}

    public function toPromptString(): string
    {
        $output = "## Table: {$this->tableName}\n";

        if ($this->description) {
            $output .= "{$this->description}\n\n";
        }

        if ($this->useCases) {
            $output .= "### Use Cases:\n";
            foreach ($this->useCases as $useCase) {
                $output .= "- {$useCase}\n";
            }
            $output .= "\n";
        }

        $output .= "### Columns:\n";
        foreach ($this->columns as $name => $description) {
            $output .= "- {$name}: {$description}\n";
        }

        if ($this->relationships) {
            $output .= "\n### Relationships:\n";
            foreach ($this->relationships as $relationship) {
                $output .= "- {$relationship}\n";
            }
        }

        if ($this->dataQualityNotes) {
            $output .= "\n### Data Quality Notes:\n";
            foreach ($this->dataQualityNotes as $note) {
                $output .= "- {$note}\n";
            }
        }

        return $output;
    }

    /**
     * @return array<string>
     */
    public function getColumnNames(): array
    {
        return array_keys($this->columns);
    }

    public function getColumn(string $name): ?string
    {
        return $this->columns[$name] ?? null;
    }

    public function hasColumn(string $name): bool
    {
        return array_key_exists($name, $this->columns);
    }
}
