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
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GaradPresentationToolServiceV5
{
    private const MAX_SLIDES = 20;

    private const STALE_AFTER_MINUTES = 30;

    private const GAMMA_LANGUAGES = [
        'en', 'en-gb', 'en-in', 'es', 'es-es', 'es-mx', 'es-419', 'ca', 'fr',
        'zh-cn', 'zh-tw', 'ko', 'ja', 'ja-da', 'pt-br', 'pt-pt', 'de', 'it',
        'ru', 'pl', 'uk', 'ro', 'hu', 'cs', 'el', 'tr', 'ar', 'ar-sa', 'he',
        'fa', 'nl', 'sv', 'da', 'nb', 'fi', 'id', 'vi', 'hi', 'gu', 'mr',
        'te', 'bn', 'ta', 'ur', 'kn', 'ml', 'th', 'sr', 'hr', 'sq', 'bg',
        'sl', 'bs', 'mk', 'sw', 'ha', 'yo', 'tl', 'ms', 'kk', 'uz', 'af',
        'lv', 'lt', 'et', 'is', 'cy',
    ];

    public function create(array $arguments): string
    {
        if (! Auth::check()) {
            throw new RuntimeException(
                'Please sign in before creating presentations.'
            );
        }

        if (
            ! MarketplaceHelper::isRegistered('ai-presentation')
            || ! class_exists(GammaService::class)
        ) {
            throw new RuntimeException(
                'AI Presentation is not installed or enabled.'
            );
        }

        $topic = trim(
            (string) (
                $arguments['topic']
                ?? $arguments['description']
                ?? ''
            )
        );

        if ($topic === '') {
            throw new RuntimeException(
                'Presentation topic is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Brand Voice
        |--------------------------------------------------------------------------
        */

        $brand = app(
            GaradBrandContextService::class
        );

        $topic = $brand->augmentPrompt(
            $topic,
            'presentation'
        );

        /*
        |--------------------------------------------------------------------------
        | Slide Count
        |--------------------------------------------------------------------------
        */

        $slideCount = max(
            3,
            min(
                self::MAX_SLIDES,
                (int) (
                    $arguments['slide_count']
                    ?? 10
                )
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Language
        |--------------------------------------------------------------------------
        */

        $requestedLanguage = trim(
            (string) (
                $arguments['language']
                ?? app()->getLocale()
                ?? 'en'
            )
        ) ?: 'en';

        [
            $gammaLanguage,
            $visibleLanguage,
        ] = $this->normalizeLanguage(
            $requestedLanguage
        );

        /*
        |--------------------------------------------------------------------------
        | Export Format
        |--------------------------------------------------------------------------
        */

        $exportAs = strtolower(
            trim(
                (string) (
                    $arguments['export_as']
                    ?? 'pptx'
                )
            )
        );

        $exportAs = in_array(
            $exportAs,
            [
                'pptx',
                'pdf',
            ],
            true
        )
            ? $exportAs
            : 'pptx';

        /*
        |--------------------------------------------------------------------------
        | Text Length
        |--------------------------------------------------------------------------
        */

        $textLength = ucfirst(
            strtolower(
                trim(
                    (string) (
                        $arguments['text_length']
                        ?? 'Medium'
                    )
                )
            )
        );

        if (
            ! in_array(
                $textLength,
                [
                    'Brief',
                    'Medium',
                    'Detailed',
                    'Extensive',
                ],
                true
            )
        ) {
            $textLength = 'Medium';
        }

        /*
        |--------------------------------------------------------------------------
        | Image Source
        |--------------------------------------------------------------------------
        */

        $imageSource = trim(
            (string) (
                $arguments['image_source']
                ?? 'webFreeToUseCommercially'
            )
        );

        $allowedImageSources = [
            'aiGenerated',
            'pictographic',
            'pexels',
            'giphy',
            'webAllImages',
            'webFreeToUse',
            'webFreeToUseCommercially',
            'themeAccent',
            'placeholder',
            'noImages',
        ];

        if (
            ! in_array(
                $imageSource,
                $allowedImageSources,
                true
            )
        ) {
            $imageSource =
                'webFreeToUseCommercially';
        }

        /*
        |--------------------------------------------------------------------------
        | Language + Brand Instructions
        |--------------------------------------------------------------------------
        */

        $additional =
            $this->languageInstruction(
                $visibleLanguage
            );

        $brandSummary =
            $brand->summaryForPresentation();

        if ($brandSummary !== '') {
            $additional .=
                ' ' .
                $brandSummary;
        }

        /*
        |--------------------------------------------------------------------------
        | Gamma Payload
        |--------------------------------------------------------------------------
        */

        $validated = [
            'description' =>
                mb_substr(
                    $topic,
                    0,
                    390000
                ),

            'language' =>
                $gammaLanguage,

            'requested_language' =>
                $requestedLanguage,

            'additionalInstructions' =>
                mb_substr(
                    trim(
                        $additional
                    ),
                    0,
                    5000
                ),

            'theme' =>
                'Auto',

            'theme_id' =>
                'auto',

            'presentation_count' =>
                (string) $slideCount,

            'text_length' =>
                $textLength,

            'exportAs' =>
                $exportAs,

            'textMode' =>
                'generate',

            'format' =>
                'presentation',

            'cardSplit' =>
                'auto',

            'imageOptions' => [
                'source' =>
                    $imageSource,
            ],

            'cardOptions' => [
                'dimensions' =>
                    '16x9',
            ],

            'textOptions' => [],
        ];

        /*
        |--------------------------------------------------------------------------
        | Tone
        |--------------------------------------------------------------------------
        */

        $tone = trim(
            (string) (
                $arguments['tone']
                ?? ''
            )
        );

        if ($tone !== '') {
            $validated[
                'textOptions'
            ][
                'tone'
            ] = mb_substr(
                $tone,
                0,
                500
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Audience
        |--------------------------------------------------------------------------
        */

        $audience = trim(
            (string) (
                $arguments['audience']
                ?? ''
            )
        );

        if ($audience !== '') {
            $validated[
                'textOptions'
            ][
                'audience'
            ] = mb_substr(
                $audience,
                0,
                500
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Credits
        |--------------------------------------------------------------------------
        */

        $prediction =
            (
                new GammaCreditPredictor()
            )->predictCredits(
                $validated
            );

        $requiredCredits =
            (float) (
                $prediction['max']
                ?? 0
            );

        $driver =
            Entity::driver(
                EntityEnum::GAMMA_AI
            )
                ->inputPresentation(
                    $requiredCredits
                )
                ->calculateCredit();

        if (
            ! $driver
                ->hasCreditBalanceForInput()
        ) {
            throw new RuntimeException(
                'Not enough presentation credits for this request.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Start Gamma Generation
        |--------------------------------------------------------------------------
        */

        $response =
            app(
                GammaService::class
            )
                ->generatePresentation(
                    $validated
                );

        if (
            (
                $response->type
                ?? 'error'
            ) !==
            'success'
        ) {
            $detail =
                data_get(
                    $response,
                    'data.message'
                )
                ?: data_get(
                    $response,
                    'data.error.message'
                )
                ?: (
                    $response->msg
                    ?? 'Presentation generation failed.'
                );

            /*
            | Provider details remain private in server logs.
            */

            Log::error(
                'Garad Chat Pro presentation generation failed',
                [
                    'user_id' =>
                        Auth::id(),

                    'detail' =>
                        (string) $detail,
                ]
            );

            throw new RuntimeException(
                'The presentation service could not start the deck. Please ask an administrator to check its configuration.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Internal Generation ID
        |--------------------------------------------------------------------------
        */

        $generationId = trim(
            (string) data_get(
                $response,
                'data.generationId',
                ''
            )
        );

        if ($generationId === '') {
            Log::error(
                'Garad Chat Pro presentation generation returned no generation ID',
                [
                    'user_id' =>
                        Auth::id(),
                ]
            );

            throw new RuntimeException(
                'The presentation service could not start the deck. Please ask an administrator to check its configuration.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Clean Chat Response
        |--------------------------------------------------------------------------
        |
        | The two markers below are internal.
        |
        | garad_chatpro_v63.js detects them and removes them from the
        | visible conversation before rendering the animated presentation card.
        |
        */

        return
            "I'm creating the presentation you requested.\n\n"
            . "[[GARAD_PRESENTATION_JOB:{$generationId}]]\n"
            . "[[GARAD_PRESENTATION_META:{$slideCount}|{$exportAs}]]";
    }

    public function status(
        array $arguments
    ): string {
        if (! Auth::check()) {
            throw new RuntimeException(
                'Please sign in to check presentation status.'
            );
        }

        $generationId = trim(
            (string) (
                $arguments[
                    'generation_id'
                ]
                ?? ''
            )
        );

        if ($generationId === '') {
            throw new RuntimeException(
                'generation_id is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ownership Security
        |--------------------------------------------------------------------------
        */

        $owned =
            AiPresentation::query()
                ->where(
                    'generation_id',
                    $generationId
                )
                ->where(
                    'user_id',
                    Auth::id()
                )
                ->exists();

        if (! $owned) {
            throw new RuntimeException(
                'Presentation not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Presentation Status
        |--------------------------------------------------------------------------
        */

        $presentation =
            app(
                GammaService::class
            )
                ->updatePresentationStatus(
                    $generationId
                );

        if (! $presentation) {
            throw new RuntimeException(
                'Presentation not found.'
            );
        }

        $status =
            strtolower(
                trim(
                    (string)
                        $presentation
                            ->status
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Completed
        |--------------------------------------------------------------------------
        */

        if (in_array($status, ['completed', 'complete', 'success', 'done'], true)) {
            $url = (string) ($presentation->pptx_url ?: $presentation->pdf_url ?: '');

            if ($url !== '') {
                $safeUrl =
                    $this->safeResultUrl(
                        $url
                    );

                if (
                    $safeUrl !==
                    null
                ) {
                    return
                        "Presentation completed successfully.\n\n"
                        . "[Open / Download presentation]({$safeUrl})";
                }

                Log::warning(
                    'Garad Chat Pro presentation completed with unsafe result URL',
                    [
                        'user_id' =>
                            Auth::id(),

                        'generation_id' =>
                            $generationId,
                    ]
                );
            }

            throw new RuntimeException(
                'Presentation completed, but the provider did not return a downloadable presentation file.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Failed
        |--------------------------------------------------------------------------
        */

        if (in_array($status, ['failed', 'error', 'cancelled', 'canceled', 'expired'], true)) {
            Log::error(
                'Garad Chat Pro presentation generation failed after start',
                [
                    'user_id' =>
                        Auth::id(),

                    'generation_id' =>
                        $generationId,

                    'detail' =>
                        (string) (
                            $presentation
                                ->error_message
                            ?: 'Presentation generation failed.'
                        ),
                ]
            );

            throw new RuntimeException(
                'Presentation generation could not be completed. Please try again.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Processing
        |--------------------------------------------------------------------------
        |
        | Generation ID is intentionally not exposed to the user.
        |
        */

        if ($presentation->created_at && $presentation->created_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            $presentation->update([
                'status' => 'failed',
                'error_message' => 'Presentation generation timed out before the provider returned a usable result.',
                'completed_at' => now(),
            ]);

            throw new RuntimeException(
                'Presentation generation took too long and was stopped. Please try again.'
            );
        }

        return 'Presentation is still processing.';
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeLanguage(
        string $requested
    ): array {
        $raw =
            trim(
                $requested
            );

        $normalized =
            strtolower(
                str_replace(
                    '_',
                    '-',
                    $raw
                )
            );

        $aliases = [
            'english' =>
                'en',

            'en-us' =>
                'en',

            'british-english' =>
                'en-gb',

            'somali' =>
                'so',

            'som' =>
                'so',

            'arabic' =>
                'ar',

            'french' =>
                'fr',

            'german' =>
                'de',

            'italian' =>
                'it',

            'spanish' =>
                'es',

            'turkish' =>
                'tr',

            'urdu' =>
                'ur',

            'swahili' =>
                'sw',
        ];

        $normalized =
            $aliases[
                $normalized
            ]
            ?? $normalized;

        $display = [
            'so' =>
                'Somali',

            'en' =>
                'English',

            'en-gb' =>
                'English',

            'en-in' =>
                'English',

            'ar' =>
                'Arabic',

            'ar-sa' =>
                'Arabic',

            'fr' =>
                'French',

            'de' =>
                'German',

            'it' =>
                'Italian',

            'es' =>
                'Spanish',

            'es-es' =>
                'Spanish',

            'es-mx' =>
                'Spanish',

            'es-419' =>
                'Spanish',

            'tr' =>
                'Turkish',

            'ur' =>
                'Urdu',

            'sw' =>
                'Swahili',
        ];

        $visible =
            $display[
                $normalized
            ]
            ?? (
                $raw !== ''
                    ? $raw
                    : 'English'
            );

        /*
        |--------------------------------------------------------------------------
        | Gamma Enum Guard
        |--------------------------------------------------------------------------
        |
        | Somali is not passed directly as a Gamma enum because it is
        | unsupported there. Gamma receives "en", while the instructions
        | force every visible slide element into Somali.
        |
        */

        $gamma =
            in_array(
                $normalized,
                self::GAMMA_LANGUAGES,
                true
            )
                ? $normalized
                : 'en';

        return [
            $gamma,

            mb_substr(
                $visible,
                0,
                80
            ),
        ];
    }

    private function languageInstruction(
        string $visibleLanguage
    ): string {
        return
            "Write every visible slide title, subtitle, body paragraph, bullet, label, caption, chart label, callout, summary and conclusion in {$visibleLanguage}. "
            . 'Do not switch the visible presentation language to English unless the user requested English.';
    }

    /*
    |--------------------------------------------------------------------------
    | Result URL Security
    |--------------------------------------------------------------------------
    */

    private function safeResultUrl(
        string $url
    ): ?string {
        $url =
            trim(
                $url
            );

        if ($url === '') {
            return null;
        }

        /*
        | Garad local path.
        */

        if (
            str_starts_with(
                $url,
                '/'
            )
            &&
            ! str_starts_with(
                $url,
                '//'
            )
        ) {
            return $url;
        }

        /*
        | Remote result.
        */

        $scheme =
            strtolower(
                (string)
                    parse_url(
                        $url,
                        PHP_URL_SCHEME
                    )
            );

        if (
            in_array(
                $scheme,
                [
                    'http',
                    'https',
                ],
                true
            )
        ) {
            return $url;
        }

        return null;
    }
}