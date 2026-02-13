<?php

namespace Knobik\SqlAgent\Contracts;

use Generator;
use Knobik\SqlAgent\Data\AgentResponse;

interface Agent
{
    /**
     * Run the agent with a question.
     *
     * @param  string  $question  The natural language question
     */
    public function run(string $question): AgentResponse;

    /**
     * Stream the agent's response.
     *
     * @param  string  $question  The natural language question
     * @param  array  $history  Previous conversation messages
     * @return Generator Yields response chunks
     */
    public function stream(string $question, array $history = []): Generator;
}
