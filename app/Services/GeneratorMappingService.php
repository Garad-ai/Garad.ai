<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Models\SettingTwo;

class GeneratorMappingService
{
    /**
     * Map an image generator identifier to its corresponding EntityEnum.
     *
     * Mirrors the resolution logic from AIController::imageOutput().
     */
    public function mapGeneratorToEntity(string $generator, ?string $action = null): ?EntityEnum
    {
        return match ($generator) {
            'openai'             => $this->getDefaultOpenAiImageModel(),
            'stable_diffusion'   => $this->getStableDiffusionDefaultModel(),
            'gpt-image-1',
            'gpt_image_1'       => EntityEnum::GPT_IMAGE_1,
            'gpt-image-1.5',
            'gpt-image-1-5',
            'gpt_image_1_5'     => EntityEnum::GPT_IMAGE_1_5,
            'flux-pro'          => $this->getDefaultFalAiModel(),
            'flux-pro-kontext',
            'flux-pro/kontext/text-to-image' => EntityEnum::FLUX_PRO_KONTEXT_TEXT_TO_IMAGE,
            'flux-pro/kontext'               => $this->resolveFluxProKontext($action),
            'nano-banana/edit'               => EntityEnum::NANO_BANANA_EDIT,
            'nano-banana-pro/edit'           => EntityEnum::NANO_BANANA_PRO_EDIT,
            'nano-banana-2/edit'             => EntityEnum::NANO_BANANA_2_EDIT,
            'ideogram',
            'ideogram-v2'                 => EntityEnum::IDEOGRAM,
            'nano-banana'                 => EntityEnum::NANO_BANANA,
            'nano-banana-pro'             => EntityEnum::NANO_BANANA_PRO,
            'nano-banana-2'               => EntityEnum::NANO_BANANA_2,
            'flux-2-flex'                 => EntityEnum::FLUX_2_FLEX,
            'flux-2-flex/edit'            => EntityEnum::FLUX_2_FLEX_EDIT,
            'flux/schnell'                => EntityEnum::FLUX_SCHNELL,
            'flux-pro/v1.1'               => EntityEnum::FLUX_PRO_1_1,
            'flux-realism'                => EntityEnum::FLUX_REALISM,
            'imagen4'                     => EntityEnum::IMAGEN_4,
            'seedream/v4/text-to-image'   => EntityEnum::SEEDREAM_4,
            'xai/grok-imagine-image'      => EntityEnum::GROK_IMAGINE_IMAGE,
            'xai/grok-imagine-image/edit' => EntityEnum::GROK_IMAGINE_IMAGE_EDIT,
            EntityEnum::MIDJOURNEY->value => EntityEnum::MIDJOURNEY,
            default                       => EntityEnum::tryFrom($generator),
        };
    }

    /**
     * Mirrors AdvancedImageController::getModel() logic for flux-pro/kontext.
     */
    private function resolveFluxProKontext(?string $action): EntityEnum
    {
        $multiTools = ['cleanup', 'image_relight', 'style_transfer'];

        if ($action && in_array($action, $multiTools, true)) {
            return EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI;
        }

        return EntityEnum::FLUX_PRO_KONTEXT;
    }

    private function getDefaultOpenAiImageModel(): EntityEnum
    {
        $settingsTwo = SettingTwo::getCache();

        $default = match ($settingsTwo->dalle) {
            'dalle3' => EntityEnum::DALL_E_3->slug(),
            'dalle2' => EntityEnum::DALL_E_2->slug(),
            default  => $settingsTwo->dalle,
        };

        return EntityEnum::fromSlug($default) ?? EntityEnum::DALL_E_2;
    }

    private function getStableDiffusionDefaultModel(): EntityEnum
    {
        $settingsTwo = SettingTwo::getCache();

        return EntityEnum::fromSlug($settingsTwo?->stablediffusion_default_model) ?? EntityEnum::SD_3;
    }

    private function getDefaultFalAiModel(): EntityEnum
    {
        return EntityEnum::fromSlug(setting('fal_ai_default_model', EntityEnum::FLUX_PRO->value)) ?? EntityEnum::FLUX_PRO;
    }
}
