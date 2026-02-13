<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

use Knobik\SqlAgent\Enums\BusinessRuleType;

class BusinessRuleData
{
    public function __construct(
        public string $name,
        public string $description,
        public BusinessRuleType $type,
        public ?string $calculation = null,
        public ?string $table = null,
        /** @var array<string> */
        public array $tablesAffected = [],
        public ?string $solution = null,
    ) {}

    public function toPromptString(): string
    {
        $output = match ($this->type) {
            BusinessRuleType::Metric => $this->formatMetric(),
            BusinessRuleType::Rule => $this->formatRule(),
            BusinessRuleType::Gotcha => $this->formatGotcha(),
        };

        return $output;
    }

    protected function formatMetric(): string
    {
        $output = "**{$this->name}**: {$this->description}";

        if ($this->table) {
            $output .= " (Table: {$this->table})";
        }

        if ($this->calculation) {
            $output .= "\n  Calculation: `{$this->calculation}`";
        }

        return $output;
    }

    protected function formatRule(): string
    {
        $output = "- {$this->name}: {$this->description}";

        if ($this->tablesAffected) {
            $tables = implode(', ', $this->tablesAffected);
            $output .= " (Tables: {$tables})";
        }

        return $output;
    }

    protected function formatGotcha(): string
    {
        $output = "**{$this->name}**: {$this->description}";

        if ($this->tablesAffected) {
            $tables = implode(', ', $this->tablesAffected);
            $output .= "\n  Affected tables: {$tables}";
        }

        if ($this->solution) {
            $output .= "\n  Solution: {$this->solution}";
        }

        return $output;
    }

    public function isMetric(): bool
    {
        return $this->type === BusinessRuleType::Metric;
    }

    public function isRule(): bool
    {
        return $this->type === BusinessRuleType::Rule;
    }

    public function isGotcha(): bool
    {
        return $this->type === BusinessRuleType::Gotcha;
    }
}
