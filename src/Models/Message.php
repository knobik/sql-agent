<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Knobik\SqlAgent\Enums\MessageRole;

/**
 * @property int $id
 * @property int $conversation_id
 * @property MessageRole $role
 * @property string $content
 * @property array<int, array{sql: string, connection: string|null}>|null $queries
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $usage
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Message extends Model
{
    use HasFactory;

    protected $table = 'sql_agent_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'queries',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'queries' => 'array',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function scopeOfRole($query, MessageRole $role)
    {
        return $query->where('role', $role);
    }

    public function scopeFromUser($query)
    {
        return $query->ofRole(MessageRole::User);
    }

    public function scopeFromAssistant($query)
    {
        return $query->ofRole(MessageRole::Assistant);
    }

    public function scopeWithQueries($query)
    {
        return $query->whereNotNull('queries');
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function isSystem(): bool
    {
        return $this->role === MessageRole::System;
    }

    public function isTool(): bool
    {
        return $this->role === MessageRole::Tool;
    }

    public function hasQueries(): bool
    {
        return ! empty($this->queries);
    }

    public function getQueries(): array
    {
        return $this->queries ?? [];
    }

    public function getToolName(): ?string
    {
        return $this->metadata['tool_name'] ?? null;
    }

    public function getToolCallId(): ?string
    {
        return $this->metadata['tool_call_id'] ?? null;
    }

    public function getExecutionTime(): ?float
    {
        return $this->metadata['execution_time'] ?? null;
    }

    public function getUsageAttribute(): ?array
    {
        return $this->metadata['usage'] ?? null;
    }
}
