<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="$watch('darkMode', val => { localStorage.setItem('darkMode', val); document.getElementById('hljs-dark').disabled = !val; document.getElementById('hljs-light').disabled = val; })" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('sql-agent.name', 'SQL Agent') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Figtree', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            200: '#fecaca',
                            300: '#fca5a5',
                            400: '#f87171',
                            500: '#ef4444',
                            600: '#dc2626',
                            700: '#b91c1c',
                            800: '#991b1b',
                            900: '#7f1d1d',
                            950: '#450a0a',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js is included with Livewire 3 -->

    <!-- Highlight.js for SQL syntax highlighting -->
    <link id="hljs-dark" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <link id="hljs-light" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    <script>
        // Immediately set correct hljs theme to avoid flash
        (function() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            document.getElementById('hljs-dark').disabled = !isDark;
            document.getElementById('hljs-light').disabled = isDark;
        })();
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/sql.min.js"></script>

    <!-- Marked.js for Markdown rendering -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #4b5563;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }

        /* Override highlight.js backgrounds */
        pre code.hljs {
            background: transparent !important;
        }

        /* Markdown content styles */
        .markdown-content p { margin-bottom: 0.75rem; line-height: 1.625; }
        .markdown-content p:last-child { margin-bottom: 0; }
        .markdown-content ul, .markdown-content ol { margin-left: 1.5rem; margin-bottom: 0.75rem; }
        .markdown-content ul { list-style-type: disc; }
        .markdown-content ol { list-style-type: decimal; }
        .markdown-content li { margin-bottom: 0.25rem; }
        .markdown-content code:not(pre code) {
            background: #f3f4f6;
            padding: 0.125rem 0.375rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #dc2626;
        }
        .dark .markdown-content code:not(pre code) {
            background: #374151;
            color: #fca5a5;
        }
        .markdown-content pre {
            margin: 0.75rem 0;
            border-radius: 0.5rem;
            overflow-x: auto;
        }
        .markdown-content pre code {
            display: block;
            padding: 1rem;
            font-size: 0.875rem;
            color: inherit;
            background: transparent;
        }
        .markdown-content strong { font-weight: 600; }
        .markdown-content a { color: #dc2626; text-decoration: underline; }
        .markdown-content a:hover { color: #b91c1c; }
        .dark .markdown-content a { color: #fca5a5; }
        .dark .markdown-content a:hover { color: #f87171; }

        /* Table styles */
        .markdown-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .dark .markdown-content table {
            border-color: #374151;
        }
        .markdown-content th {
            background: #f9fafb;
            font-weight: 600;
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }
        .dark .markdown-content th {
            background: #1f2937;
            border-bottom-color: #374151;
        }
        .markdown-content td {
            padding: 0.625rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .dark .markdown-content td {
            border-bottom-color: #374151;
        }
        .markdown-content tr:last-child td {
            border-bottom: none;
        }
        .markdown-content tr:hover td {
            background: #f9fafb;
        }
        .dark .markdown-content tr:hover td {
            background: #1f2937;
        }
        /* Ensure inline code in tables looks good */
        .markdown-content td code:not(pre code) {
            font-size: 0.8125rem;
            word-break: break-all;
        }
        /* Better spacing for h2, h3 headers */
        .markdown-content h1 { font-size: 1.5rem; font-weight: 700; margin: 1.25rem 0 0.75rem 0; }
        .markdown-content h2 { font-size: 1.25rem; font-weight: 600; margin: 1.25rem 0 0.75rem 0; }
        .markdown-content h3 { font-size: 1.125rem; font-weight: 600; margin: 1rem 0 0.5rem 0; }
        .markdown-content h4 { font-size: 1rem; font-weight: 600; margin: 1rem 0 0.5rem 0; }
        .markdown-content h1:first-child,
        .markdown-content h2:first-child,
        .markdown-content h3:first-child,
        .markdown-content h4:first-child { margin-top: 0; }

        /* Tool execution tags - displayed as compact inline pills */
        .markdown-content .tool-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            margin-bottom: 0.75rem;
            padding: 0.625rem 0.75rem;
            background: #f3f4f6;
            border-radius: 0.5rem;
            align-items: center;
        }
        .dark .markdown-content .tool-container {
            background: #1f2937;
        }
        .markdown-content .tool-container::before {
            content: 'Tools:';
            font-size: 0.75rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-right: 0.375rem;
        }
        .dark .markdown-content .tool-container::before {
            color: #6b7280;
        }
        .markdown-content tool {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            padding: 0.375rem 0.75rem;
            background: white;
            color: #4b5563;
            border-radius: 0.5rem;
            font-weight: 500;
            border: 1px solid #e5e7eb;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .dark .markdown-content tool {
            background: #374151;
            color: #9ca3af;
            border-color: #4b5563;
        }
        .markdown-content tool[data-sql] {
            cursor: pointer;
            transition: all 0.15s ease;
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .markdown-content tool[data-sql]:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }
        .dark .markdown-content tool[data-sql] {
            background: rgba(127, 29, 29, 0.3);
            color: #fca5a5;
            border-color: rgba(252, 165, 165, 0.3);
        }
        .dark .markdown-content tool[data-sql]:hover {
            background: rgba(127, 29, 29, 0.5);
            border-color: rgba(252, 165, 165, 0.5);
        }
        .markdown-content tool[data-sql]::after {
            content: 'Show';
            font-size: 0.6875rem;
            padding: 0.125rem 0.375rem;
            margin-left: 0.25rem;
            background: rgba(220, 38, 38, 0.1);
            border-radius: 0.25rem;
            font-weight: 600;
        }
        .markdown-content tool[data-sql].expanded::after {
            content: 'Hide';
        }
        .dark .markdown-content tool[data-sql]::after {
            background: rgba(252, 165, 165, 0.15);
        }
        .markdown-content .tool-sql-preview {
            display: block;
            margin: 0.5rem 0 0.75rem 0;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;
            font-size: 0.8125rem;
            white-space: pre-wrap;
            word-break: break-all;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-left: 3px solid #ef4444;
        }
        .dark .markdown-content .tool-sql-preview {
            background: #1f2937;
            color: #e5e7eb;
            border-color: #374151;
            border-left-color: #f87171;
        }
        .markdown-content tool::before {
            content: '';
            display: inline-block;
            width: 0.875rem;
            height: 0.875rem;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            flex-shrink: 0;
        }
        .markdown-content tool[data-type="sql"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23dc2626'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'/%3E%3C/svg%3E");
        }
        .markdown-content tool[data-type="schema"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4'/%3E%3C/svg%3E");
        }
        .markdown-content tool[data-type="search"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'/%3E%3C/svg%3E");
        }
        .markdown-content tool[data-type="save"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4'/%3E%3C/svg%3E");
        }
        .markdown-content tool[data-type="default"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'/%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/%3E%3C/svg%3E");
        }
        .dark .markdown-content tool[data-type="sql"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23fca5a5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'/%3E%3C/svg%3E");
        }
        .dark .markdown-content tool[data-type="schema"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4'/%3E%3C/svg%3E");
        }
        .dark .markdown-content tool[data-type="search"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'/%3E%3C/svg%3E");
        }
        .dark .markdown-content tool[data-type="save"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4'/%3E%3C/svg%3E");
        }
        .dark .markdown-content tool[data-type="default"]::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'/%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/%3E%3C/svg%3E");
        }

        /* Loading dots animation */
        .loading-dots span {
            animation: loadingDots 1.4s infinite both;
        }
        .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
        .loading-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes loadingDots {
            0%, 80%, 100% { opacity: 0; }
            40% { opacity: 1; }
        }

        /* Stream cursor */
        .stream-cursor::after {
            content: '|';
            animation: blink 1s step-end infinite;
            color: #ef4444;
        }
        @keyframes blink {
            50% { opacity: 0; }
        }

        /* Focus ring styles */
        .focus-ring:focus {
            outline: none;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #ef4444;
        }
        .dark .focus-ring:focus {
            box-shadow: 0 0 0 2px #111827, 0 0 0 4px #f87171;
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        {{ $slot }}
    </div>

    @livewireScripts

    <script>
        // Configure marked.js to allow HTML (for tool tags)
        marked.setOptions({
            breaks: true,
            gfm: true,
        });

        // Initialize highlight.js
        document.addEventListener('livewire:navigated', () => {
            hljs.highlightAll();
        });

        // Re-highlight on Livewire updates
        Livewire.hook('morph.updated', ({ el }) => {
            el.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightElement(block);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter or Cmd+Enter to send message
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const sendButton = document.querySelector('[data-send-button]');
                if (sendButton && !sendButton.disabled) {
                    sendButton.click();
                }
            }

            // Ctrl+N or Cmd+N for new conversation
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                Livewire.dispatch('new-conversation');
            }
        });

        // Group all tool tags into a single container at the start
        function groupToolTags() {
            document.querySelectorAll('.markdown-content').forEach(container => {
                // Skip if already processed
                if (container.dataset.toolsGrouped) return;
                container.dataset.toolsGrouped = 'true';

                const tools = container.querySelectorAll('tool:not(.tool-container tool)');
                if (tools.length === 0) return;

                // Create a single container for all tools
                const toolContainer = document.createElement('div');
                toolContainer.className = 'tool-container';

                // Insert the container at the beginning of the content
                const firstTool = tools[0];
                firstTool.parentNode.insertBefore(toolContainer, firstTool);

                // Move all tools into the container
                tools.forEach(tool => {
                    // Remove any preceding line breaks or whitespace-only text nodes
                    let prev = tool.previousSibling;
                    while (prev && prev.nodeType === 3 && prev.textContent.trim() === '') {
                        const toRemove = prev;
                        prev = prev.previousSibling;
                        toRemove.remove();
                    }
                    // Remove any following line breaks
                    let next = tool.nextSibling;
                    while (next && ((next.nodeType === 3 && next.textContent.trim() === '') || next.tagName === 'BR')) {
                        const toRemove = next;
                        next = next.nextSibling;
                        toRemove.remove();
                    }
                    toolContainer.appendChild(tool);
                });
            });
        }

        // Run on initial load
        setTimeout(groupToolTags, 100);

        // Re-run when Livewire updates content
        Livewire.hook('morph.updated', ({ el }) => {
            // Reset the flag so tools can be regrouped
            el.querySelectorAll('.markdown-content').forEach(mc => {
                delete mc.dataset.toolsGrouped;
            });
            setTimeout(groupToolTags, 50);
        });

        // Handle tool SQL preview toggle
        document.addEventListener('click', function(e) {
            const tool = e.target.closest('tool[data-sql]');
            if (!tool) return;

            const sql = tool.dataset.sql;
            const toolContainer = tool.closest('.tool-container');

            // Toggle off if already expanded
            if (tool.classList.contains('expanded')) {
                tool.classList.remove('expanded');
                // Find and remove the preview that follows the container
                if (toolContainer && toolContainer.nextElementSibling && toolContainer.nextElementSibling.classList.contains('tool-sql-preview')) {
                    toolContainer.nextElementSibling.remove();
                }
                return;
            }

            // Close any other expanded tool previews
            document.querySelectorAll('tool.expanded').forEach(t => {
                t.classList.remove('expanded');
            });
            document.querySelectorAll('.tool-sql-preview').forEach(p => p.remove());

            // Create and insert preview
            tool.classList.add('expanded');
            const preview = document.createElement('div');
            preview.className = 'tool-sql-preview';
            preview.textContent = sql;

            // Insert after the tool container (not inside it)
            if (toolContainer) {
                toolContainer.insertAdjacentElement('afterend', preview);
            } else {
                tool.insertAdjacentElement('afterend', preview);
            }

            // Highlight the SQL
            if (window.hljs) {
                const code = document.createElement('code');
                code.className = 'language-sql';
                code.textContent = sql;
                preview.innerHTML = '';
                preview.appendChild(code);
                hljs.highlightElement(code);
            }
        });
    </script>
</body>
</html>
