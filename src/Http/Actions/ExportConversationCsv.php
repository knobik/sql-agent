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
                'Query Count',
                'Last SQL',
                'Created At',
            ]);

            // Write messages
            foreach ($conversation->messages as $message) {
                $queries = $message->queries ?? [];
                $lastQuery = ! empty($queries) ? end($queries) : null;
                $lastSql = $lastQuery !== null ? $lastQuery['sql'] : '';

                fputcsv($handle, [
                    $message->id,
                    $message->role->value,
                    $message->content,
                    count($queries),
                    $lastSql,
                    $message->created_at->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
