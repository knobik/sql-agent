<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Agent;

use Generator;
use Knobik\SqlAgent\Contracts\Agent;
use Knobik\SqlAgent\Contracts\AgentResponse;
use Knobik\SqlAgent\Contracts\LlmDriver;
use Knobik\SqlAgent\Contracts\Tool;
use Knobik\SqlAgent\Llm\StreamChunk;
use Knobik\SqlAgent\Llm\ToolCall;
use Knobik\SqlAgent\Services\ContextBuilder;
use Knobik\SqlAgent\Tools\IntrospectSchemaTool;
use Knobik\SqlAgent\Tools\RunSqlTool;
use Throwable;

class SqlAgent implements Agent
{
    protected ?string $lastSql = null;

    protected ?array $lastResults = null;

    protected array $iterations = [];

    protected ?string $currentQuestion = null;

    protected ?array $lastPrompt = null;

    public function __construct(
        protected LlmDriver $llm,
        protected ToolRegistry $toolRegistry,
        protected ContextBuilder $contextBuilder,
        protected PromptRenderer $promptRenderer,
        protected MessageBuilder $messageBuilder,
    ) {}

    public function run(string $question, ?string $connection = null): AgentResponse
    {
        $this->reset();
        $this->currentQuestion = $question;

        try {
            // Build context
            $context = $this->contextBuilder->build($question, $connection);

            // Render system prompt
            $systemPrompt = $this->promptRenderer->renderSystem($context->toPromptString());

            // Build initial messages
            $messages = $this->messageBuilder->build($systemPrompt, $question);

            // Configure tools for the connection
            $tools = $this->prepareTools($connection, $question);

            // Run the agent loop
            $maxIterations = config('sql-agent.agent.max_iterations', 10);

            for ($i = 0; $i < $maxIterations; $i++) {
                $response = $this->llm->chat($messages, $tools);

                $iterationData = [
                    'iteration' => $i + 1,
                    'response' => $response->content,
                    'tool_calls' => array_map(
                        fn (ToolCall $tc) => ['name' => $tc->name, 'arguments' => $tc->arguments],
                        $response->toolCalls
                    ),
                    'finish_reason' => $response->finishReason,
                    'tool_results' => [],
                ];

                // If no tool calls, we're done
                if (! $response->hasToolCalls()) {
                    $this->iterations[] = $iterationData;

                    return new AgentResponse(
                        answer: $response->content,
                        sql: $this->lastSql,
                        results: $this->lastResults,
                        toolCalls: $this->collectToolCalls(),
                        iterations: $this->iterations,
                    );
                }

                // Execute tool calls
                $messages = $this->messageBuilder->append(
                    $messages,
                    $this->messageBuilder->assistantWithToolCalls($response->content, $response->toolCalls)
                );

                foreach ($response->toolCalls as $toolCall) {
                    $result = $this->executeTool($toolCall);
                    $iterationData['tool_results'][] = [
                        'tool' => $toolCall->name,
                        'success' => $result->success,
                        'data' => $result->data,
                        'error' => $result->error,
                    ];
                    $messages = $this->messageBuilder->append(
                        $messages,
                        $this->messageBuilder->toolResult($toolCall, $result)
                    );
                }

                $this->iterations[] = $iterationData;
            }

            // Max iterations reached
            return new AgentResponse(
                answer: 'I was unable to complete the task within the maximum number of iterations.',
                sql: $this->lastSql,
                results: $this->lastResults,
                toolCalls: $this->collectToolCalls(),
                iterations: $this->iterations,
                error: 'Maximum iterations reached',
            );
        } catch (Throwable $e) {
            return new AgentResponse(
                answer: "An error occurred: {$e->getMessage()}",
                sql: $this->lastSql,
                results: $this->lastResults,
                toolCalls: $this->collectToolCalls(),
                iterations: $this->iterations,
                error: $e->getMessage(),
            );
        }
    }

