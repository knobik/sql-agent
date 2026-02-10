<div class="flex-1 flex flex-col h-full overflow-hidden bg-white dark:bg-gray-800"
     x-data="chatStream()"
     x-init="init()"
     @copy-to-clipboard.window="navigator.clipboard.writeText($event.detail.text)"
>
    {{-- Header --}}
    <header class="flex-shrink-0 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $this->conversation?->title ?? 'New Conversation' }}
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">SQL Agent</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- Connection Selector --}}
                <x-sql-agent::connection-selector
                    x-bind:disabled="isStreaming"
                    :connections="$this->connections"
                    :selected="$connection"
                />

                {{-- Dark Mode Toggle --}}
                <button
                    @click="darkMode = !darkMode"
                    class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition-colors"
                    title="Toggle dark mode"
                >
                    <svg x-show="!darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg x-show="darkMode" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </button>

                {{-- Export Menu --}}
                @if($conversationId)
                    <div x-data="{ open: false }" class="relative">
                        <button
                            @click="open = !open"
                            @click.away="open = false"
                            class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition-colors"
                            title="Export"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 py-1 z-50"
                        >
                            <a
                                href="{{ route('sql-agent.export.json', $conversationId) }}"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Export as JSON
                            </a>
                            <a
                                href="{{ route('sql-agent.export.csv', $conversationId) }}"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Export as CSV
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- Messages Area --}}
    <div id="messages-container" class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6 bg-gray-50 dark:bg-gray-900">
        @if(empty($this->messages))
            {{-- Empty State (hidden when streaming) --}}
            <div x-show="!showStreamingUI" x-cloak class="flex flex-col items-center justify-center h-full text-center px-4">
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center mb-6 shadow-lg shadow-primary-500/25">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    Ask a question about your data
                </h2>
                <p class="text-gray-500 dark:text-gray-400 max-w-md mb-8">
                    I can help you query your database using natural language. Try asking something like:
                </p>
                <div class="w-full max-w-lg space-y-3">
                    <button
                        @click="messageInput = 'Show me the top 10 customers by total orders'"
                        class="group w-full p-4 text-left bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-xl text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700 transition-all shadow-sm hover:shadow"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 group-hover:bg-primary-100 dark:group-hover:bg-primary-900/50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <span class="font-medium">"Show me the top 10 customers by total orders"</span>
                        </div>
                    </button>
                    <button
                        @click="messageInput = 'What were the sales trends last month?'"
                        class="group w-full p-4 text-left bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-xl text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700 transition-all shadow-sm hover:shadow"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 group-hover:bg-primary-100 dark:group-hover:bg-primary-900/50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                            <span class="font-medium">"What were the sales trends last month?"</span>
                        </div>
                    </button>
                    <button
                        @click="messageInput = 'How many users signed up this week?'"
                        class="group w-full p-4 text-left bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-xl text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700 transition-all shadow-sm hover:shadow"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 group-hover:bg-primary-100 dark:group-hover:bg-primary-900/50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                            </div>
                            <span class="font-medium">"How many users signed up this week?"</span>
                        </div>
                    </button>
                </div>
            </div>
        @endif

        {{-- Message History --}}
        @foreach($this->messages as $msg)
            <x-sql-agent::message
                :role="$msg['role']"
                :content="$msg['content']"
                :sql="$msg['sql'] ?? null"
                :results="$msg['results'] ?? null"
                :metadata="$msg['metadata'] ?? null"
            />
        @endforeach

        {{-- User's pending message (shown while streaming) --}}
        <template x-if="showStreamingUI && pendingUserMessage">
            <div class="flex gap-4 justify-end">
                <div class="max-w-[80%]">
                    <div class="rounded-2xl px-5 py-4 bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-md shadow-primary-500/20">
                        <div class="markdown-content text-white" x-text="pendingUserMessage"></div>
                    </div>
                </div>
                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            </div>
        </template>

        {{-- Streaming Response --}}
        <template x-if="showStreamingUI">
            <div class="flex gap-4 justify-start">
                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="max-w-[80%]">
                    <div class="rounded-2xl px-5 py-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                        {{-- Rendered content --}}
                        <div x-ref="streamContent" class="markdown-content text-gray-700 dark:text-gray-200" :class="{ 'stream-cursor': isStreaming && hasRealText }"></div>
                        {{-- Thinking indicator (no content yet) --}}
                        <div x-show="isStreaming && !streamedContent" class="flex items-center gap-2 text-gray-400 dark:text-gray-500">
                            <x-sql-agent::spinner />
                            <span class="italic">Thinking...</span>
                        </div>
                        {{-- Generating indicator (tools shown but no text yet) --}}
                        <div x-show="isStreaming && streamedContent && !hasRealText" class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mt-3">
                            <x-sql-agent::spinner />
                            <span class="italic">Generating response...</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Input Area --}}
    <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 sm:p-6">
        <form @submit.prevent="sendMessage" class="max-w-4xl mx-auto">
            <div class="flex gap-3 items-start">
                <div class="flex-1 relative">
                    <textarea
                        x-model="messageInput"
                        x-ref="messageTextarea"
                        placeholder="Ask a question about your data..."
                        rows="1"
                        class="w-full h-12 px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none disabled:opacity-50 shadow-sm transition-shadow focus:shadow-md overflow-hidden"
                        @keydown.enter="if (!event.shiftKey) { event.preventDefault(); sendMessage(); }"
                        :disabled="isStreaming"
                        x-effect="
                            $el.style.height = '48px';
                            $el.style.height = Math.min($el.scrollHeight, 200) + 'px';
                            $el.style.overflowY = $el.scrollHeight > 200 ? 'auto' : 'hidden';
                        "
                    ></textarea>
                </div>

                {{-- Send button (shown when not streaming) --}}
                <button
                    x-show="!isStreaming"
                    type="submit"
                    :disabled="!messageInput.trim()"
                    class="h-12 px-5 bg-primary-500 hover:bg-primary-600 disabled:bg-gray-300 dark:disabled:bg-gray-700 text-white rounded-xl font-semibold transition-all flex items-center justify-center gap-2 disabled:cursor-not-allowed shadow-sm hover:shadow-md disabled:shadow-none"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                    </svg>
                    <span class="hidden sm:inline">Send</span>
                </button>

                {{-- Cancel button (shown during streaming) --}}
                <button
                    x-show="isStreaming"
                    x-cloak
                    type="button"
                    @click="cancelStream()"
                    class="h-12 px-5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-semibold transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-md"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span class="hidden sm:inline">Cancel</span>
                </button>
            </div>

            <div class="mt-3 flex items-center justify-center gap-4 text-xs text-gray-400 dark:text-gray-500">
                <span class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-gray-500 dark:text-gray-400 font-mono text-[10px]">Enter</kbd>
                    <span>to send</span>
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-gray-500 dark:text-gray-400 font-mono text-[10px]">Shift</kbd>
                    <span>+</span>
                    <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-gray-500 dark:text-gray-400 font-mono text-[10px]">Enter</kbd>
                    <span>for new line</span>
                </span>
            </div>
        </form>
    </div>
