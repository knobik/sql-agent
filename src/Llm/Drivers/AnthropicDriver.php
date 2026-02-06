<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Llm\Drivers;

use Generator;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Knobik\SqlAgent\Contracts\LlmDriver;
use Knobik\SqlAgent\Contracts\LlmResponse;
use Knobik\SqlAgent\Llm\StreamChunk;
use Knobik\SqlAgent\Llm\Support\StreamLineReader;
use Knobik\SqlAgent\Llm\ToolCall;
use Knobik\SqlAgent\Llm\ToolFormatter;
use RuntimeException;

class AnthropicDriver implements LlmDriver
{
    protected const API_URL = 'https://api.anthropic.com/v1';

    protected const API_VERSION = '2023-06-01';

    protected string $apiKey;

    protected string $model;

    protected float $temperature;

    protected int $maxTokens;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'claude-sonnet-4-20250514';
        $this->temperature = $config['temperature'] ?? 0.0;
        $this->maxTokens = $config['max_tokens'] ?? 4096;
    }

    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $this->ensureApiKeyConfigured();

        $payload = $this->buildPayload($messages, $tools);

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->post(self::API_URL.'/messages', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Anthropic API error: {$response->status()} - {$response->body()}"
            );
        }

        return $this->parseResponse($response->json());
    }

    public function stream(array $messages, array $tools = []): Generator
    {
        $this->ensureApiKeyConfigured();

        $payload = $this->buildPayload($messages, $tools);
        $payload['stream'] = true;

        $response = Http::withHeaders($this->headers())
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post(self::API_URL.'/messages', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Anthropic API error: {$response->status()} - {$response->body()}"
            );
        }

        yield from $this->parseStream($response->toPsrResponse()->getBody());
    }

    public function supportsToolCalling(): bool
    {
        return true;
    }

    protected function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ];
    }

    protected function buildPayload(array $messages, array $tools): array
    {
        $systemPrompt = null;
        $formattedMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemPrompt = $message['content'];

                continue;
            }

            // Handle tool results
            if ($message['role'] === 'tool') {
                $formattedMessages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $message['tool_call_id'],
                            'content' => is_string($message['content'])
                                ? $message['content']
                                : json_encode($message['content']),
                        ],
                    ],
                ];

                continue;
            }

            // Handle assistant messages with tool calls
            if ($message['role'] === 'assistant' && isset($message['tool_calls'])) {
                $content = [];

                if (! empty($message['content'])) {
                    $content[] = [
                        'type' => 'text',
                        'text' => $message['content'],
                    ];
                }

                foreach ($message['tool_calls'] as $toolCall) {
                    if ($toolCall instanceof ToolCall) {
                        $content[] = $toolCall->toAnthropicArray();
                    } else {
                        $content[] = [
                            'type' => 'tool_use',
                            'id' => $toolCall['id'],
                            'name' => $toolCall['function']['name'],
                            'input' => json_decode($toolCall['function']['arguments'], true) ?? [],
                        ];
                    }
                }

                $formattedMessages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                ];

                continue;
            }

            // Regular messages
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'messages' => $formattedMessages,
        ];

        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }

        if (! empty($tools)) {
            $payload['tools'] = ToolFormatter::toAnthropic($tools);
        }

        return $payload;
    }

    protected function parseResponse(array $data): LlmResponse
    {
        $content = '';
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = ToolCall::fromAnthropic($block);
            }
        }

        return new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $data['stop_reason'] ?? null,
            promptTokens: $data['usage']['input_tokens'] ?? null,
            completionTokens: $data['usage']['output_tokens'] ?? null,
        );
    }

    protected function parseStream($body): Generator
    {
        $buffer = '';
        $currentToolCall = null;
        $accumulatedToolCalls = [];

        foreach (StreamLineReader::readLines($body) as $line) {
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            $json = substr($line, 6);
            if ($json === '[DONE]') {
                break;
            }

            $event = json_decode($json, true);
            if ($event === null) {
                continue;
            }

            $type = $event['type'] ?? null;

            switch ($type) {
                case 'content_block_start':
                    $block = $event['content_block'] ?? [];
                    if ($block['type'] === 'tool_use') {
                        $currentToolCall = [
                            'id' => $block['id'],
                            'name' => $block['name'],
                            'input' => '',
                        ];
                    }
                    break;

                case 'content_block_delta':
                    $delta = $event['delta'] ?? [];
                    if ($delta['type'] === 'text_delta') {
                        yield StreamChunk::content($delta['text'] ?? '');
                    } elseif ($delta['type'] === 'input_json_delta' && $currentToolCall !== null) {
                        $currentToolCall['input'] .= $delta['partial_json'] ?? '';
                    }
                    break;

                case 'content_block_stop':
                    if ($currentToolCall !== null) {
                        $accumulatedToolCalls[] = $currentToolCall;
                        $currentToolCall = null;
                    }
                    break;

                case 'message_delta':
                    $delta = $event['delta'] ?? [];
                    $stopReason = $delta['stop_reason'] ?? null;

                    if ($stopReason !== null) {
                        $toolCalls = array_map(function ($tc) {
                            return ToolCall::fromAnthropic([
                                'id' => $tc['id'],
                                'name' => $tc['name'],
                                'input' => json_decode($tc['input'], true) ?? [],
                            ]);
                        }, $accumulatedToolCalls);

                        yield StreamChunk::complete($stopReason, null, $toolCalls);
                    }
                    break;
            }
        }
    }

    protected function ensureApiKeyConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new InvalidArgumentException(
                'Anthropic API key not configured. Set ANTHROPIC_API_KEY in your .env file.'
            );
        }
    }
}
