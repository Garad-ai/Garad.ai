<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProArtifact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\UserOpenaiChat;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

class GaradArtifactService
{
    private const MAX_TEXT_LENGTH = 200000;

    private const MAX_XLSX_ROWS = 5000;

    public function createPdf(array $arguments): string
    {
        $title = $this->title($arguments['title'] ?? 'Garad AI Document');
        $content = $this->content($arguments['content'] ?? '');
        $fileName = $this->fileName($title, 'pdf');

        $safeContent = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = nl2br($safeContent);

        $html = <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.55; color: #111; }
h1 { font-size: 22px; margin-bottom: 18px; }
</style>
</head>
<body>
<h1>{$safeTitle}</h1>
<div>{$body}</div>
</body>
</html>
HTML;

        $binary = Pdf::loadHTML($html)->output();
        $artifact = $this->persist($title, $fileName, 'pdf', 'application/pdf', $binary);

        return $this->downloadMessage($artifact, 'PDF');
    }

    public function createDocx(array $arguments): string
    {
        $title = $this->title($arguments['title'] ?? 'Garad AI Document');
        $content = $this->content($arguments['content'] ?? '');
        $fileName = $this->fileName($title, 'docx');

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addTitle($title, 1);

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $section->addTextBreak();
                continue;
            }

            if (str_starts_with($trimmed, '### ')) {
                $section->addTitle(substr($trimmed, 4), 3);
            } elseif (str_starts_with($trimmed, '## ')) {
                $section->addTitle(substr($trimmed, 3), 2);
            } elseif (str_starts_with($trimmed, '# ')) {
                $section->addTitle(substr($trimmed, 2), 1);
            } elseif (preg_match('/^[-*]\s+(.+)$/u', $trimmed, $match)) {
                $section->addListItem($match[1]);
            } else {
                $section->addText($trimmed);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'garad_docx_');
        if (! $tempPath) {
            throw new RuntimeException('Unable to create a temporary Word document.');
        }

        try {
            PhpWordIOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);
            $binary = file_get_contents($tempPath);
        } finally {
            @unlink($tempPath);
        }

        if ($binary === false) {
            throw new RuntimeException('Unable to render the Word document.');
        }

        $artifact = $this->persist(
            $title,
            $fileName,
            'docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $binary,
        );

        return $this->downloadMessage($artifact, 'Word');
    }

    public function createXlsx(array $arguments): string
    {
        $title = $this->title($arguments['title'] ?? 'Garad AI Spreadsheet');
        $headers = array_values(array_map('strval', (array) ($arguments['headers'] ?? [])));
        $rows = array_values((array) ($arguments['rows'] ?? []));

        if ($headers === [] && $rows === []) {
            throw new RuntimeException('Excel creation requires headers or rows.');
        }

        if (count($rows) > self::MAX_XLSX_ROWS) {
            throw new RuntimeException('Excel creation is limited to 5,000 rows per request.');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[\\\/?*\[\]:]/', '', $title) ?: 'Sheet1', 0, 31));

        $rowNumber = 1;
        if ($headers !== []) {
            foreach ($headers as $columnIndex => $header) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowNumber,
                    $header,
                    DataType::TYPE_STRING,
                );
            }
            $rowNumber++;
        }

        foreach ($rows as $row) {
            $values = is_array($row) ? array_values($row) : [(string) $row];
            foreach ($values as $columnIndex => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowNumber;
                if (is_string($value)) {
                    // Treat generated/imported strings as literal values so spreadsheet
                    // formulas cannot be injected from untrusted attachment content.
                    $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cell, $value);
                }
            }
            $rowNumber++;
        }

        $fileName = $this->fileName($title, 'xlsx');
        $tempPath = tempnam(sys_get_temp_dir(), 'garad_xlsx_');
        if (! $tempPath) {
            throw new RuntimeException('Unable to create a temporary Excel document.');
        }

        try {
            (new Xlsx($spreadsheet))->save($tempPath);
            $binary = file_get_contents($tempPath);
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($tempPath);
        }

        if ($binary === false) {
            throw new RuntimeException('Unable to render the Excel document.');
        }

        $artifact = $this->persist(
            $title,
            $fileName,
            'xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $binary,
        );

        return $this->downloadMessage($artifact, 'Excel');
    }

    private function persist(string $title, string $fileName, string $type, string $mimeType, string $binary): AIChatProArtifact
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new RuntimeException('Please sign in before creating downloadable documents.');
        }

        $chatId = (int) request()?->input('chat_id');
        if ($chatId > 0 && ! UserOpenaiChat::query()->whereKey($chatId)->where('user_id', $userId)->exists()) {
            // Keep the artifact owner-scoped even if a stale/spoofed chat ID is sent.
            $chatId = 0;
        }

        $path = sprintf('ai-chat-pro-artifacts/u-%d/%s/%s', $userId, now()->format('Y/m'), Str::uuid() . '.' . $type);

        if (! Storage::disk('local')->put($path, $binary)) {
            throw new RuntimeException('Unable to store the generated document.');
        }

        return AIChatProArtifact::create([
            'user_id'             => $userId,
            'user_openai_chat_id' => $chatId > 0 ? $chatId : null,
            'type'                => $type,
            'title'               => $title,
            'original_name'       => $fileName,
            'mime_type'           => $mimeType,
            'storage_disk'        => 'local',
            'storage_path'        => $path,
            'file_size'           => strlen($binary),
        ]);
    }

    private function downloadMessage(AIChatProArtifact $artifact, string $label): string
    {
        $url = route('dashboard.user.openai.chat.pro.artifacts.download', ['artifact' => $artifact->id]);
        $name = htmlspecialchars($artifact->original_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('✅ %s created: <a href="%s" target="_blank" rel="noopener">%s</a>', $label, $safeUrl, $name);
    }

    private function title(mixed $value): string
    {
        $title = trim((string) $value);
        if ($title === '') {
            $title = 'Garad AI Document';
        }

        return mb_substr($title, 0, 180);
    }

    private function content(mixed $value): string
    {
        $content = trim((string) $value);
        if ($content === '') {
            throw new RuntimeException('Document content cannot be empty.');
        }

        if (mb_strlen($content) > self::MAX_TEXT_LENGTH) {
            throw new RuntimeException('Document content is too large for a single generation request.');
        }

        return $content;
    }

    private function fileName(string $title, string $extension): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'garad-ai-document';
        }

        return mb_substr($slug, 0, 100) . '.' . $extension;
    }
}
