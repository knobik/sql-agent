<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Knobik\SqlAgent\Models\Conversation;
use Knobik\SqlAgent\Support\UserResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function json(Request $request, int $conversation): Response
    {
        $conv = $this->getConversation($conversation);

        if (! $conv) {
            abort(404, 'Conversation not found');
        }

        $data = [
            'id' => $conv->id,
            'title' => $conv->title,
            'connection' => $conv->getAttribute('connection'),
            'created_at' => $conv->created_at->toIso8601String(),
            'updated_at' => $conv->updated_at->toIso8601String(),
            'messages' => $conv->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'sql' => $message->sql,
                    'results' => $message->results,
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            })->toArray(),
        ];

        $filename = sprintf(
            'conversation-%d-%s.json',
            $conv->id,
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

    public function csv(Request $request, int $conversation): StreamedResponse
    {
        $conv = $this->getConversation($conversation);

        if (! $conv) {
            abort(404, 'Conversation not found');
        }

        $filename = sprintf(
            'conversation-%d-%s.csv',
            $conv->id,
            now()->format('Y-m-d-His')
        );

        return response()->streamDownload(function () use ($conv) {
            $handle = fopen('php://output', 'w');

            // Write CSV header
            fputcsv($handle, [
                'Message ID',
                'Role',
                'Content',
                'SQL',
                'Result Count',
                'Created At',
            ]);

            // Write messages
            foreach ($conv->messages as $message) {
                fputcsv($handle, [
                    $message->id,
                    $message->role->value,
                    $message->content,
                    $message->sql ?? '',
                    $message->results ? count($message->results) : 0,
                    $message->created_at->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function getConversation(int $id): ?Conversation
    {
        $conversation = Conversation::with('messages')->find($id);

        if (! $conversation) {
            return null;
        }

        // Check ownership only if user tracking is enabled
        $userResolver = app(UserResolver::class);
        if ($userResolver->isEnabled() && $conversation->user_id !== $userResolver->id()) {
            return null;
        }

        return $conversation;
    }
}
