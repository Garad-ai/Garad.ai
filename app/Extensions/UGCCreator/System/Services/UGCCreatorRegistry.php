<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Models\SettingTwo;

/**
 * Registry of runtime settings + model catalog for UGC Creator.
 *
 * Holds setting keys, defaults, the fal.ai video model catalog, and the
 * camera-style preset accessors. Mirrors the shape of UGCFactoryRegistry.
 */
class UGCCreatorRegistry
{
    public const SETTING_DEFAULT_MODEL = 'ugc-creator-default-model';

    public const SETTING_ENABLED_MODELS = 'ugc-creator-enabled-models';

    public const SETTING_QUALITY = 'ugc-creator-quality';

    public const SETTING_MAX_UPLOAD_SIZE_MB = 'ugc-creator-max-upload-size-mb';

    public const SETTING_MAX_REFERENCE_IMAGES = 'ugc-creator-max-reference-images';

    public const SETTING_DISABLED_CAMERA_PRESETS = 'ugc-creator-disabled-camera-presets';

    public const SETTING_CAMERA_PRESET_LABEL_OVERRIDES = 'ugc-creator-camera-preset-label-overrides';

    public const DEFAULT_MODEL = EntityEnum::VEO_3_1_REFERENCE_TO_VIDEO->value;

    public const DEFAULT_QUALITY = 'standard';

    public const DEFAULT_MAX_UPLOAD_SIZE_MB = 5;

    public const DEFAULT_MAX_REFERENCE_IMAGES = 4;

    public const DEFAULT_VOICEOVER_PROVIDER = 'openai';

    public const QUALITIES = ['standard', 'high'];

    public const VOICEOVER_PROVIDERS = ['openai', 'elevenlabs'];

    /**
     * fal.ai video models offered by UGC Creator.
     *
     * All entries are image-to-video — UGC Creator always has at least one
     * reference image (a user product/character, or the camera-preset fallback).
     * Payload shapes:
     *   - "reference_array" → image_urls: [...]            (multi-image)
     *   - "image_only"      → image_url                    (single image)
     *   - "start_end"       → start_image_url (+ optional end_image_url)
     *   - "image_end"       → image_url (+ optional end_image_url)
     *
     * Schema:
     *  - `label`          human-readable label for selectors
     *  - `entity`         the EntityEnum case (credit deduction + unit price)
     *  - `endpoint`       fal.ai queue path (relative to https://queue.fal.run/)
     *  - `supports_audio` whether the model has native synced audio (Veo / Kling v3 / Seedance 2.0)
     *  - `max_refs`       maximum number of reference images the model accepts
     *  - `payload_shape`  how the job maps refs into the fal payload
     */
    public const VIDEO_MODELS = [
        // Reference-to-Video (true multi-ref)
        'veo3.1/reference-to-video' => [
            'label'          => 'Veo 3.1 Reference-to-Video',
            'entity'         => EntityEnum::VEO_3_1_REFERENCE_TO_VIDEO,
            'endpoint'       => 'fal-ai/veo3.1/reference-to-video',
            'supports_audio' => true,
            'max_refs'       => 3,
            'payload_shape'  => 'reference_array',
        ],
        'bytedance/seedance-2.0/reference-to-video' => [
            'label'          => 'Seedance 2.0 Reference-to-Video',
            'entity'         => EntityEnum::SEEDANCE_2_RTV,
            'endpoint'       => 'fal-ai/bytedance/seedance-2.0/reference-to-video',
            'supports_audio' => true,
            'max_refs'       => 9,
            'payload_shape'  => 'reference_array',
        ],
        'bytedance/seedance-2.0/fast/reference-to-video' => [
            'label'          => 'Seedance 2.0 Fast Reference-to-Video',
            'entity'         => EntityEnum::SEEDANCE_2_FAST_RTV,
            'endpoint'       => 'fal-ai/bytedance/seedance-2.0/fast/reference-to-video',
            'supports_audio' => true,
            'max_refs'       => 9,
            'payload_shape'  => 'reference_array',
        ],

        // Veo 3.1 Image-to-Video (single image_url)
        'veo3.1/image-to-video' => [
            'label'          => 'Veo 3.1 Image-to-Video',
            'entity'         => EntityEnum::VEO_3_1_IMAGE_TO_VIDEO,
            'endpoint'       => 'fal-ai/veo3.1/image-to-video',
            'supports_audio' => true,
            'max_refs'       => 1,
            'payload_shape'  => 'image_only',
        ],
        'veo3.1/fast/image-to-video' => [
            'label'          => 'Veo 3.1 Fast Image-to-Video',
            'entity'         => EntityEnum::VEO_3_1_IMAGE_TO_VIDEO_FAST,
            'endpoint'       => 'fal-ai/veo3.1/fast/image-to-video',
            'supports_audio' => true,
            'max_refs'       => 1,
            'payload_shape'  => 'image_only',
        ],
        'veo3.1/lite/image-to-video' => [
            'label'          => 'Veo 3.1 Lite Image-to-Video',
            'entity'         => EntityEnum::VEO_3_1_LITE_IMAGE_TO_VIDEO,
            'endpoint'       => 'fal-ai/veo3.1/lite/image-to-video',
            'supports_audio' => true,
            'max_refs'       => 1,
            'payload_shape'  => 'image_only',
        ],

        // Kling v3 Start-End Frame (start_image_url + optional end_image_url)
        'kling-video/v3/pro/image-to-video' => [
            'label'          => 'Kling 3 Pro Image-to-Video',
            'entity'         => EntityEnum::KLING_3_PRO_ITV,
            'endpoint'       => 'fal-ai/kling-video/v3/pro/image-to-video',
            'supports_audio' => true,
            'max_refs'       => 2,
            'payload_shape'  => 'start_end',
        ],
        'kling-video/v3/standard/image-to-video' => [
            'label'          => 'Kling 3 Standard Image-to-Video',
            'entity'         => EntityEnum::KLING_3_STANDARD_ITV,
            'endpoint'       => 'fal-ai/kling-video/v3/standard/image-to-video',
            'supports_audio' => true,
            'max_refs'       => 2,
            'payload_shape'  => 'start_end',
        ],

        // Seedance Image-to-Video (image_url + optional end_image_url)
        'bytedance/seedance-2.0/image-to-video' => [
            'label'          => 'Seedance 2.0 Image-to-Video',
            'entity'         => EntityEnum::SEEDANCE_2_ITV,
            'endpoint'       => 'fal-ai/bytedance/seedance-2.0/image-to-video',
            'supports_audio' => true,
            'max_refs'       => 2,
            'payload_shape'  => 'image_end',
        ],
        'bytedance/seedance-2.0/fast/image-to-video' => [
            'label'          => 'Seedance 2.0 Fast Image-to-Video',
            'entity'         => EntityEnum::SEEDANCE_2_FAST_ITV,
            'endpoint'       => 'fal-ai/bytedance/seedance-2.0/fast/image-to-video',
            'supports_audio' => true,
            'max_refs'       => 2,
            'payload_shape'  => 'image_end',
        ],
    ];

