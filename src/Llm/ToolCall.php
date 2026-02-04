<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Llm;

class ToolCall
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {}

    /**
     * Create from OpenAI tool call format.
     */
    public static function fromOpenAi(array $toolCall): self
    {
        $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];

        return new self(
            id: $toolCall['id'],
            name: $toolCall['function']['name'],
            arguments: $arguments,
        );
    }

    /**
     * Create from Anthropic tool_use block format.
     */
    public static function fromAnthropic(array $toolUse): self
    {
        return new self(
            id: $toolUse['id'],
            name: $toolUse['name'],
            arguments: $toolUse['input'] ?? [],
        );
    }

    /**
     * Convert to OpenAI tool call format.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments),
            ],
        ];
    }

    /**
     * Convert to Anthropic tool_use format.
     */
    public function toAnthropicArray(): array
    {
        return [
            'type' => 'tool_use',
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->arguments,
        ];
    }

    /**
     * Convert to Ollama tool call format (arguments as object, not string).
     */
    public function toOllamaArray(): array
    {
        // Ensure arguments is an object (stdClass) not an array
        // This prevents PHP from encoding [] as array instead of {}
        $arguments = empty($this->arguments) ? new \stdClass : (object) $this->arguments;

        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => $arguments,
            ],
        ];
    }
}
