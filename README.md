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

## Documentation

- [Configuration](docs/configuration.md) - All config options (database, LLM, search, safety, etc.)
- [Knowledge Base](docs/knowledge-base.md) - Table metadata, business rules, and query patterns
- [LLM & Search Drivers](docs/drivers.md) - OpenAI, Anthropic, Ollama, and search driver setup
- [Usage](docs/usage.md) - Artisan commands and programmatic API
- [Web Interface](docs/web-interface.md) - Livewire chat UI and debug mode
- [Evaluation & Self-Learning](docs/evaluation.md) - Test accuracy and automatic learning
- [Events](docs/events.md) - Event hooks for custom behavior
- [Database Support](docs/database-support.md) - MySQL, PostgreSQL, SQLite, SQL Server
- [Troubleshooting](docs/troubleshooting.md) - Common issues and solutions

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

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

Laravel SQL Agent is open-sourced software licensed under the [Apache-2.0 License](LICENSE).
