# Laravel SQL Agent

> **Alpha Release** - This package is in early development. APIs may change without notice.

A self-learning text-to-SQL agent for Laravel that converts natural language questions into SQL queries using LLMs.

This package is based on [Dash](https://github.com/agno-agi/dash) and [OpenAI's in-house data agent](https://openai.com/index/inside-our-in-house-data-agent/).

## Why This Package?

Raw LLMs writing SQL hit a wall fast. The problems:

- **Schemas lack meaning** — Column names like `status` or `type` don't convey business context
- **Types are misleading** — A `position` column might be TEXT, not INTEGER
- **Tribal knowledge is missing** — "Active customer" means different things to different teams
- **No learning from mistakes** — The same errors repeat endlessly
- **Results lack interpretation** — You get data, not answers

The root cause is **missing context and missing memory**.

This package solves it with:

1. **Knowledge Base** — Curated table metadata, business rules, and query patterns that give the LLM the context it needs
2. **Self-Learning** — When a query fails and the agent recovers, it saves that learning. Next time, it knows.
3. **Multi-Layer Context** — Schema introspection, semantic search over knowledge, conversation history, and accumulated learnings
4. **SQL Safety** — Configurable guardrails to prevent destructive operations

This package provides the foundation to build reliable, context-aware data agents for Laravel applications.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Debug Mode](#debug-mode)
- [Knowledge Base](#knowledge-base)
- [LLM Drivers](#llm-drivers)
- [Search Drivers](#search-drivers)
- [Web Interface](#web-interface)
- [Artisan Commands](#artisan-commands)
- [Programmatic Usage](#programmatic-usage)
- [Evaluation System](#evaluation-system)
- [Self-Learning](#self-learning)
- [Database Support](#database-support)
- [Events](#events)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Features

- **Multi-LLM Support** - OpenAI (GPT-4, GPT-4o), Anthropic (Claude), and Ollama for local models
- **Multi-Database Support** - MySQL, PostgreSQL, SQLite, and SQL Server
- **Self-Learning** - Automatically learns from SQL errors and improves over time
- **Multiple Search Drivers** - Database full-text search, Laravel Scout integration, or hybrid approach
- **Agentic Loop** - Uses tool calling to introspect schema, run queries, and refine results
- **Livewire Chat UI** - Ready-to-use chat interface with conversation history
- **Knowledge Base System** - Define table metadata, business rules, and query patterns
- **SQL Safety** - Configurable statement restrictions and row limits
- **Evaluation Framework** - Test your agent's accuracy with automated evaluations

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- An LLM API key (OpenAI, Anthropic, or local Ollama installation)
- Optional: Livewire 3.x for the chat UI
- Optional: Laravel Scout for external search engines

## Installation

Install the package via Composer:

```bash
composer require knobik/laravel-sql-agent
```

Run the install command:

```bash
php artisan sql-agent:install
```

This will:
1. Publish the configuration file
2. Publish and run migrations
3. Create the knowledge directory structure at `resources/sql-agent/knowledge/`

Add your LLM API key to `.env`:

```env
# For OpenAI (default)
OPENAI_API_KEY=sk-your-api-key

# Or for Anthropic
ANTHROPIC_API_KEY=sk-ant-your-api-key
SQL_AGENT_LLM_DRIVER=anthropic

# Or for Ollama (local)
SQL_AGENT_LLM_DRIVER=ollama
OLLAMA_BASE_URL=http://localhost:11434
```

## Quick Start

### 1. Create a knowledge file

Create `resources/sql-agent/knowledge/tables/users.json`:

```json
{
    "table": "users",
    "description": "Contains user account information",
    "columns": {
        "id": "Primary key, auto-incrementing integer",
        "name": "User's full name",
        "email": "User's email address (unique)",
        "created_at": "Account creation timestamp",
        "updated_at": "Last update timestamp"
    }
}
```

### 2. Load knowledge into the database

```bash
php artisan sql-agent:load-knowledge
```

### 3. Run your first query

```php
use Knobik\SqlAgent\Facades\SqlAgent;

$response = SqlAgent::run('How many users signed up this month?');

echo $response->answer;  // "There are 42 users who signed up this month."
echo $response->sql;     // "SELECT COUNT(*) as count FROM users WHERE created_at >= '2026-01-01'"
```

## Configuration

The configuration file is located at `config/sql-agent.php`. Here are all available options:

### Display Name

```php
'name' => 'SqlAgent',
```

The display name used in the UI and logs.

### Database Configuration

```php
'database' => [
    // The database connection to query (your application data)
    'connection' => env('SQL_AGENT_CONNECTION', config('database.default')),

    // The connection for SqlAgent's own tables (knowledge, learnings, etc.)
    'storage_connection' => env('SQL_AGENT_STORAGE_CONNECTION', config('database.default')),
],
```

You can use a separate database for SqlAgent's internal tables by setting `SQL_AGENT_STORAGE_CONNECTION`.

### LLM Configuration

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

### Search Configuration

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

### Agent Configuration

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

### Learning Configuration

```php
'learning' => [
    // Enable/disable the self-learning feature
    'enabled' => env('SQL_AGENT_LEARNING_ENABLED', true),

    // Automatically save learnings when SQL errors occur
    'auto_save_errors' => env('SQL_AGENT_AUTO_SAVE_ERRORS', true),

    // Remove learnings older than this many days (via prune command)
    'prune_after_days' => env('SQL_AGENT_LEARNING_PRUNE_DAYS', 90),

    // Maximum auto-generated learnings per day (prevents runaway learning)
    'max_auto_learnings_per_day' => env('SQL_AGENT_MAX_AUTO_LEARNINGS', 50),
],
```

### Knowledge Configuration

```php
'knowledge' => [
    // Path to knowledge files
    'path' => env('SQL_AGENT_KNOWLEDGE_PATH', resource_path('sql-agent/knowledge')),

    // Source for knowledge: 'files' or 'database'
    'source' => env('SQL_AGENT_KNOWLEDGE_SOURCE', 'files'),
],
```

### UI Configuration

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

### User Configuration

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

### SQL Safety Configuration

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

### Evaluation Configuration

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

### Debug Configuration

```php
'debug' => [
    // Store detailed metadata (system prompt, iterations, timing) per message
    'enabled' => env('SQL_AGENT_DEBUG', false),
],
```

See [Debug Mode](#debug-mode) for details on what gets stored.

## Debug Mode

When debug mode is enabled, SqlAgent stores additional metadata alongside each assistant message in the `sql_agent_messages` table. This is useful for development and troubleshooting but should be disabled in production.

```env
SQL_AGENT_DEBUG=true
```

### What gets stored

With debug mode on, each assistant message's `metadata` JSON column will include:

| Key | Description |
|-----|-------------|
| `prompt.system` | The full system prompt sent to the LLM (including rendered context) |
| `prompt.tools` | List of tool names available to the agent |
| `prompt.tools_full` | Full JSON schema for each tool |
| `iterations` | Every tool-calling iteration: tool calls, arguments, results, and LLM responses |
| `timing.total_ms` | Wall-clock time for the entire request |
| `thinking` | The LLM's internal reasoning (for models with thinking mode) |

### Web UI

When debug mode is active, each assistant message in the chat UI shows a **"Debug: Show Prompt"** button that expands a panel with tabs for:

- **Prompt** -- System prompt and available tools
- **Iterations** -- Step-by-step tool calls and results
- **Tools Schema** -- Full JSON schema sent to the LLM
- **Thinking** -- LLM reasoning (when available)

### Storage considerations

Debug metadata can add significant size to the `metadata` column (roughly 50-60 KB per message depending on schema complexity and iteration count). Keep this in mind for long-running conversations and consider periodically pruning old conversations if storage is a concern.

## Knowledge Base

The knowledge base helps SqlAgent understand your database schema, business rules, and common query patterns.

### Directory Structure

```
resources/sql-agent/knowledge/
├── tables/          # Table metadata (JSON)
├── business/        # Business rules and metrics (JSON)
└── queries/         # Query patterns (SQL or JSON)
```

### Table Metadata

Create JSON files in `tables/` to describe your database schema:

```json
{
    "table": "orders",
    "description": "Contains customer orders and their status",
    "columns": {
        "id": "Primary key",
        "customer_id": "Foreign key to customers.id",
        "status": "Order status: pending, processing, shipped, delivered, cancelled",
        "total_amount": "Order total in cents (integer)",
        "created_at": "Order creation timestamp",
        "shipped_at": "Shipping timestamp (null if not shipped)"
    },
    "relationships": [
        "orders.customer_id -> customers.id"
    ],
    "notes": "The total_amount is stored in cents. Divide by 100 for dollars."
}
```

### Business Rules

Create JSON files in `business/` to define business logic and metrics:

```json
{
    "name": "Active Customer Definition",
    "description": "A customer is considered active if they have placed an order in the last 90 days",
    "rules": [
        "Active customers have at least one order with created_at >= NOW() - INTERVAL 90 DAY",
        "Inactive customers have no orders in the last 90 days"
    ],
    "examples": [
        {
            "question": "How many active customers do we have?",
            "sql": "SELECT COUNT(DISTINCT customer_id) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
        }
    ]
}
```

### Query Patterns

Create files in `queries/` to teach SqlAgent common query patterns:

**JSON format (`queries/revenue.json`):**

```json
{
    "name": "Monthly Revenue",
    "description": "Calculate total revenue by month",
    "pattern": "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) / 100 as revenue FROM orders WHERE status != 'cancelled' GROUP BY month ORDER BY month DESC",
    "keywords": ["revenue", "monthly", "sales", "income"]
}
```

**SQL format (`queries/top_customers.sql`):**

```sql
-- name: Top Customers by Order Count
-- description: Find customers with the most orders
-- keywords: top, customers, orders, best

SELECT
    c.id,
    c.name,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) / 100 as total_spent
FROM customers c
JOIN orders o ON o.customer_id = c.id
WHERE o.status != 'cancelled'
GROUP BY c.id, c.name
ORDER BY order_count DESC
LIMIT 10;
```

### Loading Knowledge

Load all knowledge files into the database:

```bash
php artisan sql-agent:load-knowledge
```

Load specific types:

```bash
php artisan sql-agent:load-knowledge --tables
php artisan sql-agent:load-knowledge --rules
php artisan sql-agent:load-knowledge --queries
```

Recreate all knowledge (clears existing):

```bash
php artisan sql-agent:load-knowledge --recreate
```

Use a custom path:

```bash
php artisan sql-agent:load-knowledge --path=/custom/knowledge/path
```

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

## Web Interface

SqlAgent includes a ready-to-use Livewire chat interface.

### Accessing the UI

By default, the UI is available at `/sql-agent` and protected by `web` and `auth` middleware.

### Customizing Routes

In `config/sql-agent.php`:

```php
'ui' => [
    'enabled' => true,
    'route_prefix' => 'admin/sql-agent',  // Change the URL prefix
    'middleware' => ['web', 'auth', 'admin'],  // Add custom middleware
],
```

### Disabling the UI

```php
'ui' => [
    'enabled' => false,
],
```

Or via environment:

```env
SQL_AGENT_UI_ENABLED=false
```

### Customizing Views

Publish the views:

```bash
php artisan vendor:publish --tag=sql-agent-views
```

Views will be published to `resources/views/vendor/sql-agent/`.

### Using the Livewire Component Directly

```blade
<livewire:sql-agent-chat />

{{-- With a specific conversation --}}
<livewire:sql-agent-chat :conversation-id="$conversationId" />
```

## Artisan Commands

### sql-agent:install

Install the SqlAgent package.

```bash
php artisan sql-agent:install
php artisan sql-agent:install --force  # Overwrite existing files
```

### sql-agent:load-knowledge

Load knowledge files into the database.

```bash
php artisan sql-agent:load-knowledge

# Options
--recreate        # Drop and recreate all knowledge
--tables          # Load only table metadata
--rules           # Load only business rules
--queries         # Load only query patterns
--path=<path>     # Custom path to knowledge files
```

### sql-agent:eval

Run evaluation tests to measure agent accuracy.

```bash
php artisan sql-agent:eval

# Options
--category=<cat>  # Filter by category (basic, aggregation, complex, etc.)
--llm-grader      # Use LLM to grade responses
--golden-sql      # Compare against golden SQL results
--connection=<c>  # Use specific database connection
--detailed        # Show detailed output for failed tests
--json            # Output results as JSON
--html=<path>     # Generate HTML report at path
--seed            # Seed test cases before running
```

### sql-agent:export-learnings

Export learnings to a JSON file.

```bash
php artisan sql-agent:export-learnings
php artisan sql-agent:export-learnings output.json
php artisan sql-agent:export-learnings --category=type_error
```

Categories: `type_error`, `schema_fix`, `query_pattern`, `data_quality`, `business_logic`

### sql-agent:import-learnings

Import learnings from a JSON file.

```bash
php artisan sql-agent:import-learnings learnings.json
php artisan sql-agent:import-learnings learnings.json --force  # Include duplicates
```

### sql-agent:prune-learnings

Remove old or duplicate learnings.

```bash
php artisan sql-agent:prune-learnings

# Options
--days=90         # Remove learnings older than N days (default: 90)
--duplicates      # Only remove duplicate learnings
--include-used    # Also remove learnings that have been used
--dry-run         # Show what would be removed without removing
```

## Programmatic Usage

### Basic Usage

```php
use Knobik\SqlAgent\Facades\SqlAgent;

$response = SqlAgent::run('How many users registered last week?');

// Access the response
$response->answer;      // Natural language answer
$response->sql;         // The SQL query that was executed
$response->results;     // Raw query results (array)
$response->toolCalls;   // All tool calls made during execution
$response->iterations;  // Detailed iteration data
$response->error;       // Error message if failed

// Check status
$response->isSuccess(); // true if no error
$response->hasResults(); // true if results is not empty
```

### Streaming Responses

```php
use Knobik\SqlAgent\Facades\SqlAgent;

foreach (SqlAgent::stream('Show me the top 5 customers') as $chunk) {
    echo $chunk->content;

    if ($chunk->isComplete()) {
        // Stream finished
    }
}
```

### Custom Connection

Query a specific database connection:

```php
$response = SqlAgent::run('How many orders today?', 'analytics');
```

### With Conversation History

For the streaming API with chat history:

```php
$history = [
    ['role' => 'user', 'content' => 'Show me all products'],
    ['role' => 'assistant', 'content' => 'Here are the products...'],
];

foreach (SqlAgent::stream('Now filter by price > 100', null, $history) as $chunk) {
    echo $chunk->content;
}
```

### Dependency Injection

```php
use Knobik\SqlAgent\Contracts\Agent;

class ReportController extends Controller
{
    public function __construct(
        private Agent $agent,
    ) {}

    public function generate(Request $request)
    {
        $response = $this->agent->run($request->input('question'));

        return [
            'answer' => $response->answer,
            'sql' => $response->sql,
            'data' => $response->results,
        ];
    }
}
```

## Evaluation System

The evaluation system helps you measure and improve your agent's accuracy.

### Creating Test Cases

Test cases are stored in the `sql_agent_test_cases` table. You can seed them using the built-in seeder or create your own:

```php
use Knobik\SqlAgent\Models\TestCase;

TestCase::create([
    'name' => 'Count active users',
    'category' => 'basic',
    'question' => 'How many active users are there?',
    'expected_strings' => ['active', 'users'], // Strings that should appear in response
    'golden_sql' => 'SELECT COUNT(*) FROM users WHERE status = "active"',
    'metadata' => ['difficulty' => 'easy'],
]);
```

### Running Evaluations

```bash
# Run all tests
php artisan sql-agent:eval

# Run with LLM grading
php artisan sql-agent:eval --llm-grader

# Run specific category
php artisan sql-agent:eval --category=aggregation

# Generate HTML report
php artisan sql-agent:eval --html=storage/eval-report.html
```

### Evaluation Modes

1. **String Matching** (default): Checks if expected strings appear in the response
2. **LLM Grading**: Uses an LLM to semantically evaluate the response
3. **Golden SQL**: Compares query results against a known-good SQL query

## Self-Learning

SqlAgent can automatically learn from its mistakes and improve over time.

### How It Works

1. When a SQL error occurs, the agent analyzes the error
2. If it successfully recovers, it creates a "learning" record
3. Future queries can reference these learnings for context
4. Learnings are categorized and can be exported/imported

### Learning Categories

- **Type Error**: Data type mismatches or casting issues
- **Schema Fix**: Incorrect schema assumptions (wrong table/column names)
- **Query Pattern**: Learned patterns for constructing queries
- **Data Quality**: Observations about data quality or anomalies
- **Business Logic**: Learned business rules or domain knowledge

### Managing Learnings

```bash
# Export all learnings
php artisan sql-agent:export-learnings

# Export specific category
php artisan sql-agent:export-learnings --category=schema_fix

# Import learnings
php artisan sql-agent:import-learnings learnings.json

# Prune old learnings
php artisan sql-agent:prune-learnings --days=90

# Remove duplicates
php artisan sql-agent:prune-learnings --duplicates
```

### Disabling Self-Learning

```env
SQL_AGENT_LEARNING_ENABLED=false
SQL_AGENT_AUTO_SAVE_ERRORS=false
```

## Database Support

### MySQL

Full support including:
- Full-text search with `MATCH ... AGAINST`
- Natural language and boolean search modes
- JSON column support for metadata

### PostgreSQL

Full support including:
- Full-text search with `tsvector` and `tsquery`
- Configurable text search language
- JSONB column support for metadata

### SQLite

Supported with limitations:
- Full-text search falls back to `LIKE` queries
- JSON support depends on SQLite version
- Suitable for development and small datasets

### SQL Server

Supported with full-text search:
- Requires full-text catalog to be configured
- Uses `CONTAINS` predicates

## Events

SqlAgent dispatches events you can listen to for custom behavior.

### SqlErrorOccurred

Dispatched when a SQL query fails.

```php
use Knobik\SqlAgent\Events\SqlErrorOccurred;

class SqlErrorListener
{
    public function handle(SqlErrorOccurred $event): void
    {
        Log::warning('SQL Agent error', [
            'sql' => $event->sql,
            'error' => $event->error,
            'question' => $event->question,
            'connection' => $event->connection,
        ]);
    }
}
```

### LearningCreated

Dispatched when a new learning is created.

```php
use Knobik\SqlAgent\Events\LearningCreated;

class LearningListener
{
    public function handle(LearningCreated $event): void
    {
        // Notify team about new learning
        Notification::send($admins, new NewLearningNotification($event->learning));
    }
}
```

Register listeners in `EventServiceProvider`:

```php
protected $listen = [
    \Knobik\SqlAgent\Events\SqlErrorOccurred::class => [
        \App\Listeners\SqlErrorListener::class,
    ],
    \Knobik\SqlAgent\Events\LearningCreated::class => [
        \App\Listeners\LearningListener::class,
    ],
];
```

## Testing

### Running Package Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage
```

### Testing Your Application

When testing code that uses SqlAgent, you can mock the facade:

```php
use Knobik\SqlAgent\Facades\SqlAgent;
use Knobik\SqlAgent\Contracts\AgentResponse;

public function test_it_handles_sql_agent_response(): void
{
    SqlAgent::shouldReceive('run')
        ->with('How many users?')
        ->andReturn(new AgentResponse(
            answer: 'There are 100 users.',
            sql: 'SELECT COUNT(*) FROM users',
            results: [['count' => 100]],
        ));

    $response = $this->post('/api/query', ['question' => 'How many users?']);

    $response->assertJson(['answer' => 'There are 100 users.']);
}
```

## Troubleshooting

### "No knowledge found" or poor results

1. Ensure knowledge files are in the correct format (JSON)
2. Run `php artisan sql-agent:load-knowledge --recreate`
3. Check the `sql_agent_table_metadata` table has entries
4. Add more descriptive column information

### "Maximum iterations reached"

The agent couldn't complete the task in the allowed iterations:

1. Increase `SQL_AGENT_MAX_ITERATIONS` in `.env`
2. Add more specific knowledge about the tables involved
3. Simplify the question or break it into smaller queries

### SQL errors in production

1. Check `sql-agent.sql.allowed_statements` includes needed statement types
2. Verify the query doesn't use forbidden keywords
3. Review `sql-agent.sql.max_rows` if truncation is an issue

### Slow response times

1. Use a faster model (e.g., `gpt-4o-mini` instead of `gpt-4o`)
2. Reduce `chat_history_length` to minimize context
3. Consider using the `database` search driver instead of Scout for simpler setups

### LLM API errors

1. Verify your API key is correct
2. Check your API quota/limits
3. For Ollama, ensure the service is running and the model is downloaded

### Search not finding relevant knowledge

1. Ensure full-text indexes are created (check migrations ran successfully)
2. For MySQL, verify the table uses InnoDB or MyISAM engine
3. Consider using the `hybrid` search driver for better reliability

## License

Laravel SQL Agent is open-sourced software licensed under the [Apache-2.0 License](LICENSE).
