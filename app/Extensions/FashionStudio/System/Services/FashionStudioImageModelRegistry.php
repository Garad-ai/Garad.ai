<?php

declare(strict_types=1);

namespace App\Extensions\FashionStudio\System\Services;

use App\Domains\Entity\Enums\EntityEnum;

/**
 * Registry of supported image generation models for Fashion Studio.
 *
 * Each entry pairs an EntityEnum-backed model with the provider that handles it
 * and metadata used by both the admin UI and the runtime dispatcher in
 * BaseFashionStudioController. To add a new model/provider, append an entry to
 * the MODELS map below — the controller will route it automatically based on
 * the `provider` field, no other changes required.
 *
 * @phpstan-type ModelMeta array{label: string, provider: 'fal'|'openai', supports_edit: bool, admin_selectable: bool}
 */
class FashionStudioImageModelRegistry
{
    public const PROVIDER_FAL = 'fal';

    public const PROVIDER_OPENAI = 'openai';

    public const SETTING_KEY = 'fashion-studio-image-default-model';

    public const SETTING_OPENAI_QUALITY = 'fashion-studio-openai-quality';

    public const SETTING_OPENAI_SIZE = 'fashion-studio-openai-size';

    /**
     * Allowed OpenAI image quality values for gpt-image-* models.
     *
     * @var array<int, string>
     */
    public const OPENAI_QUALITY_VALUES = ['low', 'medium', 'high', 'auto'];

    /**
     * Allowed OpenAI image size values. gpt-image-1 / 1.5 max out at 1536;
     * 2048+ and 3840 are gpt-image-2 only.
     *
     * @var array<int, string>
     */
    public const OPENAI_SIZE_VALUES = [
        '1024x1024',
        '1024x1536',
        '1536x1024',
        '2048x2048',
        '2048x1152',
        '3840x2160',
        '2160x3840',
        'auto',
    ];

    public const OPENAI_QUALITY_DEFAULT = 'low';

    public const OPENAI_SIZE_DEFAULT = '1024x1024';

    /**
     * Map of EntityEnum value => provider metadata.
     *
     * @return array<string, ModelMeta>
     */
    public static function models(): array
    {
        return [
            EntityEnum::NANO_BANANA->value => [
                'label'            => 'Nano Banana',
                'provider'         => self::PROVIDER_FAL,
                'supports_edit'    => false,
                'admin_selectable' => true,
            ],
            EntityEnum::NANO_BANANA_EDIT->value => [
                'label'            => 'Nano Banana (Edit)',
                'provider'         => self::PROVIDER_FAL,
                'supports_edit'    => true,
                'admin_selectable' => false,
            ],
            EntityEnum::NANO_BANANA_PRO->value => [
                'label'            => 'Nano Banana Pro',
                'provider'         => self::PROVIDER_FAL,
                'supports_edit'    => false,
                'admin_selectable' => true,
            ],
            EntityEnum::NANO_BANANA_PRO_EDIT->value => [
                'label'            => 'Nano Banana Pro (Edit)',
                'provider'         => self::PROVIDER_FAL,
                'supports_edit'    => true,
                'admin_selectable' => false,
            ],
            EntityEnum::NANO_BANANA_2->value => [
                'label'            => 'Nano Banana 2',
                'provider'         => self::PROVIDER_FAL,
                'supports_edit'    => false,
                'admin_selectable' => true,
            ],
            EntityEnum::NANO_BANANA_2_EDIT->value => [
                'label'            => 'Nano Banana 2 (Edit)',
                'provider'         => self::PROVIDER_FAL,
                'supports_edit'    => true,
                'admin_selectable' => false,
            ],
            EntityEnum::GPT_IMAGE_1->value => [
                'label'            => 'GPT Image 1',
                'provider'         => self::PROVIDER_OPENAI,
                'supports_edit'    => true,
                'admin_selectable' => true,
            ],
            EntityEnum::GPT_IMAGE_1_5->value => [
                'label'            => 'GPT Image 1.5',
                'provider'         => self::PROVIDER_OPENAI,
                'supports_edit'    => true,
                'admin_selectable' => true,
            ],
            EntityEnum::GPT_IMAGE_2->value => [
                'label'            => 'GPT Image 2',
                'provider'         => self::PROVIDER_OPENAI,
                'supports_edit'    => true,
                'admin_selectable' => true,
            ],
        ];
    }

