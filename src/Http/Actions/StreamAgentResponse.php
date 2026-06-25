<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Knobik\SqlAgent\Agent\SqlAgent;
use Knobik\SqlAgent\Agent\ToolRegistry;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Services\ConversationService;
use Knobik\SqlAgent\Tools\AskUserTool;

class StreamAgentResponse
{
    public function __construct(
        protected SqlAgent $agent,
        protected ConversationService $conversationService,
        protected ToolRegistry $toolRegistry,
    ) {}

    public function __invoke(string $question, int $conversationId): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        $this->sendEvent('conversation', ['id' => $conversationId]);

        $this->configureAskUserTool();

        $history = $this->conversationService->getHistory($conversationId);

        $fullContent = '';
        $fullThinking = '';
        $lastUsage = null;
        $cancelled = false;
        $debugEnabled = config('sql-agent.debug.enabled');
        $startTime = hrtime(true);

        try {
            foreach ($this->agent->stream($question, $history) as $chunk) {
                if ($chunk->hasThinking()) {
                    $fullThinking .= $chunk->thinking;
                    $this->sendEvent('thinking', ['thinking' => $chunk->thinking]);
                }

                if ($chunk->hasContent()) {
                    $fullContent .= $chunk->content;
                    $this->sendEvent('content', ['text' => $chunk->content]);
                }

                if (connection_aborted()) {
                    $cancelled = true;
                    Log::info('SQL Agent: Stream cancelled by user', [
                        'conversation_id' => $conversationId,
                        'question' => $question,
                    ]);
                    break;
                }

                if ($chunk->isComplete()) {
                    $lastUsage = $chunk->usage;
                    $truncated = $chunk->finishReason === 'length';
                    break;
                }
            }

            if (! $cancelled) {
                $this->persistAndFinish($conversationId, $fullContent, $fullThinking, $debugEnabled, $startTime, $lastUsage, $truncated ?? false);
            }
        } catch (\Throwable $e) {
            $this->conversationService->addMessage(
                $conversationId,
                MessageRole::Assistant,
                'An error occurred: '.$e->getMessage(),
                metadata: ['error' => true],
            );

            $this->sendEvent('error', ['message' => $e->getMessage()]);
        }
    }

    protected function persistAndFinish(
        int $conversationId,
        string $fullContent,
        string $fullThinking,
        bool $debugEnabled,
        int $startTime,
        ?array $usage = null,
        bool $truncated = false,
    ): void {
        $allQueries = $this->agent->getAllQueries();

        $metadata = [];
        if ($debugEnabled) {
            $lastPrompt = $this->agent->getLastPrompt();
            $metadata['prompt'] = array_intersect_key($lastPrompt, array_flip(['system', 'tools', 'tools_full']));
            $metadata['iterations'] = $this->agent->getIterations();
            $metadata['timing'] = [
                'total_ms' => round((hrtime(true) - $startTime) / 1e6),
            ];
        }
        if ($fullThinking !== '') {
            $metadata['thinking'] = $fullThinking;
        }
        if ($usage !== null) {
            $metadata['usage'] = $usage;
        }
        if ($truncated) {
            $metadata['truncated'] = true;
        }

        $message = $this->conversationService->addMessage(
            $conversationId,
            MessageRole::Assistant,
            $fullContent,
            ! empty($allQueries) ? $allQueries : null,
            $metadata ?: null,
        );

        $donePayload = [
            'queryCount' => count($allQueries),
            'messageId' => $message->id,
        ];
        if ($usage !== null) {
            $donePayload['usage'] = $usage;
        }
        if ($truncated) {
            $donePayload['truncated'] = true;
        }

        $this->sendEvent('done', $donePayload);
    }

    protected function configureAskUserTool(): void
    {
        if (! $this->toolRegistry->has('ask_user')) {
            return;
        }

        $tool = $this->toolRegistry->get('ask_user');

        if (! $tool instanceof AskUserTool) {
            return;
        }

        $tool->setRequestId(Str::uuid()->toString());
        $tool->setSendCallback(fn (array $data) => $this->sendEvent('ask_user', $data));
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
}
