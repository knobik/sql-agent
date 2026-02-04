<?php

namespace Knobik\SqlAgent\Contracts;

interface Tool
{
    /**
     * Get the unique name of the tool.
     */
    public function name(): string;

    /**
     * Get a description of what the tool does.
     */
    public function description(): string;

    /**
     * Get the JSON Schema parameters definition.
     *
     * @return array JSON Schema object describing the tool's parameters
     */
    public function parameters(): array;

    /**
     * Execute the tool with the given parameters.
     *
     * @param  array  $parameters  The parameters passed to the tool
     */
    public function execute(array $parameters): ToolResult;
}
