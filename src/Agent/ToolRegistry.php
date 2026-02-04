<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Agent;

use InvalidArgumentException;
use Knobik\SqlAgent\Contracts\Tool;

class ToolRegistry
{
    /**
     * @var array<string, Tool>
     */
    protected array $tools = [];

    /**
     * Register a single tool.
     */
    public function register(Tool $tool): self
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /**
     * Register multiple tools.
     *
     * @param  Tool[]  $tools
     */
    public function registerMany(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }

        return $this;
    }

    /**
     * Get a tool by name.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $name): Tool
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Tool '{$name}' is not registered.");
        }

        return $this->tools[$name];
    }

    /**
     * Check if a tool is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get all registered tools.
     *
     * @return Tool[]
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Get all registered tool names.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Remove a tool by name.
     */
    public function remove(string $name): self
    {
        unset($this->tools[$name]);

        return $this;
    }

    /**
     * Clear all registered tools.
     */
    public function clear(): self
    {
        $this->tools = [];

        return $this;
    }

    /**
     * Get the number of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Check if the registry is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->tools);
    }
}