    /**
     * Map of FAL nano-banana base models to their /edit counterparts.
     *
     * @return array<string, string>
     */
    protected static function editVariantMap(): array
    {
        return [
            EntityEnum::NANO_BANANA->value     => EntityEnum::NANO_BANANA_EDIT->value,
            EntityEnum::NANO_BANANA_PRO->value => EntityEnum::NANO_BANANA_PRO_EDIT->value,
            EntityEnum::NANO_BANANA_2->value   => EntityEnum::NANO_BANANA_2_EDIT->value,
        ];
    }

    /**
     * Resolve the admin-configured default model, falling back to NANO_BANANA_PRO.
     *
     * If a legacy/non-selectable value (e.g. an /edit variant) is stored, strip
     * the suffix to its base equivalent when possible, otherwise fall back.
     */
    public static function getDefaultModel(): EntityEnum
    {
        $fallback = EntityEnum::NANO_BANANA_PRO;
        $value = setting(self::SETTING_KEY, $fallback->value);

        if (! is_string($value) || ! array_key_exists($value, self::models())) {
            return $fallback;
        }

        $meta = self::models()[$value];

        if (($meta['admin_selectable'] ?? false) === false) {
            // Legacy value (likely an /edit variant) — try to derive the base.
            if (str_ends_with($value, '/edit')) {
                $base = substr($value, 0, -strlen('/edit'));
                $baseMeta = self::models()[$base] ?? null;
                if ($baseMeta && ($baseMeta['admin_selectable'] ?? false) === true) {
                    $resolved = EntityEnum::tryFrom($base) ?? $fallback;

                    return self::isModelEnabled($resolved->value) ? $resolved : $fallback;
                }
            }

            return $fallback;
        }

        if (! self::isModelEnabled($value)) {
            return $fallback;
        }

        return EntityEnum::tryFrom($value) ?? $fallback;
    }

    /**
     * Returns 'fal' or 'openai' for the given model. Defaults to FAL for unknown
     * models so existing in-flight FAL requests continue to resolve correctly.
     */
    public static function getProviderFor(EntityEnum $model): string
    {
        return self::models()[$model->value]['provider'] ?? self::PROVIDER_FAL;
    }

    /**
     * Returns whether a model supports image-editing (reference images).
     */
    public static function supportsEdit(EntityEnum $model): bool
    {
        return self::models()[$model->value]['supports_edit'] ?? false;
    }

    /**
     * Returns [enum_value => label] for use in admin select dropdowns.
     * Only entries flagged as admin_selectable are returned — the runtime
     * dispatcher will swap to the matching /edit variant automatically when
     * reference images are attached.
     *
     * @return array<string, string>
     */
    public static function getModelsForAdminSelect(): array
    {
        $out = [];
        foreach (self::models() as $value => $meta) {
            if (($meta['admin_selectable'] ?? false) !== true) {
                continue;
            }
            $out[$value] = $meta['label'];
        }

        return $out;
    }

    /**
     * Map of OpenAI image models to their global "enabled" setting key and default.
     * Models not in this map are always considered enabled.
     *
     * @return array<string, array{key: string, default: int}>
     */
    protected static function openAIEnabledFlags(): array
    {
        return [
            EntityEnum::GPT_IMAGE_1->value   => ['key' => 'enabled_gpt_image_1', 'default' => 0],
            EntityEnum::GPT_IMAGE_1_5->value => ['key' => 'enabled_gpt_image_1_5', 'default' => 0],
            EntityEnum::GPT_IMAGE_2->value   => ['key' => 'enabled_gpt_image_2', 'default' => 1],
        ];
    }

    /**
     * Whether the model is enabled globally. OpenAI gpt-image-* respect their
     * `enabled_gpt_image_*` admin toggles; all other models are always enabled.
     */
    public static function isModelEnabled(string $modelValue): bool
    {
        $flag = self::openAIEnabledFlags()[$modelValue] ?? null;

        if ($flag === null) {
            return true;
        }

        return (int) setting($flag['key'], $flag['default']) === 1;
    }

