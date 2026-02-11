<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Livewire;

use Illuminate\Contracts\View\View;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\ConversationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Conversation|null $conversation
 * @property-read int $connectionCount
 */
class ChatComponent extends Component
{
    public ?int $conversationId = null;

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;

        // Verify conversation belongs to current user (if user tracking enabled)
        if ($this->conversationId) {
            $conversation = app(ConversationService::class)->findForCurrentUser($this->conversationId);

            if (! $conversation) {
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
    public function connectionCount(): int
    {
        return count(app(ConnectionRegistry::class)->all());
    }

    #[On('load-conversation')]
    public function loadConversation(int $conversationId): void
    {
        $conversation = app(ConversationService::class)->findForCurrentUser($conversationId);

        if (! $conversation) {
            return;
        }

        $this->conversationId = $conversationId;
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

    public function render(): View
    {
        return view('sql-agent::livewire.chat-component'); // @phpstan-ignore argument.type
    }
}
