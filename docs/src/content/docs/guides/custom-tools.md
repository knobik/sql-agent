---
title: Custom Tools
description: Extend the SQL agent with your own tools — API lookups, formatting helpers, domain-specific actions, and more.
sidebar:
  order: 8
---

SqlAgent ships with a fixed set of [built-in tools](/sql-agent/reference/tools/) (SQL execution, schema introspection, knowledge search, and learning). You can register additional tools so the LLM can call your own logic during the agentic loop.

Custom tools are plain PHP classes that extend `Prism\Prism\Tool`. They are resolved from the Laravel container, so constructor dependencies are injected automatically.

## Registering Custom Tools

List your tool class names in the `agent.tools` array in `config/sql-agent.php`:

```php
'agent' => [
    // ... other options ...
    'tools' => [
        \App\SqlAgent\CurrentDateTimeTool::class,
        \App\SqlAgent\FormatCurrencyTool::class,
    ],
],
```

Each class is resolved via `app()->make()`, so any constructor dependencies are injected by the container. Custom tools appear alongside the built-in tools — the LLM sees all of them and can call any tool on each iteration.

## Creating a Custom Tool

A custom tool must extend `Prism\Prism\Tool`. Use the fluent API in the constructor to declare the tool's name, description, and parameters. Then pass `$this` to `->using()` and implement the logic in an `__invoke` method. This is the same pattern the built-in tools use.

```php
<?php

namespace App\SqlAgent;

use Prism\Prism\Tool;

class CurrentDateTimeTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('current_datetime')
            ->for('Get the current date and time. Use this when the user asks questions involving relative dates like "today", "this week", or "last month".')
            ->withStringParameter('timezone', 'IANA timezone (e.g. UTC, America/New_York). Defaults to UTC.', required: false)
            ->using($this);
    }

    public function __invoke(?string $timezone = null): string
    {
        $tz = new \DateTimeZone($timezone ?? config('app.timezone', 'UTC'));
        $now = new \DateTimeImmutable('now', $tz);

        return json_encode([
            'datetime' => $now->format('Y-m-d H:i:s'),
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('H:i:s'),
            'timezone' => $tz->getName(),
            'day_of_week' => $now->format('l'),
        ], JSON_THROW_ON_ERROR);
    }
}
```

### Tool API Reference

The fluent methods available on `Prism\Prism\Tool`:

| Method | Description |
|--------|-------------|
| `as(string $name)` | Internal tool name the LLM uses to call it (snake_case recommended). |
| `for(string $description)` | Description shown to the LLM — explain **when** to use the tool. |
| `using(callable $fn)` | The handler to invoke. Pass `$this` for the `__invoke` pattern. |
| `withStringParameter(name, description, required)` | Add a string parameter. |
| `withNumberParameter(name, description, required)` | Add a numeric parameter. |
| `withBooleanParameter(name, description, required)` | Add a boolean parameter. |
| `withEnumParameter(name, description, values, required)` | Add a parameter limited to specific values. |
| `withArrayParameter(name, description, schema, required)` | Add an array parameter with an item schema. |
| `withParameter(Schema $schema, required)` | Add a parameter with a custom schema object. |

:::tip
Write the `for()` description from the LLM's perspective — tell it **when** and **why** to use the tool, not just what it does. A good description like *"Get the current date and time. Use this when the user asks questions involving relative dates"* guides the LLM to call the tool at the right moment.
:::

## Dependency Injection

Because tools are resolved from the container, you can type-hint services in the constructor:

```php
class LookupExchangeRateTool extends Tool
{
    public function __construct(private ExchangeRateService $rates)
    {
        $this
            ->as('lookup_exchange_rate')
            ->for('Get the current exchange rate between two currencies')
            ->withStringParameter('from', 'Source currency code')
            ->withStringParameter('to', 'Target currency code')
            ->using($this);
    }

    public function __invoke(string $from, string $to): string
    {
        return (string) $this->rates->getRate($from, $to);
    }
}
```

## Return Values

Tool handlers must return a **string**. The LLM receives this string as the tool result and uses it to formulate its answer. For structured data, return JSON:

```php
public function __invoke(string $code): string
{
    $rate = $this->rates->getRate($code);

    return json_encode([
        'currency' => $code,
        'rate' => $rate,
        'updated_at' => now()->toIso8601String(),
    ], JSON_THROW_ON_ERROR);
}
```

If the tool throws an exception, the agent's error handler captures it and reports the error message back to the LLM, which may retry or adjust its approach.

## Validation

SqlAgent validates custom tools at boot time:

- If a configured class **does not exist**, an `InvalidArgumentException` is thrown with a clear message.
- If a class **does not extend** `Prism\Prism\Tool`, an `InvalidArgumentException` is thrown.

These errors surface immediately when the application boots, not at query time, so misconfigurations are caught early.

## Tips

- **Keep tools focused.** Each tool should do one thing well. Prefer two small tools over one tool with a `mode` parameter.
- **Return JSON.** Structured output helps the LLM parse results reliably.
- **Be explicit in descriptions.** The LLM decides which tool to call based on the `for()` description. Vague descriptions lead to tools being called incorrectly or not at all.
- **Test your tools.** Custom tools can be unit-tested independently — they're just classes with an `__invoke` method.
