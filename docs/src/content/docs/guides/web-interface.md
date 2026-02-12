---
title: Web Interface
description: Livewire chat UI, streaming, debug mode, and result exports.
sidebar:
  order: 5
---

SqlAgent ships with a ready-to-use Livewire chat interface that you can drop into any Laravel application. It provides a conversational UI for asking questions, viewing SQL results, and browsing conversation history.

## Accessing the UI

By default, the web interface is available at `/sql-agent` and protected by `web` and `auth` middleware:

```
http://your-app.test/sql-agent
```

## Customizing Routes

You may change the URL prefix and middleware in `config/sql-agent.php`:

```php
'ui' => [
    'enabled' => true,
    'route_prefix' => 'admin/sql-agent',
    'middleware' => ['web', 'auth', 'admin'],
],
```

## Disabling the UI

To disable the web interface entirely, set `enabled` to `false` in config or via environment:

```ini
SQL_AGENT_UI_ENABLED=false
```

## Customizing Views

To customize the look and feel, publish the views:

```bash
php artisan vendor:publish --tag=sql-agent-views
```

The views will be published to `resources/views/vendor/sql-agent/` where you can modify them freely.

## Livewire Components

You may use the Livewire components directly in your own Blade templates:

**Chat Component**

```blade
<livewire:sql-agent-chat />

{{-- With a specific conversation --}}
<livewire:sql-agent-chat :conversation-id="$conversationId" />
```

**Conversation List**

```blade
<livewire:sql-agent-conversation-list />
```

Displays a searchable list of previous conversations for the current user.

## Exporting Results

Each result table in the chat interface includes **CSV** and **JSON** export buttons in the header bar. Clicking a button downloads the full result set (all rows, not just the current page) directly from the browser — no server round-trip required.

## Streaming (SSE)

The chat interface uses Server-Sent Events for real-time streaming. The streaming endpoint (`POST /sql-agent/stream`) returns `text/event-stream` responses with the following event types:

| Event | Data | Description |
|-------|------|-------------|
| `conversation` | `{"id": 123}` | Sent first with the conversation ID |
| `thinking` | `{"thinking": "..."}` | LLM reasoning chunks (when thinking mode is enabled) |
| `content` | `{"text": "..."}` | Response text chunks |
| `done` | `{"queryCount": 2}` | Sent when streaming completes |
| `error` | `{"message": "..."}` | Sent if an error occurs |

## Debug Mode

When debug mode is enabled, SqlAgent stores detailed metadata alongside each assistant message. This is invaluable during development and troubleshooting:

```ini
SQL_AGENT_DEBUG=true
```

### Stored Metadata

Each assistant message's `metadata` JSON column will include:

| Key | Description |
|-----|-------------|
| `prompt.system` | The full system prompt sent to the LLM (including rendered context) |
| `prompt.tools` | List of tool names available to the agent |
| `prompt.tools_full` | Full JSON schema for each tool |
| `iterations` | Every tool-calling iteration: tool calls, arguments, results, and LLM responses |
| `timing.total_ms` | Wall-clock time for the entire request |
| `thinking` | The LLM's internal reasoning (for models with thinking mode) |

### Debug Panel

When debug mode is active, each assistant message in the chat UI shows a **"Debug: Show Prompt"** button that expands a panel with tabs for:

- **Prompt** — System prompt and available tools
- **Iterations** — Step-by-step tool calls and results
- **Tools Schema** — Full JSON schema sent to the LLM
- **Thinking** — LLM reasoning (when available)

### Storage Considerations

Debug metadata can add roughly 50–60 KB per message depending on schema complexity and iteration count. Keep this in mind for long-running conversations and consider periodically purging old conversations if storage is a concern.

:::caution
Debug mode should be disabled in production due to the significant storage overhead and the sensitive information stored in metadata (full system prompts, query results, etc.).
:::
