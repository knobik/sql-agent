<?php

use Illuminate\Support\Facades\Route;
use Knobik\SqlAgent\Http\Controllers\QueryController;
use Knobik\SqlAgent\Http\Controllers\StreamController;

// Only register routes if UI is enabled
if (config('sql-agent.ui.enabled', true)) {
    Route::middleware(config('sql-agent.ui.middleware'))
        ->prefix(config('sql-agent.ui.route_prefix', 'sql-agent'))
        ->name('sql-agent.')
        ->group(function () {
            // Main chat interface
            Route::get('/', fn () => view('sql-agent::chat'))->name('chat');

            // Load specific conversation
            Route::get('/conversation/{conversation}', fn ($conversation) => view('sql-agent::chat', [
                'conversationId' => $conversation,
            ]))->name('conversation');

            // Streaming endpoint for SSE
            Route::post('/stream', StreamController::class)->name('stream');

            // On-demand query execution
            Route::post('/query/execute', QueryController::class)->name('query.execute');
        });
}