    public static function getDefaultModel(): string
    {
        $value = (string) setting(self::SETTING_DEFAULT_MODEL, self::DEFAULT_MODEL);

        return array_key_exists($value, self::VIDEO_MODELS) ? $value : self::DEFAULT_MODEL;
    }

    /**
     * @return array<int, string>
     */
    public static function getEnabledModelKeys(): array
    {
        $raw = setting(self::SETTING_ENABLED_MODELS);

        if (! is_string($raw) || $raw === '') {
            return array_keys(self::VIDEO_MODELS);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return array_keys(self::VIDEO_MODELS);
        }

        $valid = array_values(array_intersect($decoded, array_keys(self::VIDEO_MODELS)));

        return $valid !== [] ? $valid : array_keys(self::VIDEO_MODELS);
    }

    /**
     * @return array<string, array{label: string, entity: EntityEnum, endpoint: string, kind: string, supports_audio: bool}>
     */
    public static function getEnabledModels(): array
    {
        $enabled = self::getEnabledModelKeys();

        return array_intersect_key(self::VIDEO_MODELS, array_flip($enabled));
    }

    public static function getModelEndpoint(string $key): ?string
    {
        return self::VIDEO_MODELS[$key]['endpoint'] ?? null;
    }

    public static function getModelEntity(string $key): ?EntityEnum
    {
        return self::VIDEO_MODELS[$key]['entity'] ?? null;
    }

    public static function modelSupportsAudio(string $key): bool
    {
        return (bool) (self::VIDEO_MODELS[$key]['supports_audio'] ?? false);
    }

    public static function getModelMaxRefs(string $key): int
    {
        return (int) (self::VIDEO_MODELS[$key]['max_refs'] ?? 1);
    }

    public static function getModelPayloadShape(string $key): string
    {
        return (string) (self::VIDEO_MODELS[$key]['payload_shape'] ?? 'reference_array');
    }