</div>

<script>
function chatStream() {
    return {
        messageInput: '',
        isStreaming: false,
        isFinishing: false, // True while waiting for Livewire refresh
        streamedContent: '',
        pendingUserMessage: '',
        conversationId: @json($conversationId),
        connection: @json($connection),
        abortController: null,

        // Show streaming UI while streaming or finishing
        get showStreamingUI() {
            return this.isStreaming || this.isFinishing;
        },

        // Check if there's actual text content (not just tool tags)
        get hasRealText() {
            if (!this.streamedContent) return false;
            // Remove tool tags and check if there's remaining text
            const withoutTools = this.streamedContent.replace(/<tool[^>]*>.*?<\/tool>/gs, '').trim();
            return withoutTools.length > 0;
        },

        init() {
            // Scroll to bottom on initial load
            this.$nextTick(() => this.scrollToBottomInstant());

            // Listen for new-conversation event to reset Alpine state
            Livewire.on('new-conversation', () => {
                this.conversationId = null;
                this.streamedContent = '';
                this.pendingUserMessage = '';
            });

            // Listen for load-conversation event to sync Alpine state
            Livewire.on('load-conversation', ([{conversationId}]) => {
                this.conversationId = conversationId;
            });
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = document.getElementById('messages-container');
                if (container) {
                    container.scrollTo({
                        top: container.scrollHeight,
                        behavior: 'smooth'
                    });
                }
            });
        },

        scrollToBottomInstant() {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        async sendMessage() {
            const message = this.messageInput.trim();
            if (!message || this.isStreaming) return;

            this.pendingUserMessage = message;
            this.messageInput = '';
            this.isStreaming = true;
            this.streamedContent = '';
            this.abortController = new AbortController();

            this.scrollToBottom();

            try {
                const response = await fetch('{{ route("sql-agent.stream") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'text/event-stream',
                    },
                    body: JSON.stringify({
                        message: message,
                        conversation_id: this.conversationId,
                        connection: this.connection,
                    }),
                    signal: this.abortController.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        if (line.startsWith('event: ')) continue;
                        if (line.startsWith('data: ')) {
                            const data = JSON.parse(line.slice(6));
                            this.handleEvent(data);
                        }
                    }
                }
            } catch (error) {
                // Check if this was a user-initiated cancellation
                if (error.name === 'AbortError') {
                    console.log('Stream cancelled by user');
                    // Reset state without showing error
                    this.isStreaming = false;
                    this.isFinishing = false;
                    this.streamedContent = '';
                    this.pendingUserMessage = '';
                    this.abortController = null;
                    // Refresh to show any saved messages
                    await this.$wire.$refresh();
                    return;
                }
                console.error('Stream error:', error);
                this.streamedContent = 'An error occurred while processing your request.';
                this.renderContent();
            }

            // Mark streaming as done but keep UI visible while refreshing
            this.isStreaming = false;
            this.isFinishing = true;
            this.pendingUserMessage = '';

            // Refresh Livewire component to show saved messages from database
            await this.$wire.$refresh();

            // Now hide the streaming UI (messages are loaded)
            this.isFinishing = false;
            this.streamedContent = '';
            this.abortController = null;
        },

        cancelStream() {
            if (this.abortController) {
                this.abortController.abort();
                this.abortController = null;
            }
            this.isStreaming = false;
            this.isFinishing = false;
            this.streamedContent = '';
            this.pendingUserMessage = '';
        },

        handleEvent(data) {
            if (data.id !== undefined) {
                // Conversation event
                this.conversationId = data.id;
                this.$wire.conversationId = data.id;
                this.$dispatch('conversation-updated');
            } else if (data.thinking !== undefined) {
                // Thinking event - ignored in UI (only saved for debug panel)
            } else if (data.text !== undefined) {
                // Content event
                this.streamedContent += data.text;
                this.renderContent();
                this.scrollToBottom();
            } else if (data.message !== undefined) {
                // Error event
                this.streamedContent = 'Error: ' + data.message;
                this.renderContent();
            } else if (data.truncated) {
                // Done event with truncation — model hit max_tokens
                this.streamedContent += '\n\n> **Warning:** The response was cut short because the model reached its token limit. You can increase `SQL_AGENT_LLM_MAX_TOKENS` in your configuration.';
                this.renderContent();
                this.scrollToBottom();
            }
        },

        renderContent() {
            if (!this.$refs.streamContent) return;

            // Parse markdown
            this.$refs.streamContent.innerHTML = marked.parse(this.streamedContent);

            // Group tool tags
            this.groupToolTags(this.$refs.streamContent);
        },

        groupToolTags(container) {
            const tools = container.querySelectorAll('tool:not(.tool-container tool)');
            if (tools.length === 0) return;

            let toolContainer = container.querySelector('.tool-container');
            if (!toolContainer) {
                toolContainer = document.createElement('div');
                toolContainer.className = 'tool-container';
                const firstTool = tools[0];
                firstTool.parentNode.insertBefore(toolContainer, firstTool);
            }

            tools.forEach(tool => {
                let prev = tool.previousSibling;
                while (prev && prev.nodeType === 3 && prev.textContent.trim() === '') {
                    const toRemove = prev;
                    prev = prev.previousSibling;
                    toRemove.remove();
                }
                let next = tool.nextSibling;
                while (next && ((next.nodeType === 3 && next.textContent.trim() === '') || next.tagName === 'BR')) {
                    const toRemove = next;
                    next = next.nextSibling;
                    toRemove.remove();
                }
                toolContainer.appendChild(tool);
            });
        }
    };
}
</script>
