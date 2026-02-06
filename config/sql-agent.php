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
        'connection' => env('SQL_AGENT_CONNECTION', config('database.default')),
        'storage_connection' => env('SQL_AGENT_STORAGE_CONNECTION', config('database.default')),
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
    | Configure the Large Language Model driver and settings.
    |
    */
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
                // Enable thinking/reasoning mode for supported models.
                // Most models accept true/false, but GPT-OSS requires "low", "medium", or "high".
                // See: https://docs.ollama.com/capabilities/thinking
                'think' => env('SQL_AGENT_OLLAMA_THINK', true),
                // Models that support tool/function calling.
                // null = all models (wildcard), [] = none, ['model1', 'model2'] = specific models
                'models_with_tool_support' => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the search driver for semantic search.
    | Supported drivers: "database", "scout", "hybrid", "null"
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
            | Scout Driver Configuration
            |--------------------------------------------------------------------------
            |
            | Laravel Scout integration for external search engines.
            | Requires models to use the Laravel\Scout\Searchable trait.
            |
            */
            'scout' => [
                // The Scout driver to use (meilisearch, algolia, etc.)
                'driver' => env('SCOUT_DRIVER', 'meilisearch'),

                // Custom index to model class mapping (optional)
                // 'index_mapping' => [],
            ],

            /*
            |--------------------------------------------------------------------------
            | Hybrid Driver Configuration
            |--------------------------------------------------------------------------
            |
            | Combines Scout as primary with database fallback.
            | Useful for reliability when external search services may be unavailable.
            |
            */
            'hybrid' => [
                // Primary search driver
                'primary' => 'scout',

                // Fallback driver if primary fails
                'fallback' => 'database',

                // Whether to merge results from both drivers (vs using primary only)
                'merge_results' => false,
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
    */
    'knowledge' => [
        'path' => env('SQL_AGENT_KNOWLEDGE_PATH', resource_path('sql-agent/knowledge')),
        'source' => env('SQL_AGENT_KNOWLEDGE_SOURCE', 'files'), // 'files' or 'database'
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
        // Model to use for LLM grading (should be fast and cheap)
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
