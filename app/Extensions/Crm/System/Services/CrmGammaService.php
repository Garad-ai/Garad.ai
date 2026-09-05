<?php

namespace App\Extensions\Crm\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmPresentation;
use App\Extensions\Crm\System\Models\SalesInvoice;
use App\Helpers\Classes\ApiHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Self-contained Gamma (gamma.app) client for the CRM Presentation feature.
 *
 * Intentionally does not depend on the AiPresentation extension so the CRM
 * extension stays standalone; it only relies on the shared ApiHelper/settings.
 */
class CrmGammaService
{
    private const API_URL = 'https://public-api.gamma.app/v1.0/generations';

    private const DEFAULT_CARDS = 8;

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = ApiHelper::setGammaApiKey();
    }

    public static function isConfigured(): bool
    {
        $key = setting('gamma_api_secret');

        return is_string($key) && trim($key) !== '' && $key !== 'empty';
    }

    /**
     * Submit a generation request to Gamma and return the generationId.
     */
    public function generate(CrmDeal $deal, ?SalesInvoice $invoice, int $numCards = self::DEFAULT_CARDS): string
    {
        $payload = [
            'inputText'   => $this->buildInputText($deal, $invoice),
            'numCards'    => $numCards,
            'exportAs'    => 'pdf',
            'textOptions' => [
                'amount'   => 'detailed',
                'language' => 'en',
            ],
        ];

        $response = Http::withHeaders([
            'X-API-KEY'    => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post(self::API_URL, $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                (string) ($response->json('message') ?? __('Failed to start Gamma generation.'))
            );
        }

        $generationId = $response->json('generationId');

        if (! $generationId) {
            throw new RuntimeException(__('No generation ID received from Gamma.'));
        }

        return $generationId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(string $generationId): array
    {
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
        ])->get(self::API_URL . '/' . $generationId);

        return $response->json() ?? [];
    }

    /**
     * Poll Gamma, persist the result onto the presentation, and deduct credits once.
     */
    public function syncStatus(CrmPresentation $presentation): CrmPresentation
    {
        return DB::transaction(function () use ($presentation) {
            /** @var CrmPresentation $locked */
            $locked = CrmPresentation::where('id', $presentation->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->generation_id) {
                return $presentation;
            }

            $status = $this->getStatus($locked->generation_id);
            $updateData = [];

            if (! empty($status['gammaUrl'])) {
                $updateData['gamma_url'] = $status['gammaUrl'];
            }

            $pdfUrl = $status['pdfUrl'] ?? $status['exportUrl'] ?? null;

            if ($pdfUrl && empty($locked->pdf_url)) {
                $updateData['pdf_url'] = $this->downloadExport($pdfUrl, $locked, 'pdf');
            }

            $gammaStatus = $status['status'] ?? null;

            if (! empty($status['error'])) {
                $metadata = $locked->metadata ?? [];
                $metadata['error'] = (string) $status['error'];
                $updateData['metadata'] = $metadata;
                $updateData['status'] = 'failed';
            } elseif ($gammaStatus === 'completed') {
                $updateData['status'] = 'completed';
            }

            if (in_array($updateData['status'] ?? '', ['completed', 'failed'], true)) {
                $updateData['completed_at'] = $locked->completed_at ?? now();
            }

            // Idempotent credit deduction based on Gamma's reported usage.
            $deducted = (float) data_get($status, 'credits.deducted', 0);
            $alreadyDeducted = ! is_null($locked->credits_deducted_at) && $locked->credits_deducted_amount > 0;

            if ($deducted > 0 && ! $alreadyDeducted) {
                Entity::driver(EntityEnum::GAMMA_AI)
                    ->inputPresentation($deducted)
                    ->calculateCredit()
                    ->decreaseCredit();

                $updateData['credits_deducted_amount'] = $deducted;
                $updateData['credits_deducted_at'] = now();
            }

            if (! empty($updateData)) {
                $locked->update($updateData);
            }

            return $locked->fresh();
        });
    }

    private function downloadExport(string $url, CrmPresentation $presentation, string $extension): string
    {
        $response = Http::get($url);

        if ($response->failed()) {
            throw new RuntimeException(__('Failed to download the Gamma export.'));
        }

        $directory = 'media/other/u-' . $presentation->user_id . '/crm-presentations';
        $filename = $presentation->generation_id . '.' . $extension;

        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        Storage::disk('public')->put($directory . '/' . $filename, $response->body());

        return '/uploads/' . $directory . '/' . $filename;
    }

    private function buildInputText(CrmDeal $deal, ?SalesInvoice $invoice): string
    {
        $lines = [
            'Create a professional sales pitch deck for the following deal.',
            '',
            'Deal: ' . $deal->title,
        ];

        if ($deal->value) {
            $lines[] = 'Value: ' . $deal->value . ' ' . ($deal->currency ?? 'USD');
        }

        if ($deal->description) {
            $lines[] = 'Description: ' . $deal->description;
        }

        if ($deal->expected_close_date) {
            $lines[] = 'Expected close date: ' . $deal->expected_close_date->format('Y-m-d');
        }

        if ($deal->company) {
            $lines[] = '';
            $lines[] = 'Company: ' . $deal->company->name
                . ($deal->company->industry ? ' (' . $deal->company->industry . ')' : '');

            if ($deal->company->website) {
                $lines[] = 'Website: ' . $deal->company->website;
            }
        }

        if ($deal->contact) {
            $lines[] = 'Primary contact: ' . $deal->contact->full_name
                . ($deal->contact->job_title ? ', ' . $deal->contact->job_title : '');
        }

        if ($invoice) {
            $lines[] = '';
            $lines[] = 'Pricing (invoice ' . $invoice->invoice_number . ', total '
                . $invoice->total . ' ' . $invoice->currency . '):';

            foreach ($invoice->items as $item) {
                $lines[] = '- ' . $item->description . ': ' . $item->quantity . ' x '
                    . $item->unit_price . ' = ' . $item->total;
            }
        }

        return implode("\n", $lines);
    }
}
