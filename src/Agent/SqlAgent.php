<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Agent;

use Generator;
use Knobik\SqlAgent\Contracts\Agent;
use Knobik\SqlAgent\Contracts\AgentResponse;
use Knobik\SqlAgent\Contracts\LlmDriver;
use Knobik\SqlAgent\Contracts\Tool;
use Knobik\SqlAgent\Contracts\ToolResult;
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
        protected ToolLabelResolver $toolLabelResolver,
        protected FallbackResponseGenerator $fallbackResponseGenerator,
    ) {}

    public function run(string $question, ?string $connection = null): AgentResponse
    {
        $this->reset();
        $this->currentQuestion = $question;

        try {
            $loop = $this->prepareLoop($question, $connection);

            $messages = $loop->messages;

            for ($i = 0; $i < $loop->maxIterations; $i++) {
                $response = $this->llm->chat($messages, $loop->tools);

                $iterationData = $this->buildIterationData($i, $response->content, $response->toolCalls, $response->finishReason);

                // If no tool calls, we're done
                if (! $response->hasToolCalls()) {
                    $this->iterations[] = $iterationData;

                    return $this->buildResponse($response->content);
                }

                // Execute tool calls
                $this->processToolCalls($response->toolCalls, $response->content, $messages, $iterationData);

                $this->iterations[] = $iterationData;
            }

            // Max iterations reached
            return $this->buildResponse(
                'I was unable to complete the task within the maximum number of iterations.',
                'Maximum iterations reached',
            );
        } catch (Throwable $e) {
            return $this->buildErrorResponse($e);
        }
    }

    public function stream(string $question, ?string $connection = null, array $history = []): Generator
    {
        $this->reset();
        $this->currentQuestion = $question;

        $loop = $this->prepareLoop($question, $connection, $history);

        $messages = $loop->messages;

        // Capture the initial prompt for debugging
        $this->lastPrompt = [
            'system' => $loop->systemPrompt,
            'messages' => $messages,
            'tools' => array_map(fn (Tool $t) => $t->name(), $loop->tools),
            'tools_full' => array_map(fn (Tool $t) => [
                'name' => $t->name(),
                'description' => $t->description(),
                'parameters' => $t->parameters(),
            ], $loop->tools),
        ];

        for ($i = 0; $i < $loop->maxIterations; $i++) {
            $content = '';
            $toolCalls = [];
            $finishReason = null;

            foreach ($this->llm->stream($messages, $loop->tools) as $chunk) {
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

            $iterationData = $this->buildIterationData($i, $content, $toolCalls, $finishReason);

            // If no tool calls, we're done
            if (empty($toolCalls)) {
                $this->iterations[] = $iterationData;

                // If content is empty but we have results, generate a fallback response
                if (empty(trim($content)) && $this->lastSql !== null && $this->lastResults !== null) {
                    $fallbackContent = $this->fallbackResponseGenerator->generate($this->lastResults);
                    yield new StreamChunk(content: $fallbackContent);
                }

                yield StreamChunk::complete('stop');

                return;
            }

            // Execute tool calls with streaming labels
            $messages = $this->messageBuilder->append(
                $messages,
                $this->messageBuilder->assistantWithToolCalls($content, $toolCalls)
            );

            foreach ($toolCalls as $toolCall) {
                yield $this->toolLabelResolver->buildStreamChunk($toolCall);

                $result = $this->executeTool($toolCall);
                $iterationData['tool_results'][] = $this->buildToolResultData($toolCall, $result);
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

    /**
     * Build iteration data structure shared by run() and stream().
     *
     * @param  ToolCall[]  $toolCalls
     */
    protected function buildIterationData(int $iteration, string $content, array $toolCalls, ?string $finishReason): array
    {
        return [
            'iteration' => $iteration + 1,
            'response' => $content,
            'tool_calls' => array_map(
                fn (ToolCall $tc) => ['name' => $tc->name, 'arguments' => $tc->arguments],
                $toolCalls
            ),
            'finish_reason' => $finishReason,
            'tool_results' => [],
        ];
    }

    /**
     * Build a tool result data entry for iteration tracking.
     */
    protected function buildToolResultData(ToolCall $toolCall, ToolResult $result): array
    {
        return [
            'tool' => $toolCall->name,
            'success' => $result->success,
            'data' => $result->data,
            'error' => $result->error,
        ];
    }

    /**
     * Process tool calls: append messages and record results (used by run()).
     *
     * @param  ToolCall[]  $toolCalls
     */
    protected function processToolCalls(array $toolCalls, string $content, array &$messages, array &$iterationData): void
    {
        $messages = $this->messageBuilder->append(
            $messages,
            $this->messageBuilder->assistantWithToolCalls($content, $toolCalls)
        );

        foreach ($toolCalls as $toolCall) {
            $result = $this->executeTool($toolCall);
            $iterationData['tool_results'][] = $this->buildToolResultData($toolCall, $result);
            $messages = $this->messageBuilder->append(
                $messages,
                $this->messageBuilder->toolResult($toolCall, $result)
            );
        }
    }

    /**
     * Build an AgentResponse with current state.
     */
    protected function buildResponse(string $answer, ?string $error = null): AgentResponse
    {
        return new AgentResponse(
            answer: $answer,
            sql: $this->lastSql,
            results: $this->lastResults,
            toolCalls: $this->collectToolCalls(),
            iterations: $this->iterations,
            error: $error,
        );
    }

    /**
     * Build an error AgentResponse from an exception.
     */
    protected function buildErrorResponse(Throwable $e): AgentResponse
    {
        return $this->buildResponse(
            "An error occurred: {$e->getMessage()}",
            $e->getMessage(),
        );
    }

    protected function prepareLoop(string $question, ?string $connection, array $history = []): AgentLoopContext
    {
        $context = $this->contextBuilder->build($question, $connection);
        $systemPrompt = $this->promptRenderer->renderSystem($context->toPromptString());
        $messages = $this->messageBuilder->build($systemPrompt, $question);

        if (! empty($history)) {
            $messages = $this->messageBuilder->withHistory($messages, $history);
        }

        $tools = $this->prepareTools($connection, $question);
        $maxIterations = config('sql-agent.agent.max_iterations', 10);

        return new AgentLoopContext($systemPrompt, $messages, $tools, $maxIterations);
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

    protected function executeTool(ToolCall $toolCall): ToolResult
    {
        if (! $this->toolRegistry->has($toolCall->name)) {
            return ToolResult::failure(
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
}
