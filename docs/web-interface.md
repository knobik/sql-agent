# Web Interface

SqlAgent includes a ready-to-use Livewire chat interface.

## Accessing the UI

By default, the UI is available at `/sql-agent` and protected by `web` and `auth` middleware.

## Customizing Routes

In `config/sql-agent.php`:

```php
'ui' => [
    'enabled' => true,
    'route_prefix' => 'admin/sql-agent',  // Change the URL prefix
    'middleware' => ['web', 'auth', 'admin'],  // Add custom middleware
],
```

## Disabling the UI

```php
'ui' => [
    'enabled' => false,
],
```

Or via environment:

```env
SQL_AGENT_UI_ENABLED=false
```

## Customizing Views

Publish the views:

```bash
php artisan vendor:publish --tag=sql-agent-views
```

Views will be published to `resources/views/vendor/sql-agent/`.

## Using the Livewire Components Directly

### Chat Component

```blade
<livewire:sql-agent-chat />

{{-- With a specific conversation --}}
<livewire:sql-agent-chat :conversation-id="$conversationId" />
```

### Conversation List Component

```blade
<livewire:sql-agent-conversation-list />
```

Displays a list of previous conversations for the current user.

## Exporting Conversations

Conversations can be exported as JSON or CSV via dedicated routes:

| Route | Description |
|-------|-------------|
| `GET /sql-agent/export/{conversation}/json` | Download conversation as JSON |
| `GET /sql-agent/export/{conversation}/csv` | Download conversation as CSV |

These routes are named `sql-agent.export.json` and `sql-agent.export.csv` and share the same middleware as the UI.

## Streaming (SSE)

The chat interface uses Server-Sent Events (SSE) for real-time streaming. The streaming endpoint (`POST /sql-agent/stream`) returns `text/event-stream` responses with the following event types:

| Event | Data | Description |
|-------|------|-------------|
| `conversation` | `{"id": 123}` | Sent first with the conversation ID |
| `thinking` | `{"thinking": "..."}` | LLM reasoning chunks (when thinking mode is enabled) |
| `content` | `{"text": "..."}` | Response text chunks |
| `done` | `{"sql": "...", "hasResults": true, "resultCount": 5}` | Sent when streaming completes |
| `error` | `{"message": "..."}` | Sent if an error occurs |

## Debug Mode

When debug mode is enabled, SqlAgent stores additional metadata alongside each assistant message in the `sql_agent_messages` table. This is useful for development and troubleshooting but should be disabled in production.

```env
SQL_AGENT_DEBUG=true
```

### What gets stored

With debug mode on, each assistant message's `metadata` JSON column will include:

| Key | Description |
|-----|-------------|
| `prompt.system` | The full system prompt sent to the LLM (including rendered context) |
| `prompt.tools` | List of tool names available to the agent |
| `prompt.tools_full` | Full JSON schema for each tool |
| `iterations` | Every tool-calling iteration: tool calls, arguments, results, and LLM responses |
| `timing.total_ms` | Wall-clock time for the entire request |
| `thinking` | The LLM's internal reasoning (for models with thinking mode) |

### Web UI

When debug mode is active, each assistant message in the chat UI shows a **"Debug: Show Prompt"** button that expands a panel with tabs for:

- **Prompt** -- System prompt and available tools
- **Iterations** -- Step-by-step tool calls and results
- **Tools Schema** -- Full JSON schema sent to the LLM
- **Thinking** -- LLM reasoning (when available)

### Storage considerations

Debug metadata can add significant size to the `metadata` column (roughly 50-60 KB per message depending on schema complexity and iteration count). Keep this in mind for long-running conversations and consider periodically pruning old conversations if storage is a concern.
