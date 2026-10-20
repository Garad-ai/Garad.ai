<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

final class GaradBrandContextService
{
    private ?array $resolvedCache = null;

    public function resolve(): array
    {
        if ($this->resolvedCache !== null) {
            return $this->resolvedCache;
        }

        $brand = [
            'selected' => false,
            'visual_identity' => false,

            'company_id' => null,
            'product_id' => null,

            'name' => null,
            'industry' => null,
            'description' => null,
            'website' => null,
            'tagline' => null,

            'brand_color' => null,

            'logo' => null,
            'public_logo_url' => null,
            'public_logo_path' => null,

            'target_audience' => null,
            'specific_instructions' => null,
            'tone_of_voice' => null,

            'product_name' => null,
            'product_type' => null,
            'product_description' => null,
            'product_features' => null,

            /*
            |--------------------------------------------------------------------------
            | Uploaded logo
            |--------------------------------------------------------------------------
            */

            'uploaded_logo' => null,
            'uploaded_logo_url' => null,
            'uploaded_logo_path' => null,

            /*
            |--------------------------------------------------------------------------
            | Effective logo
            |--------------------------------------------------------------------------
            |
            | Priority:
            |
            | 1. Uploaded logo
            | 2. Selected Brand Voice company logo
            |
            */

            'effective_logo' => null,
            'effective_logo_url' => null,
            'effective_logo_path' => null,
            'logo_source' => null,
        ];

        if (! Auth::check()) {
            return $this->resolvedCache = $brand;
        }

        /*
        |--------------------------------------------------------------------------
        | Detect uploaded logo/reference first
        |--------------------------------------------------------------------------
        */

        $uploadedLogo = $this->resolveUploadedLogo();

        /*
        |--------------------------------------------------------------------------
        | Selected Brand Voice
        |--------------------------------------------------------------------------
        */

        $companyId = (int) request()->input(
            'chat_brand_voice',
            0
        );

        $company = null;

        if (
            $companyId > 0
            && class_exists(Company::class)
        ) {
            $company = Company::query()
                ->whereKey($companyId)
                ->where(
                    'user_id',
                    Auth::id()
                )
                ->first();
        }

        /*
        |--------------------------------------------------------------------------
        | Company
        |--------------------------------------------------------------------------
        */

        if ($company) {
            $brand = array_merge(
                $brand,
                [
                    'selected' => true,

                    'company_id' =>
                        (int) $company->id,

                    'name' =>
                        $this->clean(
                            $company->name
                        ),

                    'industry' =>
                        $this->clean(
                            $company->industry
                        ),

                    'description' =>
                        $this->clean(
                            $company->description
                        ),

                    'website' =>
                        $this->clean(
                            $company->website
                        ),

                    'tagline' =>
                        $this->clean(
                            $company->tagline
                        ),

                    'brand_color' =>
                        $this->normalizeColor(
                            $company->brand_color
                        ),

                    'logo' =>
                        $this->clean(
                            $company->logo
                        ),

                    'target_audience' =>
                        $this->clean(
                            data_get(
                                $company,
                                'target_audience'
                            )
                        ),

                    'specific_instructions' =>
                        $this->clean(
                            $company
                                ->specific_instructions
                        ),

                    'tone_of_voice' =>
                        $this->clean(
                            data_get(
                                $company,
                                'tone_of_voice'
                            )
                        ),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Company logo
            |--------------------------------------------------------------------------
            */

            $brand['public_logo_url'] =
                $this->publicLogoUrl(
                    $brand['logo']
                );

            $brand['public_logo_path'] =
                $this->publicLogoPath(
                    $brand['logo']
                );

            /*
            |--------------------------------------------------------------------------
            | Selected Product / Service
            |--------------------------------------------------------------------------
            */

            $productId = (int) request()->input(
                'brand_voice_prod',
                0
            );

            if (
                $productId > 0
                && class_exists(Product::class)
            ) {
                $product = Product::query()
                    ->whereKey($productId)
                    ->where(
                        'user_id',
                        Auth::id()
                    )
                    ->where(
                        'company_id',
                        $company->id
                    )
                    ->first();

                if ($product) {
                    $brand = array_merge(
                        $brand,
                        [
                            'product_id' =>
                                (int) $product->id,

                            'product_name' =>
                                $this->clean(
                                    $product->name
                                ),

                            'product_type' =>
                                ((int) $product->type === 0)
                                    ? 'service'
                                    : 'product',

                            'product_description' =>
                                $this->clean(
                                    $product->description
                                ),

                            'product_features' =>
                                $this->clean(
                                    $product->key_features
                                ),
                        ]
                    );
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Uploaded logo
        |--------------------------------------------------------------------------
        */

        if ($uploadedLogo !== null) {
            $brand['uploaded_logo'] =
                $uploadedLogo['relative']
                ?? $uploadedLogo['url']
                ?? null;

            $brand['uploaded_logo_url'] =
                $uploadedLogo['url']
                ?? null;

            $brand['uploaded_logo_path'] =
                $uploadedLogo['path']
                ?? null;
        }

        /*
        |--------------------------------------------------------------------------
        | Effective Logo Priority
        |--------------------------------------------------------------------------
        |
        | User-uploaded logo wins.
        |
        */

        if (
            $brand['uploaded_logo_url']
            || $brand['uploaded_logo_path']
        ) {
            $brand['effective_logo'] =
                $brand['uploaded_logo'];

            $brand['effective_logo_url'] =
                $brand['uploaded_logo_url'];

            $brand['effective_logo_path'] =
                $brand['uploaded_logo_path'];

            $brand['logo_source'] =
                'upload';

        } elseif (
            $brand['public_logo_url']
            || $brand['public_logo_path']
        ) {
            $brand['effective_logo'] =
                $brand['logo'];

            $brand['effective_logo_url'] =
                $brand['public_logo_url'];

            $brand['effective_logo_path'] =
                $brand['public_logo_path'];

            $brand['logo_source'] =
                'brand_voice';
        }

        /*
        |--------------------------------------------------------------------------
        | Visual identity active
        |--------------------------------------------------------------------------
        */

        $brand['visual_identity'] =
            (bool) (
                $brand['selected']
                || $brand['effective_logo_url']
                || $brand['effective_logo_path']
                || $brand['brand_color']
            );

        return $this->resolvedCache =
            $brand;
    }

    /*
    |--------------------------------------------------------------------------
    | Universal Brand Prompt
    |--------------------------------------------------------------------------
    */

    public function augmentPrompt(
        string $prompt,
        string $medium
    ): string {
        $prompt = trim(
            $prompt
        );

        $brand =
            $this->resolve();

        if (
            ! $this->hasBrandContext(
                $brand
            )
        ) {
            return $prompt;
        }

        $medium =
            strtolower(
                trim(
                    $medium
                )
            );

        $lines = [
            '',
            '--- BRAND IDENTITY ---',

            'Apply the available brand identity consistently to the requested '
            . (
                $medium !== ''
                    ? $medium
                    : 'content'
            )
            . '.',
        ];

        $this->append(
            $lines,
            'Company',
            $brand['name']
        );

        $this->append(
            $lines,
            'Industry',
            $brand['industry']
        );

        $this->append(
            $lines,
            'Tagline',
            $brand['tagline']
        );

        $this->append(
            $lines,
            'Primary brand color',
            $brand['brand_color']
        );

        $this->append(
            $lines,
            'Tone of voice',
            $brand['tone_of_voice']
        );

        $this->append(
            $lines,
            'Target audience',
            $brand['target_audience']
        );

        $this->append(
            $lines,
            'Company description',
            $brand['description']
        );

        $this->append(
            $lines,
            'Specific brand instructions',
            $brand['specific_instructions']
        );

        $this->append(
            $lines,
            'Website',
            $brand['website']
        );

        $this->append(
            $lines,
            'Selected product/service',
            $brand['product_name']
        );

        $this->append(
            $lines,
            'Product/service description',
            $brand['product_description']
        );

        $this->append(
            $lines,
            'Product/service features',
            $brand['product_features']
        );

        /*
        |--------------------------------------------------------------------------
        | Effective Logo
        |--------------------------------------------------------------------------
        */

        if (
            $brand['effective_logo_url']
        ) {
            $lines[] =
                'Official/effective logo asset URL: '
                . $brand[
                    'effective_logo_url'
                ];

            $lines[] =
                'Logo source: '
                . (
                    $brand['logo_source']
                    === 'upload'

                        ? 'user-uploaded logo for this request'

                        : 'selected Brand Voice company logo'
                )
                . '.';

            $lines[] =
                'Preserve the exact logo identity. '
                . 'Do not redraw, reinterpret, replace, '
                . 'or invent a different logo.';
        }

        /*
        |--------------------------------------------------------------------------
        | Brand Color
        |--------------------------------------------------------------------------
        */

        if (
            $brand['brand_color']
        ) {
            $lines[] =
                'Use '
                . $brand['brand_color']
                . ' as the primary visual accent while keeping contrast '
                . 'and readability professional. '
                . 'Do not invent additional colors as official brand colors.';
        }

        /*
        |--------------------------------------------------------------------------
        | Medium-specific visual rules
        |--------------------------------------------------------------------------
        */

        foreach (
            $this->mediumInstructions(
                $medium,
                $brand
            )
            as $instruction
        ) {
            $lines[] =
                $instruction;
        }

        $lines[] =
            'The original user request remains authoritative. '
            . 'Branding should support the requested subject, not replace it.';

        $lines[] =
            '--- END BRAND IDENTITY ---';

        return trim(
            $prompt
            . "\n"
            . implode(
                "\n",
                $lines
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation compatibility
    |--------------------------------------------------------------------------
    */

    public function summaryForPresentation(): string
    {
        return $this
            ->presentationAdditionalInstructions();
    }

    /*
    |--------------------------------------------------------------------------
    | Image Brand Summary
    |--------------------------------------------------------------------------
    */

    public function summaryForImage(): string
    {
        $brand =
            $this->resolve();

        if (
            ! $this->hasBrandContext(
                $brand
            )
        ) {
            return '';
        }

        $parts = [
            'Use the available brand identity faithfully and professionally.',
        ];

        foreach (
            [
                'Company' =>
                    $brand['name'],

                'Primary color' =>
                    $brand['brand_color'],

                'Tagline' =>
                    $brand['tagline'],

                'Tone' =>
                    $brand['tone_of_voice'],

                'Target audience' =>
                    $brand['target_audience'],

                'Brand instructions' =>
                    $brand[
                        'specific_instructions'
                    ],

                'Effective logo' =>
                    $brand[
                        'effective_logo_url'
                    ],
            ]
            as $label => $value
        ) {
            if ($value) {
                $parts[] =
                    $label
                    . ': '
                    . $value
                    . '.';
            }
        }

        if (
            $brand['effective_logo_url']
        ) {
            $parts[] =
                'Use the exact supplied logo asset when the image pipeline '
                . 'supports image references; do not redraw or invent the logo.';
        }

        return mb_substr(
            implode(
                ' ',
                $parts
            ),
            0,
            4000
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Video Brand Summary
    |--------------------------------------------------------------------------
    */

    public function summaryForVideo(): string
    {
        $brand =
            $this->resolve();

        if (
            ! $this->hasBrandContext(
                $brand
            )
        ) {
            return '';
        }

        $parts = [
            'Use the available brand identity consistently throughout the video.',
        ];

        foreach (
            [
                'Company' =>
                    $brand['name'],

                'Primary color' =>
                    $brand['brand_color'],

                'Tagline' =>
                    $brand['tagline'],

                'Tone' =>
                    $brand['tone_of_voice'],

                'Target audience' =>
                    $brand['target_audience'],

                'Brand instructions' =>
                    $brand[
                        'specific_instructions'
                    ],

                'Effective logo' =>
                    $brand[
                        'effective_logo_url'
                    ],
            ]
            as $label => $value
        ) {
            if ($value) {
                $parts[] =
                    $label
                    . ': '
                    . $value
                    . '.';
            }
        }

        if (
            $brand['effective_logo_url']
        ) {
            $parts[] =
                'Use the exact logo only when the selected video model/pipeline '
                . 'supports an image or logo reference. '
                . 'Otherwise preserve the brand palette and reserve clean '
                . 'logo-safe placement rather than hallucinating a replacement mark.';
        }

        return mb_substr(
            implode(
                ' ',
                $parts
            ),
            0,
            4000
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation Brand Instructions
    |--------------------------------------------------------------------------
    */

    public function presentationAdditionalInstructions(): string
    {
        $brand =
            $this->resolve();

        if (
            ! $this->hasBrandContext(
                $brand
            )
        ) {
            return '';
        }

        $parts = [
            'Use the available company brand consistently and professionally across the presentation.',
        ];

        if (
            $brand['name']
        ) {
            $parts[] =
                'Company: '
                . $brand['name']
                . '.';
        }

        if (
            $brand['brand_color']
        ) {
            $parts[] =
                'Primary brand color: '
                . $brand['brand_color']
                . '. Use it as the main accent for headings, highlights, '
                . 'charts and visual emphasis while preserving readability.';
        }

        if (
            $brand['tone_of_voice']
        ) {
            $parts[] =
                'Tone: '
                . $brand[
                    'tone_of_voice'
                ]
                . '.';
        }

        if (
            $brand['target_audience']
        ) {
            $parts[] =
                'Target audience: '
                . $brand[
                    'target_audience'
                ]
                . '.';
        }

        if (
            $brand['tagline']
        ) {
            $parts[] =
                'Tagline: '
                . $brand['tagline']
                . '.';
        }

        if (
            $brand[
                'specific_instructions'
            ]
        ) {
            $parts[] =
                'Brand instructions: '
                . $brand[
                    'specific_instructions'
                ];
        }

        if (
            $brand[
                'effective_logo_url'
            ]
        ) {
            $parts[] =
                'Official/effective logo asset: '
                . $brand[
                    'effective_logo_url'
                ]
                . '. Use this exact logo where appropriate, '
                . 'especially on the title and closing slides; '
                . 'do not recreate or substitute it.';
        }

        return mb_substr(
            implode(
                ' ',
                $parts
            ),
            0,
            5000
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Effective Logo Reference
    |--------------------------------------------------------------------------
    |
    | Image/video services can use this directly.
    |
    | Local file path is preferred.
    |
    */

    public function effectiveLogoReference(): ?string
    {
        $brand =
            $this->resolve();

        return
            $brand[
                'effective_logo_path'
            ]
            ?: $brand[
                'effective_logo_url'
            ]
            ?: null;
    }

    public function effectiveLogoUrl(): ?string
    {
        return
            $this->resolve()[
                'effective_logo_url'
            ]
            ?: null;
    }

    public function effectiveLogoPath(): ?string
    {
        return
            $this->resolve()[
                'effective_logo_path'
            ]
            ?: null;
    }

    public function brandColor(): ?string
    {
        return
            $this->resolve()[
                'brand_color'
            ]
            ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    */

    public function metadata(): array
    {
        $brand =
            $this->resolve();

        return [
            'company_id' =>
                $brand['company_id'],

            'product_id' =>
                $brand['product_id'],

            'company_name' =>
                $brand['name'],

            'brand_color' =>
                $brand['brand_color'],

            'logo_source' =>
                $brand['logo_source'],

            'effective_logo_url' =>
                $brand[
                    'effective_logo_url'
                ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Medium Rules
    |--------------------------------------------------------------------------
    */

    private function mediumInstructions(
        string $medium,
        array $brand
    ): array {
        return match ($medium) {
            'image',
            'design' => [
                'For visual design, use the brand color as the primary accent '
                . 'and keep the composition clean, premium and readable.',

                $brand['effective_logo_url']
                    ? 'If the image model supports image-reference input, use the supplied effective logo asset as the exact logo reference.'
                    : 'Do not invent a logo when no exact logo asset is available.',
            ],

            'video' => [
                'Carry the brand palette, tone and visual identity consistently '
                . 'across scenes, titles, lower thirds and end frames.',

                $brand['effective_logo_url']
                    ? 'Use the exact supplied logo only through a supported image/logo-reference path; never hallucinate a substitute logo.'
                    : 'Do not invent a logo when no exact logo asset is available.',
            ],

            'presentation',
            'slides',
            'pptx' => [
                'Use a coherent presentation system: branded title slide, '
                . 'consistent accent color, restrained visual hierarchy, '
                . 'readable charts, and a branded closing slide.',

                $brand['effective_logo_url']
                    ? 'Use the exact supplied logo asset on appropriate slides without redrawing or altering its identity.'
                    : 'Do not invent a logo when no exact logo asset is available.',
            ],

            default => [],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Brand Context Exists?
    |--------------------------------------------------------------------------
    */

    private function hasBrandContext(
        array $brand
    ): bool {
        return (bool) (
            $brand['selected']
            || $brand['visual_identity']
            || $brand[
                'effective_logo_url'
            ]
            || $brand[
                'effective_logo_path'
            ]
            || $brand['brand_color']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Uploaded Logo Resolver
    |--------------------------------------------------------------------------
    */

    private function resolveUploadedLogo(): ?array
    {
        /*
        |--------------------------------------------------------------------------
        | Direct request URL/path
        |--------------------------------------------------------------------------
        */

        $direct =
            $this
                ->directUploadedLogoReference();

        if (
            $direct !== null
        ) {
            return $direct;
        }

        /*
        |--------------------------------------------------------------------------
        | Uploaded files
        |--------------------------------------------------------------------------
        */

        $files =
            $this->flattenUploadedFiles(
                request()->allFiles()
            );

        if (
            $files === []
        ) {
            return null;
        }

        $explicitIntent =
            $this
                ->uploadedLogoIntent();

        $fallbackImage =
            null;

        foreach (
            $files
            as $item
        ) {
            $file =
                $item['file'];

            $key =
                strtolower(
                    $item['key']
                );

            $original =
                strtolower(
                    (string)
                        $file
                            ->getClientOriginalName()
                );

            if (
                ! $this
                    ->isSafeLogoUpload(
                        $file
                    )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Automatic detection
            |--------------------------------------------------------------------------
            |
            | Examples:
            |
            | logo.png
            | arkaan-logo.webp
            | brand_mark.png
            |
            */

            $looksLikeLogo =
                (bool) preg_match(
                    '/(?:^|[._\-\s])'
                    . '(logo|brand|identity|mark|astaanta|sumad)'
                    . '(?:$|[._\-\s])/iu',

                    $key
                    . ' '
                    . $original
                );

            if (
                $looksLikeLogo
            ) {
                return
                    $this
                        ->persistUploadedLogo(
                            $file
                        );
            }

            /*
            |--------------------------------------------------------------------------
            | Fallback candidate
            |--------------------------------------------------------------------------
            */

            if (
                $fallbackImage ===
                null
            ) {
                $fallbackImage =
                    $file;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Prompt explicitly says uploaded image is logo
        |--------------------------------------------------------------------------
        */

        if (
            $explicitIntent
            && $fallbackImage
                instanceof UploadedFile
        ) {
            return
                $this
                    ->persistUploadedLogo(
                        $fallbackImage
                    );
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Direct Logo Reference
    |--------------------------------------------------------------------------
    */

    private function directUploadedLogoReference(): ?array
    {
        /*
        |--------------------------------------------------------------------------
        | URL fields
        |--------------------------------------------------------------------------
        */

        foreach (
            [
                'brand_logo_url',
                'uploaded_logo_url',
                'logo_reference_url',
                'brand_reference_url',
            ]
            as $key
        ) {
            $value =
                $this->clean(
                    request()->input(
                        $key
                    )
                );

            if (
                $value
                && $this
                    ->isSafeHttpUrl(
                        $value
                    )
            ) {
                return [
                    'url' =>
                        $value,

                    'path' =>
                        null,

                    'relative' =>
                        null,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Local path/reference fields
        |--------------------------------------------------------------------------
        */

        foreach (
            [
                'brand_logo_path',
                'uploaded_logo_path',
                'logo_reference',
                'brand_reference',
            ]
            as $key
        ) {
            $value =
                $this->clean(
                    request()->input(
                        $key
                    )
                );

            if (! $value) {
                continue;
            }

            if (
                $this
                    ->isSafeHttpUrl(
                        $value
                    )
            ) {
                return [
                    'url' =>
                        $value,

                    'path' =>
                        null,

                    'relative' =>
                        null,
                ];
            }

            $path =
                $this
                    ->publicLogoPath(
                        $value
                    );

            $url =
                $this
                    ->publicLogoUrl(
                        $value
                    );

            if (
                $path
                && $url
            ) {
                return [
                    'url' =>
                        $url,

                    'path' =>
                        $path,

                    'relative' =>
                        '/'
                        . ltrim(
                            $value,
                            '/'
                        ),
                ];
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Flatten request files
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int,array{key:string,file:UploadedFile}>
     */
    private function flattenUploadedFiles(
        array $files,
        string $prefix = ''
    ): array {
        $flat = [];

        foreach (
            $files
            as $key => $value
        ) {
            $path =
                $prefix === ''

                    ? (string) $key

                    : $prefix
                        . '.'
                        . $key;

            if (
                $value
                instanceof UploadedFile
            ) {
                $flat[] = [
                    'key' =>
                        $path,

                    'file' =>
                        $value,
                ];

                continue;
            }

            if (
                is_array(
                    $value
                )
            ) {
                $flat =
                    array_merge(
                        $flat,

                        $this
                            ->flattenUploadedFiles(
                                $value,
                                $path
                            )
                    );
            }
        }

        return $flat;
    }

    /*
    |--------------------------------------------------------------------------
    | Detect explicit logo intent
    |--------------------------------------------------------------------------
    */

    private function uploadedLogoIntent(): bool
    {
        foreach (
            [
                'use_uploaded_logo',
                'use_uploaded_as_logo',
                'use_logo_upload',
                'brand_logo_selected',
            ]
            as $key
        ) {
            if (
                filter_var(
                    request()->input(
                        $key
                    ),
                    FILTER_VALIDATE_BOOL
                )
            ) {
                return true;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Check prompt
        |--------------------------------------------------------------------------
        */

        $text = '';

        foreach (
            [
                'prompt',
                'message',
                'input',
                'chat_message',
            ]
            as $key
        ) {
            $candidate =
                trim(
                    (string)
                        request()->input(
                            $key,
                            ''
                        )
                );

            if (
                $candidate !== ''
            ) {
                $text .=
                    ' '
                    . $candidate;
            }
        }

        if (
            $text === ''
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | English + Somali logo intent
        |--------------------------------------------------------------------------
        */

        return (bool) preg_match(
            '/\b('
            . 'logo'
            . '|brand\s+logo'
            . '|brand\s+mark'
            . '|identity\s+mark'
            . ')\b'
            . '|astaanta'
            . '|sumadda'
            . '|sumad/iu',

            $text
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Uploaded logo validation
    |--------------------------------------------------------------------------
    */

    private function isSafeLogoUpload(
        UploadedFile $file
    ): bool {
        if (
            ! $file->isValid()
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Maximum 10 MB
        |--------------------------------------------------------------------------
        */

        $size =
            (int) $file->getSize();

        if (
            $size <= 0
            || $size >
                10 * 1024 * 1024
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Actual server detected MIME
        |--------------------------------------------------------------------------
        */

        $mime =
            strtolower(
                (string)
                    $file
                        ->getMimeType()
            );

        /*
        |--------------------------------------------------------------------------
        | Raster formats only for request-upload logos
        |--------------------------------------------------------------------------
        |
        | SVG is deliberately not accepted here because user-uploaded SVG
        | can contain active content.
        |
        */

        return in_array(
            $mime,
            [
                'image/png',
                'image/jpeg',
                'image/webp',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Persist uploaded logo reference
    |--------------------------------------------------------------------------
    */

    private function persistUploadedLogo(
        UploadedFile $file
    ): ?array {
        try {
            $mime =
                strtolower(
                    (string)
                        $file
                            ->getMimeType()
                );

            $extension =
                match ($mime) {
                    'image/png' =>
                        'png',

                    'image/jpeg' =>
                        'jpg',

                    'image/webp' =>
                        'webp',

                    default =>
                        null,
                };

            if (
                $extension ===
                null
            ) {
                return null;
            }

            $userId =
                (int) Auth::id();

            /*
            |--------------------------------------------------------------------------
            | Per-user directory
            |--------------------------------------------------------------------------
            */

            $relativeDirectory =
                'uploads/media/brand-references/u-'
                . $userId;

            $absoluteDirectory =
                public_path(
                    $relativeDirectory
                );

            File::ensureDirectoryExists(
                $absoluteDirectory,
                0755,
                true
            );

            /*
            |--------------------------------------------------------------------------
            | Random safe filename
            |--------------------------------------------------------------------------
            */

            $filename =
                now()->format(
                    'YmdHis'
                )
                . '-'
                . Str::uuid()
                    ->toString()
                . '.'
                . $extension;

            $absolutePath =
                $absoluteDirectory
                . DIRECTORY_SEPARATOR
                . $filename;

            $sourcePath =
                $file->getRealPath();

            if (
                ! $sourcePath
                || ! is_file(
                    $sourcePath
                )
            ) {
                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | COPY instead of MOVE
            |--------------------------------------------------------------------------
            |
            | This does not interfere with the normal Chat Pro upload flow.
            |
            */

            if (
                ! File::copy(
                    $sourcePath,
                    $absolutePath
                )
            ) {
                return null;
            }

            @chmod(
                $absolutePath,
                0644
            );

            $relative =
                '/'
                . trim(
                    $relativeDirectory,
                    '/'
                )
                . '/'
                . $filename;

            return [
                'url' =>
                    url(
                        $relative
                    ),

                'path' =>
                    $absolutePath,

                'relative' =>
                    $relative,
            ];

        } catch (Throwable) {
            /*
            |--------------------------------------------------------------------------
            | Brand reference failure must never crash Chat Pro
            |--------------------------------------------------------------------------
            */

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function append(
        array &$lines,
        string $label,
        mixed $value
    ): void {
        $value =
            $this->clean(
                $value
            );

        if (
            $value !==
            null
        ) {
            $lines[] =
                $label
                . ': '
                . $value;
        }
    }

    private function clean(
        mixed $value
    ): ?string {
        if (
            $value === null
            || is_array(
                $value
            )
            || is_object(
                $value
            )
        ) {
            return null;
        }

        $value =
            trim(
                (string)
                    $value
            );

        if (
            $value === ''
        ) {
            return null;
        }

        $value =
            str_replace(
                [
                    "\r\n",
                    "\r",
                    "\n",
                    "\t",
                ],
                ' ',
                $value
            );

        while (
            str_contains(
                $value,
                '  '
            )
        ) {
            $value =
                str_replace(
                    '  ',
                    ' ',
                    $value
                );
        }

        return mb_substr(
            $value,
            0,
            5000
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Color
    |--------------------------------------------------------------------------
    */

    private function normalizeColor(
        mixed $value
    ): ?string {
        $value =
            strtoupper(
                trim(
                    (string)
                        $value
                )
            );

        if (
            $value === ''
        ) {
            return null;
        }

        if (
            ! str_starts_with(
                $value,
                '#'
            )
        ) {
            $value =
                '#'
                . $value;
        }

        /*
        |--------------------------------------------------------------------------
        | #ABC -> #AABBCC
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/^#[0-9A-F]{3}$/',
                $value
            )
        ) {
            return sprintf(
                '#%1$s%1$s%2$s%2$s%3$s%3$s',
                $value[1],
                $value[2],
                $value[3]
            );
        }

        return preg_match(
            '/^#[0-9A-F]{6}$/',
            $value
        )
            ? $value
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Public Logo URL
    |--------------------------------------------------------------------------
    */

    private function publicLogoUrl(
        ?string $logo
    ): ?string {
        $logo =
            trim(
                (string)
                    $logo
            );

        if (
            $logo === ''
        ) {
            return null;
        }

        if (
            $this
                ->isSafeHttpUrl(
                    $logo
                )
        ) {
            return $logo;
        }

        $path =
            $this
                ->publicLogoPath(
                    $logo
                );

        if (! $path) {
            return null;
        }

        try {
            return url(
                '/'
                . ltrim(
                    $logo,
                    '/'
                )
            );
        } catch (Throwable) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Local logo absolute path
    |--------------------------------------------------------------------------
    */

    private function publicLogoPath(
        ?string $logo
    ): ?string {
        $logo =
            trim(
                (string)
                    $logo
            );

        if (
            $logo === ''
            || $this
                ->isSafeHttpUrl(
                    $logo
                )
        ) {
            return null;
        }

        try {
            $publicRoot =
                realpath(
                    public_path()
                );

            if (
                ! $publicRoot
            ) {
                return null;
            }

            $candidate =
                realpath(
                    public_path(
                        ltrim(
                            $logo,
                            '/'
                        )
                    )
                );

            if (
                ! $candidate
                || ! is_file(
                    $candidate
                )
            ) {
                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent path traversal outside /public
            |--------------------------------------------------------------------------
            */

            $rootPrefix =
                rtrim(
                    $publicRoot,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR;

            if (
                ! str_starts_with(
                    $candidate,
                    $rootPrefix
                )
            ) {
                return null;
            }

            return $candidate;

        } catch (Throwable) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP URL Security
    |--------------------------------------------------------------------------
    */

    private function isSafeHttpUrl(
        string $url
    ): bool {
        if (
            ! filter_var(
                $url,
                FILTER_VALIDATE_URL
            )
        ) {
            return false;
        }

        $scheme =
            strtolower(
                (string)
                    parse_url(
                        $url,
                        PHP_URL_SCHEME
                    )
            );

        return in_array(
            $scheme,
            [
                'http',
                'https',
            ],
            true
        );
    }
}