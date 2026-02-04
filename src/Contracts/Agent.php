<?php

namespace Knobik\SqlAgent\Contracts;

use Generator;

interface Agent
{
    /**
     * Run the agent with a question.
     *
     * @param  string  $question  The natural language question
     * @param  string|null  $connection  Optional database connection name
     */
    public function run(string $question, ?string $connection = null): AgentResponse;

    /**
     * Stream the agent's response.
     *
     * @param  string  $question  The natural language question
     * @param  string|null  $connection  Optional database connection name
     * @return Generator Yields response chunks
     */
    public function stream(string $question, ?string $connection = null): Generator;
}
