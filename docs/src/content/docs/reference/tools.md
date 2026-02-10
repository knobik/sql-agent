---
title: Agent Tools
description: Detailed reference for all tools available to the LLM agent during query execution.
sidebar:
  order: 5
---

The agent uses a set of tools during its agentic loop to introspect the database, search knowledge, execute SQL, and persist learnings. Each tool is registered with the LLM as a callable function with a JSON Schema describing its parameters.

This page documents every tool, its parameters, the JSON sent to the LLM, and what the tool returns.

## How Tools Are Sent to the LLM

Tools are serialized into the format expected by each LLM provider. The package handles this automatically via `ToolFormatter`.

### OpenAI / Ollama Format

```json
{
  "type": "function",
  "function": {
    "name": "tool_name",
    "description": "What the tool does.",
    "parameters": {
      "type": "object",
      "properties": { ... },
      "required": [ ... ]
    }
  }
}
```

### Anthropic Format

```json
{
  "name": "tool_name",
  "description": "What the tool does.",
  "input_schema": {
    "type": "object",
    "properties": { ... },
    "required": [ ... ]
  }
}
```

The `parameters` (OpenAI) and `input_schema` (Anthropic) objects are identical — only the wrapper differs.

---

## `run_sql`

Execute a SQL query against the database. This is the primary tool the agent uses to answer questions.

**Description sent to LLM:**
> Execute a SQL query against the database. Only SELECT and WITH statements are allowed. Returns query results as JSON.

### Parameters

```json
{
  "type": "object",
  "properties": {
    "sql": {
      "type": "string",
      "description": "The SQL query to execute. Must be a SELECT or WITH statement."
    }
  },
  "required": ["sql"]
}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sql` | string | Yes | The SQL query to execute. Must start with `SELECT` or `WITH`. |

### Return Value

```json
{
  "rows": [
    {"id": 1, "name": "Alice"},
    {"id": 2, "name": "Bob"}
  ],
  "row_count": 2,
  "total_rows": 2,
  "truncated": false
}
```

| Field | Type | Description |
|-------|------|-------------|
| `rows` | array | The query result rows as objects. |
| `row_count` | integer | Number of rows returned (after truncation). |
| `total_rows` | integer | Total rows the query produced before truncation. |
| `truncated` | boolean | Whether results were truncated to the configured `sql.max_rows` limit. |

### Safety

- Only `SELECT` and `WITH` statements are allowed (configurable via `sql.allowed_statements`).
- Forbidden keywords (`DROP`, `DELETE`, `UPDATE`, `INSERT`, `ALTER`, `CREATE`, `TRUNCATE`, etc.) are rejected even inside subqueries.
- Multiple statements separated by `;` are blocked.
- Results are capped at `sql.max_rows` (default: 1000).
- On error, a `SqlErrorOccurred` event is dispatched for [auto-learning](/laravel-sql-agent/guides/self-learning/).

### Example Tool Call

```json
{
  "sql": "SELECT COUNT(*) as total FROM orders WHERE status = 'delivered'"
}
```

---

## `introspect_schema`

Inspect the database schema. The agent uses this to discover tables, columns, types, foreign keys, and sample data before writing SQL.

**Description sent to LLM:**
> Get detailed schema information about database tables. Can inspect a specific table or list all available tables.

### Parameters

```json
{
  "type": "object",
  "properties": {
    "table_name": {
      "type": "string",
      "description": "Optional: The name of a specific table to inspect. If not provided, lists all tables."
    },
    "include_sample_data": {
      "type": "boolean",
      "description": "Whether to include sample data from the table (up to 3 rows). This data is for understanding the schema only - never use it directly in responses to the user.",
      "default": false
    }
  }
}
```

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `table_name` | string | No | — | A specific table to inspect. Omit to list all tables. |
| `include_sample_data` | boolean | No | `false` | Include up to 3 sample rows for schema understanding. |

### Return Value

**When listing all tables** (no `table_name` provided):

```json
{
  "tables": ["users", "orders", "products"],
  "count": 3
}
```

**When inspecting a specific table:**

