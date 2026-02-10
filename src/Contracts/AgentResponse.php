<?php

namespace Knobik\SqlAgent\Contracts;

class AgentResponse
{
    public function __construct(
        public readonly string $answer,
        public readonly ?string $sql = null,
        public readonly ?array $results = null,
        public readonly array $toolCalls = [],
        public readonly array $iterations = [],
        public readonly ?string $error = null,
        public readonly ?array $usage = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    public function hasResults(): bool
    {
        return $this->results !== null && count($this->results) > 0;
    }
}
