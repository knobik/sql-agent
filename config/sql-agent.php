<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Display Name
    |--------------------------------------------------------------------------
    |
    | The display name used in the UI and logs.
    |
    */
    'name' => 'SqlAgent',

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database connections for SqlAgent.
    | - connection: The database connection to query (your data)
    | - storage_connection: The connection for SqlAgent's own tables
    |
    */
    'database' => [
        'storage_connection' => env('SQL_AGENT_STORAGE_CONNECTION', config('database.default')),

        // Database connections the agent can query. Each entry maps a logical name
        // to a Laravel database connection. The agent autonomously decides which
        // database to query based on the user's question.
        'connections' => [
            'default' => [
                'connection' => env('SQL_AGENT_CONNECTION', config('database.default')),
                'label' => 'Database',
                'description' => 'Main application database.',
                // 'allowed_tables' => [],
                // 'denied_tables' => [],
                // 'hidden_columns' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Configuration
    |--------------------------------------------------------------------------
    |
    | Enable to scope conversations/learnings per user.
    | Use 'resolver' for custom auth: fn() => auth('admin')->id()
    |
    */
    'user' => [
        'enabled' => env('SQL_AGENT_USER_ENABLED', false),
        'model' => null,
        'resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the LLM provider and model via Prism PHP.
    | Supported providers: openai, anthropic, ollama, gemini, mistral, xai, etc.
    | See https://prismphp.com for all available providers and configuration.
    |
    | Provider credentials are configured in config/prism.php (published via
    | `php artisan vendor:publish --tag=prism-config`).
    |
    */
    'llm' => [
        'provider' => env('SQL_AGENT_LLM_PROVIDER', 'openai'),
        'model' => env('SQL_AGENT_LLM_MODEL', 'gpt-4o'),
        'temperature' => (float) env('SQL_AGENT_LLM_TEMPERATURE', 0.3),
        'max_tokens' => (int) env('SQL_AGENT_LLM_MAX_TOKENS', 16384),

        // Additional provider-specific options passed to Prism's withProviderOptions()
        // e.g. ['thinking' => true] for Ollama thinking mode
        'provider_options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the search driver for semantic search.
    | Supported drivers: "database", "pgvector", "null"
    |
    */
    'search' => [
        'default' => env('SQL_AGENT_SEARCH_DRIVER', 'database'),

        'drivers' => [
            /*
            |--------------------------------------------------------------------------
            | Database Driver Configuration
            |--------------------------------------------------------------------------
            |
            | Native database full-text search with auto-detection of database type.
            | Supports MySQL, PostgreSQL, SQL Server, and SQLite (LIKE fallback).
            |
            */
            'database' => [
                // MySQL full-text search configuration
                'mysql' => [
                    // NATURAL LANGUAGE MODE or BOOLEAN MODE
                    'mode' => 'NATURAL LANGUAGE MODE',
                ],

                // PostgreSQL full-text search configuration
                'pgsql' => [
                    // Language for text search (english, spanish, german, etc.)
                    'language' => 'english',
                ],

                // SQL Server full-text search configuration (requires full-text catalog)
                'sqlsrv' => [],

                // Custom index to model class mapping (optional)
                // 'index_mapping' => [
                //     'custom_index' => \App\Models\CustomModel::class,
                // ],
            ],

            /*
            |--------------------------------------------------------------------------
            | pgvector Driver Configuration
            |--------------------------------------------------------------------------
            |
            | Semantic similarity search using PostgreSQL pgvector extension.
            | Requires a dedicated PostgreSQL connection with pgvector installed.
            |
            */
            'pgvector' => [
                // Dedicated PostgreSQL connection for embedding storage
                'connection' => env('SQL_AGENT_EMBEDDINGS_CONNECTION'),

                // Prism embedding provider and model
                'provider' => env('SQL_AGENT_EMBEDDINGS_PROVIDER', 'openai'),
                'model' => env('SQL_AGENT_EMBEDDINGS_MODEL', 'text-embedding-3-small'),

                // Vector dimensions (must match the model's output dimensions)
                'dimensions' => (int) env('SQL_AGENT_EMBEDDINGS_DIMENSIONS', 1536),

                // Distance metric: cosine (default), l2, inner_product
                'distance_metric' => 'cosine',

                // Custom index to model class mapping (optional)
                // 'index_mapping' => [
                //     'custom_index' => \App\Models\CustomModel::class,
                // ],
            ],

        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the SQL agent behavior.
    |
    */
    'agent' => [
        'max_iterations' => env('SQL_AGENT_MAX_ITERATIONS', 10),
        'default_limit' => env('SQL_AGENT_DEFAULT_LIMIT', 100),
        'chat_history_length' => env('SQL_AGENT_CHAT_HISTORY', 10),

        // Custom tool class names resolved from the container, e.g.:
        // [App\SqlAgent\MyCustomTool::class]
        'tools' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Learning Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the self-learning features.
    |
    */
    'learning' => [
        'enabled' => env('SQL_AGENT_LEARNING_ENABLED', true),
        'auto_save_errors' => env('SQL_AGENT_AUTO_SAVE_ERRORS', true),
        'prune_after_days' => env('SQL_AGENT_LEARNING_PRUNE_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge Configuration
    |--------------------------------------------------------------------------
    |
    | Configure knowledge base settings.
    |
    | Source options:
    |   - 'database': Reads knowledge from the sql_agent_table_metadata,
    |     sql_agent_business_rules, and sql_agent_query_patterns database tables.
    |     Requires running `php artisan sql-agent:load-knowledge` first to import
    |     JSON files into the database. Supports full-text search and is the
    |     recommended option for production.
    |   - 'files': Reads knowledge directly from JSON files on disk at the
    |     configured path. No database import needed, but does not support
    |     full-text search over knowledge.
    |
    */
    'knowledge' => [
        'path' => env('SQL_AGENT_KNOWLEDGE_PATH', resource_path('sql-agent/knowledge')),
        'source' => env('SQL_AGENT_KNOWLEDGE_SOURCE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the web interface.
    |
    */
    'ui' => [
        'enabled' => env('SQL_AGENT_UI_ENABLED', true),
        'route_prefix' => env('SQL_AGENT_ROUTE_PREFIX', 'sql-agent'),
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Safety Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SQL safety rules and limits.
    |
    */
    'sql' => [
        'allowed_statements' => ['SELECT', 'WITH'],

        'forbidden_keywords' => [
            'DROP',
            'DELETE',
            'UPDATE',
            'INSERT',
            'ALTER',
            'CREATE',
            'TRUNCATE',
            'GRANT',
            'REVOKE',
            'EXEC',
            'EXECUTE',
        ],

        'max_rows' => env('SQL_AGENT_MAX_ROWS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evaluation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the evaluation and testing system.
    |
    */
    'evaluation' => [
        // Provider and model to use for LLM grading (should be fast and cheap)
        'grader_provider' => env('SQL_AGENT_GRADER_PROVIDER', 'openai'),
        'grader_model' => env('SQL_AGENT_GRADER_MODEL', 'gpt-4o-mini'),

        // Pass threshold for LLM grading (0.0 - 1.0)
        'pass_threshold' => env('SQL_AGENT_EVAL_PASS_THRESHOLD', 0.6),

        // Timeout for each test case in seconds
        'timeout' => env('SQL_AGENT_EVAL_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Configure debug features for development and troubleshooting.
    |
    */
    'debug' => [
        'enabled' => env('SQL_AGENT_DEBUG', false),
    ],
];
