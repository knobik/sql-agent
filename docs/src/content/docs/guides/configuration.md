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

SqlAgent uses two types of database connections: connections for querying your application data, and a storage connection for its own internal tables (knowledge, learnings, conversations, etc.):

```php
'database' => [
    'storage_connection' => env('SQL_AGENT_STORAGE_CONNECTION', config('database.default')),

    'connections' => [
        'default' => [
            'connection' => env('SQL_AGENT_CONNECTION', config('database.default')),
            'label' => 'Database',
            'description' => 'Main application database.',
        ],
    ],
],
```

The `storage_connection` option determines where SqlAgent's own tables are stored. By default it uses your application's default connection.

### Database Connections

The `connections` map defines which databases the agent can query. By default a single `default` entry is configured that uses your application's default database connection. The agent autonomously decides which database to query for each question and can combine results across databases.

| Option | Description | Required |
|--------|-------------|----------|
| `connection` | Laravel database connection name (from `config/database.php`) | Yes |
| `label` | Human-readable label shown to the LLM and in the UI | No (defaults to the key) |
| `description` | What data this database holds — helps the LLM choose the right database | No |
| `allowed_tables` | Whitelist of tables the agent may access (empty = all) | No |
| `denied_tables` | Blacklist of tables the agent may never access | No |
| `hidden_columns` | Columns to hide per table | No |

:::tip
Write clear, descriptive `description` values. The LLM reads these to decide which database to query. "Orders, products, and customers" is much better than "Sales data".
:::

To add more databases, add entries to the `connections` map. See the [Database Connections](/sql-agent/guides/multi-database/) guide for a complete walkthrough.

## LLM

