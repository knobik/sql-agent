<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Agent;

use Knobik\SqlAgent\Llm\StreamChunk;

class ToolLabelResolver
{
    protected const LABELS = [
        'run_sql' => 'Running SQL query',
        'introspect_schema' => 'Inspecting schema',
        'search_knowledge' => 'Searching knowledge base',
        'save_learning' => 'Saving learning',
        'save_validated_query' => 'Saving query pattern',
    ];

    protected const TYPES = [
        'run_sql' => 'sql',
        'introspect_schema' => 'schema',
        'search_knowledge' => 'search',
        'save_learning' => 'save',
        'save_validated_query' => 'save',
    ];

    public function getLabel(string $toolName): string
    {
        return self::LABELS[$toolName] ?? $toolName;
    }

    public function getType(string $toolName): string
    {
        return self::TYPES[$toolName] ?? 'default';
    }

    public function buildStreamChunkFromPrism(string $toolName, array $arguments): StreamChunk
    {
        $label = $this->getLabel($toolName);
        $type = $this->getType($toolName);

        // Append connection name when present (multi-database mode)
        $connection = $arguments['connection'] ?? null;
        if ($connection !== null && in_array($toolName, ['run_sql', 'introspect_schema'])) {
            $label .= ' on '.htmlspecialchars($connection, ENT_QUOTES, 'UTF-8');
        }

        $sqlData = '';
        if ($toolName === 'run_sql') {
            $sql = $arguments['sql'] ?? $arguments['query'] ?? '';
            $sqlData = ' data-sql="'.htmlspecialchars($sql, ENT_QUOTES, 'UTF-8').'"';
        }

        return new StreamChunk(
            content: "\n<tool data-type=\"{$type}\"{$sqlData}>{$label}</tool>\n",
        );
    }
}
