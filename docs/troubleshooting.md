# Troubleshooting

## "No knowledge found" or poor results

1. Ensure knowledge files are in the correct format (JSON)
2. Run `php artisan sql-agent:load-knowledge --recreate`
3. Check the `sql_agent_table_metadata` table has entries
4. Add more descriptive column information

## "Maximum iterations reached"

The agent couldn't complete the task in the allowed iterations:

1. Increase `SQL_AGENT_MAX_ITERATIONS` in `.env`
2. Add more specific knowledge about the tables involved
3. Simplify the question or break it into smaller queries

## SQL errors in production

1. Check `sql-agent.sql.allowed_statements` includes needed statement types
2. Verify the query doesn't use forbidden keywords
3. Review `sql-agent.sql.max_rows` if truncation is an issue

## Slow response times

1. Use a faster model (e.g., `gpt-4o-mini` instead of `gpt-4o`)
2. Reduce `chat_history_length` to minimize context
3. Consider using the `database` search driver instead of Scout for simpler setups

## LLM API errors

1. Verify your API key is correct
2. Check your API quota/limits
3. For Ollama, ensure the service is running and the model is downloaded

## Search not finding relevant knowledge

1. Ensure full-text indexes are created (check migrations ran successfully)
2. For MySQL, verify the table uses InnoDB or MyISAM engine
3. Consider using the `hybrid` search driver for better reliability
