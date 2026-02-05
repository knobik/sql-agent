<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Livewire;

use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Support\UserResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatComponent extends Component
{
    public ?int $conversationId = null;

    public ?string $connection = null;

    public bool $isLoading = false;

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;
        $this->connection = config('sql-agent.database.connection') ?: config('database.default');

        // Verify conversation belongs to current user (if user tracking enabled)
        if ($this->conversationId) {
            $conversation = Conversation::find($this->conversationId);
            $userResolver = app(UserResolver::class);

            if (! $conversation) {
                $this->conversationId = null;
            } elseif ($userResolver->isEnabled() && $conversation->user_id !== $userResolver->id()) {
                $this->conversationId = null;
            }
        }
    }

    #[Computed]
    public function conversation(): ?Conversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return Conversation::with('messages')->find($this->conversationId);
    }

    #[Computed]
    public function messages(): array
    {
        if (! $this->conversation) {
            return [];
        }

        return $this->conversation->messages()
            ->whereIn('role', [MessageRole::User, MessageRole::Assistant])
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    #[Computed]
    public function connections(): array
    {
        $connections = array_keys(config('database.connections', []));

        return array_combine($connections, $connections);
    }

    #[On('load-conversation')]
    public function loadConversation(int $conversationId): void
    {
        $conversation = Conversation::find($conversationId);
        $userResolver = app(UserResolver::class);

        if (! $conversation) {
            return;
        }

        // Check ownership only if user tracking is enabled
        if ($userResolver->isEnabled() && $conversation->user_id !== $userResolver->id()) {
            return;
        }

        $this->conversationId = $conversationId;
        $this->connection = $conversation->connection ?? config('database.default');
    }

    #[On('new-conversation')]
    public function newConversation(): void
    {
        $this->conversationId = null;
    }

    public function copyToClipboard(string $text): void
    {
        $this->dispatch('copy-to-clipboard', text: $text);
    }

    public function render()
    {
        return view('sql-agent::livewire.chat-component');
    }
}
