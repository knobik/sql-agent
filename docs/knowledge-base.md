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

## Business Rules

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

## Query Patterns

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