    /**
     * Resolve the runtime model for a generation request.
     *
     * For FAL nano-banana base models, returns the matching /edit variant when
     * reference images are attached. For OpenAI, unknown, or already-edit
     * models, returns the input unchanged.
     */
    /**
     * Admin-configured OpenAI image quality. Defaults to the cheapest setting
     * ('low'). Falls back to the default on any invalid stored value.
     */
    public static function getOpenAIQuality(): string
    {
        $value = setting(self::SETTING_OPENAI_QUALITY, self::OPENAI_QUALITY_DEFAULT);

        if (! is_string($value) || ! in_array($value, self::OPENAI_QUALITY_VALUES, true)) {
            return self::OPENAI_QUALITY_DEFAULT;
        }

        return $value;
    }

    /**
     * Admin-configured OpenAI image size. Defaults to the cheapest setting
     * ('1024x1024'). Falls back to the default on any invalid stored value.
     */
    public static function getOpenAISize(): string
    {
        $value = setting(self::SETTING_OPENAI_SIZE, self::OPENAI_SIZE_DEFAULT);

        if (! is_string($value) || ! in_array($value, self::OPENAI_SIZE_VALUES, true)) {
            return self::OPENAI_SIZE_DEFAULT;
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    public static function getOpenAIQualityOptions(): array
    {
        return [
            'low'    => 'Low (cheapest)',
            'medium' => 'Medium',
            'high'   => 'High',
            'auto'   => 'Auto',
        ];
    }

    /**
     * Snap the user's per-account resolution + ratio onto the closest valid
     * OpenAI image size. The OpenAI API only accepts a fixed set of sizes
     * (see OPENAI_SIZE_VALUES), so user picks like 2K portrait or 4K square
     * fall back to the nearest supported dimensions.
     *
     * Returns null when the user picked an `auto` ratio so callers can fall
     * back to the admin default (which itself may be 'auto').
     */
    public static function resolveOpenAISizeForUser(string $resolution, string $ratio): ?string
    {
        if ($ratio === 'auto') {
            return null;
        }

        $parts = explode(':', $ratio);

        if (count($parts) !== 2) {
            return null;
        }

        $w = (int) $parts[0];
        $h = (int) $parts[1];

        if ($w <= 0 || $h <= 0) {
            return null;
        }

        $aspect = $w / $h;

        // 5:4 (1.25) and 4:5 (0.8) are visually close to square; treat them
        // as square so the user gets the only square OpenAI option at their
        // resolution tier rather than a misleading 3:2/2:3 snap.
        $orientation = match (true) {
            $aspect >= 1.34 => 'landscape',
            $aspect <= 0.75 => 'portrait',
            default         => 'square',
        };

        return match ($resolution) {
            '1K' => match ($orientation) {
                'square'    => '1024x1024',
                'landscape' => '1536x1024',
                'portrait'  => '1024x1536',
            },
            '2K' => match ($orientation) {
                'square'    => '2048x2048',
                'landscape' => '2048x1152',
                // No 2K portrait in OPENAI_SIZE_VALUES; 1024x1536 is closer
                // to 2048 vertical than 2160x3840 is.
                'portrait'  => '1024x1536',
            },
            '4K' => match ($orientation) {
                // No 4K square in OPENAI_SIZE_VALUES; fall back to 2K square.
                'square'    => '2048x2048',
                'landscape' => '3840x2160',
                'portrait'  => '2160x3840',
            },
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function getOpenAISizeOptions(): array
    {
        return [
            '1024x1024' => '1024 x 1024 (square, cheapest)',
            '1024x1536' => '1024 x 1536 (portrait)',
            '1536x1024' => '1536 x 1024 (landscape)',
            '2048x2048' => '2048 x 2048 (square, GPT Image 2)',
            '2048x1152' => '2048 x 1152 (landscape, GPT Image 2)',
            '3840x2160' => '3840 x 2160 (4K landscape, GPT Image 2)',
            '2160x3840' => '2160 x 3840 (4K portrait, GPT Image 2)',
            'auto'      => 'Auto',
        ];
    }

    public static function resolveForRequest(EntityEnum $base, bool $hasReferenceImages): EntityEnum
    {
        if (! $hasReferenceImages) {
            return $base;
        }

        $editValue = self::editVariantMap()[$base->value] ?? null;

        if ($editValue === null) {
            return $base;
        }

        return EntityEnum::tryFrom($editValue) ?? $base;
    }
}
