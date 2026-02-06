<?php

declare(strict_types=1);

namespace Knobik\SqlAgent\Http\Actions;

use Knobik\SqlAgent\Models\Conversation;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportConversationCsv
{
    public function __invoke(Conversation $conversation): StreamedResponse
    {
        $filename = sprintf(
            'conversation-%d-%s.csv',
            $conversation->id,
            now()->format('Y-m-d-His')
        );

        return response()->streamDownload(function () use ($conversation) {
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
            foreach ($conversation->messages as $message) {
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
}