    public function stream(string $question, ?string $connection = null, array $history = []): Generator
    {
        $this->reset();
        $this->currentQuestion = $question;

        // Build context
        $context = $this->contextBuilder->build($question, $connection);

        // Render system prompt
        $systemPrompt = $this->promptRenderer->renderSystem($context->toPromptString());

        // Build initial messages
        $messages = $this->messageBuilder->build($systemPrompt, $question);

        // Include conversation history if provided
        if (! empty($history)) {
            $messages = $this->messageBuilder->withHistory($messages, $history);
        }

        // Configure tools for the connection
        $tools = $this->prepareTools($connection, $question);

        // Capture the initial prompt for debugging
        $this->lastPrompt = [
            'system' => $systemPrompt,
            'messages' => $messages,
            'tools' => array_map(fn (Tool $t) => $t->name(), $tools),
            'tools_full' => array_map(fn (Tool $t) => [
                'name' => $t->name(),
                'description' => $t->description(),
                'parameters' => $t->parameters(),
            ], $tools),
        ];

        // Run the agent loop with streaming
        $maxIterations = config('sql-agent.agent.max_iterations', 10);

        for ($i = 0; $i < $maxIterations; $i++) {
            $content = '';
            $toolCalls = [];
            $finishReason = null;

            foreach ($this->llm->stream($messages, $tools) as $chunk) {
                /** @var StreamChunk $chunk */

                // Pass through thinking chunks
                if ($chunk->hasThinking()) {
                    yield $chunk;
                }

                if ($chunk->hasContent()) {
                    $content .= $chunk->content;
                    yield $chunk;
                }

                if ($chunk->isComplete()) {
                    $toolCalls = $chunk->toolCalls;
                    $finishReason = $chunk->finishReason;
                }
            }

            $iterationData = [
                'iteration' => $i + 1,
                'response' => $content,
                'tool_calls' => array_map(
                    fn (ToolCall $tc) => ['name' => $tc->name, 'arguments' => $tc->arguments],
                    $toolCalls
                ),
                'finish_reason' => $finishReason,
                'tool_results' => [],
            ];

            // If no tool calls, we're done
            if (empty($toolCalls)) {
                $this->iterations[] = $iterationData;

                // If content is empty but we have results, generate a fallback response
                if (empty(trim($content)) && $this->lastSql !== null && $this->lastResults !== null) {
                    $fallbackContent = $this->generateFallbackResponse();
                    yield new StreamChunk(content: $fallbackContent);
                }

                yield StreamChunk::complete('stop');

                return;
            }

            // Execute tool calls
            $messages = $this->messageBuilder->append(
                $messages,
                $this->messageBuilder->assistantWithToolCalls($content, $toolCalls)
            );

            foreach ($toolCalls as $toolCall) {
                // Yield a chunk indicating tool execution (using a marker that can be styled)
                $toolLabel = match ($toolCall->name) {
                    'run_sql' => 'Running SQL query',
                    'introspect_schema' => 'Inspecting schema',
                    'search_knowledge' => 'Searching knowledge base',
                    'save_learning' => 'Saving learning',
                    'save_validated_query' => 'Saving query pattern',
                    default => $toolCall->name,
                };
                $toolType = match ($toolCall->name) {
                    'run_sql' => 'sql',
                    'introspect_schema' => 'schema',
                    'search_knowledge' => 'search',
                    'save_learning' => 'save',
                    'save_validated_query' => 'save',
                    default => 'default',
                };

                // For SQL tools, include the query in a data attribute
                $sqlData = '';
                if ($toolCall->name === 'run_sql') {
                    $sql = $toolCall->arguments['sql'] ?? $toolCall->arguments['query'] ?? '';
                    $sqlData = ' data-sql="'.htmlspecialchars($sql, ENT_QUOTES, 'UTF-8').'"';
                }

                yield new StreamChunk(
                    content: "\n<tool data-type=\"{$toolType}\"{$sqlData}>{$toolLabel}</tool>\n",
                );

                $result = $this->executeTool($toolCall);
                $iterationData['tool_results'][] = [
                    'tool' => $toolCall->name,
                    'success' => $result->success,
                    'data' => $result->data,
                    'error' => $result->error,
                ];
                $messages = $this->messageBuilder->append(
                    $messages,
                    $this->messageBuilder->toolResult($toolCall, $result)
                );
            }

            $this->iterations[] = $iterationData;
        }

        // Max iterations reached
        yield StreamChunk::content("\n\nMaximum iterations reached.");
        yield StreamChunk::complete('max_iterations');
    }

