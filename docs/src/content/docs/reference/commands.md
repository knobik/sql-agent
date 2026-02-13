---
title: Artisan Commands
description: All SqlAgent Artisan commands and their options.
sidebar:
  order: 1
---

## `sql-agent:install`

Run the initial setup: publishes the configuration file, runs migrations, and creates the knowledge directory structure.

```bash
php artisan sql-agent:install
php artisan sql-agent:install --force  # Overwrite existing files
```

## `sql-agent:setup-pgvector`

Set up pgvector support. This command publishes the embeddings migration and runs it to create the extension, table, and index on your pgvector connection.

Requires the `pgvector/pgvector` package:

```bash
composer require pgvector/pgvector
php artisan sql-agent:setup-pgvector
```

The command will:

1. Verify that the `pgvector/pgvector` package is installed
2. Verify that `SQL_AGENT_EMBEDDINGS_CONNECTION` is set and points to a PostgreSQL database
3. Skip if the `sql_agent_embeddings` table already exists
4. Publish the pgvector embeddings migration
5. Ask to run migrations (which creates the extension, table, and HNSW index)

:::tip
After running this command, generate embeddings for your existing knowledge base with `php artisan sql-agent:generate-embeddings`.
:::

## `sql-agent:load-knowledge`

Import knowledge files from disk into the database. Required after creating or changing knowledge files.

```bash
php artisan sql-agent:load-knowledge
```

| Option | Description |
|--------|-------------|
| `--recreate` | Drop and recreate all knowledge before loading |
| `--tables` | Load only table metadata |
| `--rules` | Load only business rules |
| `--queries` | Load only query patterns |
| `--path=<path>` | Load from a custom directory instead of the configured path |

## `sql-agent:eval`

Run evaluation tests to measure the agent's accuracy against known test cases.

```bash
php artisan sql-agent:eval
```

| Option | Description |
|--------|-------------|
| `--category=<cat>` | Filter by category (`basic`, `aggregation`, `complex`, etc.) |
| `--llm-grader` | Use an LLM to semantically grade responses |
| `--golden-sql` | Compare results against golden SQL output |
| `--connection=<conn>` | Use a specific database connection |
| `--detailed` | Show detailed output for failed tests |
| `--json` | Output results as JSON |
| `--html=<path>` | Generate an HTML report at the given path |
| `--seed` | Seed test cases before running |

## `sql-agent:export-learnings`

Export learnings to a JSON file for backup or sharing across environments.

```bash
php artisan sql-agent:export-learnings
php artisan sql-agent:export-learnings output.json
php artisan sql-agent:export-learnings --category=type_error
```

Available categories: `type_error`, `schema_fix`, `query_pattern`, `data_quality`, `business_logic`.

## `sql-agent:import-learnings`

Import learnings from a previously exported JSON file.

```bash
php artisan sql-agent:import-learnings learnings.json
php artisan sql-agent:import-learnings learnings.json --force  # Include duplicates
```

## `sql-agent:prune-learnings`

Remove old or duplicate learnings to keep the knowledge base clean.

```bash
php artisan sql-agent:prune-learnings
```

| Option | Description |
|--------|-------------|
| `--days=90` | Remove learnings older than N days (default: config value) |
| `--duplicates` | Only remove duplicate learnings |
| `--include-used` | Also remove learnings that have been referenced |
| `--dry-run` | Preview what would be removed without deleting |

:::tip
This command is not scheduled automatically. Add it to your scheduler for hands-off maintenance. See [Configuration — Learning](/sql-agent/guides/configuration/#learning).
:::

## `sql-agent:generate-embeddings`

Generate vector embeddings for existing knowledge base records. Required when switching to the pgvector search driver or after bulk-importing data.

```bash
php artisan sql-agent:generate-embeddings
php artisan sql-agent:generate-embeddings --model=query_patterns
php artisan sql-agent:generate-embeddings --force --batch-size=100
```

| Option | Description |
|--------|-------------|
| `--model=<name>` | Only generate for a specific model (`query_patterns` or `learnings`) |
| `--force` | Regenerate embeddings even if they already exist |
| `--batch-size=50` | Number of records to process per batch (default: 50) |

:::note
This command requires the `pgvector/pgvector` Composer package and the pgvector driver's `connection` to be configured (via `SQL_AGENT_EMBEDDINGS_CONNECTION`) pointing to a PostgreSQL database with pgvector installed.
:::

## `sql-agent:purge`

Purge SqlAgent data from the database by truncating the selected tables.

```bash
php artisan sql-agent:purge
```

| Option | Description |
|--------|-------------|
| `--conversations` | Only purge conversations and messages |
| `--learnings` | Only purge learnings |
| `--knowledge` | Only purge knowledge (table metadata, business rules, query patterns) |
| `--all` | Purge everything (default when no options specified) |
| `--force` | Skip the confirmation prompt |

When `--all` is used (or no options are specified), evaluation test cases are also purged.

:::note
When the pgvector driver's `connection` is configured (`SQL_AGENT_EMBEDDINGS_CONNECTION` is set), purging learnings or knowledge also truncates the `sql_agent_embeddings` table on the embeddings connection.
:::
