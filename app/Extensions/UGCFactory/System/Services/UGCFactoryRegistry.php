<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Models\SettingTwo;

/**
 * Registry of runtime settings for UGC Factory.
 *
 * Holds setting keys, defaults, and accessors backed by the global
 * `setting()` store. Mirrors the shape of AIPhotoshootImageModelRegistry —
 * extend with new constants/getters as UGC Factory grows.
 */
class UGCFactoryRegistry
{
    public const SETTING_MAX_UPLOAD_SIZE_MB = 'ugc-factory-max-upload-size-mb';

    public const SETTING_RESOLUTION = 'ugc-factory-resolution';

    public const SETTING_ACTOR_IMAGE_MODEL = 'ugc-factory-actor-image-model';

    public const SETTING_ACTOR_PROMPT_TEMPLATE = 'ugc-factory-actor-prompt-template';

    public const SETTING_DISABLED_PRESETS = 'ugc-factory-disabled-presets';

    public const SETTING_PRESET_LABEL_OVERRIDES = 'ugc-factory-preset-label-overrides';

    public const DEFAULT_MAX_UPLOAD_SIZE_MB = 5;

    public const DEFAULT_VOICEOVER_PROVIDER = 'openai';

    public const DEFAULT_RESOLUTION = '720p';

    public const DEFAULT_ACTOR_IMAGE_MODEL = 'gpt-image-2';

    public const VOICEOVER_PROVIDERS = ['openai', 'elevenlabs'];

    public const RESOLUTIONS = ['480p', '720p'];

    /**
     * Models offered for AI actor generation, grouped by provider.
     *
     * Order = display order. The default is set at the top of each group.
     */
    public const ACTOR_IMAGE_MODELS = [
        // OpenAI gpt-image family — synchronous, returns base64 directly
        'gpt-image-2'   => ['label' => 'GPT Image 2', 'provider' => 'openai'],
        'gpt-image-1.5' => ['label' => 'GPT Image 1.5', 'provider' => 'openai'],
        'gpt-image-1'   => ['label' => 'GPT Image 1', 'provider' => 'openai'],
        // fal.ai async queue
        'nano-banana-pro'         => ['label' => 'Nano Banana Pro', 'provider' => 'fal'],
        'nano-banana-2'           => ['label' => 'Nano Banana 2', 'provider' => 'fal'],
        'xai/grok-imagine-image'  => ['label' => 'Grok Imagine', 'provider' => 'fal'],
    ];

    /**
     * The default system prompt wrapping the user-supplied actor description.
     *
     * Use {prompt} as the placeholder for the user's text. Phrased to bias the
     * model toward photorealistic, vertical, single-subject UGC actor framing.
     */
    public const DEFAULT_ACTOR_PROMPT_TEMPLATE = <<<'PROMPT'
Photorealistic vertical 9:16 portrait of a single human content creator for a UGC-style social media video. Clearly visible face, eyes looking directly at camera, natural soft lighting, candid expression, in-context background appropriate to the described setting, no text or watermarks, no logos, no group shots, no studio backdrop unless explicitly requested.

Subject and setting:
{prompt}
PROMPT;

    public static function getMaxUploadSizeMb(): int
    {
        $value = (int) setting(self::SETTING_MAX_UPLOAD_SIZE_MB, self::DEFAULT_MAX_UPLOAD_SIZE_MB);

        return $value > 0 ? $value : self::DEFAULT_MAX_UPLOAD_SIZE_MB;
    }

    public static function getMaxUploadSizeKb(): int
    {
        return self::getMaxUploadSizeMb() * 1024;
    }

    /**
     * Derive the TTS provider for UGC Factory from the centralized
     * /dashboard/admin/settings/tts flags. OpenAI wins when both are enabled.
     * Falls back to the default if neither flag is set so admins who haven't
     * touched the central settings still get a working voiceover.
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

    public static function getResolution(): string
    {
        $value = (string) setting(self::SETTING_RESOLUTION, self::DEFAULT_RESOLUTION);

        return in_array($value, self::RESOLUTIONS, true) ? $value : self::DEFAULT_RESOLUTION;
    }

    public static function getActorImageModel(): string
    {
        $value = (string) setting(self::SETTING_ACTOR_IMAGE_MODEL, self::DEFAULT_ACTOR_IMAGE_MODEL);

        return array_key_exists($value, self::ACTOR_IMAGE_MODELS) ? $value : self::DEFAULT_ACTOR_IMAGE_MODEL;
    }

    public static function getActorImageModelEnum(): EntityEnum
    {
        return EntityEnum::from(self::getActorImageModel());
    }

    public static function getActorImageProvider(): string
    {
        $model = self::getActorImageModel();

        return self::ACTOR_IMAGE_MODELS[$model]['provider']
            ?? self::ACTOR_IMAGE_MODELS[self::DEFAULT_ACTOR_IMAGE_MODEL]['provider'];
    }

    public static function getActorPromptTemplate(): string
    {
        $value = (string) setting(self::SETTING_ACTOR_PROMPT_TEMPLATE, '');

        return $value !== '' ? $value : self::DEFAULT_ACTOR_PROMPT_TEMPLATE;
    }

    /**
     * Wraps a user-supplied actor description with the configured system prompt.
     */
    public static function buildActorPrompt(string $userPrompt): string
    {
        $template = self::getActorPromptTemplate();
        $userPrompt = trim($userPrompt);

        if (str_contains($template, '{prompt}')) {
            return str_replace('{prompt}', $userPrompt, $template);
        }

        return rtrim($template) . "\n\n" . $userPrompt;
    }

    /**
     * All premade actor presets, with admin label overrides applied.
     *
     * @return array<int, array{key: string, label: string, default_label: string, image: string}>
     */
    public static function getAllPresets(): array
    {
        $presets = require __DIR__ . '/../Data/presets.php';
        $overrides = self::getPresetLabelOverrides();

        return array_map(static function (array $preset) use ($overrides): array {
            $key = (string) $preset['key'];
            $default = (string) $preset['label'];
            $override = isset($overrides[$key]) ? trim((string) $overrides[$key]) : '';

            return [
                'key'           => $key,
                'label'         => $override !== '' ? $override : $default,
                'default_label' => $default,
                'image'         => custom_theme_url($preset['image']),
            ];
        }, $presets);
    }

    /**
     * @return array<int, string>
     */
    public static function getDisabledPresetKeys(): array
    {
        $raw = setting(self::SETTING_DISABLED_PRESETS);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * @return array<string, string>
     */
    public static function getPresetLabelOverrides(): array
    {
        $raw = setting(self::SETTING_PRESET_LABEL_OVERRIDES);

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
     * Presets visible to end users — disabled ones are stripped.
     *
     * @return array<int, array{key: string, label: string, default_label: string, image: string}>
     */
    public static function getEnabledPresets(): array
    {
        $disabled = self::getDisabledPresetKeys();

        if ($disabled === []) {
            return self::getAllPresets();
        }

        return array_values(array_filter(
            self::getAllPresets(),
            static fn (array $p): bool => ! in_array($p['key'], $disabled, true),
        ));
    }

    public static function isPresetEnabled(string $key): bool
    {
        foreach (self::getEnabledPresets() as $preset) {
            if ($preset['key'] === $key) {
                return true;
            }
        }

        return false;
    }
}