    /**
     * Get the last SQL query executed.
     */
    public function getLastSql(): ?string
    {
        return $this->lastSql;
    }

    /**
     * Get the last query results.
     */
    public function getLastResults(): ?array
    {
        return $this->lastResults;
    }

    /**
     * Get all iterations from the last run.
     */
    public function getIterations(): array
    {
        return $this->iterations;
    }

    /**
     * Get the last prompt sent to the LLM (for debugging).
     */
    public function getLastPrompt(): ?array
    {
        return $this->lastPrompt;
    }

    protected function reset(): void
    {
        $this->lastSql = null;
        $this->lastResults = null;
        $this->iterations = [];
        $this->currentQuestion = null;
        $this->lastPrompt = null;
    }

    /**
     * @return Tool[]
     */
    protected function prepareTools(?string $connection, ?string $question = null): array
    {
        $tools = $this->toolRegistry->all();

        // Configure connection and question for tools that need it
        foreach ($tools as $tool) {
            if ($tool instanceof RunSqlTool) {
                $tool->setConnection($connection);
                $tool->setQuestion($question);
            } elseif ($tool instanceof IntrospectSchemaTool) {
                $tool->setConnection($connection);
            }
        }

        return $tools;
    }

    protected function executeTool(ToolCall $toolCall): \Knobik\SqlAgent\Contracts\ToolResult
    {
        if (! $this->toolRegistry->has($toolCall->name)) {
            return \Knobik\SqlAgent\Contracts\ToolResult::failure(
                "Unknown tool: {$toolCall->name}"
            );
        }

        $tool = $this->toolRegistry->get($toolCall->name);
        $result = $tool->execute($toolCall->arguments);

        // Track SQL queries
        if ($toolCall->name === 'run_sql' && $result->success) {
            $this->lastSql = $toolCall->arguments['sql'] ?? $toolCall->arguments['query'] ?? null;
            $this->lastResults = $result->data['rows'] ?? null;
        }

        return $result;
    }

    protected function collectToolCalls(): array
    {
        $toolCalls = [];

        foreach ($this->iterations as $iteration) {
            foreach ($iteration['tool_calls'] ?? [] as $tc) {
                $toolCalls[] = $tc;
            }
        }

        return $toolCalls;
    }

    /**
     * Generate a fallback response when the LLM returns empty content but we have results.
     */
    protected function generateFallbackResponse(): string
    {
        if ($this->lastResults === null) {
            return 'The query was executed but returned no results.';
        }

        $rowCount = count($this->lastResults);

        if ($rowCount === 0) {
            return 'The query was executed successfully but returned no results.';
        }

        // For single-value results (like COUNT queries), provide a direct answer
        if ($rowCount === 1 && count($this->lastResults[0]) === 1) {
            $value = array_values($this->lastResults[0])[0];
            $key = array_keys($this->lastResults[0])[0];

            // Try to make a natural language response based on the column name
            $key = str_replace('_', ' ', $key);

            return "The result is **{$value}** ({$key}).";
        }

        // For multiple rows or columns, summarize
        if ($rowCount === 1) {
            $row = $this->lastResults[0];
            $parts = [];
            foreach ($row as $key => $value) {
                $key = str_replace('_', ' ', $key);
                $parts[] = "**{$key}**: {$value}";
            }

            return 'Here is the result: '.implode(', ', $parts);
        }

        return "The query returned **{$rowCount}** ".($rowCount === 1 ? 'row' : 'rows').'.';
    }
}
