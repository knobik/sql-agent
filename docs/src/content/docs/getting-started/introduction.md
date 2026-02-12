---
title: Introduction
description: Overview of SQL Agent for Laravel and how it works.
sidebar:
  order: 1
---

SQL Agent for Laravel is a self-learning text-to-SQL agent that converts natural language questions into SQL queries using LLMs.

This package is based on [Dash](https://github.com/agno-agi/dash) and [OpenAI's in-house data agent](https://openai.com/index/inside-our-in-house-data-agent/).

## How It Works

SqlAgent converts questions to SQL through multi-layer context assembly, an agentic tool-calling loop, and a self-learning feedback system. Each query goes through three phases.

### Context Assembly

Before the LLM sees the question, the `ContextBuilder` retrieves and assembles five context layers into the system prompt:

- **Semantic model** — Table metadata, column descriptions, and relationships from your [Knowledge Base](/sql-agent/guides/knowledge-base/)
- **Business rules** — Metrics definitions, domain rules, and common gotchas
- **Similar query patterns** — Previously validated queries that match the current question, retrieved via the active [search driver](/sql-agent/guides/drivers/)
- **Relevant learnings** — Patterns the agent discovered from past errors and corrections
- **Runtime schema** — Live database introspection for the tables most likely relevant to the question

This happens automatically on every query — no manual prompt engineering required.

### Agentic Tool Loop

The LLM doesn't just receive context and generate one response. It enters an iterative tool-calling loop where it can:

- **Search for more knowledge** (`search_knowledge`) — Find additional query patterns and learnings mid-conversation
- **Inspect live schema** (`introspect_schema`) — Check actual table structures, column types, and indexes
- **Execute SQL** (`run_sql`) — Run queries, observe results, and refine if needed
- **Save discoveries** (`save_learning`, `save_validated_query`) — Record what it learns for future queries

If a query fails, the agent can diagnose the error, fix the SQL, and try again — all within the same loop.

### Self-Learning Feedback

The agent improves with use through two mechanisms:

- **Error-based learning** — When a query fails and the agent recovers, it saves the error pattern and fix as a learning. Future queries that touch the same tables or patterns will have this context automatically.
- **Query pattern saving** — When a query successfully answers a question, the agent saves it as a reusable pattern. Future similar questions can reference the proven SQL directly.

Both feed back into the context assembly phase, so the agent's knowledge grows organically over time. See the [Self-Learning](/sql-agent/guides/self-learning/) guide for details.

:::tip
The more questions the agent handles, the richer its context becomes for future queries. Common questions get faster and more reliable as validated patterns accumulate, and past mistakes are avoided as learnings build up.
:::

This architecture follows a retrieval-augmented generation (RAG) pattern — but with multiple retrieval layers and a self-improving feedback loop.

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- [Prism PHP](https://prismphp.com) (installed automatically as a dependency)
- An LLM provider — any provider supported by Prism (OpenAI, Anthropic, Ollama, Gemini, Mistral, xAI, etc.)
- Optional: Livewire 3.x for the chat UI
- Optional: [`pgvector/pgvector`](https://github.com/pgvector/pgvector-php) package + PostgreSQL with pgvector for semantic similarity search
