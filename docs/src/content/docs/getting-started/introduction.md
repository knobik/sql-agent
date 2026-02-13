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

Before the LLM sees the question, the `ContextBuilder` retrieves and assembles six context layers:

| # | Layer | What it contains | Source |
|---|-------|-----------------|--------|
| 1 | Table Usage | Schema, columns, relationships | `knowledge/tables/*.json` |
| 2 | Human Annotations | Metrics, definitions, business rules | `knowledge/business/*.json` |
| 3 | Query Patterns | SQL known to work | `knowledge/queries/*.json` and `*.sql` |
| 4 | Learnings | Error patterns and discovered fixes | `save_learning` tool (on-demand) |
| 5 | Runtime Context | Live schema inspection | `introspect_schema` tool (on-demand) |
| 6 | Institutional Knowledge | Docs, wikis, external references | [Custom tools](/sql-agent/guides/custom-tools/) (`agent.tools` config) |

Layers 1–3 are loaded from the [Knowledge Base](/sql-agent/guides/knowledge-base/) and assembled into the system prompt automatically. Layer 4 is built up over time as the agent learns from errors. Layers 5 and 6 are available on-demand — the LLM calls them during the [tool loop](#agentic-tool-loop) when it needs live schema details or external context.

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
