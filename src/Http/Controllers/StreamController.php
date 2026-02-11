<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Controllers;

use Illuminate\Routing\Controller;
use Knobik\SqlAgent\Enums\MessageRole;
use Knobik\SqlAgent\Http\Actions\StreamAgentResponse;
use Knobik\SqlAgent\Http\Requests\StreamRequest;
use Knobik\SqlAgent\Services\ConversationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function __invoke(StreamRequest $request, StreamAgentResponse $streamAction, ConversationService $conversationService): StreamedResponse
    {
        $question = $request->getMessage();
        $conversation = $conversationService->findOrCreate($request->getConversationId(), 'multi');
        $conversationId = $conversation->id;

        $conversationService->addMessage($conversationId, MessageRole::User, $question);
        $conversation->updateTitleIfEmpty();

        return new StreamedResponse(
            fn () => $streamAction($question, $conversationId),
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }
}
