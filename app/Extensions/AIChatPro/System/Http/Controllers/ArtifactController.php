<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Models\AIChatProArtifact;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as SpreadsheetHtmlWriter;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ArtifactController extends Controller
{
    public function download(
        Request $request,
        AIChatProArtifact $artifact
    ): StreamedResponse|Response {
        $this->authorizeArtifact($artifact);

        if ($request->boolean('preview')) {
            return $this->preview($artifact);
        }

        return Storage::disk($artifact->storage_disk)->download(
            $artifact->storage_path,
            $artifact->original_name,
            [
                'Content-Type' => $artifact->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function preview(AIChatProArtifact $artifact): Response
    {
        $disk = Storage::disk($artifact->storage_disk);

        abort_unless(
            $disk->exists($artifact->storage_path),
            404
        );

        $type = strtolower((string) $artifact->type);

        if ($type === 'pdf') {
            $binary = $disk->get($artifact->storage_path);

            return response(
                $binary,
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' =>
                        'inline; filename="' .
                        addslashes($artifact->original_name) .
                        '"',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'Cache-Control' => 'private, no-store',
                ]
            );
        }

        if (in_array($type, ['doc', 'docx'], true)) {
            return $this->previewWord($artifact);
        }

        if (in_array($type, ['xls', 'xlsx'], true)) {
            return $this->previewExcel($artifact);
        }

        return response(
            $this->unsupportedPreviewHtml($artifact),
            200,
            $this->htmlHeaders()
        );
    }

    private function previewWord(
        AIChatProArtifact $artifact
    ): Response {
        $sourcePath = null;
        $htmlPath = null;

        try {
            $sourcePath = $this->temporaryArtifactPath(
                $artifact,
                'docx'
            );

            $phpWord = PhpWordIOFactory::load(
                $sourcePath
            );

            $htmlPath = $this->temporaryPath(
                'garad_word_preview_',
                'html'
            );

            PhpWordIOFactory::createWriter(
                $phpWord,
                'HTML'
            )->save($htmlPath);

            $html = file_get_contents($htmlPath);

            if ($html === false) {
                throw new \RuntimeException(
                    'Unable to prepare Word preview.'
                );
            }

            return response(
                $this->decorateHtmlPreview(
                    $html,
                    $artifact->original_name
                ),
                200,
                $this->htmlHeaders()
            );
        } catch (Throwable $e) {
            report($e);

            return response(
                $this->previewErrorHtml(
                    $artifact,
                    'Word preview could not be prepared.'
                ),
                200,
                $this->htmlHeaders()
            );
        } finally {
            if ($sourcePath && is_file($sourcePath)) {
                @unlink($sourcePath);
            }

            if ($htmlPath && is_file($htmlPath)) {
                @unlink($htmlPath);
            }
        }
    }

    private function previewExcel(
        AIChatProArtifact $artifact
    ): Response {
        $sourcePath = null;
        $htmlPath = null;
        $spreadsheet = null;

        try {
            $sourcePath = $this->temporaryArtifactPath(
                $artifact,
                'xlsx'
            );

            $spreadsheet = SpreadsheetIOFactory::load(
                $sourcePath
            );

            $htmlPath = $this->temporaryPath(
                'garad_excel_preview_',
                'html'
            );

            $writer = new SpreadsheetHtmlWriter(
                $spreadsheet
            );

            $writer->writeAllSheets();

            $writer->save($htmlPath);

            $html = file_get_contents($htmlPath);

            if ($html === false) {
                throw new \RuntimeException(
                    'Unable to prepare Excel preview.'
                );
            }

            return response(
                $this->decorateHtmlPreview(
                    $html,
                    $artifact->original_name
                ),
                200,
                $this->htmlHeaders()
            );
        } catch (Throwable $e) {
            report($e);

            return response(
                $this->previewErrorHtml(
                    $artifact,
                    'Excel preview could not be prepared.'
                ),
                200,
                $this->htmlHeaders()
            );
        } finally {
            if ($spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }

            if ($sourcePath && is_file($sourcePath)) {
                @unlink($sourcePath);
            }

            if ($htmlPath && is_file($htmlPath)) {
                @unlink($htmlPath);
            }
        }
    }

    private function temporaryArtifactPath(
        AIChatProArtifact $artifact,
        string $extension
    ): string {
        $path = $this->temporaryPath(
            'garad_artifact_',
            $extension
        );

        $binary = Storage::disk(
            $artifact->storage_disk
        )->get(
            $artifact->storage_path
        );

        if (
            file_put_contents($path, $binary)
            === false
        ) {
            throw new \RuntimeException(
                'Unable to create artifact preview.'
            );
        }

        return $path;
    }

    private function temporaryPath(
        string $prefix,
        string $extension
    ): string {
        $base = tempnam(
            sys_get_temp_dir(),
            $prefix
        );

        if ($base === false) {
            throw new \RuntimeException(
                'Unable to create temporary file.'
            );
        }

        @unlink($base);

        return $base . '.' . $extension;
    }

    private function decorateHtmlPreview(
        string $html,
        string $fileName
    ): string {
        $safeName = htmlspecialchars(
            $fileName,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $style = <<<'CSS'
<style>
html,body{
    margin:0;
    padding:0;
    background:#f7f7f8;
    color:#111827;
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}
body{
    padding:32px;
}
body > *{
    max-width:980px;
    margin-left:auto;
    margin-right:auto;
}
table{
    width:100% !important;
    border-collapse:collapse;
    background:#fff;
}
td,th{
    border:1px solid #e5e7eb;
    padding:8px 10px;
}
th{
    background:#f3f4f6;
    font-weight:600;
}
img{
    max-width:100%;
    height:auto;
}
@media(max-width:768px){
    body{
        padding:16px;
    }
}
</style>
CSS;

        $banner = '
<div style="
    max-width:980px;
    margin:0 auto 20px;
    padding:12px 16px;
    border:1px solid #e5e7eb;
    border-radius:14px;
    background:white;
    font-size:13px;
    color:#6b7280;
">
    ' . $safeName . '
</div>';

        if (
            stripos($html, '</head>')
            !== false
        ) {
            $html = str_ireplace(
                '</head>',
                $style . '</head>',
                $html
            );
        } else {
            $html = $style . $html;
        }

        if (
            stripos($html, '<body')
            !== false
        ) {
            $html = preg_replace(
                '/(<body[^>]*>)/i',
                '$1' . $banner,
                $html,
                1
            ) ?? $html;
        } else {
            $html = $banner . $html;
        }

        return $html;
    }

    private function previewErrorHtml(
        AIChatProArtifact $artifact,
        string $message
    ): string {
        $safeName = htmlspecialchars(
            $artifact->original_name,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $safeMessage = htmlspecialchars(
            $message,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$safeName}</title>
<style>
body{
    margin:0;
    min-height:100vh;
    display:grid;
    place-items:center;
    background:#f7f7f8;
    color:#111827;
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}
.card{
    width:min(520px,calc(100% - 32px));
    padding:28px;
    border-radius:20px;
    background:white;
    border:1px solid #e5e7eb;
    text-align:center;
}
h2{margin:0 0 10px}
p{color:#6b7280}
</style>
</head>
<body>
<div class="card">
<h2>{$safeName}</h2>
<p>{$safeMessage}</p>
<p>You can still download the original file.</p>
</div>
</body>
</html>
HTML;
    }

    private function unsupportedPreviewHtml(
        AIChatProArtifact $artifact
    ): string {
        return $this->previewErrorHtml(
            $artifact,
            'Preview is not available for this file type.'
        );
    }

    private function htmlHeaders(): array
    {
        return [
            'Content-Type' =>
                'text/html; charset=UTF-8',

            'X-Content-Type-Options' =>
                'nosniff',

            'X-Frame-Options' =>
                'SAMEORIGIN',

            'Cache-Control' =>
                'private, no-store',
        ];
    }

    private function authorizeArtifact(
        AIChatProArtifact $artifact
    ): void {
        abort_unless(
            Auth::check()
            && (int) $artifact->user_id
                === (int) Auth::id(),
            403
        );

        abort_unless(
            Storage::disk(
                $artifact->storage_disk
            )->exists(
                $artifact->storage_path
            ),
            404
        );
    }
}