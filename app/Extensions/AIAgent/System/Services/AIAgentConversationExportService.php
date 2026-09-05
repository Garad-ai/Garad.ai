<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Extensions\AIAgent\System\Models\AIAgentConversation;
use App\Extensions\AIAgent\System\Models\AIAgentMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AIAgentConversationExportService
{
    public function export(AIAgentConversation $conversation, string $format = 'txt'): SymfonyResponse
    {
        $messages = $conversation->messages()
            ->orderBy('id')
            ->get();

        return match ($format) {
            'csv'   => $this->exportCsv($conversation, $messages),
            'pdf'   => $this->exportPdf($conversation, $messages),
            'json'  => $this->exportJson($conversation, $messages),
            default => $this->exportTxt($conversation, $messages),
        };
    }

    private function exportTxt(AIAgentConversation $conversation, Collection $messages): SymfonyResponse
    {
        $content = '';

        foreach ($messages as $message) {
            $role = $message->direction === 'inbound' ? 'USER' : 'AGENT';
            $timestamp = $message->created_at?->format('Y-m-d H:i:s') ?? '';
            $content .= "[{$timestamp}] [{$role}] " . $message->content . PHP_EOL . PHP_EOL;
        }

        $fileName = "ai-agent-conversation-{$conversation->id}.txt";

        return Response::make($content, 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    private function exportCsv(AIAgentConversation $conversation, Collection $messages): SymfonyResponse
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Timestamp', 'Role', 'Message']);

        foreach ($messages as $message) {
            fputcsv($handle, [
                $message->created_at?->format('Y-m-d H:i:s') ?? '',
                $message->direction === 'inbound' ? 'USER' : 'AGENT',
                $message->content,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $fileName = "ai-agent-conversation-{$conversation->id}.csv";

        return Response::make($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    private function exportPdf(AIAgentConversation $conversation, Collection $messages): SymfonyResponse
    {
        $channel = $conversation->channel;

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('ai-agent::export.pdf', [
            'conversation' => $conversation,
            'messages'     => $messages,
            'channelName'  => $channel?->name,
        ]);

        return $pdf->download("ai-agent-conversation-{$conversation->id}.pdf");
    }

    private function exportJson(AIAgentConversation $conversation, Collection $messages): SymfonyResponse
    {
        $data = [
            'conversation_id' => $conversation->id,
            'channel'         => $conversation->channel?->name,
            'sender_id'       => $conversation->sender_id,
            'exported_at'     => now()->toIso8601String(),
            'messages'        => $messages->map(fn (AIAgentMessage $message) => [
                'direction' => $message->direction,
                'content'   => $message->content,
                'timestamp' => $message->created_at?->toIso8601String(),
            ])->toArray(),
        ];

        $content = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
        $fileName = "ai-agent-conversation-{$conversation->id}.json";

        return Response::make($content, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
