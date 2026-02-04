<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Livewire;

use Illuminate\Support\Facades\Auth;
use Knobik\SqlAgent\Agent\SqlAgent;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatComponent extends Component
{
    public ?int $conversationId = null;

    public string $message = '';

    public ?string $connection = null;

    public bool $isLoading = false;

    public string $streamedContent = '';

    public ?string $currentSql = null;

    public ?array $currentResults = null;

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;
        $this->connection = config('sql-agent.database.connection') ?: config('database.default');

        // Verify conversation belongs to current user
        if ($this->conversationId) {
            $conversation = Conversation::find($this->conversationId);
            if (! $conversation || $conversation->user_id !== Auth::id()) {
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

    public function sendMessage(): void
    {
        $message = trim($this->message);

        if (empty($message)) {
            return;
        }

        if ($this->isLoading) {
            return;
        }

        $this->isLoading = true;
        $this->streamedContent = '';
        $this->currentSql = null;
        $this->currentResults = null;
        $this->message = '';

        // Create or get conversation
        if (! $this->conversationId) {
            $conversation = Conversation::create([
                'user_id' => Auth::id(),
                'connection' => $this->connection,
            ]);
            $this->conversationId = $conversation->id;
        } else {
            $conversation = Conversation::find($this->conversationId);
        }

        // Save user message
        Message::create([
            'conversation_id' => $this->conversationId,
            'role' => MessageRole::User,
            'content' => $message,
        ]);

        // Update the conversation title if empty
        $conversation->updateTitleIfEmpty();

        // Dispatch event to update conversation list
        $this->dispatch('conversation-updated');

        // Process with the agent
        $this->processWithAgent($message);
    }

    protected function processWithAgent(string $question): void
    {
        $debugEnabled = config('sql-agent.debug.enabled', false);
        $debugChunks = [];
        $startTime = microtime(true);

        try {
            /** @var SqlAgent $agent */
            $agent = app(SqlAgent::class);

            // Get conversation history (excluding the current question we just added)
            $history = $this->getConversationHistory();

            $fullContent = '';

            // Stream the response with conversation history
            foreach ($agent->stream($question, $this->connection, $history) as $chunk) {
                // Capture raw chunks for debugging
                if ($debugEnabled) {
                    $debugChunks[] = [
                        'time' => round((microtime(true) - $startTime) * 1000, 2),
                        'content' => $chunk->content,
                        'hasContent' => $chunk->hasContent(),
                        'isComplete' => $chunk->isComplete(),
                        'finishReason' => $chunk->finishReason,
                        'toolCalls' => array_map(fn ($tc) => [
                            'name' => $tc->name,
                            'arguments' => $tc->arguments,
                        ], $chunk->toolCalls ?? []),
                    ];
                }

                if ($chunk->hasContent()) {
                    $fullContent .= $chunk->content;
                    $this->streamedContent = $fullContent;

                    // Stream to the browser using Livewire's stream method
                    $this->stream(
                        to: 'streamedContent',
                        content: $chunk->content,
                        replace: false
                    );
                }

                if ($chunk->isComplete()) {
                    break;
                }
            }

            // Get the final SQL and results
            $this->currentSql = $agent->getLastSql();
            $this->currentResults = $agent->getLastResults();

            // Capture debug metadata if enabled
            $metadata = [];
            if ($debugEnabled) {
                $metadata['prompt'] = $agent->getLastPrompt();
                $metadata['iterations'] = $agent->getIterations();
                $metadata['chunks'] = $debugChunks;
                $metadata['timing'] = [
                    'total_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
            }

            // Save assistant message
            Message::create([
                'conversation_id' => $this->conversationId,
                'role' => MessageRole::Assistant,
                'content' => $fullContent,
                'sql' => $this->currentSql,
                'results' => $this->currentResults,
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            $errorMessage = 'An error occurred: '.$e->getMessage();

            $metadata = ['error' => true];
            if ($debugEnabled) {
                $metadata['error_details'] = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => array_slice($e->getTrace(), 0, 5),
                ];
                $metadata['chunks'] = $debugChunks;
                $metadata['timing'] = [
                    'total_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
                // Include prompt data if available (agent was created before error)
                if (isset($agent)) {
                    $metadata['prompt'] = $agent->getLastPrompt();
                    $metadata['iterations'] = $agent->getIterations();
                }
            }

            Message::create([
                'conversation_id' => $this->conversationId,
                'role' => MessageRole::Assistant,
                'content' => $errorMessage,
                'metadata' => $metadata,
            ]);

            $this->streamedContent = $errorMessage;
        } finally {
            $this->isLoading = false;
            $this->streamedContent = '';
        }
    }

    #[On('load-conversation')]
    public function loadConversation(int $conversationId): void
    {
        $conversation = Conversation::find($conversationId);

        if (! $conversation || $conversation->user_id !== Auth::id()) {
            return;
        }

        $this->conversationId = $conversationId;
        $this->connection = $conversation->connection ?? config('database.default');
        $this->message = '';
        $this->streamedContent = '';
        $this->currentSql = null;
        $this->currentResults = null;
    }

    #[On('new-conversation')]
    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->message = '';
        $this->streamedContent = '';
        $this->currentSql = null;
        $this->currentResults = null;
    }

    /**
     * Get conversation history formatted for the LLM.
     * Excludes the most recent user message (the current question).
     * Limits history to the configured chat_history_length.
     */
    protected function getConversationHistory(): array
    {
        if (! $this->conversationId) {
            return [];
        }

        $messages = Message::where('conversation_id', $this->conversationId)
            ->whereIn('role', [MessageRole::User, MessageRole::Assistant])
            ->orderBy('created_at')
            ->get();

        // Remove the last message if it's the current user question
        if ($messages->isNotEmpty() && $messages->last()->role === MessageRole::User) {
            $messages = $messages->slice(0, -1);
        }

        // Limit to configured chat history length (take most recent messages)
        $historyLength = config('sql-agent.agent.chat_history_length', 10);
        if ($messages->count() > $historyLength) {
            $messages = $messages->slice(-$historyLength);
        }

        // Format for the LLM
        return $messages->map(fn (Message $msg) => [
            'role' => $msg->role === MessageRole::User ? 'user' : 'assistant',
            'content' => $msg->content,
        ])->values()->toArray();
    }

    public function copyToClipboard(string $text): void
    {
        $this->dispatch('copy-to-clipboard', text: $text);
    }

    public function exportJson(): void
    {
        if ($this->conversationId) {
            $this->redirect(route('sql-agent.export.json', $this->conversationId));
        }
    }

    public function exportCsv(): void
    {
        if ($this->conversationId) {
            $this->redirect(route('sql-agent.export.csv', $this->conversationId));
        }
    }

    public function render()
    {
        return view('sql-agent::livewire.chat-component');
    }
}
