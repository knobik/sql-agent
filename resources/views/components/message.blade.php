@props([
    'role' => 'user',
    'content' => '',
    'queries' => null,
    'metadata' => null,
    'messageId' => null,
    'isStreaming' => false,
])

@php
    $isUser = $role === 'user' || $role === \Knobik\SqlAgent\Enums\MessageRole::User;
    $isAssistant = $role === 'assistant' || $role === \Knobik\SqlAgent\Enums\MessageRole::Assistant;
    $debugEnabled = config('sql-agent.debug.enabled', false);
    $hasPrompt = $debugEnabled && isset($metadata['prompt']);
    $usage = $metadata['usage'] ?? null;
    $truncated = $metadata['truncated'] ?? false;
    $hasQueries = !empty($queries);
    $queryCount = $hasQueries ? count($queries) : 0;
    $isSingleQuery = $queryCount === 1;
@endphp

<div class="flex gap-4 {{ $isUser ? 'justify-end' : 'justify-start' }}">
    @if($isAssistant)
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-sm">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        </div>
    @endif

    <div class="max-w-[80%] {{ $isUser ? 'order-first' : '' }}">
        <div class="rounded-2xl px-5 py-4 {{ $isUser ? 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-md shadow-primary-500/20' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm' }}">
            <div class="markdown-content {{ $isStreaming ? 'stream-cursor' : '' }} {{ $isUser ? 'text-white [&_code]:bg-white/20 [&_code]:text-white' : 'text-gray-700 dark:text-gray-200' }}" x-data x-html="marked.parse(@js($content))"></div>
        </div>

        @if($isAssistant && $truncated)
            <div class="mt-2 flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <span>Response truncated — the model reached its token limit. Consider increasing <code class="px-1 py-0.5 bg-amber-100 dark:bg-amber-900/40 rounded text-[11px] font-mono">SQL_AGENT_LLM_MAX_TOKENS</code>.</span>
            </div>
        @endif

        @if($isAssistant && ($hasQueries || $hasPrompt || $usage))
            <div x-data="{
                showPrompt: false,
                showQueries: false,
                expandedQueries: {},
                queryResults: {},
                loadingQuery: null,
                queryErrors: {},
                toggleSql(index) {
                    this.expandedQueries[index] = !this.expandedQueries[index];
                },
                async executeQuery(index) {
                    this.loadingQuery = index;
                    this.queryErrors[index] = null;
                    try {
                        const response = await fetch('{{ route("sql-agent.query.execute") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ message_id: {{ (int) $messageId }}, query_index: index }),
                        });
                        if (!response.ok) {
                            const errorData = await response.json().catch(() => null);
                            throw new Error(errorData?.message || `HTTP ${response.status}`);
                        }
                        this.queryResults[index] = await response.json();
                    } catch (e) {
                        this.queryErrors[index] = e.message;
                    } finally {
                        this.loadingQuery = null;
                    }
                }
            }" class="mt-3">
                <div class="flex flex-wrap items-center gap-2">
                    @if($usage)
                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            {{ number_format($usage['prompt_tokens'] ?? 0) }} in / {{ number_format($usage['completion_tokens'] ?? 0) }} out{{ ($usage['cache_read_input_tokens'] ?? null) ? ' · ' . number_format($usage['cache_read_input_tokens']) . ' cached' : '' }}
                        </span>
                    @endif

                    @if($hasQueries && $isSingleQuery)
                        <button
                            @click="toggleSql(0)"
                            class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 font-medium transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span x-text="expandedQueries[0] ? 'Hide SQL' : 'Show SQL'"></span>
                        </button>
                        <button
                            @click="executeQuery(0)"
                            :disabled="loadingQuery === 0"
                            class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 text-primary-700 dark:text-primary-300 font-medium transition-colors disabled:opacity-50"
                        >
                            <template x-if="loadingQuery === 0">
                                <x-sql-agent::spinner class="w-3.5 h-3.5" />
                            </template>
                            <template x-if="loadingQuery !== 0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                            <span x-text="queryResults[0] ? 'Re-execute' : 'Execute'"></span>
                        </button>
                    @elseif($hasQueries)
                        <button
                            @click="showQueries = !showQueries"
                            class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 font-medium transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span x-text="showQueries ? 'Hide Queries ({{ $queryCount }})' : 'Show Queries ({{ $queryCount }})'"></span>
                        </button>
                    @endif

                    @if($hasPrompt)
                        <button
                            @click="showPrompt = !showPrompt"
                            class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-300 font-medium transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z" />
                            </svg>
                            <span x-text="showPrompt ? 'Hide Prompt' : 'Debug: Show Prompt'"></span>
                        </button>
                    @endif
                </div>

                {{-- Single query panels --}}
                @if($hasQueries && $isSingleQuery)
                    <div x-show="expandedQueries[0]" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3">
                        <x-sql-agent::sql-preview :sql="$queries[0]['sql']" />
                    </div>

                    {{-- Query error --}}
                    <template x-if="queryErrors[0]">
                        <div class="mt-3 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3">
                            <div class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span x-text="queryErrors[0]"></span>
                            </div>
                        </div>
                    </template>

                @endif

                {{-- Multiple queries --}}
                @if($hasQueries && !$isSingleQuery)
                    <div x-show="showQueries" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3 space-y-2">
                        @foreach($queries as $index => $query)
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-2.5">
                                    <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                                        Query {{ $index + 1 }}
                                        @if($query['connection'] ?? null)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 text-[11px] font-medium">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                                                </svg>
                                                {{ $query['connection'] }}
                                            </span>
                                        @endif
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="toggleSql({{ $index }})"
                                            class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-lg bg-white dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 font-medium transition-colors border border-gray-200 dark:border-gray-600"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                            </svg>
                                            <span x-text="expandedQueries[{{ $index }}] ? 'Hide SQL' : 'Show SQL'"></span>
                                        </button>
                                        <button
                                            @click="executeQuery({{ $index }})"
                                            :disabled="loadingQuery === {{ $index }}"
                                            class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-lg bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 text-primary-700 dark:text-primary-300 font-medium transition-colors border border-primary-200 dark:border-primary-800 disabled:opacity-50"
                                        >
                                            <template x-if="loadingQuery === {{ $index }}">
                                                <x-sql-agent::spinner class="w-3 h-3" />
                                            </template>
                                            <template x-if="loadingQuery !== {{ $index }}">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </template>
                                            <span x-text="queryResults[{{ $index }}] ? 'Re-execute' : 'Execute'"></span>
                                        </button>
                                    </div>
                                </div>

                                <div x-show="expandedQueries[{{ $index }}]" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                                    <x-sql-agent::sql-preview :sql="$query['sql']" class="rounded-none border-x-0 border-b-0 shadow-none" />
                                </div>

                                {{-- Query error --}}
                                <template x-if="queryErrors[{{ $index }}]">
                                    <div class="px-4 py-3 border-t border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20">
                                        <div class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span x-text="queryErrors[{{ $index }}]"></span>
                                        </div>
                                    </div>
                                </template>

                                {{-- Query results rendered via Alpine --}}
                                <template x-if="queryResults[{{ $index }}] && queryResults[{{ $index }}].rows">
                                    <div class="border-t border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                                            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Results</span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500" x-text="'(' + queryResults[{{ $index }}].row_count + ' of ' + queryResults[{{ $index }}].total_rows + ' rows' + (queryResults[{{ $index }}].truncated ? ', truncated' : '') + ')'"></span>
                                        </div>
                                        <div class="overflow-x-auto custom-scrollbar max-h-96">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                                                    <tr>
                                                        <template x-for="col in Object.keys(queryResults[{{ $index }}].rows[0] || {})" :key="col">
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap border-b border-gray-200 dark:border-gray-700" x-text="col"></th>
                                                        </template>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                                                    <template x-for="(row, rowIdx) in queryResults[{{ $index }}].rows.slice(0, 100)" :key="rowIdx">
                                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                            <template x-for="col in Object.keys(queryResults[{{ $index }}].rows[0] || {})" :key="col">
                                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap max-w-xs truncate">
                                                                    <span x-text="row[col] === null ? 'NULL' : (typeof row[col] === 'object' ? JSON.stringify(row[col]) : String(row[col]).substring(0, 50))"></span>
                                                                </td>
                                                            </template>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Single query results (rendered via Alpine for dynamic data) --}}
                @if($hasQueries && $isSingleQuery)
                    <template x-if="queryResults[0] && queryResults[0].rows && queryResults[0].rows.length > 0">
                        <div class="mt-3">
                            <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                                <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Results</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500" x-text="'(' + queryResults[0].row_count + ' of ' + queryResults[0].total_rows + ' rows' + (queryResults[0].truncated ? ', truncated' : '') + ')'"></span>
                                </div>
                                <div class="overflow-x-auto custom-scrollbar max-h-96">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                                            <tr>
                                                <template x-for="col in Object.keys(queryResults[0].rows[0] || {})" :key="col">
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap border-b border-gray-200 dark:border-gray-700" x-text="col"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                                            <template x-for="(row, rowIdx) in queryResults[0].rows.slice(0, 100)" :key="rowIdx">
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                    <template x-for="col in Object.keys(queryResults[0].rows[0] || {})" :key="col">
                                                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap max-w-xs truncate">
                                                            <span x-text="row[col] === null ? 'NULL' : (typeof row[col] === 'object' ? JSON.stringify(row[col]) : String(row[col]).substring(0, 50))"></span>
                                                        </td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                @endif

                @if($hasPrompt)
                    <div x-show="showPrompt" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3">
                        <x-sql-agent::prompt-preview :prompt="$metadata['prompt'] ?? []" :metadata="$metadata" />
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($isUser)
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </div>
    @endif
</div>
