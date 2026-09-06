<?php

namespace App\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Models\PdfData;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Services\VectorService;
use Exception;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Smalot\PdfParser\Parser;

class ChatPdfController extends Controller
{
    protected $client;

    protected $settings;

    protected $settings_two;

    protected $pinecone;

    public function __construct()
    {
        // Settings
        $this->settings = Setting::getCache();
        $this->settings_two = SettingTwo::getCache();
        ApiHelper::setOpenAiKey();

        // Safe 300-second limit to prevent stalled worker processes
        ini_set('max_execution_time', 300);
    }

    public function uploadPDF(Request $request)
    {
        $request->validate([
            'pdf'     => 'required|file|mimes:pdf|max:30720',
            'chat_id' => 'required',
        ]);

        $pdf = $request->file('pdf');
        $chat_id = $request->chat_id;

        // Generate isolated unique UUID filename to eliminate multi-user race conditions
        $fileName = Str::uuid()->toString() . '.pdf';
        $pdf_content = file_get_contents($pdf->getRealPath());

        // Store uniquely to public uploads disk
        Storage::disk('public')->put($fileName, $pdf_content);

        $localFullPath = public_path("uploads/{$fileName}");
        $resPath = "/uploads/{$fileName}";

        // Parse the unique user file directly
        try {
            $parser = new Parser;
            $pdfParsed = $parser->parseFile($localFullPath);
            $text = $pdfParsed->getText();
        } catch (Exception $e) {
            Log::error("ChatPdf: Failed to parse PDF [{$fileName}] for chat [{$chat_id}]: " . $e->getMessage());

            if (file_exists($localFullPath)) {
                @unlink($localFullPath);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('Unable to extract text from the PDF file. Please ensure it is not password-protected or corrupted.')
            ], 422);
        }

        // If cloud storage (S3) is active, upload after local parsing and clean up local file
        if (SettingTwo::getCache()->ai_image_storage === 's3') {
            try {
                $uploadedFile = new File($localFullPath);
                $aws_path = Storage::disk('s3')->put('', $uploadedFile);
                if (file_exists($localFullPath)) {
                    @unlink($localFullPath);
                }
                $resPath = Storage::disk('s3')->url($aws_path);
            } catch (Exception $e) {
                Log::error("ChatPdf: S3 storage upload failed for [{$fileName}]: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => 'Cloud Storage Error - ' . $e->getMessage()], 500);
            }
        }

        // Clear previous PDF chunks for this specific chat
        PdfData::where('chat_id', $chat_id)->delete();

        $page = $text;
        if (! mb_check_encoding($text, 'UTF-8')) {
            $page = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
        }

        $countwords = strlen($page) / 2000 + 1;
        for ($i = 0; $i < $countwords; $i++) {
            if (2000 * $i + 4000 > strlen($page)) {
                try {
                    $subtxt = substr($page, 2000 * $i);
                    $subtxt = mb_convert_encoding($subtxt, 'UTF-8', 'UTF-8');
                    $subtxt = iconv('UTF-8', 'UTF-8//IGNORE', $subtxt);
                    $response = OpenAI::embeddings()->create([
                        'model' => EntityEnum::TEXT_EMBEDDING_ADA_002->value,
                        'input' => $subtxt,
                    ]);

                    if (strlen(substr($page, 2000 * $i)) > 10) {
                        $chatpdf = new PdfData;
                        $chatpdf->chat_id = $chat_id;
                        $chatpdf->content = substr($page, 2000 * $i);
                        $chatpdf->vector = json_encode($response->embeddings[0]->embedding);
                        $chatpdf->save();
                    }
                } catch (Exception $e) {
                    Log::warning("ChatPdf: Embedding creation failed for chunk [{$i}] of chat [{$chat_id}]: " . $e->getMessage());
                }
            } else {
                try {
                    $subtxt = substr($page, 2000 * $i, 4000);
                    $subtxt = mb_convert_encoding($subtxt, 'UTF-8', 'UTF-8');
                    $subtxt = iconv('UTF-8', 'UTF-8//IGNORE', $subtxt);
                    $response = OpenAI::embeddings()->create([
                        'model' => EntityEnum::TEXT_EMBEDDING_ADA_002->value,
                        'input' => $subtxt,
                    ]);
                    if (strlen(substr($page, 2000 * $i, 4000)) > 10) {
                        $chatpdf = new PdfData;
                        $chatpdf->chat_id = $chat_id;
                        $chatpdf->content = substr($page, 2000 * $i, 4000);
                        $chatpdf->vector = json_encode($response->embeddings[0]->embedding);
                        $chatpdf->save();
                    }
                } catch (Exception $e) {
                    Log::warning("ChatPdf: Embedding creation failed for chunk [{$i}] of chat [{$chat_id}]: " . $e->getMessage());
                }
            }
        }

        return response()->json(['filename' => $resPath]);
    }

    public function getSimiliarContent(Request $request)
    {
        $count = $request->count;
        if ($count == null) {
            $count = 3;
        }

        $vectorService = new VectorService;

        return response()->json(['extra_prompt' => $vectorService->getMostSimilarText($request->prompt, $request->chat_id, $count)]);
    }
}

