# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel SQL Agent is a self-learning text-to-SQL package that converts natural language questions into SQL queries using LLMs. It uses an agentic tool-calling loop with schema introspection, knowledge search, and self-learning from errors.

## Commands

### Development
```bash
composer test                    # Run Pest tests
composer test-coverage           # Run tests with coverage
composer format                  # Format code with Laravel Pint
composer format-check            # Check formatting without changes
composer analyse                 # Run PHPStan (level 5)
```

### Run single test file
```bash
./vendor/bin/pest tests/Unit/ServicesTest.php
./vendor/bin/pest tests/Feature/Livewire/ChatComponentTest.php
```

### Run single test by name
```bash
./vendor/bin/pest --filter="test name pattern"
```

### Artisan Commands (require Laravel app context)
```bash
php artisan sql-agent:install              # Install package
php artisan sql-agent:load-knowledge       # Load knowledge files
php artisan sql-agent:eval                 # Run evaluation tests
php artisan sql-agent:export-learnings     # Export learnings to JSON
php artisan sql-agent:import-learnings     # Import learnings from JSON
php artisan sql-agent:prune-learnings      # Remove old learnings
```

## Architecture

### Core Flow
1. User question → `SqlAgent::ask()` or Livewire ChatComponent
2. `ContextBuilder` assembles multi-layer context (schema, business rules, learnings)
3. Agent enters tool-calling loop (max iterations configurable)
4. LLM calls tools: `IntrospectSchemaTool`, `SearchKnowledgeTool`, `RunSqlTool`
5. On SQL errors, `SqlErrorOccurred` event triggers `AutoLearnFromError` listener
6. Response returned with SQL, results, or error recovery

### Key Directories
- `src/Agent/` - Core agent: `SqlAgent`, `ToolRegistry`, `MessageBuilder`, `PromptRenderer`
- `src/Llm/` - LLM abstraction: `LlmManager` + drivers (OpenAI, Anthropic, Ollama)
- `src/Search/` - Knowledge search: `SearchManager` + drivers (Database, Scout, Hybrid)
- `src/Services/` - Business logic: `SchemaIntrospector`, `ContextBuilder`, `LearningMachine`, `EvaluationRunner`
- `src/Tools/` - Agent tools: `RunSqlTool`, `IntrospectSchemaTool`, `SearchKnowledgeTool`, `SaveLearningTool`
- `src/Models/` - Eloquent models with separate storage connection support
- `src/Livewire/` - Chat UI components

### Multi-Driver Pattern
Both LLM and Search use a manager pattern with swappable drivers:
- `LlmManager` resolves drivers via `config('sql-agent.llm.driver')`
- `SearchManager` resolves drivers via `config('sql-agent.search.driver')`
- All drivers implement their respective contracts (`LlmDriver`, `SearchDriver`)

### Database Search Strategies
Full-text search adapts per database via `FullTextSearchStrategy` interface:
- `MySqlStrategy` - NATURAL LANGUAGE MODE
- `PostgresStrategy` - to_tsvector/to_tsquery
- `SqlServerStrategy` - CONTAINS
- `SqliteLikeStrategy` - LIKE fallback

### Knowledge System
Three types of knowledge loaded from JSON files or database:
- **Table Metadata** (`TableMetadata`) - Schema descriptions, column semantics
- **Business Rules** (`BusinessRule`) - Domain-specific logic
- **Query Patterns** (`QueryPattern`) - Example queries as templates

### Self-Learning
- `Learning` model stores corrections from SQL errors
- Categories: `TypeError`, `SchemaFix`, `QueryPattern`, `DataQuality`, `BusinessLogic`
- Event-driven: `SqlErrorOccurred` → `AutoLearnFromError` listener
- Learnings included in context for future queries

## Key Contracts

- `Agent` - Main agent interface (`ask()`, `stream()`)
- `LlmDriver` - LLM provider abstraction (`chat()`, `stream()`)
- `SearchDriver` - Knowledge search abstraction (`search()`)
- `Tool` - Agent tool interface (`name()`, `description()`, `parameters()`, `handle()`)
- `FullTextSearchStrategy` - Database-specific search strategy

## Testing

Uses Pest framework with Orchestra Testbench for Laravel package testing.

- Unit tests in `tests/Unit/` - isolated component tests
- Feature tests in `tests/Feature/` - Livewire and integration tests
- Test base class: `tests/TestCase.php` extends Orchestra's TestCase

## Code Style

- Laravel Pint with Laravel preset (`pint.json`)
- PHPStan level 5 (`phpstan.neon`)
- Strict types required (`declare(strict_types=1)`)
- Spatie Laravel Data for DTOs (`src/Data/`)
