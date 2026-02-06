# Configuration

The configuration file is located at `config/sql-agent.php`. Here are all available options.

## Display Name

```php
'name' => 'SqlAgent',
```

The display name used in the UI and logs.

## Database Configuration

```php
'database' => [
    // The database connection to query (your application data)
    'connection' => env('SQL_AGENT_CONNECTION', config('database.default')),

    // The connection for SqlAgent's own tables (knowledge, learnings, etc.)
    'storage_connection' => env('SQL_AGENT_STORAGE_CONNECTION', config('database.default')),
],
```

You can use a separate database for SqlAgent's internal tables by setting `SQL_AGENT_STORAGE_CONNECTION`.

## LLM Configuration

```php
'llm' => [
    'default' => env('SQL_AGENT_LLM_DRIVER', 'openai'),

    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('SQL_AGENT_OPENAI_MODEL', 'gpt-4o'),
            'temperature' => 0.0,
            'max_tokens' => 4096,
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('SQL_AGENT_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'temperature' => 0.0,
            'max_tokens' => 4096,
        ],

        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('SQL_AGENT_OLLAMA_MODEL', 'llama3.1'),
            'temperature' => 0.0,
        ],
    ],
],
```

## Search Configuration

```php
'search' => [
    'default' => env('SQL_AGENT_SEARCH_DRIVER', 'database'),

    'drivers' => [
        'database' => [
            'mysql' => [
                'mode' => 'NATURAL LANGUAGE MODE', // or 'BOOLEAN MODE'
            ],
            'pgsql' => [
                'language' => 'english', // PostgreSQL text search language
            ],
            'sqlsrv' => [],
        ],

        'scout' => [
            'driver' => env('SCOUT_DRIVER', 'meilisearch'),
        ],

        'hybrid' => [
            'primary' => 'scout',
            'fallback' => 'database',
            'merge_results' => false,
        ],
    ],
],
```

## Agent Configuration

```php
'agent' => [
    // Maximum number of tool-calling iterations before stopping
    'max_iterations' => env('SQL_AGENT_MAX_ITERATIONS', 10),

    // Default LIMIT for queries without explicit limits
    'default_limit' => env('SQL_AGENT_DEFAULT_LIMIT', 100),

    // Number of previous messages to include for context
    'chat_history_length' => env('SQL_AGENT_CHAT_HISTORY', 10),
],
```

## Learning Configuration

```php
'learning' => [
    // Enable/disable the self-learning feature
    'enabled' => env('SQL_AGENT_LEARNING_ENABLED', true),

    // Automatically save learnings when SQL errors occur
    'auto_save_errors' => env('SQL_AGENT_AUTO_SAVE_ERRORS', true),

    // Remove learnings older than this many days (via prune command)
    'prune_after_days' => env('SQL_AGENT_LEARNING_PRUNE_DAYS', 90),
],
```

## Knowledge Configuration

```php
'knowledge' => [
    // Path to knowledge files
    'path' => env('SQL_AGENT_KNOWLEDGE_PATH', resource_path('sql-agent/knowledge')),

    // Source for knowledge: 'files' or 'database'
    'source' => env('SQL_AGENT_KNOWLEDGE_SOURCE', 'files'),
],
```

## UI Configuration

```php
'ui' => [
    // Enable/disable the web interface
    'enabled' => env('SQL_AGENT_UI_ENABLED', true),

    // URL prefix (e.g., /sql-agent)
    'route_prefix' => env('SQL_AGENT_ROUTE_PREFIX', 'sql-agent'),

    // Middleware for the UI routes
    'middleware' => ['web', 'auth'],
],
```

## User Configuration

By default, user tracking is disabled. Enable it to scope conversations and learnings per user.

```php
'user' => [
    'enabled' => env('SQL_AGENT_USER_ENABLED', false),
    'model' => null,
    'resolver' => null,
],
```

**Enable user tracking:**

```env
SQL_AGENT_USER_ENABLED=true
```

**Custom auth guard:**

```php
'user' => [
    'enabled' => true,
    'model' => \App\Models\Admin::class,
    'resolver' => fn() => auth('admin')->id(),
],
```

**Multi-tenancy:**

```php
'user' => [
    'enabled' => true,
    'resolver' => fn() => tenant()->owner_id,
],
```

## SQL Safety Configuration

```php
'sql' => [
    // Only these statement types are allowed
    'allowed_statements' => ['SELECT', 'WITH'],

    // These keywords will cause queries to be rejected
    'forbidden_keywords' => [
        'DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER',
        'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE',
    ],

    // Maximum rows returned by any query
    'max_rows' => env('SQL_AGENT_MAX_ROWS', 1000),
],
```

## Evaluation Configuration

```php
'evaluation' => [
    // Model used for LLM grading of test results
    'grader_model' => env('SQL_AGENT_GRADER_MODEL', 'gpt-4o-mini'),

    // Minimum score to pass LLM grading (0.0 - 1.0)
    'pass_threshold' => env('SQL_AGENT_EVAL_PASS_THRESHOLD', 0.6),

    // Timeout for each test case in seconds
    'timeout' => env('SQL_AGENT_EVAL_TIMEOUT', 60),
],
```

## Debug Configuration

```php
'debug' => [
    // Store detailed metadata (system prompt, iterations, timing) per message
    'enabled' => env('SQL_AGENT_DEBUG', false),
],
```

See [Web Interface](web-interface.md#debug-mode) for details on what gets stored.
