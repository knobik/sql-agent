<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Actions;

use Illuminate\Http\Response;
use Knobik\SqlAgent\Models\Conversation;

class ExportConversationJson
{
    public function __invoke(Conversation $conversation): Response
    {
        $data = [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'connection' => $conversation->getAttribute('connection'),
            'created_at' => $conversation->created_at->toIso8601String(),
            'updated_at' => $conversation->updated_at->toIso8601String(),
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'queries' => $message->queries,
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            })->toArray(),
        ];

        $filename = sprintf(
            'conversation-%d-%s.json',
            $conversation->id,
            now()->format('Y-m-d-His')
        );

        return response(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }
}
