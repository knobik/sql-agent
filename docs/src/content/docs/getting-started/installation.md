---
title: Installation
description: Install and configure SQL Agent for Laravel in your Laravel application.
sidebar:
  order: 2
---

## Install via Composer

```bash
composer require knobik/sql-agent
```

## Run the Install Command

```bash
php artisan sql-agent:install
```

This will:
1. Publish the configuration file
2. Publish and run migrations
3. Create the knowledge directory structure at `resources/sql-agent/knowledge/`

## Configure Your LLM Provider

SqlAgent uses [Prism PHP](https://prismphp.com) to communicate with LLM providers. First, publish the Prism config and set your provider credentials:

```bash
php artisan vendor:publish --tag=prism-config
```

Then set the provider and model in your `.env`:

```ini
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

API keys and base URLs are configured in `config/prism.php`. See the [Prism documentation](https://prismphp.com) for provider-specific setup.

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

## Publishing Assets

The install command publishes the config, migrations, and knowledge directory automatically. You can also publish individual assets at any time:

| Tag | Command | Publishes To |
|-----|---------|-------------|
| `sql-agent-config` | `php artisan vendor:publish --tag=sql-agent-config` | `config/sql-agent.php` |
| `sql-agent-migrations` | `php artisan vendor:publish --tag=sql-agent-migrations` | `database/migrations/` |
| `sql-agent-views` | `php artisan vendor:publish --tag=sql-agent-views` | `resources/views/vendor/sql-agent/` |
| `sql-agent-knowledge` | `php artisan vendor:publish --tag=sql-agent-knowledge` | `resources/sql-agent/knowledge/` |
| `sql-agent-prompts` | `php artisan vendor:publish --tag=sql-agent-prompts` | `resources/views/vendor/sql-agent/prompts/` |

Published views and prompts override the package defaults, so you can customize the [chat UI](/sql-agent/guides/web-interface/#customizing-views) and the [system prompt](/sql-agent/guides/self-learning/#customizing-the-system-prompt) without modifying the package.
