---
title: Database Connections
description: Configure which databases the agent can query and how access is controlled.
sidebar:
  order: 9
---

SqlAgent uses the `database.connections` map in `config/sql-agent.php` to determine which databases the agent can query. Each entry maps a logical name to a Laravel database connection. The agent autonomously decides which database to query for each question and can combine results across databases.

## How It Works

1. The agent receives schema context for **all** configured databases.
2. The `run_sql` and `introspect_schema` tools have a `connection` parameter.
3. The LLM chooses which database to query on each tool call.
4. For cross-database questions, the LLM runs separate queries and combines results in its response.

No special "query planner" is needed — the LLM's existing tool-calling loop handles multi-step reasoning naturally.

:::note
Cross-database JOINs are not supported. The agent runs separate queries per database and merges results at the response level. This works well for aggregations, comparisons, and lookups across systems.
:::

## Configuration

Add entries to the `connections` map under the `database` key in `config/sql-agent.php`:

```php
'database' => [
    'storage_connection' => env('SQL_AGENT_STORAGE_CONNECTION', config('database.default')),

    'connections' => [
        'crm' => [
            'connection' => 'mysql_crm',
            'label' => 'CRM Database',
            'description' => 'Customers, contacts, deals, and activities.',
        ],
        'analytics' => [
            'connection' => 'pgsql_analytics',
            'label' => 'Analytics Database',
            'description' => 'Page views, events, funnels, and attribution data.',
        ],
    ],
],
```

Each connection accepts these options:

| Option | Description | Required |
|--------|-------------|----------|
| `connection` | The Laravel database connection name (from `config/database.php`) | Yes |
| `label` | Human-readable label shown to the LLM and in the UI | No (defaults to the key) |
| `description` | What data this database holds — helps the LLM choose the right database | No |
| `allowed_tables` | Whitelist of tables the agent may access on this connection (empty = all) | No |
| `denied_tables` | Blacklist of tables the agent may never access on this connection | No |
| `hidden_columns` | Columns to hide per table on this connection | No |

:::tip
Write clear, descriptive `description` values. The LLM reads these to decide which database to query. "Orders, products, and customers" is much better than "Sales data".
:::

### Single Database

By default, SqlAgent ships with a single `default` connection that uses your application's default database. If your application only has one database, the default config works out of the box — just update the `label` and `description` to match your data:

```php
'connections' => [
    'default' => [
        'connection' => env('SQL_AGENT_CONNECTION', config('database.default')),
        'label' => 'Database',
        'description' => 'All application data including users, orders, and products.',
    ],
],
```

## Per-Connection Access Control

Each connection can define its own table and column restrictions:

```php
'connections' => [
    'hr' => [
        'connection' => 'pgsql_hr',
        'label' => 'HR Database',
        'description' => 'Employees, departments, and leave records.',
        'allowed_tables' => ['employees', 'departments', 'leave_requests'],
        'denied_tables' => ['salary_details', 'performance_reviews'],
        'hidden_columns' => [
            'employees' => ['ssn', 'bank_account'],
        ],
    ],
],
```

**How it works:**

- **`allowed_tables`** acts as a whitelist. When non-empty, only listed tables are visible to the agent on this connection. Leave empty to allow all tables.
- **`denied_tables`** acts as a blacklist. Listed tables are always denied, even if they appear in `allowed_tables`. This takes precedence.
- **`hidden_columns`** removes specific columns from schema introspection and semantic model output. The agent will not know these columns exist.

Restrictions are enforced at every layer:

- Schema introspection (listing tables, inspecting columns)
- Semantic model loading (table metadata from database)
- SQL execution (queries referencing denied tables are rejected)

## Web Interface

The chat header shows a badge indicating the number of connected databases (e.g., "2 databases connected"). The LLM handles connection routing — no user selection is needed.

Tool call indicators in the streaming UI show which database is being queried, for example "Running SQL query on crm" or "Inspecting schema on analytics".

## Example: Cross-Database Question

With a CRM and analytics database configured, you might ask:

> "Which of our top 10 customers by revenue had the most page views last month?"

The agent will:

1. Query the CRM database for the top 10 customers by revenue.
2. Query the analytics database for page views grouped by customer.
3. Combine the results and present an insightful answer.

Each step is visible in the streaming UI as a separate tool call with its connection label.

## Knowledge Loading

Table metadata is scoped per connection using the `connection` field in each JSON knowledge file. When you run `sql-agent:load-knowledge`, the loader reads the `connection` field from each table JSON file and stores it in the database alongside the metadata.

### Tagging Knowledge Files

Add a `connection` field to your table JSON files matching the **logical connection name** (the key in your `connections` config, not the Laravel connection name):

```json
{
  "connection": "crm",
  "table": "customers",
  "description": "All registered customers.",
  "columns": {
    "id": "Primary key",
    "name": "Customer full name",
    "email": "Contact email address"
  }
}
```

Files without a `connection` field default to `"default"` and are included for all connections.

### Loading Workflow

1. Tag each table JSON file with the appropriate `connection` value.
2. Run `php artisan sql-agent:load-knowledge` to import all files.
3. The agent loads only the metadata matching each connection when building context.

:::tip
Use the same logical names in your JSON files that you use as keys in the `database.connections` config. For example, if your config has `'crm' => [...]`, set `"connection": "crm"` in the corresponding knowledge files.
:::

## Limitations

- **No cross-database JOINs.** The agent runs separate queries and combines results programmatically.
- **Learnings and query patterns are global.** They are not scoped per connection.
- **All schemas are loaded upfront.** For databases with many tables, consider using `allowed_tables` to limit what the agent sees.
