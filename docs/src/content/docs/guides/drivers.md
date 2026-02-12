---
title: LLM & Search Drivers
description: Configure LLM providers via Prism PHP and search drivers for knowledge retrieval.
sidebar:
  order: 4
---

SqlAgent uses [Prism PHP](https://prismphp.com) for LLM integration and a driver-based architecture for knowledge search. You can switch providers and drivers via environment variables without changing any code.

## LLM Providers (via Prism PHP)

SqlAgent delegates all LLM communication to Prism PHP, which provides a unified interface for many providers. Set the active provider and model using environment variables:

```ini
SQL_AGENT_LLM_PROVIDER=openai
SQL_AGENT_LLM_MODEL=gpt-4o
```

Provider credentials (API keys, base URLs) are configured in Prism's own config file at `config/prism.php`. Publish it with:

```bash
php artisan vendor:publish --tag=prism-config
```

### Available Providers

Prism supports a wide range of providers out of the box. Here are some common options:

| Provider | `SQL_AGENT_LLM_PROVIDER` | Example Model |
|----------|--------------------------|---------------|
| OpenAI | `openai` | `gpt-4o`, `gpt-4o-mini` |
| Anthropic | `anthropic` | `claude-sonnet-4-20250514` |
| Ollama | `ollama` | `llama3.1`, `qwen2.5` |
| Google Gemini | `gemini` | `gemini-2.0-flash` |
| Mistral | `mistral` | `mistral-large-latest` |
| xAI | `xai` | `grok-2` |

See the [Prism documentation](https://prismphp.com) for the full list of supported providers and their configuration.

### Provider-Specific Options

Use the `provider_options` config array to pass provider-specific options. For example, to enable thinking/reasoning mode on Ollama models:

```php
// config/sql-agent.php
'llm' => [
    'provider' => 'ollama',
    'model' => 'qwen2.5',
    'provider_options' => ['thinking' => true],
],
```

These options are passed directly to Prism's `withProviderOptions()` method.

### Adding a Custom Provider

Since SqlAgent uses Prism for all LLM communication, adding a new provider means adding it to Prism. See the [Prism documentation](https://prismphp.com) for instructions on registering custom providers.

## Search Drivers

Search drivers control how SqlAgent finds relevant knowledge (table metadata, business rules, query patterns) based on the user's question. Set the active driver using `SQL_AGENT_SEARCH_DRIVER`.

### Database

Uses your database's native full-text search capabilities. No external services required:

```ini
SQL_AGENT_SEARCH_DRIVER=database
```

The behavior varies by database engine:

| Database | Implementation | Notes |
|----------|---------------|-------|
| MySQL | `MATCH ... AGAINST` | Supports natural language and boolean mode |
| PostgreSQL | `to_tsvector` / `to_tsquery` | Configurable text search language |
| SQLite | `LIKE` queries | Less accurate, but functional for development |
| SQL Server | `CONTAINS` predicates | Requires a full-text catalog to be configured |

### pgvector

Uses PostgreSQL's [pgvector](https://github.com/pgvector/pgvector) extension for semantic similarity search via vector embeddings. This provides the most accurate search results by understanding the meaning of queries rather than just matching keywords.

The pgvector driver uses a **dedicated PostgreSQL connection** for storing embeddings, separate from your main application database. This means you can use MySQL, SQLite, or any other database for your app and SqlAgent storage while running pgvector on a specialized PostgreSQL instance.

#### Installation

The pgvector search driver requires the `pgvector/pgvector` Composer package, which is not installed by default:

```bash
composer require pgvector/pgvector
```

#### Configuration

Add a PostgreSQL connection to `config/database.php`:

```php
'connections' => [
    'pgvector' => [
        'driver' => 'pgsql',
        'host' => env('PGVECTOR_HOST', '127.0.0.1'),
        'port' => env('PGVECTOR_PORT', '5432'),
        'database' => env('PGVECTOR_DATABASE', 'embeddings'),
        'username' => env('PGVECTOR_USERNAME', 'postgres'),
        'password' => env('PGVECTOR_PASSWORD', ''),
    ],
],
```

Set the environment variables in your `.env`:

```ini
SQL_AGENT_SEARCH_DRIVER=pgvector
SQL_AGENT_EMBEDDINGS_CONNECTION=pgvector
SQL_AGENT_EMBEDDINGS_PROVIDER=openai
SQL_AGENT_EMBEDDINGS_MODEL=text-embedding-3-small
SQL_AGENT_EMBEDDINGS_DIMENSIONS=1536
```

#### Setting Up the Database

Run the setup command to publish the pgvector migration and create the embeddings table:

```bash
php artisan sql-agent:setup-pgvector
```

This command will:

1. Verify that `SQL_AGENT_EMBEDDINGS_CONNECTION` points to a PostgreSQL database
2. Publish the embeddings migration
3. Run migrations (creates the extension, table, and HNSW index)

Then generate embeddings for any existing knowledge base records:

```bash
php artisan sql-agent:generate-embeddings
```

Embeddings are automatically kept in sync when records are created or updated.

:::tip
The `sql-agent:setup-pgvector` command is idempotent — it skips table creation if the table already exists, so it's safe to run more than once.
:::
