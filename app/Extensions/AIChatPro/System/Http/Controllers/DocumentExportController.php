<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Models\AIChatProArtifact;
use App\Extensions\AIChatPro\System\Models\AIChatProAttachment;
use App\Extensions\AIChatPro\System\Services\AIChatProStorageQuotaService;
use App\Extensions\AIChatPro\System\Services\GaradArtifactService;
use App\Extensions\AIChatPro\System\Services\GaradPresentationToolService;
use App\Extensions\AiPresentation\System\Models\AiPresentation;
use App\Extensions\AiPresentation\System\Services\GammaService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Thin HTTP layer over the existing GaradArtifactService so the React chat can
 * export an assistant answer as Word / PDF / Excel and list generated files.
 * Files are stored and downloaded through the existing, owner-checked artifact route.
 */
class DocumentExportController extends Controller
{
    private const FORMATS = ['word' => 'docx', 'docx' => 'docx', 'pdf' => 'pdf', 'xlsx' => 'xlsx', 'excel' => 'xlsx', 'md' => 'md', 'markdown' => 'md', 'txt' => 'txt', 'text' => 'txt'];

    public function export(Request $request, GaradArtifactService $service, string $format): JsonResponse
    {
        $extension = self::FORMATS[strtolower($format)] ?? null;
        if ($extension === null) {
            return response()->json(['message' => 'Unsupported document format.'], 422);
        }

        $validated = $request->validate([
            'title'   => ['nullable', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:200000'],
            'chat_id' => ['nullable', 'integer'],
        ]);

        $title = trim((string) ($validated['title'] ?? '')) ?: $this->deriveTitle($validated['content']);
        $content = $validated['content'];

        try {
            if ($extension === 'xlsx') {
                $table = $this->firstMarkdownTable($content);
                if ($table === null) {
                    return response()->json([
                        'message' => 'This answer has no table to export to Excel.',
                    ], 422);
                }
                $service->createXlsx([
                    'title'   => $title,
                    'headers' => $table['headers'],
                    'rows'    => $table['rows'],
                ]);
            } elseif (in_array($extension, ['md', 'txt'], true)) {
                $service->createText($title, $content, $extension);
            } elseif ($extension === 'pdf') {
                $service->createPdf(['title' => $title, 'content' => $content]);
            } else {
                $service->createDocx(['title' => $title, 'content' => $content]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Garad document export failed', ['format' => $extension, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'The document could not be generated. Please try again.'], 500);
        }

        $artifact = $service->lastArtifact;
        if (! $artifact) {
            return response()->json(['message' => 'The document was not saved.'], 500);
        }

        return response()->json(['data' => $this->present($artifact)], 201);
    }

    public function createPresentation(Request $request, GaradPresentationToolService $tool): JsonResponse
    {
        $data = $request->validate([
            'content'     => ['required', 'string', 'max:60000'],
            'title'       => ['nullable', 'string', 'max:160'],
            'slide_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        try {
            $out = $tool->create([
                'topic'       => trim(($data['title'] ?? '') . "\n\n" . $data['content']),
                'slide_count' => (int) ($data['slide_count'] ?? 8),
                'export_as'   => 'pptx',
            ]);
        } catch (Throwable $e) {
            Log::warning('Chat presentation start failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! preg_match('/\[\[GARAD_PRESENTATION_JOB:([A-Za-z0-9_\-]+)\]\]/', $out, $m)) {
            return response()->json(['message' => 'Presentation could not be started.'], 502);
        }

        return response()->json(['generationId' => $m[1], 'status' => 'processing', 'slideCount' => (int) ($data['slide_count'] ?? 8)], 202);
    }

    public function presentationStatus(string $generationId): JsonResponse
    {
        $owned = AiPresentation::query()->where('generation_id', $generationId)->where('user_id', Auth::id())->first();
        abort_if(! $owned, 404);

        try {
            $p = app(GammaService::class)->updatePresentationStatus($generationId);
        } catch (Throwable $e) {
            Log::warning('Chat presentation status failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => 'processing', 'transient' => true]);
        }

        $status = strtolower((string) ($p->status ?? 'processing'));
        if ($status === 'failed') {
            return response()->json(['status' => 'failed', 'message' => $p->error_message ?: 'Presentation generation failed.']);
        }
        if ($status !== 'completed' || ! $p->pptx_url) {
            return response()->json(['status' => 'processing']);
        }

        $artifact = AIChatProArtifact::query()
            ->where('user_id', Auth::id())
            ->where('type', 'pptx')
            ->where('metadata', 'like', '%' . $generationId . '%')
            ->first();

        if (! $artifact) {
            $rel = preg_replace('#^/uploads/#', '', $p->pptx_url);
            $disk = Storage::disk('public');
            abort_if(! $disk->exists($rel), 404);
            $title = trim(mb_substr((string) preg_replace('/\s+/', ' ', strtok((string) $p->input_text, "\n")), 0, 80)) ?: 'Garad AI Presentation';
            $artifact = app(GaradArtifactService::class)->importBinary(
                $title,
                'Garad-AI-Presentation.pptx',
                'pptx',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                $disk->get($rel),
                ['generation_id' => $generationId, 'slide_count' => (int) $p->num_cards]
            );
        }

        return response()->json(['status' => 'completed', 'artifact' => $this->present($artifact)]);
    }

    public function library(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $query = AIChatProArtifact::query()
            ->where('user_id', $userId)
            ->latest('id');

        $kind = $request->query('kind');

        $artifacts = $query->limit(200)
            ->get()
            ->map(fn ($artifact) => $this->present($artifact));
        $attachments = AIChatProAttachment::query()
            ->where('user_id', $userId)
            ->where('upload_status', 'uploaded')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn ($attachment) => $this->presentAttachment($attachment));
        $items = $artifacts
            ->concat($attachments)
            ->filter(fn (array $item) => $this->matchesLibraryKind($item, $kind))
            ->sortByDesc('createdAt')
            ->take(200)
            ->values();

        return response()
            ->json(['data' => $items->all()])
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(int $id, AIChatProStorageQuotaService $storageQuota): JsonResponse
    {
        $userId = (int) Auth::id();

        try {
            return $storageQuota->withUserLock($userId, function () use ($id, $userId): JsonResponse {
                $artifact = AIChatProArtifact::query()
                    ->where('user_id', $userId)
                    ->findOrFail($id);

                $disk = Storage::disk($artifact->storage_disk);
                if ($disk->exists($artifact->storage_path) && ! $disk->delete($artifact->storage_path)) {
                    throw new RuntimeException('The library file could not be removed from storage.');
                }

                if (! $artifact->delete()) {
                    throw new RuntimeException('The library file record could not be removed.');
                }

                return response()->json(['ok' => true]);
            });
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The library file could not be deleted.'], 500);
        }
    }

    private function presentAttachment(AIChatProAttachment $attachment): array
    {
        $mimeType = strtolower((string) $attachment->mime_type);
        $kind = match (true) {
            $mimeType === 'application/pdf'                                                          => 'pdf',
            str_contains($mimeType, 'spreadsheet') || $mimeType === 'application/vnd.ms-excel'       => 'spreadsheet',
            str_contains($mimeType, 'presentation') || $mimeType === 'application/vnd.ms-powerpoint' => 'presentation',
            str_starts_with($mimeType, 'image/')                                                     => 'image',
            str_starts_with($mimeType, 'video/')                                                     => 'video',
            default                                                                                  => 'document',
        };
        $url = route('dashboard.user.ai-chat-pro.attachments.show', ['attachment' => $attachment->uuid]);
        $inlineTypes = [
            'application/pdf',
            'text/plain',
            'text/csv',
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif',
            'image/bmp',
            'audio/mpeg',
            'audio/mp4',
            'audio/x-m4a',
            'audio/wav',
            'audio/x-wav',
            'audio/vnd.wave',
            'audio/ogg',
            'application/ogg',
            'audio/flac',
            'audio/x-flac',
            'audio/aac',
            'audio/vnd.dlna.adts',
            'audio/x-aac',
            'video/mp4',
            'application/mp4',
            'video/quicktime',
            'video/x-matroska',
            'application/x-matroska',
            'video/x-msvideo',
            'video/avi',
            'video/x-m4v',
            'video/webm',
        ];

        return [
            'id'          => $attachment->uuid,
            'name'        => $attachment->original_name,
            'mimeType'    => $attachment->mime_type,
            'extension'   => strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)),
            'size'        => (int) $attachment->file_size,
            'kind'        => $kind,
            'status'      => 'ready',
            'progress'    => 100,
            'url'         => $url,
            'downloadUrl' => $url . '?download=1',
            'previewUrl'  => in_array($mimeType, $inlineTypes, true) ? $url : null,
            'createdAt'   => optional($attachment->created_at)->toIso8601String(),
            'updatedAt'   => optional($attachment->updated_at)->toIso8601String(),
        ];
    }

    private function matchesLibraryKind(array $item, mixed $kind): bool
    {
        if (! is_string($kind) || $kind === '') {
            return true;
        }

        return ($item['kind'] ?? null) === $kind;
    }

    private function present(AIChatProArtifact $artifact): array
    {
        $type = strtolower((string) $artifact->type);
        $kind = match ($type) {
            'pdf' => 'pdf',
            'docx', 'doc' => 'document',
            'xlsx', 'xls' => 'spreadsheet',
            'pptx'  => 'presentation',
            default => 'generated',
        };
        $label = match ($kind) {
            'pdf'          => 'PDF document',
            'document'     => 'Word document',
            'spreadsheet'  => 'Excel spreadsheet',
            'presentation' => 'PowerPoint presentation',
            default        => $type === 'md' ? 'Markdown file' : ($type === 'txt' ? 'Text file' : 'File'),
        };
        $downloadUrl = route('dashboard.user.openai.chat.pro.artifacts.download', ['artifact' => $artifact->id]);
        $metadata = is_array($artifact->metadata) ? $artifact->metadata : [];

        return [
            'id'             => (string) $artifact->id,
            'name'           => $artifact->original_name,
            'type'           => $type,
            'format'         => $type,
            'kind'           => $kind,
            'description'    => $label . ' · ' . $this->formatBytes((int) $artifact->file_size),
            'status'         => 'ready',
            'url'            => $downloadUrl,
            'downloadUrl'    => $downloadUrl,
            'previewUrl'     => in_array($type, ['pdf', 'doc', 'docx', 'xls', 'xlsx'], true) ? $downloadUrl . '?preview=1' : null,
            'slideCount'     => isset($metadata['slide_count']) ? (int) $metadata['slide_count'] : null,
            'conversationId' => $artifact->user_openai_chat_id ? (string) $artifact->user_openai_chat_id : null,
            'size'           => (int) $artifact->file_size,
            'mimeType'       => $artifact->mime_type,
            'createdAt'      => optional($artifact->created_at)->toIso8601String(),
            'updatedAt'      => optional($artifact->updated_at)->toIso8601String(),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    private function deriveTitle(string $content): string
    {
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $line = trim(preg_replace('/^[#>\-\*\s]+/u', '', trim($line)) ?? '');
            $line = trim(str_replace(['**', '__', '`'], '', $line));
            if ($line !== '') {
                return mb_strimwidth($line, 0, 80, '');
            }
        }

        return 'Garad AI Document';
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}|null
     */
    private function firstMarkdownTable(string $content): ?array
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        $count = count($lines);

        for ($i = 0; $i < $count - 1; $i++) {
            if (! str_contains($lines[$i], '|') || ! preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $lines[$i + 1])) {
                continue;
            }

            $headers = $this->splitRow($lines[$i]);
            $rows = [];
            for ($j = $i + 2; $j < $count && str_contains($lines[$j], '|'); $j++) {
                $rows[] = $this->splitRow($lines[$j]);
            }

            return $headers === [] ? null : ['headers' => $headers, 'rows' => $rows];
        }

        return null;
    }

    /** @return list<string> */
    private function splitRow(string $line): array
    {
        $line = trim(trim($line), '|');

        return array_map(
            static fn (string $cell): string => trim(str_replace(['**', '__', '`'], '', $cell)),
            explode('|', $line)
        );
    }
}
