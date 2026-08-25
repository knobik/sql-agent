<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Agent;

use Prism\Prism\Contracts\Message;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

class AgentLoopContext
{
    /**
     * @param  string  $systemPrompt  The rendered system prompt
     * @param  Message[]  $messages  Prism message objects for the conversation
     * @param  Tool[]  $tools  The prepared tools for the connection
     * @param  int  $maxIterations  Maximum agent loop iterations (Prism maxSteps)
     * @param  SystemMessage[]  $systemPrompts  System blocks sent to the provider,
     *                                          split so the static prefix can be cached
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly array $messages,
        public readonly array $tools,
        public readonly int $maxIterations,
        public readonly array $systemPrompts = [],
    ) {}

    /**
     * The system blocks to send, falling back to the single rendered prompt.
     *
     * @return SystemMessage[]
     */
    public function systemPrompts(): array
    {
        return $this->systemPrompts !== []
            ? $this->systemPrompts
            : [new SystemMessage($this->systemPrompt)];
    }
}
