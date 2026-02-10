<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Agent;

use Generator;
use Knobik\SqlAgent\Contracts\Agent;
use Knobik\SqlAgent\Contracts\AgentResponse;
use Knobik\SqlAgent\Llm\StreamChunk;
use Knobik\SqlAgent\Services\ContextBuilder;
use Knobik\SqlAgent\Tools\IntrospectSchemaTool;
use Knobik\SqlAgent\Tools\RunSqlTool;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StepFinishEvent;
use Prism\Prism\Streaming\Events\StepStartEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\Tool;
use Throwable;

class SqlAgent implements Agent
{
    protected ?string $lastSql = null;

    protected ?array $lastResults = null;

    protected array $iterations = [];

    protected ?string $currentQuestion = null;

    protected ?array $lastPrompt = null;

    public function __construct(
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

            $response = $this->buildPrismRequest($loop)
                ->asText();

            $this->syncFromRunSqlTool($loop->tools);
            $this->iterations = $this->extractIterations($response);

            return $this->buildResponse($response->text);
        } catch (Throwable $e) {
            return $this->buildErrorResponse($e);
        }
    }

    public function stream(string $question, ?string $connection = null, array $history = []): Generator
    {
        $this->reset();
        $this->currentQuestion = $question;

        $loop = $this->prepareLoop($question, $connection, $history);

        $this->lastPrompt = [
            'system' => $loop->systemPrompt,
            'messages' => $loop->messages,
            'tools' => array_map(fn (Tool $t) => $t->name(), $loop->tools),
            'tools_full' => array_map(fn (Tool $t) => [
                'name' => $t->name(),
                'description' => $t->description(),
                'parameters' => $t->parametersAsArray(),
            ], $loop->tools),
        ];

        $fullContent = '';
        $currentStep = [];
        $stepIndex = 0;

        try {
            $events = $this->buildPrismRequest($loop)->asStream();

            foreach ($events as $event) {
                if ($event instanceof StepStartEvent) {
                    $currentStep = ['text' => '', 'tool_calls' => [], 'tool_results' => []];
                } elseif ($event instanceof TextDeltaEvent) {
                    $fullContent .= $event->delta;
                    $currentStep['text'] = ($currentStep['text'] ?? '').$event->delta;
                    yield StreamChunk::content($event->delta);
                } elseif ($event instanceof ThinkingEvent) {
                    yield StreamChunk::thinking($event->delta);
                } elseif ($event instanceof ToolCallEvent) {
                    $currentStep['tool_calls'][] = [
                        'name' => $event->toolCall->name,
                        'arguments' => $event->toolCall->arguments(),
                    ];
                    yield $this->toolLabelResolver->buildStreamChunkFromPrism(
                        $event->toolCall->name,
                        $event->toolCall->arguments(),
                    );
                } elseif ($event instanceof ToolResultEvent) {
                    $currentStep['tool_results'][] = [
                        'tool' => $event->toolResult->toolCallId,
                        'success' => $event->success,
                        'data' => $event->success ? $event->toolResult->result : null,
                        'error' => $event->error,
                    ];
                } elseif ($event instanceof StepFinishEvent) {
                    $stepIndex++;
                    $this->iterations[] = [
                        'iteration' => $stepIndex,
                        'response' => $currentStep['text'] ?? '',
                        'tool_calls' => $currentStep['tool_calls'] ?? [],
                        'finish_reason' => ! empty($currentStep['tool_calls']) ? 'toolCalls' : 'stop',
                        'tool_results' => $currentStep['tool_results'] ?? [],
                    ];
                    $currentStep = [];
                } elseif ($event instanceof StreamEndEvent) {
                    // Capture any remaining step data not closed by StepFinishEvent
                    if (! empty($currentStep['tool_calls']) || ! empty($currentStep['text'])) {
                        $stepIndex++;
                        $this->iterations[] = [
                            'iteration' => $stepIndex,
                            'response' => $currentStep['text'] ?? '',
                            'tool_calls' => $currentStep['tool_calls'] ?? [],
                            'finish_reason' => $event->finishReason->value,
                            'tool_results' => $currentStep['tool_results'] ?? [],
                        ];
                    }
                }
            }

            $this->syncFromRunSqlTool($loop->tools);

            // Fallback if content is empty but we have results
            if (empty(trim($fullContent)) && $this->lastSql !== null && $this->lastResults !== null) {
                $fallbackContent = $this->fallbackResponseGenerator->generate($this->lastResults);
                yield StreamChunk::content($fallbackContent);
            }

            yield StreamChunk::complete('stop');
        } catch (Throwable $e) {
            yield StreamChunk::content("\n\nAn error occurred: {$e->getMessage()}");
            yield StreamChunk::complete('error');
        }
    }

    public function getLastSql(): ?string
    {
        return $this->lastSql;
    }

    public function getLastResults(): ?array
    {
        return $this->lastResults;
    }

    public function getIterations(): array
    {
        return $this->iterations;
    }

    public function getLastPrompt(): ?array
    {
        return $this->lastPrompt;
    }

    protected function buildPrismRequest(AgentLoopContext $loop): \Prism\Prism\Text\PendingRequest
    {
        $request = Prism::text()
            ->using(config('sql-agent.llm.provider'), config('sql-agent.llm.model'))
            ->withSystemPrompt($loop->systemPrompt)
            ->withMaxSteps($loop->maxIterations)
            ->usingTemperature(config('sql-agent.llm.temperature'))
            ->withMaxTokens(config('sql-agent.llm.max_tokens'));

        $providerOptions = config('sql-agent.llm.provider_options');
        if (! empty($providerOptions)) {
            $request->withProviderOptions($providerOptions);
        }

        if (! empty($loop->tools)) {
            $request->withTools($loop->tools);
        }

        if (! empty($loop->messages)) {
            $request->withMessages($loop->messages);
        }

        return $request;
    }

    protected function syncFromRunSqlTool(array $tools): void
    {
        foreach ($tools as $tool) {
            if ($tool instanceof RunSqlTool) {
                $this->lastSql = $tool->lastSql;
                $this->lastResults = $tool->lastResults;

                return;
            }
        }
    }

    protected function extractIterations(PrismResponse $response): array
    {
        $iterations = [];

        foreach ($response->steps as $index => $step) {
            $toolCalls = array_map(
                fn (\Prism\Prism\ValueObjects\ToolCall $tc) => [
                    'name' => $tc->name,
                    'arguments' => $tc->arguments(),
                ],
                $step->toolCalls
            );

            $toolResults = array_map(
                fn (\Prism\Prism\ValueObjects\ToolResult $tr) => [
                    'tool' => $tr->toolCallId,
                    'success' => true,
                    'data' => $tr->result,
                ],
                $step->toolResults
            );

            $iterations[] = [
                'iteration' => $index + 1,
                'response' => $step->text,
                'tool_calls' => $toolCalls,
                'finish_reason' => $step->finishReason->value,
                'tool_results' => $toolResults,
            ];
        }

        return $iterations;
    }

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
        $systemPrompt = $this->promptRenderer->renderSystem(
            $context->toPromptString(),
        );

        $messages = $this->messageBuilder->buildPrismMessages($question, $history);

        $tools = $this->prepareTools($connection, $question);
        $maxIterations = config('sql-agent.agent.max_iterations');

        return new AgentLoopContext($systemPrompt, $messages, $tools, $maxIterations);
    }

    protected function reset(): void
    {
        $this->lastSql = null;
        $this->lastResults = null;
        $this->iterations = [];
        $this->currentQuestion = null;
        $this->lastPrompt = null;

        // Reset tool state
        foreach ($this->toolRegistry->all() as $tool) {
            if ($tool instanceof RunSqlTool) {
                $tool->lastSql = null;
                $tool->lastResults = null;
            }
        }
    }

    /**
     * @return Tool[]
     */
    protected function prepareTools(?string $connection, ?string $question = null): array
    {
        $tools = $this->toolRegistry->all();

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
