@props(['prompt', 'metadata' => null])

@php
    // Support both old (prompt only) and new (full metadata) usage
    $debugData = $metadata ?? ['prompt' => $prompt];
    $promptData = $debugData['prompt'] ?? $prompt ?? [];
    $iterations = $debugData['iterations'] ?? [];
    $chunks = $debugData['chunks'] ?? [];
    $timing = $debugData['timing'] ?? [];
    $errorDetails = $debugData['error_details'] ?? null;
    $toolsFull = $promptData['tools_full'] ?? [];
    $thinking = $debugData['thinking'] ?? null;
@endphp

<div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 overflow-hidden">
    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-2.5 bg-amber-100 dark:bg-amber-900/40 border-b border-amber-200 dark:border-amber-800">
        <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z" />
            </svg>
            <span class="text-sm font-semibold">Debug: Full LLM Prompt</span>
        </div>
        <div class="flex items-center gap-3 text-xs text-amber-600 dark:text-amber-400">
            @if(!empty($timing['total_ms']))
                <span>{{ $timing['total_ms'] }}ms</span>
            @endif
            <span>{{ count($promptData['messages'] ?? []) }} messages</span>
            @if(count($iterations) > 0)
                <span>{{ count($iterations) }} iterations</span>
            @endif
        </div>
    </div>

    {{-- Content --}}
    <div x-data="{ activeTab: 'prompt' }" class="flex flex-col">
        {{-- Tabs --}}
        <div class="flex border-b border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30">
            <button
                @click="activeTab = 'prompt'"
                :class="activeTab === 'prompt' ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-2 text-xs font-medium border-b-2 transition-colors"
            >
                Prompt
            </button>
            @if(count($iterations) > 0)
                <button
                    @click="activeTab = 'iterations'"
                    :class="activeTab === 'iterations' ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-2 text-xs font-medium border-b-2 transition-colors"
                >
                    Iterations ({{ count($iterations) }})
                </button>
            @endif
            @if(count($chunks) > 0)
                <button
                    @click="activeTab = 'chunks'"
                    :class="activeTab === 'chunks' ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-2 text-xs font-medium border-b-2 transition-colors"
                >
                    Raw Chunks ({{ count($chunks) }})
                </button>
            @endif
            @if(count($toolsFull) > 0)
                <button
                    @click="activeTab = 'tools'"
                    :class="activeTab === 'tools' ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-2 text-xs font-medium border-b-2 transition-colors"
                >
                    Tools Schema ({{ count($toolsFull) }})
                </button>
            @endif
            @if($thinking)
                <button
                    @click="activeTab = 'thinking'"
                    :class="activeTab === 'thinking' ? 'border-purple-500 text-purple-700 dark:text-purple-300' : 'border-transparent text-purple-500 hover:text-purple-700 dark:hover:text-purple-300'"
                    class="px-4 py-2 text-xs font-medium border-b-2 transition-colors"
                >
                    Thinking
                </button>
            @endif
            @if($errorDetails)
                <button
                    @click="activeTab = 'error'"
                    :class="activeTab === 'error' ? 'border-red-500 text-red-700 dark:text-red-300' : 'border-transparent text-red-500 hover:text-red-700 dark:hover:text-red-300'"
                    class="px-4 py-2 text-xs font-medium border-b-2 transition-colors"
                >
                    Error
                </button>
            @endif
        </div>

        {{-- Tab Content --}}
        <div class="p-4 space-y-4 max-h-[500px] overflow-y-auto custom-scrollbar">
            {{-- Prompt Tab --}}
            <div x-show="activeTab === 'prompt'" class="space-y-4">
                {{-- System Prompt --}}
                @if(!empty($promptData['system']))
                    <div>
                        <div class="text-xs font-semibold text-amber-600 dark:text-amber-400 mb-2 uppercase tracking-wider">System Prompt</div>
                        <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-mono bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-gray-700 max-h-64 overflow-y-auto custom-scrollbar">{{ $promptData['system'] }}</pre>
                    </div>
                @endif

                {{-- Messages --}}
                @if(!empty($promptData['messages']))
                    <div>
                        <div class="text-xs font-semibold text-amber-600 dark:text-amber-400 mb-2 uppercase tracking-wider">Messages</div>
                        <div class="space-y-2">
                            @foreach($promptData['messages'] as $msg)
                                @if(($msg['role'] ?? '') !== 'system')
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-gray-700">
                                        <div class="text-xs font-semibold mb-1 {{ ($msg['role'] ?? '') === 'user' ? 'text-blue-600 dark:text-blue-400' : 'text-green-600 dark:text-green-400' }}">
                                            {{ ucfirst($msg['role'] ?? 'unknown') }}
                                        </div>
                                        <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-mono max-h-32 overflow-y-auto custom-scrollbar">{{ $msg['content'] ?? '' }}</pre>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Tools --}}
                @if(!empty($promptData['tools']))
                    <div>
                        <div class="text-xs font-semibold text-amber-600 dark:text-amber-400 mb-2 uppercase tracking-wider">Available Tools</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($promptData['tools'] as $tool)
                                <span class="text-xs px-2 py-1 rounded bg-white dark:bg-gray-800 border border-amber-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 font-mono">
                                    {{ $tool }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Iterations Tab --}}
            @if(count($iterations) > 0)
                <div x-show="activeTab === 'iterations'" x-cloak class="space-y-3">
                    @foreach($iterations as $index => $iteration)
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                    Iteration {{ $iteration['iteration'] ?? $index + 1 }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ $iteration['finish_reason'] ?? 'unknown' }}
                                </span>
                            </div>

                            @if(!empty($iteration['tool_calls']))
                                <div class="mb-2">
                                    <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tool Calls:</div>
                                    @foreach($iteration['tool_calls'] as $tcIndex => $toolCall)
                                        @php
                                            $toolResult = $iteration['tool_results'][$tcIndex] ?? null;
                                        @endphp
                                        <div class="bg-gray-50 dark:bg-gray-900 rounded p-2 mb-1" x-data="{ showResult: false }">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-mono text-purple-600 dark:text-purple-400">{{ $toolCall['name'] ?? 'unknown' }}</span>
                                                @if($toolResult)
                                                    <button
                                                        @click="showResult = !showResult"
                                                        class="text-xs px-2 py-0.5 rounded {{ ($toolResult['success'] ?? false) ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' }} hover:opacity-80 transition-opacity"
                                                    >
                                                        <span x-text="showResult ? 'Hide Result' : 'Show Result'"></span>
                                                    </button>
                                                @endif
                                            </div>
                                            <pre class="text-xs text-gray-600 dark:text-gray-400 mt-1 whitespace-pre-wrap">{{ json_encode($toolCall['arguments'] ?? [], JSON_PRETTY_PRINT) }}</pre>

                                            @if($toolResult)
                                                <div x-show="showResult" x-cloak x-transition class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                                    <div class="text-xs font-medium mb-1 {{ ($toolResult['success'] ?? false) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                        {{ ($toolResult['success'] ?? false) ? 'Result:' : 'Error:' }}
                                                    </div>
                                                    @if($toolResult['success'] ?? false)
                                                        <pre class="text-xs text-gray-600 dark:text-gray-400 whitespace-pre-wrap max-h-48 overflow-y-auto custom-scrollbar">{{ json_encode($toolResult['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    @else
                                                        <pre class="text-xs text-red-600 dark:text-red-400 whitespace-pre-wrap">{{ $toolResult['error'] ?? 'Unknown error' }}</pre>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($iteration['response']))
                                <div>
                                    <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Response:</div>
                                    <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-mono max-h-32 overflow-y-auto custom-scrollbar">{{ $iteration['response'] }}</pre>
                                </div>
                            @else
                                <div class="text-xs text-gray-400 italic">No text response in this iteration</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Raw Chunks Tab --}}
            @if(count($chunks) > 0)
                <div x-show="activeTab === 'chunks'" x-cloak class="space-y-2">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        Showing {{ count($chunks) }} raw chunks from the LLM stream
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-amber-200 dark:border-gray-700 overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-2 py-1 text-left font-medium text-gray-600 dark:text-gray-400">#</th>
                                    <th class="px-2 py-1 text-left font-medium text-gray-600 dark:text-gray-400">Time (ms)</th>
                                    <th class="px-2 py-1 text-left font-medium text-gray-600 dark:text-gray-400">Content</th>
                                    <th class="px-2 py-1 text-left font-medium text-gray-600 dark:text-gray-400">Flags</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($chunks as $index => $chunk)
                                    <tr class="{{ $chunk['isComplete'] ?? false ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                        <td class="px-2 py-1 text-gray-500">{{ $index + 1 }}</td>
                                        <td class="px-2 py-1 text-gray-500">{{ $chunk['time'] ?? '?' }}</td>
                                        <td class="px-2 py-1 font-mono text-gray-700 dark:text-gray-300 max-w-xs truncate" title="{{ $chunk['content'] ?? $chunk['thinking'] ?? '' }}">
                                            @if(!empty($chunk['content']))
                                                {{ Str::limit($chunk['content'], 50) }}
                                            @elseif(!empty($chunk['thinking']))
                                                <span class="text-purple-600 dark:text-purple-400">{{ Str::limit($chunk['thinking'], 50) }}</span>
                                            @elseif(!empty($chunk['toolCalls']))
                                                <span class="text-amber-600 dark:text-amber-400">[{{ count($chunk['toolCalls']) }} tool call(s)]</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1">
                                            @if($chunk['isComplete'] ?? false)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                                    complete: {{ $chunk['finishReason'] ?? '?' }}
                                                </span>
                                            @endif
                                            @if($chunk['hasThinking'] ?? false)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">
                                                    thinking
                                                </span>
                                            @endif
                                            @if($chunk['hasContent'] ?? false)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                                                    content
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Tools Schema Tab --}}
            @if(count($toolsFull) > 0)
                <div x-show="activeTab === 'tools'" x-cloak class="space-y-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        Full JSON schema sent to the LLM for tool calling
                    </div>
                    @foreach($toolsFull as $tool)
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-purple-600 dark:text-purple-400 font-mono">{{ $tool['name'] ?? 'unknown' }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">{{ $tool['description'] ?? '' }}</div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-500 mb-1">Parameters Schema:</div>
                            <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-mono bg-gray-50 dark:bg-gray-900 rounded p-2 max-h-48 overflow-y-auto custom-scrollbar">{{ json_encode($tool['parameters'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Thinking Tab --}}
            @if($thinking)
                <div x-show="activeTab === 'thinking'" x-cloak class="space-y-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        LLM's internal reasoning process (from models with thinking mode enabled)
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3 border border-purple-200 dark:border-purple-800 max-h-96 overflow-y-auto custom-scrollbar">
                        <div class="prose prose-sm prose-purple dark:prose-invert max-w-none text-purple-800 dark:text-purple-200" x-data x-html="marked.parse(@js($thinking))"></div>
                    </div>
                </div>
            @endif

            {{-- Error Tab --}}
            @if($errorDetails)
                <div x-show="activeTab === 'error'" x-cloak class="space-y-3">
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border border-red-200 dark:border-red-800">
                        <div class="text-xs font-semibold text-red-600 dark:text-red-400 mb-2">Error Message</div>
                        <pre class="text-xs text-red-700 dark:text-red-300 whitespace-pre-wrap font-mono">{{ $errorDetails['message'] ?? 'Unknown error' }}</pre>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-gray-700">
                        <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Location</div>
                        <pre class="text-xs text-gray-700 dark:text-gray-300 font-mono">{{ $errorDetails['file'] ?? '?' }}:{{ $errorDetails['line'] ?? '?' }}</pre>
                    </div>

                    @if(!empty($errorDetails['trace']))
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-gray-700">
                            <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Stack Trace (first 5 frames)</div>
                            <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-mono max-h-48 overflow-y-auto custom-scrollbar">{{ json_encode($errorDetails['trace'], JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