SqlAgent uses [Prism PHP](https://prismphp.com) as its LLM abstraction layer. Prism provides a unified interface for many providers including OpenAI, Anthropic, Ollama, Gemini, Mistral, xAI, and more.

```php
'llm' => [
    'provider' => env('SQL_AGENT_LLM_PROVIDER', 'openai'),
    'model' => env('SQL_AGENT_LLM_MODEL', 'gpt-4o'),
    'temperature' => (float) env('SQL_AGENT_LLM_TEMPERATURE', 0.3),
    'max_tokens' => (int) env('SQL_AGENT_LLM_MAX_TOKENS', 16384),
    'provider_options' => [],
    'cache_system_prompt' => (bool) env('SQL_AGENT_LLM_CACHE_SYSTEM_PROMPT', false),
    'cache_type' => env('SQL_AGENT_LLM_CACHE_TYPE', 'ephemeral'),
    'cache_ttl' => env('SQL_AGENT_LLM_CACHE_TTL'),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `provider` | The Prism provider name (`openai`, `anthropic`, `ollama`, `gemini`, etc.) | `openai` |
| `model` | The model identifier for the chosen provider | `gpt-4o` |
| `temperature` | Sampling temperature (0.0 = deterministic, 1.0 = creative) | `0.3` |
| `max_tokens` | Maximum tokens in the LLM response | `16384` |
| `provider_options` | Additional provider-specific options passed to Prism's `withProviderOptions()` | `[]` |
| `cache_system_prompt` | Mark the static part of the system prompt as cacheable | `false` |
| `cache_type` | Cache type sent to the provider. Anthropic supports `ephemeral` | `ephemeral` |
| `cache_ttl` | Cache lifetime. Anthropic supports `5m` (its default) and `1h` | `null` |

### Prompt Caching

The system prompt is sent as two blocks. The static prefix holds the instructions, the tool descriptions, the database schema, and the business rules — it is identical for every question. The dynamic suffix holds the current time and the query patterns and learnings retrieved for the question at hand.

Setting `cache_system_prompt` marks the static prefix as cacheable, so the provider stores it after the first request and reuses it afterwards. This matters most in the agentic loop: without caching, a schema of tens of thousands of tokens is re-processed on every tool-calling round, which shows up as both latency and cost.

```ini
SQL_AGENT_LLM_CACHE_SYSTEM_PROMPT=true
SQL_AGENT_LLM_CACHE_TTL=1h
```

:::note
Prompt caching is an Anthropic feature. Other providers ignore these options, so leaving them enabled is harmless when you switch providers.
:::

:::caution
Anthropic charges a write premium the first time a prefix is cached. The prefix must be stable to earn that back, so avoid putting per-request values into a published `system.blade.php` — a timestamp at the top of the prompt invalidates the cache on every request. Schema retrieval keeps the prefix stable by placing the retrieved schema in the dynamic block instead.
:::

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
- **`pgvector`** — Uses PostgreSQL pgvector for semantic similarity search via vector embeddings. Requires the `pgvector/pgvector` Composer package and a dedicated PostgreSQL connection with pgvector installed. See the [pgvector setup guide](/sql-agent/guides/drivers/#pgvector).
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

Both the `database` and `pgvector` drivers support an `index_mapping` option that maps search index names to Eloquent model classes. By default, the drivers register three indexes:

| Index | Model |
|-------|-------|
| `query_patterns` | `Knobik\SqlAgent\Models\QueryPattern` |
| `learnings` | `Knobik\SqlAgent\Models\Learning` |
| `table_metadata` | `Knobik\SqlAgent\Models\TableMetadata` |

The `table_metadata` index backs [schema retrieval](#schema-retrieval). It is a built-in index rather than a custom one, so its results are never included as "Additional Knowledge".

You can add custom indexes by providing an `index_mapping` array in the driver config. Custom mappings are merged with the defaults, so you only need to specify additional indexes:

```php
'database' => [
    // ...
    'index_mapping' => [
        'faq' => \App\Models\Faq::class,
    ],
],
```

Custom indexes are fully integrated into the search system:

- The `search_knowledge` tool automatically exposes custom indexes to the LLM as additional type options.
- The `ContextBuilder` searches custom indexes and includes matching results as "Additional Knowledge" in the system prompt.
- Both `database` and `pgvector` drivers support custom indexes identically.

Each model referenced in `index_mapping` must extend `Illuminate\Database\Eloquent\Model` and implement the `Knobik\SqlAgent\Contracts\Searchable` interface, which requires two methods:

- `getSearchableColumns()` — Returns the column names to index for search.
- `toSearchableArray()` — Returns the searchable representation of the model.

## Schema

The `schema` option controls how much table metadata reaches the system prompt:

```php
'schema' => [
    'mode' => env('SQL_AGENT_SCHEMA_MODE', 'full'),

    'rag' => [
        'limit' => (int) env('SQL_AGENT_SCHEMA_RAG_LIMIT', 10),
        'always_include' => [],
        'expand_relationships' => (bool) env('SQL_AGENT_SCHEMA_RAG_EXPAND_RELATIONSHIPS', true),
        'expansion_depth' => (int) env('SQL_AGENT_SCHEMA_RAG_EXPANSION_DEPTH', 1),
        'include_table_list' => (bool) env('SQL_AGENT_SCHEMA_RAG_INCLUDE_TABLE_LIST', true),
    ],
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `mode` | `full` describes every accessible table, `rag` describes only the tables retrieved for the question | `full` |
| `rag.limit` | Number of tables retrieved per connection | `10` |
| `rag.always_include` | Table names always described, whatever the question | `[]` |
| `rag.expand_relationships` | Also describe the tables that the retrieved tables relate to | `true` |
| `rag.expansion_depth` | How many rounds of relationship expansion to perform | `1` |
| `rag.include_table_list` | List the names of the tables that were left out | `true` |

In `full` mode, every table the agent may access is described on every request. This is the most accurate option and the right default: the model can see the whole data model at once.

That stops being free once the schema is large. A hundred-table schema can run to tens of thousands of tokens, and in `full` mode you pay for them on every tool-calling round of every request.

### Schema Retrieval

Setting `mode` to `rag` describes only the tables relevant to the question. Table metadata is searched through the configured [search driver](/guides/drivers/), exactly like query patterns and learnings:

```ini
SQL_AGENT_SCHEMA_MODE=rag
SQL_AGENT_SCHEMA_RAG_LIMIT=10
```

Retrieval alone tends to miss join targets — a question about revenue matches `orders`, not the `customers` table it joins to. Three settings close that gap:

- `expand_relationships` adds the tables named in the relationships of the retrieved tables, so join targets come along.
- `always_include` pins the tables that belong in every query, such as a tenants or users table.
- `include_table_list` names the omitted tables without describing them, so the agent knows they exist and can reach for `introspect_schema`.

When retrieval returns nothing — an unusual question, or embeddings that were never generated — the full semantic model is used instead. An incomplete schema produces wrong SQL, so the fallback errs toward the larger prompt.

With the `pgvector` driver, generate embeddings for your table metadata before switching modes:

```bash
php artisan sql-agent:generate-embeddings --model=table_metadata
```

:::caution
Retrieval trades accuracy for latency. A table that is not retrieved cannot be queried correctly, and the failure is quiet — the agent writes plausible SQL against the tables it was given. Run your [evaluation suite](/guides/evaluation/) in both modes before enabling this in production.
:::

:::note
In `rag` mode the schema moves into the dynamic part of the system prompt, since it changes with every question. This keeps the cacheable prefix stable when `cache_system_prompt` is also enabled.
:::

## Agent Behavior

Control how the agentic loop operates:

```php
'agent' => [
    'max_iterations' => env('SQL_AGENT_MAX_ITERATIONS', 10),
    'default_limit' => env('SQL_AGENT_DEFAULT_LIMIT', 100),
    'chat_history_length' => env('SQL_AGENT_CHAT_HISTORY', 10),
    'search_first' => (bool) env('SQL_AGENT_SEARCH_FIRST', false),
    'ask_user_timeout' => env('SQL_AGENT_ASK_USER_TIMEOUT', 300),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `max_iterations` | Maximum number of tool-calling rounds before the agent stops | `10` |
| `default_limit` | `LIMIT` applied to queries that don't specify one | `100` |
| `chat_history_length` | Number of previous messages included for conversational context | `10` |
| `search_first` | Instruct the agent to always call `search_knowledge` before writing SQL | `false` |
| `ask_user_timeout` | Seconds to wait for a user reply when the `ask_user` tool is invoked | `300` |

### Search-First Behavior

Every request already runs a search for the question and puts the matching query patterns and learnings into the context. With `search_first` disabled, the agent is told to read that context and reach for `search_knowledge` only when it proves insufficient — which saves a full round trip on most questions.

Enable it when your knowledge base is large enough that the top matches regularly miss what the agent needs, and you would rather pay for the extra round trip than risk a worse query:

```ini
SQL_AGENT_SEARCH_FIRST=true
```

### Custom Tools

All agent tools — including built-in ones — are registered via the `tools` array. You can add your own tools, remove built-in ones you don't need, or reorder them:

```php
'agent' => [
    // ... other options ...
    'tools' => array_merge([
        \Knobik\SqlAgent\Tools\RunSqlTool::class,
        \Knobik\SqlAgent\Tools\IntrospectSchemaTool::class,
        \Knobik\SqlAgent\Tools\SearchKnowledgeTool::class,
        \Knobik\SqlAgent\Tools\AskUserTool::class,
    ], env('SQL_AGENT_LEARNING_ENABLED', true) ? [
        \Knobik\SqlAgent\Tools\SaveLearningTool::class,
        \Knobik\SqlAgent\Tools\SaveQueryTool::class,
    ] : []),
],
```

To disable a tool, simply remove its entry from the array. For example, remove `AskUserTool::class` to prevent the LLM from asking clarifying questions. The learning tools (`SaveLearningTool`, `SaveQueryTool`) are automatically included or excluded based on the `SQL_AGENT_LEARNING_ENABLED` environment variable.

Each class must extend `Prism\Prism\Tool` and is resolved from the Laravel container with full dependency injection support. See the [Custom Tools](/sql-agent/guides/custom-tools/) guide for detailed examples and best practices.

### MCP Server Tools (Relay)

If you have [Prism Relay](https://github.com/prism-php/relay) installed, you can bring tools from MCP servers into the agent by listing server names from `config/relay.php`:

```php
'agent' => [
    // ... other options ...
    'relay' => [
        'weather-server',
        'filesystem-server',
    ],
],
```

The `relay` key is silently ignored when `prism-php/relay` is not installed. See the [Custom Tools](/sql-agent/guides/custom-tools/#mcp-server-tools-relay) guide for full setup instructions.

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

Configure the knowledge base path:

```php
'knowledge' => [
    'path' => env('SQL_AGENT_KNOWLEDGE_PATH', resource_path('sql-agent/knowledge')),
],
```

The `path` option sets the directory containing your JSON knowledge files. This path is used by the `sql-agent:load-knowledge` command to import files into the database.

Knowledge is always read from the database at runtime — from the `sql_agent_table_metadata`, `sql_agent_business_rules`, and `sql_agent_query_patterns` tables. You must run `php artisan sql-agent:load-knowledge` after creating or changing knowledge files.

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

Set `SQL_AGENT_UI_ENABLED=false` to disable the web interface entirely. See the [Web Interface](/sql-agent/guides/web-interface/) guide for more details on customization.

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

### Table & Column Restrictions

Table and column restrictions are configured per connection in the `database.connections` map. Each connection can define its own `allowed_tables`, `denied_tables`, and `hidden_columns`. See the [Database Connections](/sql-agent/guides/multi-database/) guide for details.

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

See the [Evaluation & Self-Learning](/sql-agent/guides/evaluation/) guide for details on running evaluations.

## Debug

Enable debug mode to store detailed metadata alongside each assistant message:

```php
'debug' => [
    'enabled' => env('SQL_AGENT_DEBUG', false),
],
```

When enabled, each message's `metadata` column will include the full system prompt, tool schemas, iteration details, and timing data. This is useful for development but adds significant storage overhead (~50–60 KB per message). Disable in production.

See the [Web Interface — Debug Mode](/sql-agent/guides/web-interface/#debug-mode) guide for details on what gets stored and how to inspect it.
