<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Services;

use InvalidArgumentException;
use Knobik\SqlAgent\Data\ConnectionConfig;

class ConnectionRegistry
{
    /** @var array<string, ConnectionConfig>|null */
    protected ?array $connections = null;

    /**
     * Get logical connection names (keys from config).
     *
     * @return array<string>
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->all());
    }

    /**
     * Get a named connection config.
     */
    public function getConnection(string $name): ConnectionConfig
    {
        $connections = $this->all();

        if (! isset($connections[$name])) {
            throw new InvalidArgumentException("Unknown connection: {$name}");
        }

        return $connections[$name];
    }

    /**
     * Get the Laravel connection name for a logical name.
     */
    public function getLaravelConnection(string $name): string
    {
        return $this->getConnection($name)->connection;
    }

    /**
     * Resolve a logical name to a Laravel connection name.
     *
     * With a name: returns the mapped Laravel connection.
     * Without a name: returns null (caller decides).
     */
    public function resolveConnection(?string $name): ?string
    {
        if ($name !== null) {
            return $this->getLaravelConnection($name);
        }

        return null;
    }

    /**
     * Get all connection configs.
     *
     * @return array<string, ConnectionConfig>
     */
    public function all(): array
    {
        if ($this->connections !== null) {
            return $this->connections;
        }

        $this->connections = [];

        foreach (config('sql-agent.database.connections', []) as $name => $config) {
            $this->connections[$name] = new ConnectionConfig(
                name: $name,
                connection: $config['connection'],
                label: $config['label'] ?? $name,
                description: $config['description'] ?? '',
                allowedTables: $config['allowed_tables'] ?? [],
                deniedTables: $config['denied_tables'] ?? [],
                hiddenColumns: $config['hidden_columns'] ?? [],
            );
        }

        return $this->connections;
    }
}
