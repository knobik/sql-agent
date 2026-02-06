<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Knobik\SqlAgent\Http\Actions\ExportConversationCsv;
use Knobik\SqlAgent\Http\Actions\ExportConversationJson;
use Knobik\SqlAgent\Services\ConversationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function json(Request $request, int $conversation, ConversationService $conversationService, ExportConversationJson $action): Response
    {
        $conv = $conversationService->findForCurrentUserWithMessages($conversation);

        if (! $conv) {
            abort(404, 'Conversation not found');
        }

        return $action($conv);
    }

    public function csv(Request $request, int $conversation, ConversationService $conversationService, ExportConversationCsv $action): StreamedResponse
    {
        $conv = $conversationService->findForCurrentUserWithMessages($conversation);

        if (! $conv) {
            abort(404, 'Conversation not found');
        }

        return $action($conv);
    }
}
