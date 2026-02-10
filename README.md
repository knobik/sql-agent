<p align="center">
  <img src="art/logo.svg" width="200" alt="SQL Agent for Laravel">
</p>

# SQL Agent for Laravel

> **Alpha Release** - This package is in early development. APIs may change without notice.

A self-learning text-to-SQL agent that converts natural language questions into SQL queries using LLMs.

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

## How It Works

SqlAgent uses multi-layer context assembly to give the LLM everything it needs before writing SQL, then lets it retrieve more during execution.

1. **Context Assembly** — Before the LLM sees the question, the agent retrieves relevant table metadata, business rules, similar query patterns, and past learnings. This assembled context is injected into the system prompt.
2. **Agentic Tool Loop** — The LLM enters a tool-calling loop where it can introspect live schema, search for additional knowledge, execute SQL, and refine results iteratively.
3. **Self-Learning** — When queries fail and the agent recovers, it saves what it learned. When queries succeed, it saves them as reusable patterns. Both feed back into step 1 for future queries.

This creates a feedback loop — the more the agent is used, the better its context becomes.

## Features

- **Multi-LLM Support** - Any provider supported by [Prism PHP](https://prismphp.com) (OpenAI, Anthropic, Ollama, Gemini, Mistral, xAI, and more)
- **Multi-Database Support** - MySQL, PostgreSQL, SQLite, and SQL Server
- **Self-Learning** - Automatically learns from SQL errors and improves over time
- **Multiple Search Drivers** - Database full-text search or pgvector semantic search
- **Agentic Loop** - Uses tool calling to introspect schema, run queries, and refine results
- **Livewire Chat UI** - Ready-to-use chat interface with conversation history
- **Knowledge Base System** - Define table metadata, business rules, and query patterns
- **SQL Safety** - Configurable statement restrictions and row limits
- **Evaluation Framework** - Test your agent's accuracy with automated evaluations

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- [Prism PHP](https://prismphp.com) (installed automatically as a dependency)
- An LLM API key or local Ollama installation
- Optional: Livewire 3.x for the chat UI
- Optional: PostgreSQL with pgvector for semantic similarity search via vector embeddings

## Installation

For detailed setup instructions, see the [full documentation](https://knobik.github.io/sql-agent/getting-started/installation/).

Install the package via Composer:

```bash
composer require knobik/sql-agent
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
SQL_AGENT_LLM_PROVIDER=openai
SQL_AGENT_LLM_MODEL=gpt-4o

# Or for Anthropic
SQL_AGENT_LLM_PROVIDER=anthropic
SQL_AGENT_LLM_MODEL=claude-sonnet-4-20250514

# Or for Ollama (local)
SQL_AGENT_LLM_PROVIDER=ollama
SQL_AGENT_LLM_MODEL=llama3.1
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

**[Read the full documentation](https://knobik.github.io/sql-agent/)**

- [Configuration](https://knobik.github.io/sql-agent/guides/configuration/) - All config options (database, LLM, search, safety, etc.)
- [Knowledge Base](https://knobik.github.io/sql-agent/guides/knowledge-base/) - Table metadata, business rules, and query patterns
- [LLM & Search Drivers](https://knobik.github.io/sql-agent/guides/drivers/) - Configure LLM providers and search drivers
- [Artisan Commands](https://knobik.github.io/sql-agent/reference/commands/) - All available commands and options
- [Programmatic API](https://knobik.github.io/sql-agent/reference/api/) - Facade, streaming, and dependency injection
- [Web Interface](https://knobik.github.io/sql-agent/guides/web-interface/) - Livewire chat UI and debug mode
- [Evaluation](https://knobik.github.io/sql-agent/guides/evaluation/) - Test accuracy with automated evaluations
- [Self-Learning](https://knobik.github.io/sql-agent/guides/self-learning/) - Automatic learning from errors
- [Events](https://knobik.github.io/sql-agent/reference/events/) - Event hooks for custom behavior
- [Agent Tools](https://knobik.github.io/sql-agent/reference/tools/) - All LLM tools with parameters and JSON schemas
- [Database Support](https://knobik.github.io/sql-agent/reference/database-support/) - MySQL, PostgreSQL, SQLite, SQL Server
- [Troubleshooting](https://knobik.github.io/sql-agent/troubleshooting/) - Common issues and solutions

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

SQL Agent for Laravel is open-sourced software licensed under the [Apache-2.0 License](LICENSE).
