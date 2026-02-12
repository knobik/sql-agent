---
title: Troubleshooting
description: Common issues and solutions when using SqlAgent.
---

## No Knowledge Found or Poor Results

If the agent isn't finding relevant context or producing poor SQL:

1. Verify your knowledge files are valid JSON and follow the [expected format](/sql-agent/guides/knowledge-base/#table-metadata).
2. Reimport your knowledge with `php artisan sql-agent:load-knowledge --recreate`.
3. Check that the `sql_agent_table_metadata` table has entries: `SELECT COUNT(*) FROM sql_agent_table_metadata`.
4. Add more descriptive column information — the richer your descriptions, the better the agent performs.

## Maximum Iterations Reached

The agent couldn't complete the task within the allowed number of tool-calling rounds:

1. Increase the limit by setting `SQL_AGENT_MAX_ITERATIONS` in your `.env` file.
2. Add more specific knowledge about the tables involved so the agent needs fewer introspection steps.
3. Simplify the question or break it into smaller, more focused queries.

## SQL Errors in Production

If queries are being rejected or failing:

1. Check that `sql-agent.sql.allowed_statements` includes the statement types your queries need (default: `SELECT` and `WITH`).
2. Verify the query doesn't use any of the `sql-agent.sql.forbidden_keywords`.
3. Review `sql-agent.sql.max_rows` if result truncation is causing issues.

## Slow Response Times

If the agent is taking too long to respond:

1. Use a faster model (e.g., `gpt-4o-mini` instead of `gpt-4o`).
2. Reduce `chat_history_length` to minimize the context sent to the LLM.
3. Consider the `database` search driver for simpler setups — it avoids external service round-trips.

## LLM API Errors

If you're getting authentication or connection errors from the LLM provider:

1. Verify your API key is correct and has not expired.
2. Check your API quota and rate limits with your provider.
3. For Ollama, ensure the service is running (`ollama serve`) and the model has been pulled (`ollama pull <model>`).

## Search Not Finding Relevant Knowledge

If the search driver isn't returning expected results:

1. Ensure migrations ran successfully — full-text indexes are created in the migrations.
2. For MySQL, verify the tables use InnoDB or MyISAM engine (both support full-text indexes).
3. For SQL Server, ensure a full-text catalog is configured.
4. Consider switching to the `pgvector` search driver for semantic similarity search.

## pgvector Driver Errors

If you see errors about missing classes like `Pgvector\Laravel\Vector` or `Pgvector\Laravel\HasNeighbors`:

1. Install the required package: `composer require pgvector/pgvector`.
2. Run `php artisan sql-agent:setup-pgvector` to publish migrations and create the embeddings table.
3. Verify your `SQL_AGENT_EMBEDDINGS_CONNECTION` points to a PostgreSQL database with the pgvector extension installed.
