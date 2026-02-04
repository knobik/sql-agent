<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'sql_agent_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'connection',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForConnection($query, string $connection)
    {
        return $query->where('connection', $connection);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function getLastMessage(): ?Message
    {
        return $this->messages()->latest()->first();
    }

    public function getMessageCount(): int
    {
        return $this->messages()->count();
    }

    public function generateTitle(): string
    {
        $firstUserMessage = $this->messages()
            ->where('role', 'user')
            ->first();

        if (! $firstUserMessage) {
            return 'New Conversation';
        }

        $content = $firstUserMessage->content;

        return strlen($content) > 50
            ? substr($content, 0, 47).'...'
            : $content;
    }

    public function updateTitleIfEmpty(): void
    {
        if (empty($this->title)) {
            $this->update(['title' => $this->generateTitle()]);
        }
    }
}