    public static function getQuality(): string
    {
        $value = (string) setting(self::SETTING_QUALITY, self::DEFAULT_QUALITY);

        return in_array($value, self::QUALITIES, true) ? $value : self::DEFAULT_QUALITY;
    }

    public static function getMaxUploadSizeMb(): int
    {
        $value = (int) setting(self::SETTING_MAX_UPLOAD_SIZE_MB, self::DEFAULT_MAX_UPLOAD_SIZE_MB);

        return $value > 0 ? $value : self::DEFAULT_MAX_UPLOAD_SIZE_MB;
    }

    public static function getMaxUploadSizeKb(): int
    {
        return self::getMaxUploadSizeMb() * 1024;
    }

    public static function getMaxReferenceImages(): int
    {
        $value = (int) setting(self::SETTING_MAX_REFERENCE_IMAGES, self::DEFAULT_MAX_REFERENCE_IMAGES);

        return $value > 0 ? $value : self::DEFAULT_MAX_REFERENCE_IMAGES;
    }

    /**
     * All camera-style presets, with admin label overrides applied.
     *
     * @return array<int, array{key: string, label: string, default_label: string, image: string|null, prompt_injection: string}>
     */
    public static function getAllCameraPresets(): array
    {
        $presets = require __DIR__ . '/../Data/camera-presets.php';
        $overrides = self::getCameraPresetLabelOverrides();

        return array_map(static function (array $preset) use ($overrides): array {
            $key = (string) $preset['key'];
            $default = (string) $preset['label'];
            $override = isset($overrides[$key]) ? trim((string) $overrides[$key]) : '';

            return [
                'key'              => $key,
                'label'            => $override !== '' ? $override : $default,
                'default_label'    => $default,
                'image'            => isset($preset['image']) ? custom_theme_url((string) $preset['image']) : null,
                'prompt_injection' => (string) ($preset['prompt_injection'] ?? ''),
            ];
        }, $presets);
    }

    /**
     * @return array<int, string>
     */
    public static function getDisabledCameraPresetKeys(): array
    {
        $raw = setting(self::SETTING_DISABLED_CAMERA_PRESETS);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * @return array<string, string>
     */
    public static function getCameraPresetLabelOverrides(): array
    {
        $raw = setting(self::SETTING_CAMERA_PRESET_LABEL_OVERRIDES);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{key: string, label: string, default_label: string, image: string|null, prompt_injection: string}>
     */
    public static function getEnabledCameraPresets(): array
    {
        $disabled = self::getDisabledCameraPresetKeys();

        if ($disabled === []) {
            return self::getAllCameraPresets();
        }

        return array_values(array_filter(
            self::getAllCameraPresets(),
            static fn (array $p): bool => ! in_array($p['key'], $disabled, true),
        ));
    }

    public static function isCameraPresetEnabled(string $key): bool
    {
        foreach (self::getEnabledCameraPresets() as $preset) {
            if ($preset['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    public static function getCameraPresetInjection(string $key): string
    {
        foreach (self::getAllCameraPresets() as $preset) {
            if ($preset['key'] === $key) {
                return (string) $preset['prompt_injection'];
            }
        }

        return '';
    }

    /**
     * Raw (un-resolved) image path for a camera preset, e.g.
     * "/vendor/ugc-creator/images/camera-presets/auto.webp". Used as a fallback
     * `image_url` for image-to-video models when the user doesn't upload a
     * product or character reference.
     */
    public static function getCameraPresetImagePath(string $key): ?string
    {
        $presets = require __DIR__ . '/../Data/camera-presets.php';

        foreach ($presets as $preset) {
            if (($preset['key'] ?? null) === $key) {
                $image = $preset['image'] ?? null;

                return is_string($image) && $image !== '' ? $image : null;
            }
        }

        return null;
    }

    /**
     * Derive the TTS provider for UGC Creator from the central settings — OpenAI
     * wins when both are enabled. Falls back to the default so admins who haven't
     * configured a central TTS provider still get a working voiceover.
     */
    public static function getVoiceoverProvider(): string
    {
        $central = SettingTwo::getCache();

        if (! empty($central->feature_tts_openai) && in_array('openai', self::VOICEOVER_PROVIDERS, true)) {
            return 'openai';
        }

        if (! empty($central->feature_tts_elevenlabs) && in_array('elevenlabs', self::VOICEOVER_PROVIDERS, true)) {
            return 'elevenlabs';
        }

        return self::DEFAULT_VOICEOVER_PROVIDER;
    }
}
