# Evaluation & Self-Learning

## Evaluation System

The evaluation system helps you measure and improve your agent's accuracy.

### Creating Test Cases

Test cases are stored in the `sql_agent_test_cases` table. You can seed them using the built-in seeder or create your own:

```php
use Knobik\SqlAgent\Models\TestCase;

TestCase::create([
    'name' => 'Count active users',
    'category' => 'basic',
    'question' => 'How many active users are there?',
    'expected_strings' => ['active', 'users'], // Strings that should appear in response
    'golden_sql' => 'SELECT COUNT(*) FROM users WHERE status = "active"',
    'metadata' => ['difficulty' => 'easy'],
]);
```

### Running Evaluations

```bash
# Run all tests
php artisan sql-agent:eval

# Run with LLM grading
php artisan sql-agent:eval --llm-grader

# Run specific category
php artisan sql-agent:eval --category=aggregation

# Generate HTML report
php artisan sql-agent:eval --html=storage/eval-report.html
```

### Evaluation Modes

1. **String Matching** (default): Checks if expected strings appear in the response
2. **LLM Grading**: Uses an LLM to semantically evaluate the response
3. **Golden SQL**: Compares query results against a known-good SQL query

## Self-Learning

SqlAgent can automatically learn from its mistakes and improve over time.

### How It Works

1. When a SQL error occurs, the agent analyzes the error
2. If it successfully recovers, it creates a "learning" record
3. Future queries can reference these learnings for context
4. Learnings are categorized and can be exported/imported

### Learning Categories

- **Type Error**: Data type mismatches or casting issues
- **Schema Fix**: Incorrect schema assumptions (wrong table/column names)
- **Query Pattern**: Learned patterns for constructing queries
- **Data Quality**: Observations about data quality or anomalies
- **Business Logic**: Learned business rules or domain knowledge

### Managing Learnings

```bash
# Export all learnings
php artisan sql-agent:export-learnings

# Export specific category
php artisan sql-agent:export-learnings --category=schema_fix

# Import learnings
php artisan sql-agent:import-learnings learnings.json

# Prune old learnings
php artisan sql-agent:prune-learnings --days=90

# Remove duplicates
php artisan sql-agent:prune-learnings --duplicates
```

### Disabling Self-Learning

```env
SQL_AGENT_LEARNING_ENABLED=false
SQL_AGENT_AUTO_SAVE_ERRORS=false
```
