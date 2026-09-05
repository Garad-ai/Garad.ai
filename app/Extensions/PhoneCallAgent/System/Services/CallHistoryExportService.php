<?php

namespace App\Extensions\PhoneCallAgent\System\Services;

use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentCall;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CallHistoryExportService
{
    public function export(Collection $calls, string $format = 'csv'): SymfonyResponse
    {
        return match ($format) {
            'json'  => $this->exportJson($calls),
            'pdf'   => $this->exportPdf($calls),
            default => $this->exportCsv($calls),
        };
    }

    private function exportCsv(Collection $calls): SymfonyResponse
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['ID', 'Agent', 'Caller Number', 'Called Number', 'Status', 'Duration (s)', 'Started At', 'Ended At']);

        foreach ($calls as $call) {
            fputcsv($handle, [
                $call->id,
                $call->agent?->title ?? '',
                $call->caller_number ?? '',
                $call->called_number ?? '',
                $call->status?->value ?? $call->status ?? '',
                $call->duration ?? 0,
                $call->started_at?->format('Y-m-d H:i:s') ?? '',
                $call->ended_at?->format('Y-m-d H:i:s') ?? '',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="call-history-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    private function exportJson(Collection $calls): SymfonyResponse
    {
        $data = [
            'exported_at' => now()->toIso8601String(),
            'total'       => $calls->count(),
            'calls'       => $calls->map(fn (ExtPhoneCallAgentCall $call) => [
                'id'             => $call->id,
                'agent'          => $call->agent?->title,
                'caller_number'  => $call->caller_number,
                'called_number'  => $call->called_number,
                'status'         => $call->status?->value ?? $call->status,
                'duration'       => $call->duration,
                'started_at'     => $call->started_at?->toIso8601String(),
                'ended_at'       => $call->ended_at?->toIso8601String(),
                'transcripts'    => $call->transcripts->map(fn ($t) => [
                    'role'    => $t->role?->value ?? $t->role,
                    'message' => $t->message,
                ])->toArray(),
            ])->toArray(),
        ];

        $content = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

        return Response::make($content, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="call-history-' . now()->format('Y-m-d') . '.json"',
        ]);
    }

    private function exportPdf(Collection $calls): SymfonyResponse
    {
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('phone-call-agent::export.pdf', [
            'calls'       => $calls,
            'exported_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return $pdf->download('call-history-' . now()->format('Y-m-d') . '.pdf');
    }
}
