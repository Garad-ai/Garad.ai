<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Http\Controllers\AIController;
use App\Services\Ai\OpenAI\Image\CreateImageEditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GaradImageToolServiceV5
{
    public function create(array $arguments): string
    {
        if (! Auth::check()) {
            throw new RuntimeException(
                'Please sign in before generating images.'
            );
        }

        $rawPrompt = trim(
            (string) ($arguments['prompt'] ?? '')
        );

        if ($rawPrompt === '') {
            throw new RuntimeException(
                'Image prompt is required.'
            );
        }

        $brand = app(
            GaradBrandContextService::class
        );

        $assets = app(
            GaradReferenceAssetService::class
        );

        $reference =
            $assets->bestImageForPrompt(
                $rawPrompt
            );

        $resolvedBrand =
            $brand->resolve();

        if (
            ! $reference &&
            ($resolvedBrand['selected'] ?? false)
        ) {
            $reference =
                $assets->brandLogo();
        }

        $prompt =
            $brand->augmentPrompt(
                $rawPrompt,
                'image/design'
            );

        if ($reference) {
            return $this
                ->editFromReferenceWithSafeFallback(
                    $prompt,
                    $reference
                );
        }

        return $this
            ->generateWithoutReference(
                $prompt
            );
    }

    private function editFromReferenceWithSafeFallback(
        string $prompt,
        string $reference
    ): string {
        $entity =
            $this->editEntity();

        $driver =
            Entity::driver($entity)
                ->inputImageCount(1)
                ->calculateCredit();

        $hasCredit =
            method_exists(
                $driver,
                'hasCreditBalanceForInput'
            )
                ? $driver
                    ->hasCreditBalanceForInput()
                : $driver
                    ->hasCreditBalance();

        if (! $hasCredit) {
            throw new RuntimeException(
                'You have no credits left for image generation.'
            );
        }

        $service =
            (new CreateImageEditService())
                ->setModel(
                    $entity->value
                )
                ->setPrompt(
                    $prompt
                )
                ->setSize('auto')
                ->setQuality('auto')
                ->setImages([
                    $reference,
                ]);

        $result =
            $service->generate();

        if (
            ! ($result['status'] ?? false)
        ) {
            throw new RuntimeException(
                (string) (
                    $result['message']
                    ??
                    'Image generation from the reference asset failed.'
                )
            );
        }

        $path = trim(
            (string) (
                $result['path']
                ?? ''
            )
        );

        if ($path === '') {
            throw new RuntimeException(
                'Image was generated but no output path was returned.'
            );
        }

        try {
            $publicUrl =
                $this
                    ->makePermanentPublicImage(
                        $path
                    );

            /*
             * Garad credits are charged only
             * after a usable image file exists.
             */
            $driver->decreaseCredit();

            return $this
                ->cleanResultMessage(
                    $publicUrl,
                    true
                );
        } catch (Throwable $e) {
            Log::warning(
                'Garad Chat Pro reference image output was unusable; using stable generation fallback.',
                [
                    'user_id' =>
                        Auth::id(),

                    'output' =>
                        mb_substr(
                            $path,
                            0,
                            500
                        ),

                    'error' =>
                        $e->getMessage(),
                ]
            );

            /*
             * Do not charge the failed
             * reference-image attempt here.
             *
             * Fall back to the existing stable
             * Garad image-generation pipeline.
             */
            return $this
                ->generateWithoutReference(
                    $prompt
                );
        }
    }

    private function generateWithoutReference(
        string $prompt
    ): string {
        $result = trim(
            (string) app(
                AIController::class
            )->generateImageWithOpenAI(
                $prompt
            )
        );

        if (
            $result === ''
            ||
            str_contains(
                mb_strtolower($result),
                'failed'
            )
        ) {
            throw new RuntimeException(
                $result !== ''
                    ? $result
                    : 'Image generation failed.'
            );
        }

        $candidate =
            $this->extractImageUrl(
                $result
            );

        if ($candidate !== null) {
            try {
                $publicUrl =
                    $this
                        ->makePermanentPublicImage(
                            $candidate
                        );

                return $this
                    ->cleanResultMessage(
                        $publicUrl,
                        false
                    );
            } catch (Throwable $e) {
                Log::warning(
                    'Garad Chat Pro could not normalize generated image URL.',
                    [
                        'user_id' =>
                            Auth::id(),

                        'candidate' =>
                            mb_substr(
                                $candidate,
                                0,
                                500
                            ),

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }

        /*
         * Compatibility fallback.
         *
         * Keep the original production response
         * if this provider returns a format that
         * does not expose an extractable URL.
         */
        return $result;
    }

    private function cleanResultMessage(
        string $url,
        bool $usedReference
    ): string {
        $encoded =
            $this->base64UrlEncode(
                $url
            );

        $lead =
            $usedReference
                ? 'Image created successfully using your selected brand/reference asset.'
                : 'Image created successfully.';

        return
            $lead
            . "\n\n"
            . "[[GARAD_IMAGE_RESULT:{$encoded}]]";
    }

    private function extractImageUrl(
        string $result
    ): ?string {
        $decoded =
            html_entity_decode(
                $result,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

        $patterns = [
            '/!\[[^\]]*\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/i',

            '/<img[^>]+src=["\']([^"\']+)["\']/i',

            '/href=["\']([^"\']+\.(?:png|jpe?g|webp|gif)(?:\?[^"\']*)?)["\']/i',

            '/(https?:\/\/[^\s<>"\']+\.(?:png|jpe?g|webp|gif)(?:\?[^\s<>"\']*)?)/i',

            '/(\/uploads\/[^\s<>"\']+\.(?:png|jpe?g|webp|gif)(?:\?[^\s<>"\']*)?)/i',
        ];

        foreach (
            $patterns as $pattern
        ) {
            if (
                preg_match(
                    $pattern,
                    $decoded,
                    $match
                ) === 1
            ) {
                $value = trim(
                    (string) (
                        $match[1]
                        ?? ''
                    )
                );

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function makePermanentPublicImage(
        string $source
    ): string {
        $source = trim(
            html_entity_decode(
                $source,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        if ($source === '') {
            throw new RuntimeException(
                'Generated image source is empty.'
            );
        }

        /*
         * Base64/data URI image.
         */
        if (
            str_starts_with(
                $source,
                'data:image/'
            )
        ) {
            return $this
                ->storeDataUri(
                    $source
                );
        }

        /*
         * HTTP/HTTPS image.
         */
        if (
            filter_var(
                $source,
                FILTER_VALIDATE_URL
            )
        ) {
            $parts =
                parse_url(
                    $source
                );

            $host =
                strtolower(
                    (string) (
                        $parts['host']
                        ?? ''
                    )
                );

            $path =
                (string) (
                    $parts['path']
                    ?? ''
                );

            $appHost =
                strtolower(
                    (string)
                    parse_url(
                        (string)
                        config('app.url'),
                        PHP_URL_HOST
                    )
                );

            $requestHost =
                strtolower(
                    (string)
                    request()?->getHost()
                );

            /*
             * URL points to our own public folder.
             */
            if (
                $path !== ''
                &&
                (
                    $host === $appHost
                    ||
                    $host === $requestHost
                )
            ) {
                $local =
                    public_path(
                        ltrim(
                            $path,
                            '/'
                        )
                    );

                if (
                    is_file($local)
                    &&
                    is_readable($local)
                ) {
                    return url(
                        '/'
                        .
                        ltrim(
                            $path,
                            '/'
                        )
                    );
                }
            }

            /*
             * External provider URL.
             *
             * Download it immediately and store
             * permanently inside Garad AI.
             */
            return $this
                ->downloadAndStoreRemoteImage(
                    $source
                );
        }

        /*
         * Relative public path.
         */
        $relative =
            '/'
            .
            ltrim(
                $source,
                '/'
            );

        $publicCandidate =
            public_path(
                ltrim(
                    $relative,
                    '/'
                )
            );

        if (
            is_file($publicCandidate)
            &&
            is_readable(
                $publicCandidate
            )
        ) {
            return url(
                $relative
            );
        }

        /*
         * Absolute local filesystem path.
         */
        if (
            is_file($source)
            &&
            is_readable($source)
        ) {
            $bytes =
                file_get_contents(
                    $source
                );

            if (
                $bytes === false
                ||
                $bytes === ''
            ) {
                throw new RuntimeException(
                    'Generated image file could not be read.'
                );
            }

            $mime =
                mime_content_type(
                    $source
                )
                ?: 'image/png';

            return $this
                ->storeImageBytes(
                    $bytes,
                    $mime
                );
        }

        throw new RuntimeException(
            'Generated image output does not point to a readable file.'
        );
    }

    private function downloadAndStoreRemoteImage(
        string $url
    ): string {
        $response =
            Http::timeout(25)
                ->connectTimeout(8)
                ->withHeaders([
                    'Accept' =>
                        'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',

                    'User-Agent' =>
                        'GaradAI-ImageFetcher/1.0',
                ])
                ->get(
                    $url
                );

        if (
            ! $response->successful()
        ) {
            throw new RuntimeException(
                'Generated image URL returned HTTP '
                .
                $response->status()
                .
                '.'
            );
        }

        $bytes =
            $response->body();

        if ($bytes === '') {
            throw new RuntimeException(
                'Generated image URL returned an empty response.'
            );
        }

        $mime =
            strtolower(
                trim(
                    (string)
                    $response->header(
                        'Content-Type'
                    )
                )
            );

        $mime =
            trim(
                explode(
                    ';',
                    $mime
                )[0]
                ?? ''
            );

        if (
            $mime === ''
            ||
            ! str_starts_with(
                $mime,
                'image/'
            )
        ) {
            $detected =
                (
                    new \finfo(
                        FILEINFO_MIME_TYPE
                    )
                )->buffer(
                    $bytes
                )
                ?: '';

            $mime =
                strtolower(
                    (string)
                    $detected
                );
        }

        if (
            ! str_starts_with(
                $mime,
                'image/'
            )
        ) {
            throw new RuntimeException(
                'Generated URL did not return an image.'
            );
        }

        return $this
            ->storeImageBytes(
                $bytes,
                $mime
            );
    }

    private function storeDataUri(
        string $dataUri
    ): string {
        if (
            preg_match(
                '/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s',
                $dataUri,
                $match
            ) !== 1
        ) {
            throw new RuntimeException(
                'Generated image data URI is invalid.'
            );
        }

        $bytes =
            base64_decode(
                (string) $match[2],
                true
            );

        if (
            $bytes === false
            ||
            $bytes === ''
        ) {
            throw new RuntimeException(
                'Generated image data could not be decoded.'
            );
        }

        return $this
            ->storeImageBytes(
                $bytes,
                strtolower(
                    (string)
                    $match[1]
                )
            );
    }

    private function storeImageBytes(
        string $bytes,
        string $mime
    ): string {
        /*
         * 25 MB safety limit.
         */
        if (
            strlen($bytes)
            >
            25 * 1024 * 1024
        ) {
            throw new RuntimeException(
                'Generated image is too large to store safely.'
            );
        }

        $extension =
            match (
                strtolower($mime)
            ) {
                'image/jpeg',
                'image/jpg' =>
                    'jpg',

                'image/webp' =>
                    'webp',

                'image/gif' =>
                    'gif',

                'image/avif' =>
                    'avif',

                default =>
                    'png',
            };

        $userId =
            (int)
            Auth::id();

        $directory =
            public_path(
                "uploads/media/images/u-{$userId}"
            );

        File::ensureDirectoryExists(
            $directory,
            0755,
            true
        );

        $filename =
            Str::uuid()
                ->toString()
            .
            '-garad-chat-pro.'
            .
            $extension;

        $absolute =
            $directory
            .
            DIRECTORY_SEPARATOR
            .
            $filename;

        if (
            File::put(
                $absolute,
                $bytes,
                true
            ) === false
        ) {
            throw new RuntimeException(
                'Generated image could not be saved to local storage.'
            );
        }

        @chmod(
            $absolute,
            0644
        );

        return url(
            "/uploads/media/images/u-{$userId}/{$filename}"
        );
    }

    private function base64UrlEncode(
        string $value
    ): string {
        return rtrim(
            strtr(
                base64_encode(
                    $value
                ),
                '+/',
                '-_'
            ),
            '='
        );
    }

    private function editEntity(): EntityEnum
    {
        $configured =
            strtolower(
                trim(
                    (string)
                    setting(
                        'ai_image_pro_edit_model',
                        'gpt-image-1.5'
                    )
                )
            );

        return match (
            $configured
        ) {
            'gpt-image-2' =>
                EntityEnum::GPT_IMAGE_2,

            'gpt-image-1' =>
                EntityEnum::GPT_IMAGE_1,

            default =>
                EntityEnum::GPT_IMAGE_1_5,
        };
    }
}