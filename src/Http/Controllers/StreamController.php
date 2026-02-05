<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Knobik\SqlAgent\Agent\SqlAgent;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Support\UserResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function __invoke(Request $request, SqlAgent $agent, UserResolver $userResolver): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|integer|exists:sql_agent_conversations,id',
            'connection' => 'nullable|string',
        ]);

        $question = $request->input('message');
        $conversationId = $request->input('conversation_id');
        $connection = $request->input('connection') ?: config('sql-agent.database.connection') ?: config('database.default');

        // Verify conversation belongs to current user
        if ($conversationId) {
            $conversation = Conversation::find($conversationId);
            if (! $conversation) {
                $conversationId = null;
            } elseif ($userResolver->isEnabled() && $conversation->user_id !== $userResolver->id()) {
                $conversationId = null;
            }
        }

        return new StreamedResponse(function () use ($agent, $question, $conversationId, $connection, $userResolver) {
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Create or get conversation
            if (! $conversationId) {
                $conversation = Conversation::create([
                    'user_id' => $userResolver->id(),
                    'connection' => $connection,
                ]);
                $conversationId = $conversation->id;
            } else {
                $conversation = Conversation::find($conversationId);
            }

            // Save user message
            Message::create([
                'conversation_id' => $conversationId,
                'role' => MessageRole::User,
                'content' => $question,
            ]);

            // Update conversation title
            $conversation->updateTitleIfEmpty();

            // Send conversation ID
            $this->sendEvent('conversation', ['id' => $conversationId]);

            // Get conversation history
            $history = $this->getConversationHistory($conversationId);

            $fullContent = '';
            $lastSql = null;
            $lastResults = null;

            try {
                foreach ($agent->stream($question, $connection, $history) as $chunk) {
                    if ($chunk->hasContent()) {
                        $fullContent .= $chunk->content;
                        $this->sendEvent('content', ['text' => $chunk->content]);
                    }

                    if ($chunk->isComplete()) {
                        break;
                    }
                }

                $lastSql = $agent->getLastSql();
                $lastResults = $agent->getLastResults();

                // Save assistant message
                $metadata = [];
                if (config('sql-agent.debug.enabled', false)) {
                    $metadata['prompt'] = $agent->getLastPrompt();
                    $metadata['iterations'] = $agent->getIterations();
                }

                Message::create([
                    'conversation_id' => $conversationId,
                    'role' => MessageRole::Assistant,
                    'content' => $fullContent,
                    'sql' => $lastSql,
                    'results' => $lastResults,
                    'metadata' => $metadata ?: null,
                ]);

                // Send completion event
                $this->sendEvent('done', [
                    'sql' => $lastSql,
                    'hasResults' => ! empty($lastResults),
                    'resultCount' => $lastResults ? count($lastResults) : 0,
                ]);

            } catch (\Throwable $e) {
                $errorMessage = 'An error occurred: '.$e->getMessage();

                Message::create([
                    'conversation_id' => $conversationId,
                    'role' => MessageRole::Assistant,
                    'content' => $errorMessage,
                    'metadata' => ['error' => true],
                ]);

                $this->sendEvent('error', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data)."\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    protected function getConversationHistory(int $conversationId): array
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
        $historyLength = config('sql-agent.agent.chat_history_length', 10);
        if ($messages->count() > $historyLength) {
            $messages = $messages->slice(-$historyLength);
        }

        return $messages->map(fn (Message $msg) => [
            'role' => $msg->role === MessageRole::User ? 'user' : 'assistant',
            'content' => $msg->content,
        ])->values()->toArray();
    }
}
