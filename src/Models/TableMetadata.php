<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Knobik\SqlAgent\Contracts\Searchable;

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
class TableMetadata extends Model implements Searchable
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

    /**
     * Columns indexed by the database full-text search driver.
     *
     * @return array<string>
     */
    public function getSearchableColumns(): array
    {
        return ['table_name', 'description'];
    }

    /**
     * Representation embedded by the pgvector driver.
     *
     * Column names and relationships are included so questions phrased in terms
     * of the data ("revenue per customer") match the tables that hold it.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $columns = [];
        foreach ($this->columns ?? [] as $name => $description) {
            $columns[] = $description ? "{$name} ({$description})" : $name;
        }

        return [
            'table' => $this->table_name,
            'description' => $this->description,
            'columns' => $columns,
            'relationships' => $this->relationships ?? [],
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
