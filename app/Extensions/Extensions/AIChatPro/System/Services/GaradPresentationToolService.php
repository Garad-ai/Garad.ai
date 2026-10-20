<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiPresentation\System\Models\AiPresentation;
use App\Extensions\AiPresentation\System\Services\GammaCreditPredictor;
use App\Extensions\AiPresentation\System\Services\GammaService;
use App\Helpers\Classes\MarketplaceHelper;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class GaradPresentationToolService
{
    public function create(array $arguments): string
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in before creating presentations.');
        }

        if (! MarketplaceHelper::isRegistered('ai-presentation')) {
            throw new RuntimeException('AI Presentation is not available on this installation.');
        }

        $topic = trim((string) ($arguments['topic'] ?? $arguments['description'] ?? ''));
        if ($topic === '') {
            throw new RuntimeException('Presentation topic is required.');
        }

        $slideCount = max(1, min(10, (int) ($arguments['slide_count'] ?? 10)));
        $language = trim((string) ($arguments['language'] ?? app()->getLocale() ?? 'en')) ?: 'en';
        $language = mb_substr($language, 0, 10);
        $exportAs = strtolower((string) ($arguments['export_as'] ?? 'pptx'));
        $exportAs = in_array($exportAs, ['pptx', 'pdf'], true) ? $exportAs : 'pptx';

        $textLength = ucfirst(strtolower((string) ($arguments['text_length'] ?? 'Medium')));
        if (! in_array($textLength, ['Brief', 'Medium', 'Detailed', 'Extensive'], true)) {
            $textLength = 'Medium';
        }

        $validated = [
            'description'        => mb_substr($topic, 0, 400000),
            'language'           => $language,
            'theme'              => 'Auto',
            'theme_id'           => 'auto',
            'presentation_count' => (string) $slideCount,
            'text_length'        => $textLength,
            'exportAs'           => $exportAs,
            'imageOptions'       => [
                'source' => (string) ($arguments['image_source'] ?? 'webFreeToUseCommercially'),
            ],
            'textOptions'        => [],
        ];

        if (! empty($arguments['tone'])) {
            $validated['textOptions']['tone'] = mb_substr((string) $arguments['tone'], 0, 50);
        }
        if (! empty($arguments['audience'])) {
            $validated['textOptions']['audience'] = mb_substr((string) $arguments['audience'], 0, 50);
        }

        $predictor = new GammaCreditPredictor;
        $prediction = $predictor->predictCredits($validated);
        $driver = Entity::driver(EntityEnum::GAMMA_AI)
            ->inputPresentation((float) $prediction['max'])
            ->calculateCredit();

        if (! $driver->hasCreditBalanceForInput()) {
            throw new RuntimeException(sprintf(
                'Not enough presentation credits. At least %s credits are required for this request.',
                $prediction['max'],
            ));
        }

        $response = app(GammaService::class)->generatePresentation($validated);
        if (($response->type ?? 'error') !== 'success') {
            throw new RuntimeException((string) ($response->msg ?? 'Presentation generation failed.'));
        }

        $generationId = (string) data_get($response, 'data.generationId', '');
        if ($generationId === '') {
            throw new RuntimeException('Presentation generation did not return a generation ID.');
        }

        return sprintf(
            '✅ Presentation generation started. Generation ID: <strong>%s</strong>. Ask me to check the presentation status using this ID.',
            e($generationId),
        );
    }

    public function status(array $arguments): string
    {
        if (! Auth::check()) {
            throw new RuntimeException('Please sign in to check presentation status.');
        }

        $generationId = trim((string) ($arguments['generation_id'] ?? ''));
        if ($generationId === '') {
            throw new RuntimeException('generation_id is required.');
        }

        $owned = AiPresentation::query()
            ->where('generation_id', $generationId)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $owned) {
            throw new RuntimeException('Presentation not found.');
        }

        $presentation = app(GammaService::class)->updatePresentationStatus($generationId);
        if (! $presentation) {
            throw new RuntimeException('Presentation not found.');
        }

        if ($presentation->status === 'completed') {
            $url = $presentation->pptx_url ?: $presentation->pdf_url ?: $presentation->gamma_url;
            if ($url) {
                $safeUrl = e($url);

                return sprintf('✅ Presentation completed: <a href="%s" target="_blank" rel="noopener">Open / Download presentation</a>', $safeUrl);
            }

            return '✅ Presentation completed.';
        }

        if ($presentation->status === 'failed') {
            return '❌ Presentation generation failed: ' . e((string) ($presentation->error_message ?: 'Unknown error'));
        }

        return '⏳ Presentation is still processing. Generation ID: <strong>' . e($generationId) . '</strong>.';
    }
}
