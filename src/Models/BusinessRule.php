<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Knobik\SqlAgent\Enums\BusinessRuleType;

/**
 * @property int $id
 * @property BusinessRuleType $type
 * @property string $name
 * @property string $description
 * @property array<string, mixed>|null $conditions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BusinessRule extends Model
{
    use HasFactory;

    protected $table = 'sql_agent_business_rules';

    protected $fillable = [
        'type',
        'name',
        'description',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'type' => BusinessRuleType::class,
            'conditions' => 'array',
        ];
    }

    public function scopeOfType($query, BusinessRuleType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeMetrics($query)
    {
        return $query->ofType(BusinessRuleType::Metric);
    }

    public function scopeRules($query)
    {
        return $query->ofType(BusinessRuleType::Rule);
    }

    public function scopeGotchas($query)
    {
        return $query->ofType(BusinessRuleType::Gotcha);
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

    public function getTablesAffected(): array
    {
        return $this->conditions['tables_affected'] ?? [];
    }

    public function getCalculation(): ?string
    {
        return $this->conditions['calculation'] ?? null;
    }

    public function getSolution(): ?string
    {
        return $this->conditions['solution'] ?? null;
    }
}
