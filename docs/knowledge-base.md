# Knowledge Base

The knowledge base helps SqlAgent understand your database schema, business rules, and common query patterns.

## Directory Structure

```
resources/sql-agent/knowledge/
├── tables/          # Table metadata (JSON)
├── business/        # Business rules and metrics (JSON)
└── queries/         # Query patterns (SQL or JSON)
```

## Table Metadata

Create JSON files in `tables/` to describe your database schema:

```json
{
    "table_name": "orders",
    "table_description": "Contains customer orders and their status",
    "use_cases": [
        "Order management",
        "Revenue reporting",
        "Customer analytics"
    ],
    "data_quality_notes": [
        "total_amount is stored in cents, divide by 100 for dollars",
        "shipped_at is null if the order has not been shipped yet"
    ],
    "table_columns": [
        {
            "name": "id",
            "type": "bigint unsigned",
            "description": "Primary key",
            "primary_key": true,
            "nullable": false
        },
        {
            "name": "customer_id",
            "type": "bigint unsigned",
            "description": "Foreign key to customers.id",
            "foreign_key": true,
            "foreign_table": "customers",
            "foreign_column": "id",
            "nullable": false
        },
        {
            "name": "status",
            "type": "enum('pending','processing','shipped','delivered','cancelled')",
            "description": "Order status",
            "nullable": false,
            "default": "pending"
        },
        {
            "name": "total_amount",
            "type": "int unsigned",
            "description": "Order total in cents",
            "nullable": false
        },
        {
            "name": "created_at",
            "type": "timestamp",
            "description": "Order creation timestamp",
            "nullable": true
        },
        {
            "name": "shipped_at",
            "type": "timestamp",
            "description": "Shipping timestamp, null if not shipped",
            "nullable": true
        }
    ],
    "relationships": [
        {
            "type": "belongsTo",
            "related_table": "customers",
            "foreign_key": "customer_id",
            "description": "The customer who placed the order"
        }
    ]
}
```

The loader also accepts `description` (or `table_description`), `columns` (or `table_columns`) as alternative field names for backwards compatibility.

## Business Rules

Create JSON files in `business/` to define business logic, metrics, and common pitfalls. Each file can contain three types of entries:

```json
{
    "metrics": [
        {
            "name": "Active Customer",
            "definition": "A customer who has placed an order in the last 90 days",
            "table": "customers",
            "calculation": "WHERE last_order_at > NOW() - INTERVAL 90 DAY"
        },
        {
            "name": "High-Value Order",
            "definition": "An order with total_amount >= 10000 (i.e. $100+)",
            "table": "orders",
            "calculation": "WHERE total_amount >= 10000 AND status != 'cancelled'"
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
            "solution": "Always divide total_amount by 100 when displaying dollar amounts. SUM(total_amount) / 100 for revenue."
        },
        {
            "issue": "Timezone handling",
            "tables_affected": ["orders", "customers"],
            "solution": "All timestamps are stored in UTC. Convert in the application layer, not SQL."
        }
    ]
}
```

Each type is loaded as a separate `BusinessRule` record with a type of `Metric`, `Rule`, or `Gotcha` (see `BusinessRuleType` enum).

Alternative field names are supported: `rules` (for `business_rules`) and `gotchas` (for `common_gotchas`).

## Query Patterns

Create files in `queries/` to teach SqlAgent common query patterns:

**JSON format (`queries/revenue.json`):**

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
            "sql": "SELECT c.id, c.name, COUNT(o.id) as order_count, SUM(o.total_amount) / 100 as total_spent FROM customers c JOIN orders o ON o.customer_id = c.id WHERE o.status != 'cancelled' GROUP BY c.id, c.name ORDER BY order_count DESC LIMIT 10",
            "summary": "Top 10 customers ranked by order count",
            "tables_used": ["customers", "orders"],
            "data_quality_notes": "Excludes cancelled orders from the count"
        }
    ]
}
```

Alternative field names are supported: `query` (for `sql`), `description` (for `summary`), `tables` (for `tables_used`). A `queries` key can be used instead of `patterns`.

**SQL format (`queries/top_customers.sql`):**

SQL files use XML-like comment tags to define query patterns:

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
SELECT
    u.id,
    u.name,
    COUNT(p.id) as post_count,
    SUM(p.view_count) as total_views
FROM users u
JOIN posts p ON p.user_id = u.id
WHERE u.deleted_at IS NULL
  AND p.status = 'published'
GROUP BY u.id, u.name
ORDER BY total_views DESC
LIMIT 10
-- </query>
```

Each `<query name>...</query name>` block defines a separate query pattern. The `<query description>` block is optional and provides context. The `<query>` block contains the actual SQL. Tables are automatically extracted from the SQL.

## Loading Knowledge

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
