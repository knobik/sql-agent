---
title: Self-Learning
description: Automatic learning from SQL errors to improve agent accuracy over time.
sidebar:
  order: 7
---

SqlAgent can automatically learn from its mistakes and improve over time without any manual intervention. The agent maintains two dynamic knowledge systems that grow as it processes queries.

## How It Works

The agent's system prompt instructs it to follow a learning-oriented workflow:

1. **Search First** — Before writing any SQL, the agent searches for relevant learnings and query patterns from previous interactions
2. **Execute & Observe** — The agent runs the query and observes the result
3. **Learn from Errors** — If a query fails, the agent diagnoses the issue, fixes it, and saves a learning about what went wrong
4. **Save Validated Queries** — When a query successfully answers a question, the agent saves it as a reusable pattern for future similar questions
5. **Build Context Over Time** — On future queries, relevant learnings and saved patterns are included in the agent's context, so it avoids past mistakes and reuses proven approaches

## Two Knowledge Systems

The system prompt defines two complementary knowledge systems:

**Knowledge** (static, curated) — Table schemas, business rules, and manually authored query patterns. These come from your [Knowledge Base](/sql-agent/guides/knowledge-base/) files and are loaded via `sql-agent:load-knowledge`.

**Learnings** (dynamic, discovered) — Patterns the agent discovers through its own interactions. These include type gotchas, date formats, column quirks, and validated queries. The agent manages these automatically using two tools:

### `save_learning`

The agent saves a learning when it discovers something important about the database — typically after recovering from an error or when a user corrects it. Examples from the system prompt:

After fixing a type error:
```
save_learning(
  title="users.status is VARCHAR not INT",
  description="Use status = 'active' not status = 1",
  category="type_error"
)
```

After discovering a date format:
```
save_learning(
  title="orders.created_at date handling",
  description="Use DATE(created_at) for date comparisons, stored as datetime",
  category="query_pattern"
)
```

After a user corrects the agent:
```
save_learning(
  title="Soft deletes on users table",
  description="Always filter WHERE deleted_at IS NULL unless counting deleted records",
  category="schema_fix"
)
```

### `save_validated_query`

After successfully answering a question, the agent saves the query as a reusable pattern. This populates the same query patterns table used by the [Knowledge Base](/sql-agent/guides/knowledge-base/#query-patterns), so future searches can find proven SQL for similar questions.

The agent saves:
- The natural language question
- The validated SQL query
- A summary of what the query returns
- The tables used
- Optional data quality notes

:::tip
The system prompt instructs the agent to **always** save validated queries after successful execution. This means the agent's knowledge grows organically as it handles more questions — common queries get faster and more reliable over time.
:::

## Learning Categories

Learnings are automatically categorized:

| Category | Description |
|----------|-------------|
| `type_error` | Data type mismatches or casting issues |
| `schema_fix` | Incorrect schema assumptions (wrong table or column names) |
| `query_pattern` | Learned patterns for constructing queries |
| `data_quality` | Observations about data quality or anomalies |
| `business_logic` | Learned business rules or domain knowledge |

## Managing Learnings

Export and import learnings to share them across environments or back them up:

```bash
# Export all learnings to JSON
php artisan sql-agent:export-learnings

# Export a specific category
php artisan sql-agent:export-learnings --category=schema_fix

# Import learnings from a file
php artisan sql-agent:import-learnings learnings.json

# Prune learnings older than 90 days
php artisan sql-agent:prune-learnings --days=90

# Remove only duplicates
php artisan sql-agent:prune-learnings --duplicates

# Preview what would be removed
php artisan sql-agent:prune-learnings --dry-run
```

## Customizing the System Prompt

The agent's behavior is driven by a Blade template at `resources/prompts/system.blade.php`. This template defines the workflow, tool usage instructions, SQL rules, and response guidelines that the LLM follows.

To customize it, publish the prompt to your application:

```bash
php artisan vendor:publish --tag=sql-agent-prompts
```

This copies the template to `resources/views/vendor/sql-agent/prompts/system.blade.php`. Your published version takes precedence over the package default — Laravel's view override mechanism handles this automatically.

### What You Can Customize

The system prompt is a standard Blade template with access to config values and a `$context` variable containing the assembled knowledge. Common customizations include:

- **Changing the agent's persona** — Modify the opening instructions to match your domain (e.g., "You are a financial data analyst" instead of the default)
- **Adjusting the workflow** — Reorder or remove steps, add domain-specific instructions
- **Adding custom rules** — Include company-specific SQL conventions, naming standards, or data handling requirements
- **Changing response style** — Modify the insights guidelines to match your preferred output format
- **Restricting or expanding tool usage** — Adjust when the agent should save learnings or validated queries

### Example: Domain-Specific Prompt

```blade
You are an e-commerce analytics assistant that helps the team understand sales trends and customer behavior.

## Important Rules
- Always convert total_amount from cents to dollars in results
- Use fiscal quarters (Q1 = Feb-Apr) instead of calendar quarters
- Exclude test orders (email ending in @example.com) from all metrics
- When reporting revenue, always break down by currency

{{-- Keep the rest of the default template --}}
@if(config('sql-agent.learning.enabled', true))
## When to save_learning
...
@endif

## Context

{!! $context !!}
```

:::note
The `{!! $context !!}` variable at the end of the template is required — it injects the assembled knowledge (table metadata, business rules, query patterns, and learnings) that the agent needs to write accurate SQL.
:::

### Adjusting Temperature

The `temperature` setting in `config/sql-agent.php` controls how deterministic or creative the LLM's responses are:

```php
'llm' => [
    'temperature' => (float) env('SQL_AGENT_LLM_TEMPERATURE', 0.3),
],
```

| Temperature | Behavior |
|-------------|----------|
| `0.0` | Most deterministic — the agent produces concise, consistent answers with minimal variation between runs |
| `0.3–0.5` | Balanced — slight variation in wording and analysis while staying focused on the question |
| `0.7–1.0` | More creative — the agent may run additional queries, produce richer analysis, and provide more detailed explanations |

Lower temperatures work best for production environments where you want predictable, repeatable results. Higher temperatures can be useful during development or when you want the agent to explore the data more thoroughly and provide deeper insights.

:::caution
Higher temperatures increase token usage and response time since the agent may take more steps (additional SQL queries, longer explanations). They also increase the chance of hallucinations — the agent may generate incorrect SQL, reference non-existent columns, misinterpret query results, etc. Start with `0.0` or `0.3` and increase only if you need more exploratory behavior.
:::

## Disabling Self-Learning

To disable the self-learning feature entirely:

```ini
SQL_AGENT_LEARNING_ENABLED=false
```

This removes both `save_learning` and `save_validated_query` tools from the agent and hides the related instructions from the system prompt.

To keep manual learning (via the `save_learning` tool) but disable automatic error-based learning:

```ini
SQL_AGENT_AUTO_SAVE_ERRORS=false
```
