<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Llm\Drivers;

use Generator;
use InvalidArgumentException;
use Knobik\SqlAgent\Contracts\LlmDriver;
use Knobik\SqlAgent\Contracts\LlmResponse;
use Knobik\SqlAgent\Llm\StreamChunk;
use Knobik\SqlAgent\Llm\ToolCall;
use Knobik\SqlAgent\Llm\ToolFormatter;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiDriver implements LlmDriver
{
    protected string $apiKey;

    protected string $model;

    protected float $temperature;

    protected int $maxTokens;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4o';
        $this->temperature = $config['temperature'] ?? 0.0;
        $this->maxTokens = $config['max_tokens'] ?? 4096;
    }

    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $this->ensureApiKeyConfigured();
        $params = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        if (! empty($tools)) {
            $params['tools'] = ToolFormatter::toOpenAi($tools);
            $params['tool_choice'] = 'auto';
        }

        $response = OpenAI::chat()->create($params);

        return $this->parseResponse($response);
    }

    public function stream(array $messages, array $tools = []): Generator
    {
        $this->ensureApiKeyConfigured();

        $params = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'stream' => true,
        ];

        if (! empty($tools)) {
            $params['tools'] = ToolFormatter::toOpenAi($tools);
            $params['tool_choice'] = 'auto';
        }

        $stream = OpenAI::chat()->createStreamed($params);

        $accumulatedToolCalls = [];

        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta ?? null;
            $finishReason = $response->choices[0]->finishReason ?? null;

            if ($delta === null) {
                continue;
            }

            // Handle content
            if (! empty($delta->content)) {
                yield StreamChunk::content($delta->content);
            }

            // Accumulate tool calls
            if (! empty($delta->toolCalls)) {
                foreach ($delta->toolCalls as $toolCallDelta) {
                    $index = $toolCallDelta->index;

                    if (! isset($accumulatedToolCalls[$index])) {
                        $accumulatedToolCalls[$index] = [
                            'id' => '',
                            'function' => [
                                'name' => '',
                                'arguments' => '',
                            ],
                        ];
                    }

                    if (! empty($toolCallDelta->id)) {
                        $accumulatedToolCalls[$index]['id'] = $toolCallDelta->id;
                    }
                    if (! empty($toolCallDelta->function->name)) {
                        $accumulatedToolCalls[$index]['function']['name'] = $toolCallDelta->function->name;
                    }
                    if (! empty($toolCallDelta->function->arguments)) {
                        $accumulatedToolCalls[$index]['function']['arguments'] .= $toolCallDelta->function->arguments;
                    }
                }
            }

            // Handle finish
            if ($finishReason !== null) {
                $toolCalls = [];
                foreach ($accumulatedToolCalls as $tc) {
                    $toolCalls[] = ToolCall::fromOpenAi($tc);
                }

                yield StreamChunk::complete($finishReason, null, $toolCalls);
            }
        }
    }

    public function supportsToolCalling(): bool
    {
        return true;
    }

    protected function parseResponse($response): LlmResponse
    {
        $choice = $response->choices[0] ?? null;

        if ($choice === null) {
            return new LlmResponse(content: '');
        }

        $content = $choice->message->content ?? '';
        $toolCalls = [];

        if (! empty($choice->message->toolCalls)) {
            foreach ($choice->message->toolCalls as $toolCall) {
                $toolCalls[] = ToolCall::fromOpenAi([
                    'id' => $toolCall->id,
                    'function' => [
                        'name' => $toolCall->function->name,
                        'arguments' => $toolCall->function->arguments,
                    ],
                ]);
            }
        }

        return new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $choice->finishReason ?? null,
            promptTokens: $response->usage?->promptTokens,
            completionTokens: $response->usage?->completionTokens,
        );
    }

    protected function ensureApiKeyConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new InvalidArgumentException(
                'OpenAI API key not configured. Set OPENAI_API_KEY in your .env file.'
            );
        }
    }
}