```json
{
  "table": "orders",
  "description": "Table comment if set in the database",
  "columns": [
    {
      "name": "id",
      "type": "bigint",
      "nullable": false,
      "primary_key": true,
      "foreign_key": false,
      "references": null,
      "default": null,
      "description": null
    },
    {
      "name": "customer_id",
      "type": "bigint",
      "nullable": false,
      "primary_key": false,
      "foreign_key": true,
      "references": "customers.id",
      "default": null,
      "description": null
    }
  ],
  "relationships": [
    {
      "type": "belongsTo",
      "related_table": "customers",
      "foreign_key": "customer_id",
      "local_key": "id"
    }
  ],
  "sample_data": [
    {"id": 1, "customer_id": 42, "status": "delivered", "total_amount": 9999}
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `table` | string | Table name. |
| `description` | string\|null | Table comment from the database, if set. |
| `columns` | array | Column details including type, nullability, keys, and defaults. |
| `relationships` | array | Foreign key relationships detected from the schema. |
| `sample_data` | array | Up to 3 sample rows (only when `include_sample_data` is `true`). |

### Example Tool Call

```json
{
  "table_name": "orders",
  "include_sample_data": true
}
```

---

## `search_knowledge`

Search the knowledge base for relevant query patterns and learnings. The agent calls this before writing SQL to find proven patterns and avoid known pitfalls.

**Description sent to LLM:**
> Search the knowledge base for relevant query patterns and learnings. Use this to find similar queries, understand business logic, or discover past learnings about the database.

### Parameters

```json
{
  "type": "object",
  "properties": {
    "query": {
      "type": "string",
      "description": "The search query to find relevant knowledge."
    },
    "type": {
      "type": "string",
      "description": "Filter results: 'all' (default), 'patterns' (saved query patterns), or 'learnings' (discovered fixes/gotchas).",
      "enum": ["all", "patterns", "learnings"]
    },
    "limit": {
      "type": "integer",
      "description": "Maximum number of results to return.",
      "minimum": 1,
      "maximum": 20
    }
  },
  "required": ["query"]
}
```

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `query` | string | Yes | — | The search query text. |
| `type` | string | No | `all` | Filter by `all`, `patterns`, or `learnings`. |
| `limit` | integer | No | `5` | Max results to return (1–20). |

### Return Value

```json
{
  "query_patterns": [
    {
      "name": "monthly_revenue",
      "question": "Calculate total revenue by month",
      "sql": "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) / 100 as revenue FROM orders WHERE status != 'cancelled' GROUP BY month ORDER BY month DESC",
      "summary": "Monthly revenue from non-cancelled orders",
      "tables_used": ["orders"],
      "relevance_score": 8.5
    }
  ],
  "learnings": [
    {
      "title": "orders.total_amount is in cents",
      "description": "Always divide total_amount by 100 when displaying dollar amounts.",
      "category": "data_quality",
      "sql": null,
      "relevance_score": 7.2
    }
  ],
  "total_found": 2
}
```

| Field | Type | Description |
|-------|------|-------------|
| `query_patterns` | array | Matching query patterns (from knowledge files and saved validated queries). |
| `learnings` | array | Matching learnings (agent-discovered patterns). Only included when learning is enabled. |
| `total_found` | integer | Total number of results across both types. |

### Example Tool Call

```json
{
  "query": "monthly revenue calculation",
  "type": "patterns",
  "limit": 5
}
```

---

## `save_learning`

Save a new learning to the knowledge base. The agent uses this when it discovers something important — typically after recovering from a SQL error or when a user provides a correction.

**Description sent to LLM:**
> Save a new learning to the knowledge base. Use this when you discover something important about the database schema, business logic, or query patterns that would be useful for future queries.

:::note
This tool is only available when `sql-agent.learning.enabled` is `true` (the default).
:::

### Parameters

```json
{
  "type": "object",
  "properties": {
    "title": {
      "type": "string",
      "description": "A short, descriptive title for the learning (max 100 characters)."
    },
    "description": {
      "type": "string",
      "description": "A detailed description of what was learned and why it matters."
    },
    "category": {
      "type": "string",
      "description": "The category of this learning.",
      "enum": ["type_error", "schema_fix", "query_pattern", "data_quality", "business_logic"]
    },
    "sql": {
      "type": "string",
      "description": "Optional: The SQL query related to this learning."
    }
  },
  "required": ["title", "description", "category"]
}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `title` | string | Yes | Short title, max 100 characters. |
| `description` | string | Yes | Detailed description of what was learned. |
| `category` | string | Yes | One of `type_error`, `schema_fix`, `query_pattern`, `data_quality`, `business_logic`. |
| `sql` | string | No | Related SQL query. |

