<?php

namespace App\Extensions\AiPresentation\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiPresentation\System\Models\AiPresentation;
use App\Helpers\Classes\ApiHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use App\Extensions\AIChatPro\System\Services\DocumentReaderService;

class GammaService
{
    protected string $apiUrl = 'https://public-api.gamma.app/v1.0/generations';

    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = ApiHelper::setGammaApiKey();
    }

    public function generatePresentation(array $validated): object
    {
        $prompt = trim($validated['description'] ?? '');

if ($prompt === '' && !empty($validated['uploadedFiles'])) {

    $firstFile = $validated['uploadedFiles'][0];

    $fileName = pathinfo(
        $firstFile->getClientOriginalName(),
        PATHINFO_FILENAME
    );

    // Ka saar random suffix
    $fileName = preg_replace('/_[A-Za-z0-9]{5,}$/', '', $fileName);

    // Ka saar timestamp
    $fileName = preg_replace('/_[0-9]{6,}$/', '', $fileName);

    // Beddel _ iyo - meel bannaan
    $fileName = str_replace(['-', '_'], ' ', $fileName);

    // Nadiifi spaces
    $fileName = preg_replace('/\s+/', ' ', trim($fileName));

    // Capitalize words
    $prompt = ucwords($fileName);
}

if (!empty($validated['uploadedFiles'])) {

    $reader = new DocumentReaderService();

    foreach ($validated['uploadedFiles'] as $file) {

        try {

            $document = $reader->readUploadedFile($file);

            if (!empty($document['extracted_text'])) {

                $prompt .= "\n\n---------------------------------\n";
                $prompt .= "Attached Document: " . $file->getClientOriginalName() . "\n\n";
                $prompt .= $document['extracted_text'];

            }

        } catch (\Throwable $e) {

            // ignore unsupported files

        }

    }

}
        // Gamma v1.0 requires textMode. Keep user-facing content language
        // separate from Gamma's accepted enum so Somali (and any future
        // unsupported locale) never reaches textOptions.language directly.
        [$gammaLanguage, $visibleLanguage] = $this->normalizeGammaLanguage(
            (string) ($validated['requested_language'] ?? $validated['language'] ?? 'en')
        );

        $additionalInstructions = trim((string) ($validated['additionalInstructions'] ?? ''));
        if ($visibleLanguage !== '') {
            $languageInstruction = "Write all visible output text in {$visibleLanguage}. Do not switch the visible content language to English unless English was requested.";
            $additionalInstructions = trim($languageInstruction . ' ' . $additionalInstructions);
        }

        // Build the v1.0 request using safe defaults. Existing callers that
        // already provide these fields keep their values.
        $data = [
            'inputText'               => $prompt,
            'textMode'                => (string) ($validated['textMode'] ?? 'generate'),
            'format'                  => (string) ($validated['format'] ?? 'presentation'),
            'numCards'                => max(1, (int) ($validated['presentation_count'] ?? 10)),
            'cardSplit'               => (string) ($validated['cardSplit'] ?? 'auto'),
            'exportAs'                => (string) ($validated['exportAs'] ?? 'pptx'),
        ];

        if ($additionalInstructions !== '') {
            $data['additionalInstructions'] = mb_substr($additionalInstructions, 0, 5000);
        }

        // Map text_length to textOptions.amount.
        $amount = strtolower((string) ($validated['text_length'] ?? 'medium'));
        if (! in_array($amount, ['brief', 'medium', 'detailed', 'extensive'], true)) {
            $amount = 'medium';
        }

        $textOptions = [
            'amount'   => $amount,
            'language' => $gammaLanguage,
        ];

        // Add optional textOptions (tone and audience)
        if (! empty($validated['textOptions']['tone'])) {
            $textOptions['tone'] = $validated['textOptions']['tone'];
        }
        if (! empty($validated['textOptions']['audience'])) {
            $textOptions['audience'] = $validated['textOptions']['audience'];
        }

        // Always include textOptions since it has required fields
        $data['textOptions'] = $textOptions;
      
        // Handle imageOptions (source, model, style)
        $imageOptions = [];
        if (! empty($validated['imageOptions']['source'])) {
            $imageOptions['source'] = $validated['imageOptions']['source'];
        }
        if (! empty($validated['imageOptions']['model'])) {
            $imageOptions['model'] = $validated['imageOptions']['model'];
        }
        if (! empty($validated['imageOptions']['style'])) {
            $imageOptions['style'] = $validated['imageOptions']['style'];
        }
        if (! empty($imageOptions)) {
            $data['imageOptions'] = $imageOptions;
        }

        // Handle cardOptions (dimensions)
        if (! empty($validated['cardOptions']['dimensions'])) {
            $data['cardOptions'] = [
                'dimensions' => $validated['cardOptions']['dimensions'],
            ];
        }

        // Make API request
        $response = Http::connectTimeout(10)->timeout(45)->withHeaders([
            'X-API-KEY'      => $this->apiKey,
            'Content-Type'   => 'application/json',
        ])->post($this->apiUrl, $data);

        if (! $response->successful()) {
            return (object) [
                'msg'  => trans('Failed to start presentation generation'),
                'type' => 'error',
                'data' => $response->json(),
            ];
        }
     

        // Return response with proper structure
        $result = $response->json();
        $generationId = $result['generationId'] ?? null;

        if (! $generationId) {
            return (object) [
                'msg'  => trans('No generation ID received'),
                'type' => 'error',
                'data' => $result,
            ];
        }

        // Store the presentation record
        $this->storePresentationRecord($generationId, $data);

        return (object) [
            'msg'  => trans('Presentation generation started successfully'),
            'type' => 'success',
            'data' => [
                'generationId' => $generationId,
                'status'       => 'processing',
            ],
        ];
    }

    /** @return array{0:string,1:string} */
    private function normalizeGammaLanguage(string $requested): array
    {
        $raw = trim($requested);
        $normalized = strtolower(str_replace('_', '-', $raw));
        $aliases = [
            'english' => 'en', 'en-us' => 'en', 'british-english' => 'en-gb',
            'somali' => 'so', 'som' => 'so', 'arabic' => 'ar', 'french' => 'fr',
            'german' => 'de', 'italian' => 'it', 'spanish' => 'es', 'turkish' => 'tr',
            'urdu' => 'ur', 'swahili' => 'sw',
        ];
        $normalized = $aliases[$normalized] ?? $normalized;

        $supported = [
            'en', 'en-gb', 'en-in', 'es', 'es-es', 'es-mx', 'es-419', 'ca', 'fr',
            'zh-cn', 'zh-tw', 'ko', 'ja', 'ja-da', 'pt-br', 'pt-pt', 'de', 'it',
            'ru', 'pl', 'uk', 'ro', 'hu', 'cs', 'el', 'tr', 'ar', 'ar-sa', 'he',
            'fa', 'nl', 'sv', 'da', 'nb', 'fi', 'id', 'vi', 'hi', 'gu', 'mr',
            'te', 'bn', 'ta', 'ur', 'kn', 'ml', 'th', 'sr', 'hr', 'sq', 'bg',
            'sl', 'bs', 'mk', 'sw', 'ha', 'yo', 'tl', 'ms', 'kk', 'uz', 'af',
            'lv', 'lt', 'et', 'is', 'cy',
        ];

        $display = [
            'so' => 'Somali', 'en' => 'English', 'en-gb' => 'English', 'en-in' => 'English',
            'ar' => 'Arabic', 'ar-sa' => 'Arabic', 'fr' => 'French', 'de' => 'German',
            'it' => 'Italian', 'es' => 'Spanish', 'es-es' => 'Spanish', 'es-mx' => 'Spanish',
            'es-419' => 'Spanish', 'tr' => 'Turkish', 'ur' => 'Urdu', 'sw' => 'Swahili',
        ];

        $visibleLanguage = $display[$normalized] ?? ($raw !== '' ? $raw : 'English');
        $gammaLanguage = in_array($normalized, $supported, true) ? $normalized : 'en';

        return [$gammaLanguage, mb_substr($visibleLanguage, 0, 80)];
    }

    protected function storePresentationRecord(string $generationId, array $requestData): void
    {
        AiPresentation::create([
            'user_id'       => Auth::id(),
            'generation_id' => $generationId,
            'status'        => 'processing',
            'format'        => $requestData['format'] ?? 'presentation',
            'theme_name'    => $requestData['themeName'] ?? null,
            'num_cards'     => $requestData['numCards'] ?? null,
            'input_text'    => $requestData['inputText'] ?? null,
            'request_data'  => $requestData,
        ]);
    }

    public function getGenerationStatus(string $generationId): array
    {
        $response = Http::connectTimeout(8)->timeout(20)->withHeaders([
            'X-API-KEY' => $this->apiKey,
        ])->get("{$this->apiUrl}/{$generationId}");

        if (! $response->successful()) {
            throw new RuntimeException('Unable to read presentation status right now.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Presentation status returned an invalid response.');
        }

        return $payload;
    }

    public function updatePresentationStatus(string $generationId): ?AiPresentation
    {
        if (! Auth::check()) {
            return null;
        }

        return DB::transaction(function () use ($generationId) {
            // Lock only the current user's row to prevent cross-user status/download access.
            $presentation = AiPresentation::where('generation_id', $generationId)
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->first();

            if (! $presentation) {
                return null;
            }

            $status = $this->getGenerationStatus($generationId);

            $updateData = [
                'response_data' => $status,
            ];

            $providerStatus = strtolower(trim((string) ($status['status'] ?? '')));
            if (in_array($providerStatus, ['completed', 'complete', 'success', 'done'], true)) {
                $updateData['status'] = 'completed';
            } elseif (in_array($providerStatus, ['failed', 'error', 'cancelled', 'canceled', 'expired'], true)) {
                $updateData['status'] = 'failed';
            } elseif ($providerStatus !== '') {
                $updateData['status'] = 'processing';
            }

            if (isset($status['gammaUrl'])) {
                $updateData['gamma_url'] = $status['gammaUrl'];
            }

          $exportAs = data_get($presentation->request_data, 'exportAs', 'pptx');

            if ($exportAs === 'pdf') {
                if (isset($status['pdfUrl']) || isset($status['exportUrl'])) {
                    $pdfUrl = $status['pdfUrl'] ?? $status['exportUrl'];

                    // Only fetch & save the PDF if we don't already have one
                    if (empty($presentation->pdf_url)) {
                        $response = Http::connectTimeout(10)->timeout(60)->get($pdfUrl);
                        if ($response->failed()) {
                            throw new RuntimeException('Failed to download PDF');
                        }

                        $directory = 'media/other/u-' . auth()->id() . '/presentations';
                        $filename = $generationId . '.pdf';

                        if (! Storage::disk('public')->exists($directory)) {
                            Storage::disk('public')->makeDirectory($directory);
                        }

                        Storage::disk('public')->put($directory . '/' . $filename, $response->body());
                        $updateData['pdf_url'] = '/uploads/' . $directory . '/' . $filename;
                    }
                }
            } else {
                if (isset($status['pptxUrl']) || isset($status['exportUrl'])) {
                    $pptxUrl = $status['pptxUrl'] ?? $status['exportUrl'];

                    // Only fetch & save the PPTX if we don't already have one
                    if (empty($presentation->pptx_url)) {
                        $response = Http::connectTimeout(10)->timeout(60)->get($pptxUrl);
                        if ($response->failed()) {
                            throw new RuntimeException('Failed to download PPTX');
                        }

                        $directory = 'media/other/u-' . auth()->id() . '/presentations';
                        $filename = $generationId . '.pptx';

                        if (! Storage::disk('public')->exists($directory)) {
                            Storage::disk('public')->makeDirectory($directory);
                        }

                        Storage::disk('public')->put($directory . '/' . $filename, $response->body());
                        $updateData['pptx_url'] = '/uploads/' . $directory . '/' . $filename;
                    }
                }
            }

            if (isset($status['error'])) {
                $updateData['error_message'] = $status['error'];
                $updateData['status'] = 'failed';
            }

            if (in_array($updateData['status'] ?? '', ['completed', 'failed'])) {
                $updateData['completed_at'] = $presentation->completed_at ?? now();
            }

            // ---- Idempotent credit deduction ----
            if (isset($status['credits']['deducted'])) {
                $deductedCredits = (float) $status['credits']['deducted'];

                // Only deduct if we haven't already
                $alreadyDeducted = ! is_null($presentation->credits_deducted_at)
                    && $presentation->credits_deducted_amount > 0;

                if (! $alreadyDeducted && $deductedCredits > 0) {
                    Entity::driver(EntityEnum::GAMMA_AI)
                        ->inputPresentation($deductedCredits)
                        ->calculateCredit()
                        ->decreaseCredit();

                    $updateData['credits_deducted_amount'] = $deductedCredits;
                    $updateData['credits_deducted_at'] = now();
                }
            }
            // -------------------------------------

            $presentation->update($updateData);

            return $presentation->fresh();
        });
    }
}
