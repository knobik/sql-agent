<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Illuminate\Support\Collection;
use Knobik\SqlAgent\Data\BusinessRuleData;
use Knobik\SqlAgent\Enums\BusinessRuleType;
use Knobik\SqlAgent\Models\BusinessRule;

class BusinessRulesLoader
{
    /**
     * Load business rules from the database.
     *
     * @return Collection<int, BusinessRuleData>
     */
    public function load(): Collection
    {
        return BusinessRule::all()->map(fn (BusinessRule $model) => $this->modelToBusinessRuleData($model));
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
