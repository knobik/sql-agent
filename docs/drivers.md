# LLM & Search Drivers

## LLM Drivers

### OpenAI

The default driver. Requires the OpenAI API key.

```env
OPENAI_API_KEY=sk-your-api-key
SQL_AGENT_LLM_DRIVER=openai
SQL_AGENT_OPENAI_MODEL=gpt-4o
```

### Anthropic

Use Claude models from Anthropic.

```env
ANTHROPIC_API_KEY=sk-ant-your-api-key
SQL_AGENT_LLM_DRIVER=anthropic
SQL_AGENT_ANTHROPIC_MODEL=claude-sonnet-4-20250514
```

### Ollama

Use local models with Ollama. No API key required.

```env
SQL_AGENT_LLM_DRIVER=ollama
OLLAMA_BASE_URL=http://localhost:11434
SQL_AGENT_OLLAMA_MODEL=llama3.1
```

### Custom Drivers

Implement the `Knobik\SqlAgent\Contracts\LlmDriver` interface:

```php
<?php

namespace App\Llm;

use Generator;
use Knobik\SqlAgent\Contracts\LlmDriver;
use Knobik\SqlAgent\Contracts\LlmResponse;

class CustomLlmDriver implements LlmDriver
{
    public function chat(array $messages, array $tools = []): LlmResponse
    {
        // Your implementation
    }

    public function stream(array $messages, array $tools = []): Generator
    {
        // Your implementation
    }

    public function supportsToolCalling(): bool
    {
        return true;
    }
}
```

Register in a service provider:

```php
$this->app->bind('sql-agent.llm.custom', CustomLlmDriver::class);
```

## Search Drivers

Search drivers are used to find relevant knowledge (table metadata, business rules, query patterns) based on the user's question.

### Database Driver

Uses native database full-text search. No external services required.

```env
SQL_AGENT_SEARCH_DRIVER=database
```

**MySQL:** Uses `MATCH ... AGAINST` with natural language or boolean mode.

**PostgreSQL:** Uses `to_tsvector` and `to_tsquery` for full-text search.

**SQLite:** Falls back to `LIKE` queries (less accurate but functional).

**SQL Server:** Uses `CONTAINS` full-text predicates (requires full-text catalog).

### Scout Driver

Integrates with Laravel Scout for external search engines like Meilisearch or Algolia.

```env
SQL_AGENT_SEARCH_DRIVER=scout
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-key
```

Requires `laravel/scout` package:

```bash
composer require laravel/scout
```

### Hybrid Driver

Combines Scout as primary with database as fallback. Useful for reliability.

```env
SQL_AGENT_SEARCH_DRIVER=hybrid
```

Configure in `config/sql-agent.php`:

```php
'hybrid' => [
    'primary' => 'scout',
    'fallback' => 'database',
    'merge_results' => false, // Set true to combine results from both
],
```
