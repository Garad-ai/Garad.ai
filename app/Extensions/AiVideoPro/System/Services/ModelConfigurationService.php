<?php

namespace App\Extensions\AiVideoPro\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Packages\FalAI\Enums\AspectRatioEnum;

class ModelConfigurationService
{
    public static function getConfig(): array
    {
        return [
            'sora' => [
                'label'       => __('Sora'),
                'description' => __('Best for social media'),
                'isActive'    => setting('sora_active', 1) == 1,
                'subModels'   => [
                    'sora2' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::SORA_2->value => [
                                'label'  => EntityEnum::SORA_2->label(),
                                'inputs' => [
                                    self::promptInput(),
                                    self::imageUpload(null, 'image_url', __('Reference Image'), false, __('Optional - Must match the requested width and height')),
                                    self::selectInput('sora_seconds', __('Duration'), [
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '12', 'label' => '12s'],
                                    ], '4', __('Select video duration (4, 8, or 12 seconds).')),
                                    self::selectInput('sora_size', __('Size'), [
                                        ['value' => '720x1280', 'label' => '720x1280'],
                                        ['value' => '1280x720', 'label' => '1280x720'],
                                        ['value' => '1024×1792', 'label' => '1024×1792'],
                                        ['value' => '1792×1024', 'label' => '1792×1024'],
                                    ], '720x1280'),
                                ],
                            ],
                            EntityEnum::SORA_2_PRO->value => [
                                'label'  => EntityEnum::SORA_2_PRO->label(),
                                'inputs' => [
                                    self::promptInput(),
                                    self::imageUpload(null, 'image_url', __('Reference Image'), false, __('Optional - Must match the requested width and height')),
                                    self::selectInput('sora_seconds', __('Duration'), [
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '12', 'label' => '12s'],
                                    ], '4', __('Select video duration (4, 8, or 12 seconds).')),
                                    self::selectInput('sora_size', __('Size'), [
                                        ['value' => '720x1280', 'label' => '720x1280'],
                                        ['value' => '1280x720', 'label' => '1280x720'],
                                        ['value' => '1024×1792', 'label' => '1024×1792'],
                                        ['value' => '1792×1024', 'label' => '1792×1024'],
                                    ], '720x1280'),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'veo' => [
                'label'       => __('Veo'),
                'description' => __('Best for story-telling'),
                'isActive'    => true,
                'subModels'   => [
                    'veo2' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::VEO_2->value => [
                                'label'  => EntityEnum::VEO_2->label(),
                                'inputs' => [
                                    self::promptInput('Prompt'),
                                    self::aspectRatioSelect(AspectRatioEnum::class),
                                    self::durationSelect('5s', ['5s']),
                                    self::checkboxInput('enhance_prompt', __('Enhance Prompt'), true, __('Improves your prompt for higher-quality results')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                        ],
                    ],
                    'veo3' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::VEO_3->value => [
                                'label'  => EntityEnum::VEO_3->label(),
                                'inputs' => [
                                    self::promptInput('Prompt'),
                                    self::aspectRatioSelect(AspectRatioEnum::class),
                                    self::durationSelect('8s', ['4s', '6s', '8s']),
                                    self::checkboxInput('enhance_prompt', __('Enhance Prompt'), true, __('Improves your prompt for higher-quality results')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                            EntityEnum::VEO_3_FAST->value => [
                                'label'  => EntityEnum::VEO_3_FAST->label(),
                                'inputs' => [
                                    self::promptInput('Prompt'),
                                    self::aspectRatioSelect(AspectRatioEnum::class),
                                    self::durationSelect('8s', ['4s', '6s', '8s']),
                                    self::checkboxInput('enhance_prompt', __('Enhance Prompt'), true, __('Improves your prompt for higher-quality results')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                        ],
                    ],
                    'Veo 3.1' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::VEO_3_1_TEXT_TO_VIDEO->value => [
                                'label'  => EntityEnum::VEO_3_1_TEXT_TO_VIDEO->label(),
                                'inputs' => [
                                    self::promptInput('Prompt'),
                                    self::durationSelect('8s', ['4s', '6s', '8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square - will be outpainted)'],
                                    ], '16:9'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::negativePromptInput(),
                                    self::checkboxInput('enhance_prompt', __('Enhance Prompt'), true, __('Improves your prompt for higher-quality results'), true),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                            EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST->value => [
                                'label'  => EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST->label(),
                                'inputs' => [
                                    self::promptInput('Prompt'),
                                    self::durationSelect('8s', ['4s', '6s', '8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square - will be outpainted)'],
                                    ], '16:9'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::negativePromptInput(),
                                    self::checkboxInput('enhance_prompt', __('Enhance Prompt'), true, __('Improves your prompt for higher-quality results'), true),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                            EntityEnum::VEO_3_1_IMAGE_TO_VIDEO->value => [
                                'label'  => EntityEnum::VEO_3_1_IMAGE_TO_VIDEO->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video (720p or higher)')),
                                    self::promptInput('Motion Description', 'Describe how the image should animate...', 3),
                                    self::durationSelect('8s', ['8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], '16:9'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                ],
                            ],
                            EntityEnum::VEO_3_1_IMAGE_TO_VIDEO_FAST->value  => [
                                'label'  => EntityEnum::VEO_3_1_IMAGE_TO_VIDEO_FAST->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video (720p or higher)')),
                                    self::promptInput('Motion Description', 'Describe how the image should animate...', 3),
                                    self::durationSelect('8s', ['8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], '16:9'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                ],
                            ],
                            EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO->value  => [
                                'label'  => EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'first_frame_url', __('First Frame Image')),
                                    self::imageUpload(null, 'last_frame_url', __('Last Frame Image')),
                                    self::promptInput('Prompt', 'Describe the transition between frames...', 3),
                                    self::durationSelect('8s', ['8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square - will be outpainted)'],
                                    ], '16:9'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                ],
                            ],
                            EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST->value => [
                                'label'  => EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'first_frame_url', __('First Frame Image')),
                                    self::imageUpload(null, 'last_frame_url', __('Last Frame Image')),
                                    self::promptInput('Prompt', 'Describe the transition between frames...', 3),
                                    self::durationSelect('8s', ['8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square - will be outpainted)'],
                                    ], '16:9'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                ],
                            ],
                            EntityEnum::VEO_3_1_REFERENCE_TO_VIDEO->value => [
                                'label'  => EntityEnum::VEO_3_1_REFERENCE_TO_VIDEO->label(),
                                'inputs' => [
                                    self::multipleImageUpload('image_urls[]', __('Upload Reference Images (1-3 images to guide style/subject)')),
                                    self::promptInput('Video Style Description', 'Describe how to modify the reference video...'),
                                    self::durationSelect('8s', ['8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                ],
                            ],
                            EntityEnum::VEO_3_1_LITE_TEXT_TO_VIDEO->value => [
                                'label'  => EntityEnum::VEO_3_1_LITE_TEXT_TO_VIDEO->label(),
                                'inputs' => [
                                    self::promptInput('Prompt'),
                                    self::durationSelect('8s', ['4s', '6s', '8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], '16:9'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::negativePromptInput(),
                                    self::checkboxInput('enhance_prompt', __('Enhance Prompt'), true, __('Improves your prompt for higher-quality results'), true),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                            EntityEnum::VEO_3_1_LITE_IMAGE_TO_VIDEO->value => [
                                'label'  => EntityEnum::VEO_3_1_LITE_IMAGE_TO_VIDEO->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video (720p or higher, up to 8MB)')),
                                    self::promptInput('Motion Description', 'Describe how the image should animate...', 3),
                                    self::durationSelect('8s', ['4s', '6s', '8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto (infer from image)')],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::negativePromptInput(),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                            EntityEnum::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO->value => [
                                'label'  => EntityEnum::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'first_frame_url', __('First Frame Image (up to 8MB)')),
                                    self::imageUpload(null, 'last_frame_url', __('Last Frame Image (up to 8MB)')),
                                    self::promptInput('Prompt', 'Describe the transition between frames...', 3),
                                    self::durationSelect('8s', ['8s']),
                                    self::selectInput('resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '1080p', 'label' => '1080p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto (infer from frames)')],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('If disabled, video will be silent (saves 33% credits)')),
                                    self::seedInput(),
                                    self::negativePromptInput(),
                                    self::checkboxInput('auto_fix', __('Auto Fix Prompts'), true, __('Automatically rewrite prompts that fail content policy'), true),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'luma-dream-machine' => [
                'label'       => __('Luma Dream Machine'),
                'description' => __('Best for dynamic scenes'),
                'isActive'    => true,
                'subModels'   => [
                    'luma-dream-machine' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::LUMA_DREAM_MACHINE->value => [
                                'label'  => EntityEnum::LUMA_DREAM_MACHINE->label(),
                                'inputs' => [
                                    self::promptInput('Dream Prompt', 'Describe your dream video...'),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'kling' => [
                'label'       => __('Kling'),
                'description' => __('Best for motion control'),
                'isActive'    => true,
                'subModels'   => [
                    'kling-v1' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::KLING->value => [
                                'label'  => EntityEnum::KLING->label(),
                                'inputs' => [
                                    self::promptInput(),
                                ],
                            ],
                        ],
                    ],
                    'kling-v1.6' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::KLING_IMAGE->value => [
                                'label'  => EntityEnum::KLING_IMAGE->label(),
                                'inputs' => [
                                    self::imageUpload(),
                                    self::promptInput('Animation Description', 'Describe the animation...', 3),
                                ],
                            ],
                        ],
                    ],
                    'kling-v2.1' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::KLING_2_1->value => [
                                'label'  => EntityEnum::KLING_2_1->label(),
                                'inputs' => [
                                    self::imageUpload(),
                                    self::promptInput(),
                                ],
                            ],
                        ],
                    ],
                    'kling-v2.5' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::KLING_2_5_TURBO_PRO_TTV->value => [
                                'label'  => EntityEnum::KLING_2_5_TURBO_PRO_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe your video in detail...', 5),
                                    self::selectInput('kling25turbo_duration', __('Duration'), [
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '10', 'label' => '10s'],
                                    ], '5', __('Video duration (5 or 10 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9'),
                                    self::seedInput(0, 2147483647),
                                    self::negativePromptInput(),
                                    self::numberInput('cfg_scale', __('CFG Scale'), 0, 1, 0.1, '0.5', __('Guidance scale (0.0-1.0). Higher values follow prompt more closely. Default: 0.5'), false, true),
                                    self::checkboxInput('loop', __('Loop Video'), false, __('Make video seamlessly loop (only for 5s duration in Pro mode)'), true),
                                ],
                            ],
                            EntityEnum::KLING_2_5_TURBO_PRO_ITV->value => [
                                'label'  => EntityEnum::KLING_2_5_TURBO_PRO_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video')),
                                    self::promptInput('Video Prompt', 'Describe the video transformation...', 4),
                                    self::selectInput('kling25turbo_duration', __('Duration'), [
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '10', 'label' => '10s'],
                                    ], '5', __('Video duration (5 or 10 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9'),
                                    self::seedInput(0, 2147483647),
                                    self::negativePromptInput(),
                                    self::checkboxInput('loop', __('Loop Video'), false, __('Make video seamlessly loop (only for 5s duration in Pro mode)'), true),
                                ],
                            ],
                            EntityEnum::KLING_2_5_TURBO_STANDARD_ITV->value => [
                                'label'  => EntityEnum::KLING_2_5_TURBO_STANDARD_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video')),
                                    self::promptInput('Video Prompt', 'Describe the video transformation...', 4),
                                    self::selectInput('kling25turbo_duration', __('Duration'), [
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '10', 'label' => '10s'],
                                    ], '5', __('Video duration (5 or 10 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9'),
                                    self::seedInput(0, 2147483647),
                                    self::negativePromptInput(),
                                ],
                            ],
                        ],
                    ],
                    'kling-v2.6' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::KLING_2_6_PRO_TTV->value => [
                                'label'  => EntityEnum::KLING_2_6_PRO_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe your video in detail...', 5),
                                    self::selectInput('kling26pro_duration', __('Duration'), [
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '10', 'label' => '10s'],
                                    ], '5', __('Video duration (5 or 10 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9'),
                                    self::negativePromptInput(),
                                    self::numberInput('cfg_scale', __('CFG Scale'), 0, 1, 0.1, '0.5', __('Guidance scale (0.0-1.0). Higher values follow prompt more closely. Default: 0.5'), false, true),
                                ],
                            ],
                            EntityEnum::KLING_2_6_PRO_ITV->value => [
                                'label'  => EntityEnum::KLING_2_6_PRO_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video')),
                                    self::promptInput('Video Prompt', 'Describe the video transformation...', 4),
                                    self::selectInput('kling25pro_duration', __('Duration'), [
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '10', 'label' => '10s'],
                                    ], '5', __('Video duration (5 or 10 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9'),
                                    self::negativePromptInput(),
                                ],
                            ],
                            EntityEnum::KLING_2_6_STANDARD_MOTION_CONTROL->value => [
                                'label'  => EntityEnum::KLING_2_6_STANDARD_MOTION_CONTROL->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Character Image (must be >300px, aspect ratio 2:5 to 5:2)')),
                                    self::videoUpload('video_url', __('Reference Movement Video (3-30s, character must be visible)')),
                                    self::selectInput('character_orientation', __('Character Orientation'), [
                                        ['value' => 'image', 'label' => __('Image Orientation (max 10s)')],
                                        ['value' => 'video', 'label' => __('Video Orientation (max 30s)')],
                                    ], 'image', __('Choose how the character is oriented: preserve image pose or adopt video orientation')),
                                    self::promptInput('Video Description (Optional)', 'Describe the desired output (focus on character identity, environment, style)...', 3, false),
                                    self::checkboxInput('keep_original_sound', __('Keep Original Audio'), true, __('Retain audio from reference video')),
                                ],
                            ],
                            EntityEnum::KLING_2_6_PRO_MOTION_CONTROL->value => [
                                'label'  => EntityEnum::KLING_2_6_PRO_MOTION_CONTROL->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Character Image (must be >300px, aspect ratio 2:5 to 5:2)')),
                                    self::videoUpload('video_url', __('Reference Movement Video (3-30s, character must be visible)')),
                                    self::selectInput('character_orientation', __('Character Orientation'), [
                                        ['value' => 'image', 'label' => __('Image Orientation (max 10s)')],
                                        ['value' => 'video', 'label' => __('Video Orientation (max 30s)')],
                                    ], 'image', __('Choose how the character is oriented: preserve image pose or adopt video orientation')),
                                    self::promptInput('Video Description (Optional)', 'Describe the desired output (focus on character identity, environment, style)...', 3, false),
                                    self::checkboxInput('keep_original_sound', __('Keep Original Audio'), true, __('Retain audio from reference video')),
                                ],
                            ],
                        ],
                    ],
                    'kling-v3' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::KLING_3_PRO_TTV->value => [
                                'label'  => EntityEnum::KLING_3_PRO_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe your video in detail...', 5),
                                    self::selectInput('kling_v3_duration', __('Duration'), [
                                        ['value' => '3', 'label' => '3s'],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], '5', __('Video duration (3 to 15 seconds)')),
                                    self::selectInput('kling_v3_aspect_ratio', __('Aspect Ratio'), [
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9', __('Aspect ratio of the output video')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation'), true),
                                    self::selectInput('kling_v3_shot_type', __('Shot Type'), [
                                        ['value' => 'customize', 'label' => __('Customize')],
                                        ['value' => 'intelligent', 'label' => __('Intelligent')],
                                    ], 'customize', __('Type of multi-shot generation (used with Multi Prompt JSON)'), false),
                                    self::negativePromptInput(),
                                    self::numberInput('kling_v3_cfg_scale', __('CFG Scale'), 0, 1, 0.1, '0.5', __('Guidance scale (0.0-1.0). Higher values follow prompt more closely. Default: 0.5'), false, true),
                                ],
                            ],
                            EntityEnum::KLING_3_PRO_ITV->value => [
                                'label'  => EntityEnum::KLING_3_PRO_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'start_image_url', __('Start Frame Image')),
                                    self::imageUpload(null, 'end_image_url', __('End Frame Image (optional)'), false),
                                    self::promptInput('Video Prompt', 'Describe motion or scene transition... (optional when using Multi Prompt JSON)', 4, false),
                                    self::selectInput('kling_v3_duration', __('Duration'), [
                                        ['value' => '3', 'label' => '3s'],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], '5', __('Video duration (3 to 15 seconds)')),
                                    self::selectInput('kling_v3_aspect_ratio', __('Aspect Ratio'), [
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9', __('Aspect ratio of the output video')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation'), true),
                                    self::selectInput('kling_v3_shot_type', __('Shot Type'), [
                                        ['value' => 'customize', 'label' => __('Customize')],
                                        ['value' => 'intelligent', 'label' => __('Intelligent')],
                                    ], 'customize', __('Type of multi-shot generation (used with Multi Prompt JSON)'), false),
                                    self::negativePromptInput(),
                                    self::numberInput('kling_v3_cfg_scale', __('CFG Scale'), 0, 1, 0.1, '0.5', __('Guidance scale (0.0-1.0). Higher values follow prompt more closely. Default: 0.5'), false, true),
                                ],
                            ],
                            EntityEnum::KLING_3_STANDARD_TTV->value => [
                                'label'  => EntityEnum::KLING_3_STANDARD_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe your video in detail...', 5),
                                    self::selectInput('kling_v3_duration', __('Duration'), [
                                        ['value' => '3', 'label' => '3s'],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], '5', __('Video duration (3 to 15 seconds)')),
                                    self::selectInput('kling_v3_aspect_ratio', __('Aspect Ratio'), [
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9', __('Aspect ratio of the output video')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation'), true),
                                    self::selectInput('kling_v3_shot_type', __('Shot Type'), [
                                        ['value' => 'customize', 'label' => __('Customize')],
                                        ['value' => 'intelligent', 'label' => __('Intelligent')],
                                    ], 'customize', __('Type of multi-shot generation (used with Multi Prompt JSON)'), false),
                                    self::negativePromptInput(),
                                    self::numberInput('kling_v3_cfg_scale', __('CFG Scale'), 0, 1, 0.1, '0.5', __('Guidance scale (0.0-1.0). Higher values follow prompt more closely. Default: 0.5'), false, true),
                                ],
                            ],
                            EntityEnum::KLING_3_STANDARD_ITV->value => [
                                'label'  => EntityEnum::KLING_3_STANDARD_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'start_image_url', __('Start Frame Image')),
                                    self::imageUpload(null, 'end_image_url', __('End Frame Image (optional)'), false),
                                    self::promptInput('Video Prompt', 'Describe motion or scene transition... (optional when using Multi Prompt JSON)', 4, false),
                                    self::selectInput('kling_v3_duration', __('Duration'), [
                                        ['value' => '3', 'label' => '3s'],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], '5', __('Video duration (3 to 15 seconds)')),
                                    self::selectInput('kling_v3_aspect_ratio', __('Aspect Ratio'), [
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], '16:9', __('Aspect ratio of the output video')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation'), true),
                                    self::selectInput('kling_v3_shot_type', __('Shot Type'), [
                                        ['value' => 'customize', 'label' => __('Customize')],
                                        ['value' => 'intelligent', 'label' => __('Intelligent')],
                                    ], 'customize', __('Type of multi-shot generation (used with Multi Prompt JSON)'), false),
                                    self::negativePromptInput(),
                                    self::numberInput('kling_v3_cfg_scale', __('CFG Scale'), 0, 1, 0.1, '0.5', __('Guidance scale (0.0-1.0). Higher values follow prompt more closely. Default: 0.5'), false, true),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'grok-imagine-video' => [
                'label'       => __('Grok Imagine'),
                'description' => __('AI video from xAI'),
                'isActive'    => true,
                'subModels'   => [
                    'grok-imagine-video' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::GROK_IMAGINE_VIDEO_TTV->value => [
                                'label'  => EntityEnum::GROK_IMAGINE_VIDEO_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe the video you want to generate...', 5),
                                    self::selectInput('grok_video_duration', __('Duration'), [
                                        ['value' => '3', 'label' => '3s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], '6', __('Video duration (1-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '3:2', 'label' => '3:2'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                        ['value' => '2:3', 'label' => '2:3'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                    ], '16:9'),
                                    self::selectInput('grok_video_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                ],
                            ],
                            EntityEnum::GROK_IMAGINE_VIDEO_ITV->value => [
                                'label'  => EntityEnum::GROK_IMAGINE_VIDEO_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video')),
                                    self::promptInput('Motion Description', 'Describe the desired changes or motion...', 4),
                                    self::selectInput('grok_video_duration', __('Duration'), [
                                        ['value' => '3', 'label' => '3s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], '6', __('Video duration (1-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => 'Auto'],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                    ], 'auto'),
                                    self::selectInput('grok_video_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'seedance' => [
                'label'       => __('Seedance 2.0'),
                'description' => __('AI video from ByteDance'),
                'isActive'    => true,
                'subModels'   => [
                    'seedance-2.0' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::SEEDANCE_2_TTV->value => [
                                'label'  => EntityEnum::SEEDANCE_2_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe the video you want to generate...', 5),
                                    self::selectInput('seedance_duration', __('Duration'), [
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], 'auto', __('Video duration (auto or 4-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '21:9', 'label' => '21:9 (Ultra-wide)'],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::selectInput('seedance_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation including sound effects and lip-synced speech')),
                                    self::seedInput(),
                                ],
                            ],
                            EntityEnum::SEEDANCE_2_ITV->value => [
                                'label'  => EntityEnum::SEEDANCE_2_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Start Image')),
                                    self::imageUpload(null, 'end_image_url', __('End Frame Image (optional)'), false),
                                    self::promptInput('Motion Description', 'Describe the desired motion and action...', 4),
                                    self::selectInput('seedance_duration', __('Duration'), [
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], 'auto', __('Video duration (auto or 4-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '21:9', 'label' => '21:9 (Ultra-wide)'],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::selectInput('seedance_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation including sound effects and lip-synced speech')),
                                    self::seedInput(),
                                ],
                            ],
                            EntityEnum::SEEDANCE_2_RTV->value => [
                                'label'  => EntityEnum::SEEDANCE_2_RTV->label(),
                                'inputs' => [
                                    self::multipleImageUpload('image_urls[]', __('Reference Images (up to 9)'), false, 0, 9),
                                    self::multipleVideoUpload('video_urls[]', __('Reference Videos (up to 3, optional)')),
                                    self::multipleAudioUpload('audio_urls[]', __('Reference Audio (up to 3, optional)')),
                                    self::promptInput('Video Description', 'Describe what to generate, use @Image1, @Image2 to reference uploaded images...', 5),
                                    self::selectInput('seedance_duration', __('Duration'), [
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], 'auto', __('Video duration (auto or 4-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '21:9', 'label' => '21:9 (Ultra-wide)'],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::selectInput('seedance_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation including sound effects and lip-synced speech')),
                                    self::seedInput(),
                                ],
                            ],
                        ],
                    ],
                    'seedance-2.0-fast' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::SEEDANCE_2_FAST_TTV->value => [
                                'label'  => EntityEnum::SEEDANCE_2_FAST_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe the video you want to generate...', 5),
                                    self::selectInput('seedance_duration', __('Duration'), [
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], 'auto', __('Video duration (auto or 4-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '21:9', 'label' => '21:9 (Ultra-wide)'],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::selectInput('seedance_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation including sound effects and lip-synced speech')),
                                    self::seedInput(),
                                ],
                            ],
                            EntityEnum::SEEDANCE_2_FAST_ITV->value => [
                                'label'  => EntityEnum::SEEDANCE_2_FAST_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Start Image')),
                                    self::imageUpload(null, 'end_image_url', __('End Frame Image (optional)'), false),
                                    self::promptInput('Motion Description', 'Describe the desired motion and action...', 4),
                                    self::selectInput('seedance_duration', __('Duration'), [
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], 'auto', __('Video duration (auto or 4-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '21:9', 'label' => '21:9 (Ultra-wide)'],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::selectInput('seedance_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation including sound effects and lip-synced speech')),
                                    self::seedInput(),
                                ],
                            ],
                            EntityEnum::SEEDANCE_2_FAST_RTV->value => [
                                'label'  => EntityEnum::SEEDANCE_2_FAST_RTV->label(),
                                'inputs' => [
                                    self::multipleImageUpload('image_urls[]', __('Reference Images (up to 9)'), false, 0, 9),
                                    self::multipleVideoUpload('video_urls[]', __('Reference Videos (up to 3, optional)')),
                                    self::multipleAudioUpload('audio_urls[]', __('Reference Audio (up to 3, optional)')),
                                    self::promptInput('Video Description', 'Describe what to generate, use @Image1, @Image2 to reference uploaded images...', 5),
                                    self::selectInput('seedance_duration', __('Duration'), [
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '4', 'label' => '4s'],
                                        ['value' => '5', 'label' => '5s'],
                                        ['value' => '6', 'label' => '6s'],
                                        ['value' => '7', 'label' => '7s'],
                                        ['value' => '8', 'label' => '8s'],
                                        ['value' => '9', 'label' => '9s'],
                                        ['value' => '10', 'label' => '10s'],
                                        ['value' => '11', 'label' => '11s'],
                                        ['value' => '12', 'label' => '12s'],
                                        ['value' => '13', 'label' => '13s'],
                                        ['value' => '14', 'label' => '14s'],
                                        ['value' => '15', 'label' => '15s'],
                                    ], 'auto', __('Video duration (auto or 4-15 seconds)')),
                                    self::aspectRatioSelect([
                                        ['value' => 'auto', 'label' => __('Auto')],
                                        ['value' => '21:9', 'label' => '21:9 (Ultra-wide)'],
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '4:3', 'label' => '4:3'],
                                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                                        ['value' => '3:4', 'label' => '3:4'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], 'auto'),
                                    self::selectInput('seedance_resolution', __('Resolution'), [
                                        ['value' => '720p', 'label' => '720p'],
                                        ['value' => '480p', 'label' => '480p'],
                                    ], '720p', __('Video resolution quality')),
                                    self::checkboxInput('generate_audio', __('Generate Audio'), true, __('Enable synchronized audio generation including sound effects and lip-synced speech')),
                                    self::seedInput(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'gemini-omni-flash' => [
                'label'       => __('Gemini Omni Flash'),
                'description' => __('Video with synchronized audio'),
                'isActive'    => setting('gemini_omni_flash_active', 1) == 1,
                'subModels'   => [
                    'gemini-omni-flash' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::GEMINI_OMNI_FLASH_TTV->value => [
                                'label'  => EntityEnum::GEMINI_OMNI_FLASH_TTV->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Describe the video you want to generate...', 5),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], '16:9'),
                                    self::omniFlashDurationSelect(),
                                ],
                            ],
                            EntityEnum::GEMINI_OMNI_FLASH_ITV->value => [
                                'label'  => EntityEnum::GEMINI_OMNI_FLASH_ITV->label(),
                                'inputs' => [
                                    self::imageUpload(null, 'image_url', __('Upload Image for Image-to-Video')),
                                    self::promptInput('Motion Description', 'Describe how the image should animate...', 4),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], '16:9'),
                                    self::omniFlashDurationSelect(),
                                ],
                            ],
                            EntityEnum::GEMINI_OMNI_FLASH_RTV->value => [
                                'label'  => EntityEnum::GEMINI_OMNI_FLASH_RTV->label(),
                                'inputs' => [
                                    self::multipleImageUpload('image_urls[]', __('Reference Images (up to 4)'), true, 1, 4),
                                    self::promptInput('Video Description', 'Describe the video, use <IMAGE_REF_0>, <IMAGE_REF_1> to reference uploaded images...', 5),
                                    self::aspectRatioSelect([
                                        ['value' => '16:9', 'label' => '16:9 (Landscape)'],
                                        ['value' => '9:16', 'label' => '9:16 (Portrait)'],
                                    ], '16:9'),
                                    self::omniFlashDurationSelect(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'minimax' => [
                'label'       => __('Minimax'),
                'description' => __('Fast and low-cost'),
                'isActive'    => true,
                'subModels'   => [
                    'minimax' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::MINIMAX->value => [
                                'label'  => EntityEnum::MINIMAX->label(),
                                'inputs' => [
                                    self::promptInput('Video Description', 'Enter your video prompt...'),
                                    self::negativePromptInput(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'background-removal' => [
                'label'       => __('Background Removal'),
                'description' => __('Remove video backgrounds'),
                'isActive'    => true,
                'subModels'   => [
                    'veed' => [
                        'isActive' => true,
                        'features' => [
                            EntityEnum::VIDEO_BACKGROUND_REMOVAL->value => [
                                'label'  => EntityEnum::VIDEO_BACKGROUND_REMOVAL->label(),
                                'inputs' => [
                                    self::videoUpload('video_url', __('Upload Video')),
                                    self::selectInput('output_codec', __('Output Codec'), [
                                        ['value' => 'vp9', 'label' => 'VP9 (WebM with alpha)'],
                                        ['value' => 'h264', 'label' => 'H264 (RGB + alpha)'],
                                    ], 'vp9', __('VP9 outputs a single video with transparency. H264 outputs two separate videos.')),
                                    self::checkboxInput('refine_foreground_edges', __('Refine Foreground Edges'), true, __('Improves the quality of the extracted object\'s edges')),
                                    self::checkboxInput('subject_is_person', __('Subject is Person'), true, __('Set to off if the subject is not a person')),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ============== GENERAL INPUT BUILDERS ==============

    private static function promptInput(
        string $label = 'Video Description',
        string $placeholder = 'Describe the video you want to generate...',
        int $rows = 4,
        bool $required = true
    ): array {
        return [
            'type'        => 'textarea',
            'name'        => 'prompt',
            'label'       => $label,
            'placeholder' => $placeholder,
            'required'    => $required,
            'rows'        => $rows,
        ];
    }

    private static function negativePromptInput(): array
    {
        return [
            'type'        => 'textarea',
            'name'        => 'negative_prompt',
            'label'       => __('Negative Prompt'),
            'placeholder' => __('What to avoid in the video...'),
            'required'    => false,
            'rows'        => 3,
            'advanced'    => true,
        ];
    }

    private static function jsonTextareaInput(
        string $name,
        string $label,
        string $tooltip,
        string $placeholder
    ): array {
        return [
            'type'        => 'textarea',
            'name'        => $name,
            'label'       => $label,
            'placeholder' => $placeholder,
            'required'    => false,
            'rows'        => 3,
            'tooltip'     => $tooltip,
            'advanced'    => true,
        ];
    }

    private static function selectInput(
        string $name,
        string $label,
        array $options,
        string $default,
        ?string $tooltip = null,
        bool $required = true
    ): array {
        $input = [
            'type'     => 'select',
            'name'     => $name,
            'label'    => $label,
            'required' => $required,
            'options'  => $options,
            'default'  => $default,
        ];

        if ($tooltip !== null) {
            $input['tooltip'] = $tooltip;
        }

        return $input;
    }

    private static function numberInput(
        string $name,
        string $label,
        ?float $min = null,
        ?float $max = null,
        ?float $step = null,
        ?string $placeholder = null,
        ?string $tooltip = null,
        bool $required = true,
        bool $advanced = false
    ): array {
        $input = [
            'type'     => 'number',
            'name'     => $name,
            'label'    => $label,
            'required' => $required,
        ];

        if ($min !== null) {
            $input['min'] = $min;
        }
        if ($max !== null) {
            $input['max'] = $max;
        }
        if ($step !== null) {
            $input['step'] = $step;
        }
        if ($placeholder !== null) {
            $input['placeholder'] = $placeholder;
        }
        if ($tooltip !== null) {
            $input['tooltip'] = $tooltip;
        }
        if ($advanced) {
            $input['advanced'] = true;
        }

        return $input;
    }

    private static function checkboxInput(
        string $name,
        string $label,
        bool $default = false,
        ?string $tooltip = null,
        bool $advanced = false
    ): array {
        $input = [
            'type'     => 'checkbox',
            'name'     => $name,
            'label'    => $label,
            'default'  => $default,
            'required' => false,
        ];

        if ($tooltip !== null) {
            $input['tooltip'] = $tooltip;
        }

        if ($advanced) {
            $input['advanced'] = true;
        }

        return $input;
    }

    private static function imageUpload(
        ?int $maxSize = null,
        string $name = 'image_url',
        string $label = 'Upload Image',
        bool $required = true,
        ?string $tooltip = null,
    ): array {
        $input = [
            'type'     => 'file',
            'name'     => $name,
            'label'    => $label,
            'accept'   => 'image/*',
            'required' => $required,
            'tooltip'  => $tooltip,
        ];

        if ($maxSize !== null) {
            $input['max_size'] = $maxSize;
        }

        return $input;
    }

    private static function multipleImageUpload(
        string $name,
        string $label,
        bool $required = true,
        int $minFiles = 1,
        int $maxFiles = 3,
        ?string $tooltip = null
    ): array {
        return [
            'type'      => 'file',
            'name'      => $name,
            'label'     => $label,
            'accept'    => 'image/*',
            'multiple'  => true,
            'required'  => $required,
            'min_files' => $minFiles,
            'max_files' => $maxFiles,
            'tooltip'   => $tooltip,
        ];
    }

    private static function multipleVideoUpload(
        string $name,
        string $label,
        bool $required = false,
        int $maxFiles = 3,
        ?string $tooltip = null
    ): array {
        return [
            'type'                => 'file',
            'name'                => $name,
            'label'               => $label,
            'accept'              => 'video/*',
            'multiple'            => true,
            'required'            => $required,
            'min_files'           => 0,
            'max_files'           => $maxFiles,
            'excludeMediaManager' => true,
            'tooltip'             => $tooltip,
        ];
    }

    private static function multipleAudioUpload(
        string $name,
        string $label,
        bool $required = false,
        int $maxFiles = 3,
        ?string $tooltip = null
    ): array {
        return [
            'type'      => 'file',
            'name'      => $name,
            'label'     => $label,
            'accept'    => 'audio/*',
            'multiple'  => true,
            'required'  => $required,
            'min_files' => 0,
            'max_files' => $maxFiles,
            'tooltip'   => $tooltip,

        ];
    }

    private static function videoUpload(
        string $name,
        string $label,
        ?string $tooltip = null
    ): array {
        return [
            'type'                => 'file',
            'name'                => $name,
            'label'               => $label,
            'accept'              => 'video/*',
            'required'            => true,
            'excludeMediaManager' => true,
            'tooltip'             => $tooltip,
        ];
    }

    private static function seedInput(
        ?int $min = null,
        ?int $max = null
    ): array {
        return self::numberInput(
            'seed',
            __('Seed'),
            $min,
            $max,
            null,
            __('Leave empty for random'),
            __('Optional: numeric seed for deterministic results'),
            false,
            true
        );
    }

    // ============== SPECIALIZED INPUT BUILDERS ==============

    private static function omniFlashDurationSelect(): array
    {
        $options = array_map(fn ($s) => [
            'value' => (string) $s,
            'label' => $s . 's',
        ], range(3, 10));

        return self::selectInput('duration', __('Duration'), $options, '8', __('Video duration (3-10 seconds)'));
    }

    private static function durationSelect(
        string $default,
        array $options,
        ?string $tooltip = null,
    ): array {
        $formattedOptions = array_map(fn ($d) => [
            'value' => $d,
            'label' => $d,
        ], $options);

        $tooltip = count($options) === 1
            ? __('Video duration (8 seconds only for this mode)')
            : __('Video duration');

        return self::selectInput('duration', __('Duration'), $formattedOptions, $default, $tooltip);
    }

    private static function aspectRatioSelect(
        string|array $optionsOrEnum,
        ?string $default = null,
        ?string $tooltip = null
    ): array {
        // If enum class string is passed, build options from it
        if (is_string($optionsOrEnum) && class_exists($optionsOrEnum) && method_exists($optionsOrEnum, 'cases')) {
            $options = [];
            foreach ($optionsOrEnum::cases() as $ratio) {
                $options[] = ['value' => $ratio->value, 'label' => $ratio->label()];
            }
            $default = $default ?? '16:9';
        } else {
            // Direct options array passed
            $options = $optionsOrEnum;
            $default = $default ?? $options[0]['value'];
        }

        return self::selectInput(
            'aspect_ratio',
            __('Aspect Ratio'),
            $options,
            $default,
            $tooltip
        );
    }
}
