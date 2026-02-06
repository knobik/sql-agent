# Usage

## Artisan Commands

### sql-agent:install

Install the SqlAgent package.

```bash
php artisan sql-agent:install
php artisan sql-agent:install --force  # Overwrite existing files
```

### sql-agent:load-knowledge

Load knowledge files into the database.

```bash
php artisan sql-agent:load-knowledge

# Options
--recreate        # Drop and recreate all knowledge
--tables          # Load only table metadata
--rules           # Load only business rules
--queries         # Load only query patterns
--path=<path>     # Custom path to knowledge files
```

### sql-agent:eval

Run evaluation tests to measure agent accuracy.

```bash
php artisan sql-agent:eval

# Options
--category=<cat>  # Filter by category (basic, aggregation, complex, etc.)
--llm-grader      # Use LLM to grade responses
--golden-sql      # Compare against golden SQL results
--connection=<c>  # Use specific database connection
--detailed        # Show detailed output for failed tests
--json            # Output results as JSON
--html=<path>     # Generate HTML report at path
--seed            # Seed test cases before running
```

### sql-agent:export-learnings

Export learnings to a JSON file.

```bash
php artisan sql-agent:export-learnings
php artisan sql-agent:export-learnings output.json
php artisan sql-agent:export-learnings --category=type_error
```

Categories: `type_error`, `schema_fix`, `query_pattern`, `data_quality`, `business_logic`

### sql-agent:import-learnings

Import learnings from a JSON file.

```bash
php artisan sql-agent:import-learnings learnings.json
php artisan sql-agent:import-learnings learnings.json --force  # Include duplicates
```

### sql-agent:prune-learnings

Remove old or duplicate learnings.

```bash
php artisan sql-agent:prune-learnings

# Options
--days=90         # Remove learnings older than N days (default: 90)
--duplicates      # Only remove duplicate learnings
--include-used    # Also remove learnings that have been used
--dry-run         # Show what would be removed without removing
```

### sql-agent:purge

Purge SqlAgent data from the database. Truncates the selected tables.

```bash
php artisan sql-agent:purge

# Options
--conversations   # Only purge conversations and messages
--learnings       # Only purge learnings
--knowledge       # Only purge knowledge (query patterns, table metadata, business rules)
--all             # Purge everything (default if no options specified)
--force           # Skip confirmation prompt
```

When `--all` is used (or no options are specified), evaluation test cases are also purged.

## Programmatic Usage

### Basic Usage

```php
use Knobik\SqlAgent\Facades\SqlAgent;

$response = SqlAgent::run('How many users registered last week?');

// Access the response
$response->answer;      // Natural language answer
$response->sql;         // The SQL query that was executed
$response->results;     // Raw query results (array)
$response->toolCalls;   // All tool calls made during execution
$response->iterations;  // Detailed iteration data
$response->error;       // Error message if failed

// Check status
$response->isSuccess(); // true if no error
$response->hasResults(); // true if results is not empty
```

### Streaming Responses

```php
use Knobik\SqlAgent\Facades\SqlAgent;

foreach (SqlAgent::stream('Show me the top 5 customers') as $chunk) {
    echo $chunk->content;

    if ($chunk->isComplete()) {
        // Stream finished
    }
}
```

**Method signature:**

```php
SqlAgent::stream(
    string $question,           // The natural language question
    ?string $connection = null, // Database connection name (null for default)
    array $history = [],        // Previous conversation messages for context
): Generator
```

### Custom Connection

Query a specific database connection:

```php
$response = SqlAgent::run('How many orders today?', 'analytics');
```

### With Conversation History

For the streaming API with chat history:

```php
$history = [
    ['role' => 'user', 'content' => 'Show me all products'],
    ['role' => 'assistant', 'content' => 'Here are the products...'],
];

foreach (SqlAgent::stream('Now filter by price > 100', null, $history) as $chunk) {
    echo $chunk->content;
}
```

### Dependency Injection

```php
use Knobik\SqlAgent\Contracts\Agent;

class ReportController extends Controller
{
    public function __construct(
        private Agent $agent,
    ) {}

    public function generate(Request $request)
    {
        $response = $this->agent->run($request->input('question'));

        return [
            'answer' => $response->answer,
            'sql' => $response->sql,
            'data' => $response->results,
        ];
    }
}
```
