@props([
    'role' => 'user',
    'content' => '',
    'sql' => null,
    'results' => null,
    'isStreaming' => false,
])

@php
    $isUser = $role === 'user' || $role === \Knobik\SqlAgent\Enums\MessageRole::User;
    $isAssistant = $role === 'assistant' || $role === \Knobik\SqlAgent\Enums\MessageRole::Assistant;
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

        @if($isAssistant && ($sql || $results))
            <div x-data="{ showSql: false, showResults: false }" class="mt-3">
                <div class="flex flex-wrap gap-2">
                    @if($sql)
                        <button
                            @click="showSql = !showSql"
                            class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 font-medium transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span x-text="showSql ? 'Hide Final SQL' : 'Show Final SQL'"></span>
                        </button>
                    @endif

                    @if($results && count($results) > 0)
                        <button
                            @click="showResults = !showResults"
                            class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 font-medium transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <span x-text="showResults ? 'Hide Results' : 'Results (' + {{ count($results) }} + ')'"></span>
                        </button>
                    @endif
                </div>

                @if($sql)
                    <div x-show="showSql" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3">
                        <x-sql-agent::sql-preview :sql="$sql" />
                    </div>
                @endif

                @if($results && count($results) > 0)
                    <div x-show="showResults" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3">
                        <x-sql-agent::results-table :results="$results" />
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
