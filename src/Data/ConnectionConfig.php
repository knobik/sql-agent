<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Data;

class ConnectionConfig
{
    public function __construct(
        /** The logical name (key in the config map). */
        public string $name,

        /** The Laravel database connection name. */
        public string $connection,

        /** Human-readable label for display. */
        public string $label,

        /** Description of what data this connection holds. */
        public string $description,

        /** @var array<string> Whitelist of allowed tables (empty = all). */
        public array $allowedTables = [],

        /** @var array<string> Blacklist of denied tables. */
        public array $deniedTables = [],

        /** @var array<string, array<string>> Hidden columns per table. */
        public array $hiddenColumns = [],
    ) {}
}
