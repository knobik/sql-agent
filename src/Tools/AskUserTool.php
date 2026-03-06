<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Tools;

use Closure;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

class AskUserTool extends Tool
{
    protected ?Closure $sendCallback = null;

    protected string $requestId = '';

    protected int $invocationCounter = 0;

    public function __construct()
    {
        $this
            ->as('ask_user')
            ->for('Ask the user a clarifying question when their request is ambiguous. Use this when you need more information before proceeding, such as which time period, which metric, or which entity the user is referring to. You may provide suggested options with optional descriptions. Set multiple=true to let the user pick more than one option. The user can always type a custom free-text response instead of picking a suggestion.')
            ->withStringParameter('question', 'The clarifying question to ask the user.')
            ->withArrayParameter(
                'suggestions',
                'Optional list of suggested answers. Each suggestion has a label (shown on the button) and an optional description (shown below the label to provide context).',
                new ObjectSchema(
                    'suggestion',
                    'A suggested answer option.',
                    [
                        new StringSchema('label', 'The short label for this suggestion (displayed on the button).'),
                        new StringSchema('description', 'Optional longer description explaining this option.'),
                    ],
                    requiredFields: ['label'],
                ),
                required: false,
            )
            ->withBooleanParameter('multiple', 'Set to true to allow the user to select multiple suggestions. Defaults to false (single-select).', required: false)
            ->using($this);
    }

    public function __invoke(string $question, ?array $suggestions = null, ?bool $multiple = null): string
    {
        $parsedSuggestions = $this->parseSuggestions($suggestions);

        if ($this->sendCallback === null) {
            return 'User interaction is not available in non-streaming mode. Please make your best guess based on the available context and proceed.';
        }

        $cacheKey = "sql-agent:ask-user:{$this->requestId}:{$this->invocationCounter}";
        $this->invocationCounter++;

        ($this->sendCallback)([
            'question' => $question,
            'suggestions' => $parsedSuggestions,
            'multiple' => $multiple ?? false,
            'request_id' => $cacheKey,
        ]);

        $timeout = config('sql-agent.agent.ask_user_timeout');
        $elapsed = 0;
        $pollInterval = 500_000; // 500ms in microseconds

        while ($elapsed < $timeout * 1_000_000) {
            $answer = Cache::get($cacheKey);

            if ($answer !== null) {
                Cache::forget($cacheKey);

                return "User answered: {$answer}";
            }

            if (connection_aborted()) {
                return 'The user disconnected before answering. Please make your best guess based on the available context and proceed.';
            }

            usleep($pollInterval);
            $elapsed += $pollInterval;
        }

        return 'The user did not respond in time. Please make your best guess based on the available context and proceed.';
    }

    public function setSendCallback(?Closure $callback): self
    {
        $this->sendCallback = $callback;

        return $this;
    }

    public function setRequestId(string $requestId): self
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function reset(): void
    {
        $this->invocationCounter = 0;
    }

    /**
     * @param  array<int, mixed>|null  $suggestions
     * @return array<int, array{label: string, description: string|null}>
     */
    protected function parseSuggestions(?array $suggestions): array
    {
        if ($suggestions === null || empty($suggestions)) {
            return [];
        }

        $parsed = [];

        foreach ($suggestions as $suggestion) {
            if (is_string($suggestion)) {
                $trimmed = trim($suggestion);
                if ($trimmed !== '') {
                    $parsed[] = ['label' => $trimmed, 'description' => null];
                }
            } elseif (is_array($suggestion) && isset($suggestion['label']) && is_string($suggestion['label'])) {
                $label = trim($suggestion['label']);
                if ($label !== '') {
                    $description = isset($suggestion['description']) && is_string($suggestion['description'])
                        ? trim($suggestion['description'])
                        : null;
                    $parsed[] = ['label' => $label, 'description' => $description ?: null];
                }
            }
        }

        return $parsed;
    }
}
