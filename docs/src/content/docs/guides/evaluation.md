---
title: Evaluation
description: Test agent accuracy with automated evaluations against known test cases.
---

The evaluation system helps you measure and improve your agent's accuracy by running it against a suite of test cases with known expected outcomes.

## Creating Test Cases

Test cases are stored in the `sql_agent_test_cases` table. You can seed them using the built-in seeder (`--seed` flag) or create your own:

```php
use Knobik\SqlAgent\Models\TestCase;

TestCase::create([
    'name' => 'Count active users',
    'category' => 'basic',
    'question' => 'How many active users are there?',
    'expected_values' => ['count' => 42],
    'golden_sql' => 'SELECT COUNT(*) as count FROM users WHERE status = "active"',
    'golden_result' => [['count' => 42]],
]);
```

| Field | Description |
|-------|-------------|
| `name` | A descriptive name for the test case |
| `category` | Grouping category (e.g., `basic`, `aggregation`, `complex`) |
| `question` | The natural language question to ask the agent |
| `expected_values` | Key-value pairs to match against results (supports dot notation) |
| `golden_sql` | The known-good SQL query for comparison |
| `golden_result` | The expected full result set |

## Running Evaluations

```bash
# Run all test cases
php artisan sql-agent:eval

# Run with LLM grading
php artisan sql-agent:eval --llm-grader

# Run a specific category
php artisan sql-agent:eval --category=aggregation

# Generate an HTML report
php artisan sql-agent:eval --html=storage/eval-report.html

# Seed built-in test cases first
php artisan sql-agent:eval --seed
```

## Evaluation Modes

Three evaluation modes are available:

| Mode | Description |
|------|-------------|
| **String Matching** (default) | Checks if expected values appear in the response |
| **LLM Grading** (`--llm-grader`) | Uses an LLM to semantically evaluate whether the response is correct |
| **Golden SQL** (`--golden-sql`) | Runs the golden SQL and compares its results against the agent's results |
