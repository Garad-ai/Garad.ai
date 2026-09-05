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
        // Build the base required data with correct API parameter names
        $data = [
            'inputText' => $prompt,
            // 'themeName'  => $validated['theme'],
            'numCards'   => (int) $validated['presentation_count'],
           'exportAs'   => $validated['exportAs'] ?? 'pptx',
        ];

        // Map text_length to textOptions.amount
        $textOptions = [
            'amount'   => strtolower($validated['text_length']),
            'language' => $validated['language'],
        ];

        // Add optional top-level fields
        if (! empty($validated['textMode'])) {
            $data['textMode'] = $validated['textMode'];
        }
      
        if (! empty($validated['format'])) {
            $data['format'] = $validated['format'];
        }

        if (! empty($validated['cardSplit'])) {
            $data['cardSplit'] = $validated['cardSplit'];
        }

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
        $response = Http::withHeaders([
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
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
        ])->get("{$this->apiUrl}/{$generationId}");

        return $response->json();
    }

    public function updatePresentationStatus(string $generationId): ?AiPresentation
    {
        return DB::transaction(function () use ($generationId) {
            // Lock the row to avoid race conditions from overlapping polls
            $presentation = AiPresentation::where('generation_id', $generationId)
                ->lockForUpdate()
                ->first();

            if (! $presentation) {
                return null;
            }

            $status = $this->getGenerationStatus($generationId);

            $updateData = [
                'response_data' => $status,
            ];

            if (isset($status['status'])) {
                $updateData['status'] = $status['status'];
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
                        $response = Http::get($pdfUrl);
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
                        $response = Http::get($pptxUrl);
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