### Categories

| Category | Description |
|----------|-------------|
| `type_error` | A correction for a data type mismatch or casting issue. |
| `schema_fix` | A correction for incorrect schema assumptions. |
| `query_pattern` | A learned pattern for constructing queries. |
| `data_quality` | An observation about data quality or anomalies. |
| `business_logic` | A learned business rule or domain knowledge. |

### Return Value

```json
{
  "success": true,
  "message": "Learning saved successfully.",
  "learning_id": 12,
  "title": "users.status is VARCHAR not INT",
  "category": "type_error"
}
```

### Example Tool Call

```json
{
  "title": "orders.total_amount is in cents",
  "description": "The total_amount column stores values in cents, not dollars. Always divide by 100 when displaying monetary values.",
  "category": "data_quality",
  "sql": "SELECT total_amount / 100 as amount_dollars FROM orders"
}
```

---

## `save_validated_query`

Save a validated query pattern after successfully answering a question. This builds the knowledge base organically — future similar questions can reference proven SQL.

**Description sent to LLM:**
> Save a validated query pattern to the knowledge base. Use this when you have successfully executed a SQL query that correctly answers a user question. This helps future queries by providing proven patterns.

:::note
This tool is only available when `sql-agent.learning.enabled` is `true` (the default). Saved queries appear alongside patterns from the [Knowledge Base](/laravel-sql-agent/guides/knowledge-base/#query-patterns) in `search_knowledge` results.
:::

### Parameters

```json
{
  "type": "object",
  "properties": {
    "name": {
      "type": "string",
      "description": "A short, descriptive name for the query pattern (max 100 characters)."
    },
    "question": {
      "type": "string",
      "description": "The natural language question this query answers."
    },
    "sql": {
      "type": "string",
      "description": "The validated SQL query that correctly answers the question."
    },
    "summary": {
      "type": "string",
      "description": "A brief summary of what the query does and what data it returns."
    },
    "tables_used": {
      "type": "array",
      "description": "List of table names used in the query.",
      "items": {
        "type": "string"
      }
    },
    "data_quality_notes": {
      "type": "string",
      "description": "Optional: Notes about data quality issues, edge cases, or important considerations for this query."
    }
  },
  "required": ["name", "question", "sql", "summary", "tables_used"]
}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Short name for the pattern, max 100 characters. |
| `question` | string | Yes | The natural language question this query answers. |
| `sql` | string | Yes | The validated SQL (must start with `SELECT` or `WITH`). |
| `summary` | string | Yes | Brief summary of what the query returns. |
| `tables_used` | array of strings | Yes | Tables referenced in the query. |
| `data_quality_notes` | string | No | Notes about edge cases or data quality considerations. |

### Return Value

```json
{
  "success": true,
  "message": "Query pattern saved successfully.",
  "pattern_id": 7,
  "name": "monthly_active_users",
  "tables_used": ["users", "logins"]
}
```

### Duplicate Detection

If a query pattern with the same question already exists, the tool returns an error instead of creating a duplicate. This prevents the knowledge base from accumulating redundant patterns.

### Example Tool Call

```json
{
  "name": "monthly_active_users",
  "question": "How many active users were there last month?",
  "sql": "SELECT COUNT(DISTINCT user_id) as active_users FROM logins WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
  "summary": "Count of unique users who logged in during the last calendar month",
  "tables_used": ["logins"],
  "data_quality_notes": "Only counts users with at least one login event"
}
```

---

## Tool Availability

Not all tools are available in every configuration:

| Tool | Always Available | Condition |
|------|-----------------|-----------|
| `run_sql` | Yes | — |
| `introspect_schema` | Yes | — |
| `search_knowledge` | Yes | — |
| `save_learning` | No | Requires `sql-agent.learning.enabled = true` |
| `save_validated_query` | No | Requires `sql-agent.learning.enabled = true` |

When learning is disabled (`SQL_AGENT_LEARNING_ENABLED=false`), the `save_learning` and `save_validated_query` tools are not registered with the LLM, and the related instructions are removed from the system prompt.

In addition to the built-in tools above, you can register your own tools via the `agent.tools` config option. See the [Custom Tools](/laravel-sql-agent/guides/custom-tools/) guide for details.
