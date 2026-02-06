<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $connection
 * @property string $table_name
 * @property string|null $description
 * @property array<int, array<string, mixed>>|null $columns
 * @property array<int, array<string, mixed>>|null $relationships
 * @property array<int, string>|null $data_quality_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TableMetadata extends Model
{
    use HasFactory;

    protected $table = 'sql_agent_table_metadata';

    protected $fillable = [
        'connection',
        'table_name',
        'description',
        'columns',
        'relationships',
        'data_quality_notes',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'relationships' => 'array',
            'data_quality_notes' => 'array',
        ];
    }

    public function scopeForConnection($query, ?string $connection = null)
    {
        $connection = $connection ?? config('sql-agent.database.connection', 'default');

        return $query->where('connection', $connection);
    }

    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function getColumnNames(): array
    {
        return collect($this->columns ?? [])
            ->pluck('name')
            ->all();
    }

    public function getColumn(string $name): ?array
    {
        return collect($this->columns ?? [])
            ->firstWhere('name', $name);
    }

    public function getRelationshipsForTable(string $tableName): array
    {
        return collect($this->relationships ?? [])
            ->filter(fn ($rel) => ($rel['related_table'] ?? null) === $tableName)
            ->all();
    }
}
