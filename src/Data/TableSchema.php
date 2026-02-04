<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class TableSchema extends Data
{
    public function __construct(
        public string $tableName,
        public ?string $description = null,
        /** @var Collection<int, ColumnInfo> */
        #[DataCollectionOf(ColumnInfo::class)]
        public Collection $columns = new Collection,
        /** @var Collection<int, RelationshipInfo> */
        #[DataCollectionOf(RelationshipInfo::class)]
        public Collection $relationships = new Collection,
        /** @var array<string> */
        public array $dataQualityNotes = [],
        /** @var array<string> */
        public array $useCases = [],
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
        foreach ($this->columns as $column) {
            $output .= "- {$column->toPromptString()}\n";
        }

        if ($this->relationships->isNotEmpty()) {
            $output .= "\n### Relationships:\n";
            foreach ($this->relationships as $relationship) {
                $output .= "- {$relationship->toPromptString()}\n";
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

    public function getColumnNames(): array
    {
        return $this->columns->pluck('name')->all();
    }

    public function getColumn(string $name): ?ColumnInfo
    {
        return $this->columns->first(fn (ColumnInfo $col) => $col->name === $name);
    }

    public function hasColumn(string $name): bool
    {
        return $this->getColumn($name) !== null;
    }

    public function getPrimaryKeyColumns(): Collection
    {
        return $this->columns->filter(fn (ColumnInfo $col) => $col->isPrimaryKey);
    }

    public function getForeignKeyColumns(): Collection
    {
        return $this->columns->filter(fn (ColumnInfo $col) => $col->isForeignKey);
    }
}
