---
title: Knowledge Base
description: Define table metadata, business rules, and query patterns to give the agent context.
sidebar:
  order: 3
---

The knowledge base provides SqlAgent with the context it needs to write accurate SQL — your database schema, business terminology, metrics definitions, and example queries. Without this context, LLMs must guess at column meanings, business rules, and data quirks.

Knowledge is organized into three types, each stored in its own subdirectory as JSON (or SQL) files.

## Directory Structure

After running `php artisan sql-agent:install`, the following directory structure is created:

```
resources/sql-agent/knowledge/
├── tables/          # Table metadata
├── business/        # Business rules, metrics, and gotchas
└── queries/         # Example query patterns
```

The install command publishes a starter skeleton with example files for each type. You can also publish (or re-publish) the knowledge directory at any time:

```bash
php artisan vendor:publish --tag=sql-agent-knowledge
```

This copies example files into `resources/sql-agent/knowledge/` — including sample table metadata, business rules, and query patterns — that you can use as a starting point. Replace them with your own files to match your database.

## Table Metadata

Table metadata files describe your database schema in a way the LLM can understand. Each JSON file in `tables/` describes one table.

Columns are defined as a simple map of column name to description. Relationships are a list of human-readable strings:

```json
{
    "connection": "sales",
    "table": "orders",
    "description": "Contains customer orders and their status",
    "use_cases": [
        "Order management",
        "Revenue reporting",
        "Customer analytics"
    ],
    "data_quality_notes": [
        "total_amount is stored in cents, divide by 100 for dollars",
        "shipped_at is null if the order has not been shipped yet"
    ],
    "columns": {
        "id": "Primary key, auto-incrementing bigint unsigned",
        "customer_id": "FK -> customers.id, NOT NULL",
        "status": "Order status: 'pending', 'processing', 'shipped', 'delivered', 'cancelled' (default: 'pending')",
        "total_amount": "Order total in cents, int unsigned, NOT NULL",
        "created_at": "Order creation timestamp",
        "shipped_at": "Shipping timestamp, null if not shipped"
    },
    "relationships": [
        "Belongs to customers via customer_id -> customers.id"
    ]
}
```

**Available fields:**

| Field | Required | Description |
|-------|----------|-------------|
| `table` | Yes | Table name. Also accepts `table_name`. |
| `connection` | No | Logical connection name matching a key in your `database.connections` config. Files without this field default to `"default"` and are included for all connections. |
| `description` | No | Human-readable table description. Also accepts `table_description`. |
| `columns` | No | Map of column name to description string. |
| `relationships` | No | List of relationship description strings. |
| `use_cases` | No | List of common use cases for this table. |
| `data_quality_notes` | No | Caveats about the data (nullability quirks, encoding, units, etc.). |

:::tip
Column descriptions are free-form text. Include whatever context helps the LLM — data types, foreign key references, enum values, defaults, and business meaning. The more context you provide, the better the agent's SQL will be.
:::

:::tip
When using multiple databases, tag each table file with the `connection` field matching the key in your `database.connections` config. For example, if your config has `'sales' => [...]`, set `"connection": "sales"` in the corresponding table files. See the [Database Connections](/sql-agent/guides/multi-database/) guide for details.
:::

## Business Rules

Business rule files in `business/` define metrics, rules, and common pitfalls. Each file may contain any combination of three entry types:

```json
{
    "metrics": [
        {
            "name": "Active Customer",
            "definition": "A customer who has placed an order in the last 90 days",
            "table": "customers",
            "calculation": "WHERE last_order_at > NOW() - INTERVAL 90 DAY"
        }
    ],
    "business_rules": [
        "Cancelled orders (status='cancelled') should be excluded from revenue calculations",
        "Only delivered orders count toward fulfillment metrics",
        "The total_amount column stores cents, not dollars"
    ],
    "common_gotchas": [
        {
            "issue": "Order total in cents",
            "tables_affected": ["orders"],
            "solution": "Always divide total_amount by 100 when displaying dollar amounts."
        },
        {
            "issue": "Timezone handling",
            "tables_affected": ["orders", "customers"],
            "solution": "All timestamps are stored in UTC. Convert in the application layer, not SQL."
        }
    ]
}
```

Each entry is stored as a `BusinessRule` record with a type of `Metric`, `Rule`, or `Gotcha`.

:::note
Alternative field names are accepted: `rules` for `business_rules`, and `gotchas` for `common_gotchas`. Business rules may also be simple strings instead of objects.
:::

## Query Patterns

Query patterns teach the agent how to answer common questions. They serve as few-shot examples — when a user asks something similar, the agent can reference these patterns. Files go in `queries/` and may be JSON or SQL.

### JSON Format

```json
{
    "patterns": [
        {
            "name": "monthly_revenue",
            "question": "Calculate total revenue by month",
            "sql": "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) / 100 as revenue FROM orders WHERE status != 'cancelled' GROUP BY month ORDER BY month DESC",
            "summary": "Monthly revenue from non-cancelled orders",
            "tables_used": ["orders"]
        },
        {
            "name": "top_customers_by_orders",
            "question": "Find customers with the most orders",
            "sql": "SELECT c.id, c.name, COUNT(o.id) as order_count FROM customers c JOIN orders o ON o.customer_id = c.id WHERE o.status != 'cancelled' GROUP BY c.id, c.name ORDER BY order_count DESC LIMIT 10",
            "summary": "Top 10 customers ranked by order count",
            "tables_used": ["customers", "orders"],
            "data_quality_notes": "Excludes cancelled orders from the count"
        }
    ]
}
```

Alternative field names are accepted: `query` for `sql`, `description` for `summary`, `tables` for `tables_used`, and `queries` for `patterns`.

### SQL Format

SQL files use comment tags to define query patterns. This format is convenient when you already have working queries:

```sql
-- <query name>active_users_count</query name>
-- <query description>
-- Count the number of active users (logged in within 30 days)
-- </query description>
-- <query>
SELECT COUNT(*) as active_users
FROM users
WHERE deleted_at IS NULL
  AND last_login_at > NOW() - INTERVAL 30 DAY
-- </query>

-- <query name>top_authors_by_views</query name>
-- <query description>
-- Find the top 10 authors by total post views
-- </query description>
-- <query>
SELECT u.id, u.name, COUNT(p.id) as post_count, SUM(p.view_count) as total_views
FROM users u
JOIN posts p ON p.user_id = u.id
WHERE u.deleted_at IS NULL AND p.status = 'published'
GROUP BY u.id, u.name
ORDER BY total_views DESC
LIMIT 10
-- </query>
```

Each `<query name>` block defines a separate pattern. The `<query description>` block is optional. Tables are automatically extracted from the SQL.

## Loading Knowledge

After creating or updating your knowledge files, import them into the database using the `sql-agent:load-knowledge` Artisan command:

```bash
php artisan sql-agent:load-knowledge
```

You may load specific types of knowledge individually:

```bash
php artisan sql-agent:load-knowledge --tables
php artisan sql-agent:load-knowledge --rules
php artisan sql-agent:load-knowledge --queries
```

To clear all existing knowledge and reimport from scratch, use the `--recreate` flag:

```bash
php artisan sql-agent:load-knowledge --recreate
```

You may also specify a custom path to your knowledge files:

```bash
php artisan sql-agent:load-knowledge --path=/custom/knowledge/path
```

:::caution
When using the default `database` knowledge source, you **must** run this command after creating or changing knowledge files. The agent reads from the database at runtime, not directly from disk.
:::
