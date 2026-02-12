<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Support\UserResolver;

class ConversationService
{
    public function __construct(
        protected UserResolver $userResolver,
    ) {}

    /**
     * Find a conversation that belongs to the current user.
     */
    public function findForCurrentUser(int $id): ?Conversation
    {
        $conversation = Conversation::find($id);

        if (! $conversation) {
            return null;
        }

        if ($this->userResolver->isEnabled() && $conversation->user_id !== $this->userResolver->id()) {
            return null;
        }

        return $conversation;
    }

    /**
     * Find a conversation with messages that belongs to the current user.
     */
    public function findForCurrentUserWithMessages(int $id): ?Conversation
    {
        $conversation = Conversation::with('messages')->find($id);

        if (! $conversation) {
            return null;
        }

        if ($this->userResolver->isEnabled() && $conversation->user_id !== $this->userResolver->id()) {
            return null;
        }

        return $conversation;
    }

    /**
     * Create a new conversation for the current user.
     */
    public function createForCurrentUser(string $connection): Conversation
    {
        return Conversation::create([
            'user_id' => $this->userResolver->id(),
            'connection' => $connection,
        ]);
    }

    /**
     * Find an existing conversation or create a new one.
     */
    public function findOrCreate(?int $conversationId, string $connection): Conversation
    {
        if ($conversationId) {
            $conversation = $this->findForCurrentUser($conversationId);

            if ($conversation) {
                return $conversation;
            }
        }

        return $this->createForCurrentUser($connection);
    }

    /**
     * Add a message to a conversation.
     */
    public function addMessage(
        int $conversationId,
        MessageRole $role,
        string $content,
        ?array $queries = null,
        ?array $metadata = null,
    ): Message {
        return Message::create([
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'queries' => $queries,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get conversation history formatted for the LLM.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getHistory(int $conversationId): array
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->whereIn('role', [MessageRole::User, MessageRole::Assistant])
            ->orderBy('created_at')
            ->get();

        // Remove the last message (current user question)
        if ($messages->isNotEmpty() && $messages->last()->role === MessageRole::User) {
            $messages = $messages->slice(0, -1);
        }

        // Limit history
        $historyLength = config('sql-agent.agent.chat_history_length');
        if ($messages->count() > $historyLength) {
            $messages = $messages->slice(-$historyLength);
        }

        return $messages->map(fn (Message $msg) => [
            'role' => $msg->role === MessageRole::User ? 'user' : 'assistant',
            'content' => $msg->content,
        ])->values()->toArray();
    }
}
