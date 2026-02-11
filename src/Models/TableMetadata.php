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
 * @property array<string, string>|null $columns
 * @property array<string>|null $relationships
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

    public function scopeForConnection($query, string $connection)
    {
        return $query->where('connection', $connection);
    }

    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    /**
     * @return array<string>
     */
    public function getColumnNames(): array
    {
        return array_keys($this->columns ?? []);
    }

    public function getColumn(string $name): ?string
    {
        return ($this->columns ?? [])[$name] ?? null;
    }
}
