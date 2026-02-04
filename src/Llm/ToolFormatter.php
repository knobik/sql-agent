<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Llm;

use Knobik\SqlAgent\Contracts\Tool;

class ToolFormatter
{
    /**
     * Convert tools to OpenAI format.
     *
     * @param  Tool[]  $tools
     */
    public static function toOpenAi(array $tools): array
    {
        return array_map(fn (Tool $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ], $tools);
    }

    /**
     * Convert tools to Anthropic format.
     *
     * @param  Tool[]  $tools
     */
    public static function toAnthropic(array $tools): array
    {
        return array_map(fn (Tool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->parameters(),
        ], $tools);
    }

    /**
     * Convert tools to Ollama format (OpenAI-compatible).
     *
     * @param  Tool[]  $tools
     */
    public static function toOllama(array $tools): array
    {
        // Ollama uses OpenAI-compatible format
        return self::toOpenAi($tools);
    }

    /**
     * Convert a single tool to OpenAI format.
     */
    public static function toolToOpenAi(Tool $tool): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ];
    }

    /**
     * Convert a single tool to Anthropic format.
     */
    public static function toolToAnthropic(Tool $tool): array
    {
        return [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->parameters(),
        ];
    }
}
