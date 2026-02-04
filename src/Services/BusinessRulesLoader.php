<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Knobik\SqlAgent\Data\BusinessRuleData;
use Knobik\SqlAgent\Enums\BusinessRuleType;
use Knobik\SqlAgent\Models\BusinessRule;

class BusinessRulesLoader
{
    /**
     * Load business rules from the configured source.
     *
     * @return Collection<int, BusinessRuleData>
     */
    public function load(): Collection
    {
        $source = config('sql-agent.knowledge.source', 'files');

        return match ($source) {
            'files' => $this->loadFromFiles(),
            'database' => $this->loadFromDatabase(),
            default => throw new \InvalidArgumentException("Unknown knowledge source: {$source}"),
        };
    }

    /**
     * Format loaded business rules as a prompt string.
     */
    public function format(): string
    {
        $rules = $this->load();

        if ($rules->isEmpty()) {
            return 'No business rules defined.';
        }

        $metrics = $rules->filter(fn (BusinessRuleData $r) => $r->isMetric());
        $businessRules = $rules->filter(fn (BusinessRuleData $r) => $r->isRule());
        $gotchas = $rules->filter(fn (BusinessRuleData $r) => $r->isGotcha());

        $sections = [];

        if ($metrics->isNotEmpty()) {
            $sections[] = "## Metrics & Definitions\n\n".$metrics
                ->map(fn (BusinessRuleData $r) => $r->toPromptString())
                ->implode("\n\n");
        }

        if ($businessRules->isNotEmpty()) {
            $sections[] = "## Business Rules\n\n".$businessRules
                ->map(fn (BusinessRuleData $r) => $r->toPromptString())
                ->implode("\n");
        }

        if ($gotchas->isNotEmpty()) {
            $sections[] = "## Common Gotchas\n\n".$gotchas
                ->map(fn (BusinessRuleData $r) => $r->toPromptString())
                ->implode("\n\n");
        }

        return implode("\n\n", $sections);
    }

    /**
     * Load business rules from JSON files.
     *
     * @return Collection<int, BusinessRuleData>
     */
    protected function loadFromFiles(): Collection
    {
        $path = config('sql-agent.knowledge.path').'/business';

        if (! File::isDirectory($path)) {
            return collect();
        }

        $files = File::glob("{$path}/*.json");

        return collect($files)
            ->flatMap(fn (string $file) => $this->parseJsonFile($file))
            ->filter();
    }

    /**
     * Load business rules from the database.
     *
     * @return Collection<int, BusinessRuleData>
     */
    protected function loadFromDatabase(): Collection
    {
        return BusinessRule::all()->map(fn (BusinessRule $model) => $this->modelToBusinessRuleData($model));
    }

    /**
     * Parse a JSON file into BusinessRuleData objects.
     *
     * @return Collection<int, BusinessRuleData>
     */
    protected function parseJsonFile(string $filePath): Collection
    {
        try {
            $data = json_decode(File::get($filePath), true, 512, JSON_THROW_ON_ERROR);
            $rules = collect();

            // Parse metrics
            foreach ($data['metrics'] ?? [] as $metric) {
                $rules->push(new BusinessRuleData(
                    name: $metric['name'],
                    description: $metric['definition'] ?? $metric['description'] ?? '',
                    type: BusinessRuleType::Metric,
                    calculation: $metric['calculation'] ?? null,
                    table: $metric['table'] ?? null,
                ));
            }

            // Parse business rules (can be array of strings or objects)
            foreach ($data['business_rules'] ?? $data['rules'] ?? [] as $rule) {
                if (is_string($rule)) {
                    $rules->push(new BusinessRuleData(
                        name: 'Business Rule',
                        description: $rule,
                        type: BusinessRuleType::Rule,
                    ));
                } else {
                    $rules->push(new BusinessRuleData(
                        name: $rule['name'] ?? 'Business Rule',
                        description: $rule['description'] ?? $rule['rule'] ?? '',
                        type: BusinessRuleType::Rule,
                        tablesAffected: $rule['tables_affected'] ?? [],
                    ));
                }
            }

            // Parse gotchas
            foreach ($data['common_gotchas'] ?? $data['gotchas'] ?? [] as $gotcha) {
                if (is_string($gotcha)) {
                    $rules->push(new BusinessRuleData(
                        name: 'Gotcha',
                        description: $gotcha,
                        type: BusinessRuleType::Gotcha,
                    ));
                } else {
                    $rules->push(new BusinessRuleData(
                        name: $gotcha['issue'] ?? $gotcha['name'] ?? 'Gotcha',
                        description: $gotcha['description'] ?? $gotcha['issue'] ?? '',
                        type: BusinessRuleType::Gotcha,
                        tablesAffected: $gotcha['tables_affected'] ?? [],
                        solution: $gotcha['solution'] ?? null,
                    ));
                }
            }

            return $rules;
        } catch (\JsonException $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Convert a BusinessRule model to a BusinessRuleData DTO.
     */
    protected function modelToBusinessRuleData(BusinessRule $model): BusinessRuleData
    {
        return new BusinessRuleData(
            name: $model->name,
            description: $model->description,
            type: $model->type,
            calculation: $model->getCalculation(),
            table: $model->conditions['table'] ?? null,
            tablesAffected: $model->getTablesAffected(),
            solution: $model->getSolution(),
        );
    }

    /**
     * Get rules by type.
     *
     * @return Collection<int, BusinessRuleData>
     */
    public function getByType(BusinessRuleType $type): Collection
    {
        return $this->load()->filter(fn (BusinessRuleData $r) => $r->type === $type);
    }

    /**
     * Get metrics only.
     *
     * @return Collection<int, BusinessRuleData>
     */
    public function getMetrics(): Collection
    {
        return $this->getByType(BusinessRuleType::Metric);
    }

    /**
     * Get business rules only.
     *
     * @return Collection<int, BusinessRuleData>
     */
    public function getRules(): Collection
    {
        return $this->getByType(BusinessRuleType::Rule);
    }

    /**
     * Get gotchas only.
     *
     * @return Collection<int, BusinessRuleData>
     */
    public function getGotchas(): Collection
    {
        return $this->getByType(BusinessRuleType::Gotcha);
    }
}
