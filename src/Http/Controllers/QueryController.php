<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Knobik\SqlAgent\Http\Requests\ExecuteQueryRequest;
use Knobik\SqlAgent\Models\Message;
use Knobik\SqlAgent\Services\ConnectionRegistry;
use Knobik\SqlAgent\Services\ConversationService;
use Knobik\SqlAgent\Services\SqlValidator;
use RuntimeException;
use Throwable;

class QueryController extends Controller
{
    public function __invoke(
        ExecuteQueryRequest $request,
        SqlValidator $sqlValidator,
        ConnectionRegistry $connectionRegistry,
        ConversationService $conversationService,
    ): JsonResponse {
        $message = Message::findOrFail($request->getMessageId());

        // Verify the message's conversation belongs to the current user
        $conversation = $conversationService->findForCurrentUser($message->conversation_id);
        if (! $conversation) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        $queries = $message->getQueries();
        $queryIndex = $request->getQueryIndex();

        if (! isset($queries[$queryIndex])) {
            return response()->json(['message' => 'Query index out of range.'], 422);
        }

        $query = $queries[$queryIndex];
        $sql = trim($query['sql']);
        $connectionName = $query['connection'] ?? null;

        try {
            $sqlValidator->validate($sql, $connectionName);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $resolvedConnection = $connectionRegistry->resolveConnection($connectionName);
        $maxRows = config('sql-agent.sql.max_rows');

        try {
            $results = DB::connection($resolvedConnection)->select($sql);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rows = array_map(fn ($row) => (array) $row, $results);

        $totalRows = count($rows);
        $rows = array_slice($rows, 0, $maxRows);

        return response()->json([
            'rows' => $rows,
            'row_count' => count($rows),
            'total_rows' => $totalRows,
            'truncated' => $totalRows > $maxRows,
        ]);
    }
}
