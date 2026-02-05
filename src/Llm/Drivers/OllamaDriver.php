<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Llm\Drivers;

use Generator;
use Illuminate\Support\Facades\Http;
use Knobik\SqlAgent\Contracts\LlmDriver;
use Knobik\SqlAgent\Contracts\LlmResponse;
use Knobik\SqlAgent\Llm\StreamChunk;
use Knobik\SqlAgent\Llm\ToolCall;
use Knobik\SqlAgent\Llm\ToolFormatter;
use RuntimeException;

class OllamaDriver implements LlmDriver
{
    protected string $baseUrl;

    protected string $model;

    protected float $temperature;

    protected string|bool $think;

    protected ?array $modelsWithToolSupport;

    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['model'] ?? 'llama3.1';
        $this->temperature = $config['temperature'] ?? 0.0;
        $this->think = $config['think'] ?? true;
        // null = all models support tools (wildcard), array = only listed models
        $this->modelsWithToolSupport = $config['models_with_tool_support'] ?? null;
    }

    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->formatMessages($messages),
            'stream' => false,
            'options' => $this->buildOptions(),
        ];

        if (! empty($tools) && $this->supportsToolCalling()) {
            $payload['tools'] = ToolFormatter::toOllama($tools);
        }

        $response = Http::timeout(300)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/api/chat", $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Ollama API error: {$response->status()} - {$response->body()}"
            );
        }

        return $this->parseResponse($response->json());
    }

    public function stream(array $messages, array $tools = []): Generator
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->formatMessages($messages),
            'stream' => true,
            'options' => $this->buildOptions(),
        ];

        if (! empty($tools) && $this->supportsToolCalling()) {
            $payload['tools'] = ToolFormatter::toOllama($tools);
        }

        // Log payload for debugging if enabled
        if (config('sql-agent.debug.enabled', false)) {
            logger()->debug('Ollama request payload', [
                'model' => $this->model,
                'tools_count' => count($payload['tools'] ?? []),
                'payload_json' => json_encode($payload, JSON_PRETTY_PRINT),
            ]);
        }

        $response = Http::timeout(300)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/api/chat", $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Ollama API error: {$response->status()} - {$response->body()}"
            );
        }

        yield from $this->parseStream($response->getBody());
    }

    public function supportsToolCalling(): bool
    {
        // null = wildcard, all models support tools
        if ($this->modelsWithToolSupport === null) {
            return true;
        }

        // Empty array = no models support tools
        if (empty($this->modelsWithToolSupport)) {
            return false;
        }

        // Check if current model matches any in the list
        $modelBase = strtolower(explode(':', $this->model)[0]);

        foreach ($this->modelsWithToolSupport as $supported) {
            if (str_starts_with($modelBase, strtolower($supported))) {
                return true;
            }
        }

        return false;
    }

    protected function buildOptions(): array
    {
        $options = [
            'temperature' => $this->temperature,
        ];

        if ($this->think !== false) {
            $options['think'] = $this->think;
        }

        return $options;
    }

    protected function formatMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            // Handle tool results
            if ($message['role'] === 'tool') {
                $content = $message['content'];

                // Ensure content is a string
                if (! is_string($content)) {
                    $content = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }

                // Sanitize content to remove any problematic characters
                // that might confuse Ollama's JSON parser
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

                $formatted[] = [
                    'role' => 'tool',
                    'content' => $content,
                ];

                continue;
            }

            // Handle assistant messages with tool calls
            if ($message['role'] === 'assistant' && isset($message['tool_calls'])) {
                $toolCalls = [];
                foreach ($message['tool_calls'] as $toolCall) {
                    if ($toolCall instanceof ToolCall) {
                        $toolCalls[] = $toolCall->toOllamaArray();
                    } else {
                        // If it's already an array, ensure arguments is not a JSON string
                        if (isset($toolCall['function']['arguments']) && is_string($toolCall['function']['arguments'])) {
                            $toolCall['function']['arguments'] = json_decode($toolCall['function']['arguments'], true) ?? [];
                        }

                        // Ensure arguments is an object (stdClass) not an array
                        // This prevents PHP from encoding [] as array instead of {}
                        if (isset($toolCall['function']['arguments']) && is_array($toolCall['function']['arguments'])) {
                            $toolCall['function']['arguments'] = (object) $toolCall['function']['arguments'];
                        }

                        $toolCalls[] = $toolCall;
                    }
                }

                $formatted[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? '',
                    'tool_calls' => $toolCalls,
                ];

                continue;
            }

            // Regular messages
            $formatted[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        return $formatted;
    }

    protected function parseResponse(array $data): LlmResponse
    {
        $message = $data['message'] ?? [];
        $content = $message['content'] ?? '';
        $toolCalls = [];

        if (! empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                $arguments = $toolCall['function']['arguments'] ?? [];

                // Parse arguments if they're a JSON string
                if (is_string($arguments)) {
                    $parsed = json_decode($arguments, true);
                    $arguments = is_array($parsed) ? $parsed : [];
                }

                $toolCalls[] = new ToolCall(
                    id: $toolCall['id'] ?? uniqid('tc_'),
                    name: $toolCall['function']['name'] ?? '',
                    arguments: $arguments,
                );
            }
        }

        return new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $data['done'] ? 'stop' : null,
            promptTokens: $data['prompt_eval_count'] ?? null,
            completionTokens: $data['eval_count'] ?? null,
        );
    }

    protected function parseStream($body): Generator
    {
        $toolCalls = [];

        foreach ($this->readLines($body) as $line) {
            $event = json_decode($line, true);
            if ($event === null) {
                continue;
            }

            $message = $event['message'] ?? [];

            // Handle thinking
            if (! empty($message['thinking'])) {
                yield StreamChunk::thinking($message['thinking']);
            }

            // Handle content
            if (! empty($message['content'])) {
                yield StreamChunk::content($message['content']);
            }

            // Handle tool calls
            if (! empty($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $toolCall) {
                    $arguments = $toolCall['function']['arguments'] ?? [];

                    // Parse arguments if they're a JSON string
                    if (is_string($arguments)) {
                        $parsed = json_decode($arguments, true);
                        $arguments = is_array($parsed) ? $parsed : [];
                    }

                    $toolCalls[] = new ToolCall(
                        id: $toolCall['id'] ?? uniqid('tc_'),
                        name: $toolCall['function']['name'] ?? '',
                        arguments: $arguments,
                    );
                }
            }

            // Handle completion
            if ($event['done'] ?? false) {
                $finishReason = ! empty($toolCalls) ? 'tool_calls' : 'stop';
                yield StreamChunk::complete($finishReason, null, $toolCalls);
            }
        }
    }

    protected function readLines($stream): Generator
    {
        $buffer = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);
                if ($line !== '') {
                    yield $line;
                }
            }
        }

        if (trim($buffer) !== '') {
            yield trim($buffer);
        }
    }
}
