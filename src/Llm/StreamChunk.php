<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Llm;

class StreamChunk
{
    public function __construct(
        public readonly ?string $content = null,
        public readonly array $toolCalls = [],
        public readonly ?string $finishReason = null,
        public readonly ?string $thinking = null,
        public readonly ?array $usage = null,
    ) {}

    /**
     * Check if this chunk has thinking content.
     */
    public function hasThinking(): bool
    {
        return $this->thinking !== null && $this->thinking !== '';
    }

    /**
     * Check if this chunk has text content.
     */
    public function hasContent(): bool
    {
        return $this->content !== null && $this->content !== '';
    }

    /**
     * Check if this chunk has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Check if streaming is complete.
     */
    public function isComplete(): bool
    {
        return $this->finishReason !== null;
    }

    /**
     * Check if the finish reason indicates tool use.
     */
    public function isToolUse(): bool
    {
        return in_array($this->finishReason, ['tool_calls', 'tool_use', 'function_call'], true);
    }

    /**
     * Check if the finish reason indicates normal stop.
     */
    public function isStop(): bool
    {
        return in_array($this->finishReason, ['stop', 'end_turn'], true);
    }

    /**
     * Create a content chunk.
     */
    public static function content(string $content): self
    {
        return new self(content: $content);
    }

    /**
     * Create a thinking chunk.
     */
    public static function thinking(string $thinking): self
    {
        return new self(thinking: $thinking);
    }

    /**
     * Create a tool calls chunk.
     */
    public static function toolCalls(array $toolCalls): self
    {
        return new self(toolCalls: $toolCalls);
    }

    /**
     * Create a completion chunk.
     */
    public static function complete(string $finishReason, ?string $content = null, array $toolCalls = [], ?array $usage = null): self
    {
        return new self(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            usage: $usage,
        );
    }
}
