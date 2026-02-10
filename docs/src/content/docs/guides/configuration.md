---
title: Configuration
description: All SqlAgent configuration options — database, LLM, search, safety, and more.
sidebar:
  order: 1
---

All SqlAgent configuration lives in the `config/sql-agent.php` file. Each option is documented below with its purpose, accepted values, and default.

After installation, you can publish the configuration file using:

```bash
php artisan vendor:publish --tag=sql-agent-config
```

## Display Name

The `name` option defines the display name used in the web UI and log messages:

```php
'name' => 'SqlAgent',
```

## Database

SqlAgent uses two database connections: one for querying your application data, and one for storing its own internal tables (knowledge, learnings, conversations, etc.):

```php
'database' => [
    'connection' => env('SQL_AGENT_CONNECTION', config('database.default')),
    'storage_connection' => env('SQL_AGENT_STORAGE_CONNECTION', config('database.default')),
],
```

The `connection` option determines which database the agent will run queries against. The `storage_connection` option determines where SqlAgent's own tables are stored. By default, both use your application's default connection.

:::tip
If your application data lives on a separate database from your main application, set `SQL_AGENT_CONNECTION` accordingly. You may also want to store SqlAgent's tables on a different connection using `SQL_AGENT_STORAGE_CONNECTION`.
:::

## LLM

