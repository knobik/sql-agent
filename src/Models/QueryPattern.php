<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Knobik\SqlAgent\Contracts\Searchable;

/**
 * @property int $id
 * @property string $name
 * @property string $question
 * @property string $sql
 * @property string|null $summary
 * @property array<int, string>|null $tables_used
 * @property string|null $data_quality_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class QueryPattern extends Model implements Searchable
{
    use HasFactory;

    protected $table = 'sql_agent_query_patterns';

    protected $fillable = [
        'name',
        'question',
        'sql',
        'summary',
        'tables_used',
        'data_quality_notes',
    ];

    protected function casts(): array
    {
        return [
            'tables_used' => 'array',
        ];
    }

    public function getSearchableColumns(): array
    {
        return ['name', 'question', 'summary'];
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'question' => $this->question,
            'summary' => $this->summary,
            'sql' => $this->sql,
        ];
    }

    public function scopeUsingTable($query, string $tableName)
    {
        return $query->whereJsonContains('tables_used', $tableName);
    }

    public function scopeSearch($query, string $term)
    {
        $term = '%'.strtolower($term).'%';

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(question) LIKE ?', [$term])
                ->orWhereRaw('LOWER(summary) LIKE ?', [$term]);
        });
    }

    public function usesTable(string $tableName): bool
    {
        return in_array($tableName, $this->tables_used ?? [], true);
    }
}
