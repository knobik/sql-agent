---
title: Self-Learning
description: Automatic learning from SQL errors to improve agent accuracy over time.
---

SqlAgent can automatically learn from its mistakes and improve over time without any manual intervention.

## How It Works

1. The agent executes a SQL query and it fails
2. The agent analyzes the error, adjusts, and retries
3. If the recovery succeeds, a "learning" record is saved with the error context and fix
4. On future queries, relevant learnings are included in the agent's context
5. The agent avoids making the same mistake again

## Learning Categories

Learnings are automatically categorized:

| Category | Description |
|----------|-------------|
| `type_error` | Data type mismatches or casting issues |
| `schema_fix` | Incorrect schema assumptions (wrong table or column names) |
| `query_pattern` | Learned patterns for constructing queries |
| `data_quality` | Observations about data quality or anomalies |
| `business_logic` | Learned business rules or domain knowledge |

## Managing Learnings

Export and import learnings to share them across environments or back them up:

```bash
# Export all learnings to JSON
php artisan sql-agent:export-learnings

# Export a specific category
php artisan sql-agent:export-learnings --category=schema_fix

# Import learnings from a file
php artisan sql-agent:import-learnings learnings.json

# Prune learnings older than 90 days
php artisan sql-agent:prune-learnings --days=90

# Remove only duplicates
php artisan sql-agent:prune-learnings --duplicates

# Preview what would be removed
php artisan sql-agent:prune-learnings --dry-run
```

## Disabling Self-Learning

To disable the self-learning feature entirely:

```ini
SQL_AGENT_LEARNING_ENABLED=false
```

To keep manual learning (via the `SaveLearningTool`) but disable automatic error-based learning:

```ini
SQL_AGENT_AUTO_SAVE_ERRORS=false
```