SqlAgent uses [Prism PHP](https://prismphp.com) as its LLM abstraction layer. Prism provides a unified interface for many providers including OpenAI, Anthropic, Ollama, Gemini, Mistral, xAI, and more.

```php
'llm' => [
    'provider' => env('SQL_AGENT_LLM_PROVIDER', 'openai'),
    'model' => env('SQL_AGENT_LLM_MODEL', 'gpt-4o'),
    'temperature' => (float) env('SQL_AGENT_LLM_TEMPERATURE', 0.3),
    'max_tokens' => (int) env('SQL_AGENT_LLM_MAX_TOKENS', 16384),
    'provider_options' => [],
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `provider` | The Prism provider name (`openai`, `anthropic`, `ollama`, `gemini`, etc.) | `openai` |
| `model` | The model identifier for the chosen provider | `gpt-4o` |
| `temperature` | Sampling temperature (0.0 = deterministic, 1.0 = creative) | `0.3` |
| `max_tokens` | Maximum tokens in the LLM response | `16384` |
| `provider_options` | Additional provider-specific options passed to Prism's `withProviderOptions()` | `[]` |

Provider credentials (API keys, base URLs) are configured in Prism's own config file. Publish it with:

```bash
php artisan vendor:publish --tag=prism-config
```

Then configure your provider in `config/prism.php`. See the [Prism documentation](https://prismphp.com) for details on each provider.

### Quick Setup Examples

**OpenAI** (default):

```ini
SQL_AGENT_LLM_PROVIDER=openai
SQL_AGENT_LLM_MODEL=gpt-4o
```

Set your API key in `config/prism.php` or via `OPENAI_API_KEY` in `.env`.

**Anthropic:**

```ini
SQL_AGENT_LLM_PROVIDER=anthropic
SQL_AGENT_LLM_MODEL=claude-sonnet-4-20250514
```

**Ollama** (local):

```ini
SQL_AGENT_LLM_PROVIDER=ollama
SQL_AGENT_LLM_MODEL=llama3.1
```

**Thinking Mode** (for models that support it):

Use `provider_options` in the config to enable thinking/reasoning mode:

```php
'provider_options' => ['thinking' => true],
```

When thinking mode is active, the LLM's internal reasoning is captured in streaming SSE events and stored in debug metadata.

## Search

Search drivers determine how SqlAgent finds relevant knowledge (table metadata, business rules, query patterns) based on the user's question:

```php
'search' => [
    'default' => env('SQL_AGENT_SEARCH_DRIVER', 'database'),

    'drivers' => [
        'database' => [
            'mysql' => ['mode' => 'NATURAL LANGUAGE MODE'],
            'pgsql' => ['language' => 'english'],
            'sqlsrv' => [],
        ],

        'pgvector' => [
            'connection' => env('SQL_AGENT_EMBEDDINGS_CONNECTION'),
            'provider' => env('SQL_AGENT_EMBEDDINGS_PROVIDER', 'openai'),
            'model' => env('SQL_AGENT_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('SQL_AGENT_EMBEDDINGS_DIMENSIONS', 1536),
            'distance_metric' => 'cosine',
        ],
    ],
],
```

Three drivers are available:

- **`database`** — Uses native full-text search (`MATCH ... AGAINST` on MySQL, `tsvector` on PostgreSQL, `LIKE` on SQLite, `CONTAINS` on SQL Server). No external services required.
- **`pgvector`** — Uses PostgreSQL pgvector for semantic similarity search via vector embeddings. Requires a dedicated PostgreSQL connection with pgvector installed. See the pgvector options below.
- **`null`** — Disables search entirely. Useful for testing or when knowledge search is not needed.

### Database Driver Options

| Option | Description | Default |
|--------|-------------|---------|
| `mysql.mode` | MySQL full-text search mode (`NATURAL LANGUAGE MODE` or `BOOLEAN MODE`) | `NATURAL LANGUAGE MODE` |
| `pgsql.language` | PostgreSQL text search language (`english`, `spanish`, `german`, etc.) | `english` |
| `index_mapping` | Custom index name to model class mapping (see [Index Mapping](#index-mapping)) | `[]` |

### pgvector Driver Options

| Option | Description | Default |
|--------|-------------|---------|
| `connection` | Dedicated PostgreSQL connection name for embedding storage | `null` |
| `provider` | Prism embedding provider (`openai`, `ollama`, `gemini`, `mistral`, `voyageai`) | `openai` |
| `model` | Embedding model identifier | `text-embedding-3-small` |
| `dimensions` | Vector dimensions (must match the model's output dimensions) | `1536` |
| `distance_metric` | Distance function for similarity search (`cosine`, `l2`, `inner_product`) | `cosine` |
| `index_mapping` | Custom index name to model class mapping (see below) | `[]` |

:::caution
The `connection` must point to a PostgreSQL database with the pgvector extension installed. This connection is only used for embedding storage — your main app and SqlAgent storage tables can use any supported database.
:::

### Index Mapping

Both the `database` and `pgvector` drivers support an `index_mapping` option that maps search index names to Eloquent model classes. By default, the drivers register two indexes:

| Index | Model |
|-------|-------|
| `query_patterns` | `Knobik\SqlAgent\Models\QueryPattern` |
| `learnings` | `Knobik\SqlAgent\Models\Learning` |

You can add custom indexes by providing an `index_mapping` array in the driver config. Custom mappings are merged with the defaults, so you only need to specify additional indexes:

```php
'database' => [
    // ...
    'index_mapping' => [
        'custom_index' => \App\Models\CustomModel::class,
    ],
],
```

Each model referenced in `index_mapping` must extend `Illuminate\Database\Eloquent\Model` and implement the `Knobik\SqlAgent\Contracts\Searchable` interface, which requires two methods:

- `getSearchableColumns()` — Returns the column names to index for search.
- `toSearchableArray()` — Returns the searchable representation of the model.

## Agent Behavior

Control how the agentic loop operates:

```php
'agent' => [
    'max_iterations' => env('SQL_AGENT_MAX_ITERATIONS', 10),
    'default_limit' => env('SQL_AGENT_DEFAULT_LIMIT', 100),
    'chat_history_length' => env('SQL_AGENT_CHAT_HISTORY', 10),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `max_iterations` | Maximum number of tool-calling rounds before the agent stops | `10` |
| `default_limit` | `LIMIT` applied to queries that don't specify one | `100` |
| `chat_history_length` | Number of previous messages included for conversational context | `10` |

## Learning

SqlAgent can automatically learn from SQL errors and improve over time:

```php
'learning' => [
    'enabled' => env('SQL_AGENT_LEARNING_ENABLED', true),
    'auto_save_errors' => env('SQL_AGENT_AUTO_SAVE_ERRORS', true),
    'prune_after_days' => env('SQL_AGENT_LEARNING_PRUNE_DAYS', 90),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `enabled` | Enable the self-learning feature | `true` |
| `auto_save_errors` | Automatically create learnings when SQL errors occur and the agent recovers | `true` |
| `prune_after_days` | Age threshold (in days) for the prune command | `90` |

The `prune_after_days` value is used by the `sql-agent:prune-learnings` Artisan command. This command is **not scheduled automatically** — you need to run it manually or register it in your scheduler:

```php
// routes/console.php
Schedule::command('sql-agent:prune-learnings')->daily();
```

## Knowledge

Configure where SqlAgent reads knowledge from at runtime:

```php
'knowledge' => [
    'path' => env('SQL_AGENT_KNOWLEDGE_PATH', resource_path('sql-agent/knowledge')),
    'source' => env('SQL_AGENT_KNOWLEDGE_SOURCE', 'database'),
],
```

The `path` option sets the directory containing your JSON knowledge files. This path is used both when loading knowledge via `sql-agent:load-knowledge` and when the `files` source reads directly from disk.

The `source` option controls how the agent loads knowledge at runtime:

- **`database`** (default, recommended) — Reads from the `sql_agent_table_metadata`, `sql_agent_business_rules`, and `sql_agent_query_patterns` tables. You must run `php artisan sql-agent:load-knowledge` to import your JSON files first. Supports full-text search over knowledge.
- **`files`** — Reads directly from JSON files on disk. No import step needed, but full-text search is not available.

## Web Interface

SqlAgent ships with a Livewire chat UI. Configure its routes and access:

```php
'ui' => [
    'enabled' => env('SQL_AGENT_UI_ENABLED', true),
    'route_prefix' => env('SQL_AGENT_ROUTE_PREFIX', 'sql-agent'),
    'middleware' => ['web', 'auth'],
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `enabled` | Enable the web interface | `true` |
| `route_prefix` | URL prefix for the UI (e.g., `/sql-agent`) | `sql-agent` |
| `middleware` | Middleware applied to all UI routes | `['web', 'auth']` |

Set `SQL_AGENT_UI_ENABLED=false` to disable the web interface entirely. See the [Web Interface](/laravel-sql-agent/guides/web-interface/) guide for more details on customization.

## User Tracking

By default, user tracking is disabled. Enable it to scope conversations and learnings per user:

```php
'user' => [
    'enabled' => env('SQL_AGENT_USER_ENABLED', false),
    'model' => null,
    'resolver' => null,
],
```

When enabled, SqlAgent uses `auth()->id()` to resolve the current user. You can customize this for non-standard authentication setups:

**Custom auth guard:**

```php
'user' => [
    'enabled' => true,
    'model' => \App\Models\Admin::class,
    'resolver' => fn () => auth('admin')->id(),
],
```

**Multi-tenancy:**

```php
'user' => [
    'enabled' => true,
    'resolver' => fn () => tenant()->owner_id,
],
```

## SQL Safety

SqlAgent includes configurable guardrails to prevent destructive SQL operations:

```php
'sql' => [
    'allowed_statements' => ['SELECT', 'WITH'],
    'forbidden_keywords' => [
        'DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER',
        'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE',
    ],
    'max_rows' => env('SQL_AGENT_MAX_ROWS', 1000),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `allowed_statements` | Only these SQL statement types may be executed | `['SELECT', 'WITH']` |
| `forbidden_keywords` | Queries containing these keywords are rejected | See above |
| `max_rows` | Maximum number of rows returned by any query | `1000` |
| `allowed_tables` | Whitelist of tables the agent may access (empty = all allowed) | `[]` |
| `denied_tables` | Blacklist of tables the agent may never access (takes precedence over `allowed_tables`) | `[]` |
| `hidden_columns` | Columns to hide per table (associative array) | `[]` |

### Table & Column Restrictions

You can restrict which tables and columns the agent can see and query. This is useful for preventing access to sensitive data such as password hashes, API keys, or audit logs.

```php
'sql' => [
    // ... other options ...

    // Only allow the agent to access these tables (empty = all tables allowed)
    'allowed_tables' => ['users', 'orders', 'products'],

    // Always deny access to these tables (takes precedence over allowed_tables)
    'denied_tables' => ['password_resets', 'personal_access_tokens'],

    // Hide specific columns from the agent per table
    'hidden_columns' => [
        'users' => ['password', 'remember_token', 'two_factor_secret'],
    ],
],
```

**How it works:**

- **`allowed_tables`** acts as a whitelist. When non-empty, only listed tables are visible to the agent. Leave empty to allow all tables.
- **`denied_tables`** acts as a blacklist. Listed tables are always denied, even if they appear in `allowed_tables`. This takes precedence.
- **`hidden_columns`** removes specific columns from schema introspection and semantic model output. The agent will not know these columns exist.

Restrictions are enforced at every layer:

- Schema introspection (listing tables, inspecting columns)
- Semantic model loading (table metadata from files or database)
- SQL execution (queries referencing denied tables are rejected)
- Query pattern saving (patterns cannot reference restricted tables)

Restricted tables and hidden columns are never exposed to the LLM. The agent simply cannot see them in any schema or metadata, and any SQL that references a denied table is rejected at execution time.

:::caution
Table name extraction from SQL is regex-based and best-effort. It catches common patterns (`FROM`, `JOIN`) but may not detect every reference in complex queries. Always combine table restrictions with other safety measures such as database-level permissions.
:::

## Evaluation

Configure the evaluation framework for testing agent accuracy:

```php
'evaluation' => [
    'grader_provider' => env('SQL_AGENT_GRADER_PROVIDER', 'openai'),
    'grader_model' => env('SQL_AGENT_GRADER_MODEL', 'gpt-4o-mini'),
    'pass_threshold' => env('SQL_AGENT_EVAL_PASS_THRESHOLD', 0.6),
    'timeout' => env('SQL_AGENT_EVAL_TIMEOUT', 60),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `grader_provider` | Prism provider used for semantic grading | `openai` |
| `grader_model` | LLM model used for semantic grading of test results | `gpt-4o-mini` |
| `pass_threshold` | Minimum score (0.0–1.0) to pass LLM grading | `0.6` |
| `timeout` | Maximum seconds allowed per test case | `60` |

See the [Evaluation & Self-Learning](/laravel-sql-agent/guides/evaluation/) guide for details on running evaluations.

## Debug

Enable debug mode to store detailed metadata alongside each assistant message:

```php
'debug' => [
    'enabled' => env('SQL_AGENT_DEBUG', false),
],
```

When enabled, each message's `metadata` column will include the full system prompt, tool schemas, iteration details, and timing data. This is useful for development but adds significant storage overhead (~50–60 KB per message). Disable in production.

See the [Web Interface — Debug Mode](/laravel-sql-agent/guides/web-interface/#debug-mode) guide for details on what gets stored and how to inspect it.
