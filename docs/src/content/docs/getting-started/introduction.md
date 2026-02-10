---
title: Introduction
description: Overview of SQL Agent for Laravel and how it works.
sidebar:
  order: 1
---

SQL Agent for Laravel is a self-learning text-to-SQL package that converts natural language questions into SQL queries using LLMs.

This package is based on [Dash](https://github.com/agno-agi/dash) and [OpenAI's in-house data agent](https://openai.com/index/inside-our-in-house-data-agent/).

## How It Works

1. User asks a question via `SqlAgent::run()` or the web UI
2. The `ContextBuilder` assembles context — schema, business rules, learnings
3. The agent enters a tool-calling loop with the LLM
4. Tools (`IntrospectSchemaTool`, `SearchKnowledgeTool`, `RunSqlTool`, `SaveLearningTool`) gather data and execute SQL
5. On SQL errors, the auto-learning system captures patterns for future queries
6. The agent returns a structured response with the answer, SQL, and results

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- [Prism PHP](https://prismphp.com) (installed automatically as a dependency)
- An LLM provider — any provider supported by Prism (OpenAI, Anthropic, Ollama, Gemini, Mistral, xAI, etc.)
- Optional: Livewire 3.x for the chat UI
- Optional: PostgreSQL with pgvector for semantic similarity search via vector embeddings
