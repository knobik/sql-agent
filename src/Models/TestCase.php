<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $category
 * @property string $name
 * @property string $question
 * @property array<string, mixed>|null $expected_values
 * @property string|null $golden_sql
 * @property array<int, mixed>|null $golden_result
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TestCase extends Model
{
    use HasFactory;

    protected $table = 'sql_agent_test_cases';

    protected $fillable = [
        'category',
        'name',
        'question',
        'expected_values',
        'golden_sql',
        'golden_result',
    ];

    protected function casts(): array
    {
        return [
            'expected_values' => 'array',
            'golden_result' => 'array',
        ];
    }

    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeWithGoldenSql($query)
    {
        return $query->whereNotNull('golden_sql');
    }

    public function scopeWithExpectedValues($query)
    {
        return $query->whereNotNull('expected_values');
    }

    public function hasGoldenSql(): bool
    {
        return ! empty($this->golden_sql);
    }

    public function hasExpectedValues(): bool
    {
        return ! empty($this->expected_values);
    }

    public function hasGoldenResult(): bool
    {
        return ! empty($this->golden_result);
    }

    public function matchesExpectedValues(array $result): bool
    {
        if (! $this->hasExpectedValues()) {
            return true;
        }

        foreach ($this->expected_values as $key => $expected) {
            // Support both flat and nested value structures
            $actual = $this->extractValue($result, $key);

            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    protected function extractValue(array $data, string $key): mixed
    {
        // Support dot notation for nested values
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            if (! is_array($value) || ! array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function compareResults(array $generatedResult): array
    {
        return [
            'matches_expected_values' => $this->matchesExpectedValues($generatedResult),
            'matches_golden_result' => $this->hasGoldenResult()
                ? $this->golden_result === $generatedResult
                : null,
            'expected_values' => $this->expected_values,
            'golden_result' => $this->golden_result,
            'actual_result' => $generatedResult,
        ];
    }
}
