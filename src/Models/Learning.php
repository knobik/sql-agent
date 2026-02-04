<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Knobik\SqlAgent\Contracts\Searchable;
use Knobik\SqlAgent\Enums\LearningCategory;

class Learning extends Model implements Searchable
{
    use HasFactory;

    protected $table = 'sql_agent_learnings';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'sql',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'category' => LearningCategory::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public function getSearchableColumns(): array
    {
        return ['title', 'description'];
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'sql' => $this->sql,
            'category' => $this->category?->value,
        ];
    }

    public function scopeOfCategory($query, LearningCategory $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeSearch($query, string $term)
    {
        $term = '%'.strtolower($term).'%';

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
        });
    }

    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    public function getOriginalQuestion(): ?string
    {
        return $this->metadata['original_question'] ?? null;
    }

    public function getErrorMessage(): ?string
    {
        return $this->metadata['error_message'] ?? null;
    }

    public function getFixedSql(): ?string
    {
        return $this->metadata['fixed_sql'] ?? null;
    }
}
