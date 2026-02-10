---
title: Recommended Configurations
description: Ready-to-use configuration presets for common deployment scenarios.
sidebar:
  order: 2
---

These are opinionated starting points designed to get you up and running quickly. Copy the preset that matches your goals, then tweak as needed.

## Best Results

This preset prioritizes SQL generation quality above all else. It pairs a top-tier cloud LLM with semantic vector search for the most accurate knowledge retrieval.

### Environment Variables

```ini
# LLM — Anthropic Claude Sonnet
SQL_AGENT_LLM_PROVIDER=anthropic
SQL_AGENT_LLM_MODEL=claude-sonnet-4-20250514
SQL_AGENT_LLM_TEMPERATURE=0.3

# Search — pgvector semantic search
SQL_AGENT_SEARCH_DRIVER=pgvector

# Embeddings — OpenAI (used by pgvector driver)
SQL_AGENT_EMBEDDINGS_CONNECTION=pgvector
SQL_AGENT_EMBEDDINGS_PROVIDER=openai
SQL_AGENT_EMBEDDINGS_MODEL=text-embedding-3-small
SQL_AGENT_EMBEDDINGS_DIMENSIONS=1536

# Agent — more iterations for self-correction
SQL_AGENT_MAX_ITERATIONS=15

# Learning — enabled with auto error capture
SQL_AGENT_LEARNING_ENABLED=true
SQL_AGENT_AUTO_SAVE_ERRORS=true

# Knowledge — database source for full-text search
SQL_AGENT_KNOWLEDGE_SOURCE=database
```

### pgvector Database Connection

The pgvector search driver needs a dedicated PostgreSQL connection with the pgvector extension installed. Add this to your `config/database.php` connections array:

```php
'pgvector' => [
    'driver' => 'pgsql',
    'host' => env('PGVECTOR_HOST', '127.0.0.1'),
    'port' => env('PGVECTOR_PORT', '5432'),
    'database' => env('PGVECTOR_DATABASE', 'forge'),
    'username' => env('PGVECTOR_USERNAME', 'forge'),
    'password' => env('PGVECTOR_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
],
```

:::tip
This preset requires an **Anthropic API key** (configured in `config/prism.php`) and a **PostgreSQL instance with the pgvector extension** installed. If you already use PostgreSQL as your main database, you can point the embeddings connection at the same instance.
:::

## Budget-Friendly

This preset keeps everything local with zero API costs. Your data never leaves your machine, making it ideal for development, experimentation, or privacy-sensitive environments.

### Environment Variables

```ini
# LLM — Ollama local model
SQL_AGENT_LLM_PROVIDER=ollama
SQL_AGENT_LLM_MODEL=qwen3:8b
SQL_AGENT_LLM_TEMPERATURE=0.3

# Search — database full-text search (no external services)
SQL_AGENT_SEARCH_DRIVER=database

# Agent — default iterations
SQL_AGENT_MAX_ITERATIONS=10

# Learning — enabled
SQL_AGENT_LEARNING_ENABLED=true
SQL_AGENT_AUTO_SAVE_ERRORS=true

# Knowledge — database source
SQL_AGENT_KNOWLEDGE_SOURCE=database
```

No embeddings configuration is needed since the database search driver uses native full-text search instead of vector embeddings.

:::tip
Make sure Ollama is installed and running before starting your application. Pull the model first:

```bash
ollama pull qwen3:8b
ollama serve
```

Then configure the Ollama base URL in `config/prism.php` (publish with `php artisan vendor:publish --tag=prism-config`).
:::

:::caution
Local model quality depends heavily on your hardware and model choice. An 8B parameter model works well on machines with 16 GB+ RAM. If you have a GPU with sufficient VRAM, consider larger models like `qwen3:14b` or `qwen3:32b` for better results.
:::

## Mixing and Matching

The LLM provider and search driver are completely independent — you can freely combine any LLM with any search driver. For example, you could pair Ollama for local inference with pgvector for high-quality semantic search, or use Anthropic Claude with the simple database full-text driver if you don't need vector embeddings. Pick the LLM that fits your quality and cost requirements, then choose the search driver that matches your infrastructure.

These presets are just starting points. See the full [Configuration](/sql-agent/guides/configuration/) guide for every available option and the [LLM & Search Drivers](/sql-agent/guides/drivers/) guide for all supported providers and search backends.
