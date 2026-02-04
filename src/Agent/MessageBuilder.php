<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Agent;

use Knobik\SqlAgent\Contracts\ToolResult;
use Knobik\SqlAgent\Llm\ToolCall;

class MessageBuilder
{
    /**
     * Build initial messages with system prompt and user question.
     */
    public function build(string $systemPrompt, string $question): array
    {
        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $question,
            ],
        ];
    }

    /**
     * Add conversation history to messages.
     *
     * @param  array  $messages  Current messages
     * @param  array  $history  Array of historical messages with 'role' and 'content'
     */
    public function withHistory(array $messages, array $history): array
    {
        if (empty($history)) {
            return $messages;
        }

        // Insert history after system message
        $systemMessage = array_shift($messages);
        $userMessage = array_pop($messages);

        return array_merge(
            [$systemMessage],
            $history,
            $messages,
            [$userMessage]
        );
    }

    /**
     * Create a system message.
     */
    public function system(string $content): array
    {
        return [
            'role' => 'system',
            'content' => $content,
        ];
    }

    /**
     * Create a user message.
     */
    public function user(string $content): array
    {
        return [
            'role' => 'user',
            'content' => $content,
        ];
    }

    /**
     * Create an assistant message.
     */
    public function assistant(string $content): array
    {
        return [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    /**
     * Create an assistant message with tool calls.
     *
     * @param  ToolCall[]  $toolCalls
     */
    public function assistantWithToolCalls(string $content, array $toolCalls): array
    {
        return [
            'role' => 'assistant',
            'content' => $content,
            'tool_calls' => array_map(fn (ToolCall $tc) => $tc->toArray(), $toolCalls),
        ];
    }

    /**
     * Create a tool result message.
     */
    public function toolResult(ToolCall $toolCall, ToolResult $result): array
    {
        $content = $result->success
            ? $this->formatToolResultData($result->data)
            : "Error: {$result->error}";

        return [
            'role' => 'tool',
            'tool_call_id' => $toolCall->id,
            'content' => $content,
        ];
    }

    /**
     * Format tool result data for the message.
     *
     * Note: We use compact JSON (no pretty print) to avoid issues with some LLM
     * providers that have trouble parsing nested JSON with newlines in content.
     */
    protected function formatToolResultData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return json_encode($data->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_object($data) && method_exists($data, '__toString')) {
            return (string) $data;
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Append a message to the messages array.
     */
    public function append(array $messages, array $message): array
    {
        $messages[] = $message;

        return $messages;
    }

    /**
     * Append multiple messages to the messages array.
     */
    public function appendMany(array $messages, array $newMessages): array
    {
        return array_merge($messages, $newMessages);
    }
}
