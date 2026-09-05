<?php

declare(strict_types=1);

/**
 * Camera-style presets for UGC Creator.
 *
 * Each entry: { key, label, image, prompt_injection }
 *  - `key`              : machine identifier, stored on UGCCreatorVideo.camera_preset.
 *  - `label`            : default display label; admins can override via settings.
 *  - `image`            : thumbnail path under public/, resolved via custom_theme_url().
 *                         null for the "auto" preset, which uses an icon tile.
 *  - `prompt_injection` : silent prompt modifiers prepended to the user's
 *                         description before being sent to the AI model. Empty
 *                         for "auto", which leaves the prompt untouched.
 */
return [
    [
        'key'              => 'auto',
        'label'            => 'General / Auto',
        'image'            => '/vendor/ugc-creator/images/camera-presets/auto.webp',
        'prompt_injection' => '',
    ],
    [
        'key'              => 'product-review',
        'label'            => 'Product Review',
        'image'            => '/vendor/ugc-creator/images/camera-presets/product-review.webp',
        'prompt_injection' => 'Filmed as a casual product review: medium close-up handheld shot, creator holds the product near their face, natural soft lighting, candid energy, vertical 9:16 framing.',
    ],
    [
        'key'              => 'promoting-app',
        'label'            => 'Promoting App',
        'image'            => '/vendor/ugc-creator/images/camera-presets/promoting-app.webp',
        'prompt_injection' => 'Filmed as an app promotion: creator holds a phone with the screen visible to camera, points to the screen at moments, vertical 9:16 framing, modern indoor setting, friendly upbeat tone.',
    ],
    [
        'key'              => 'chat-at-the-cafe',
        'label'            => 'Chatting at the cafe',
        'image'            => '/vendor/ugc-creator/images/camera-presets/chat-at-the-cafe.webp',
        'prompt_injection' => 'Filmed at a cafe table: shallow depth of field with soft ambient cafe background, creator speaks directly to camera near a coffee cup, warm window light, intimate conversational tone.',
    ],
    [
        'key'              => 'cctv',
        'label'            => 'CCTV',
        'image'            => '/vendor/ugc-creator/images/camera-presets/cctv.webp',
        'prompt_injection' => 'CCTV surveillance-style footage: wide overhead angle, slight fisheye distortion, low contrast and slightly desaturated colors, timestamp overlay aesthetic, no direct camera address.',
    ],
    [
        'key'              => 'showing-the-product',
        'label'            => 'Showing the Product',
        'image'            => '/vendor/ugc-creator/images/camera-presets/showing-the-product.webp',
        'prompt_injection' => 'Demonstration shot: creator confidently shows the product toward the camera, hands prominently in frame, mid close-up, natural setting with realistic textures.',
    ],
    [
        'key'              => 'unboxing',
        'label'            => 'Unboxing',
        'image'            => '/vendor/ugc-creator/images/camera-presets/unboxing.webp',
        'prompt_injection' => 'Unboxing shot: hands opening packaging with anticipation, the product becomes the focal point as it is revealed, soft directional light, paced reveal.',
    ],
    [
        'key'              => 'stabilized-cam',
        'label'            => 'Stabilized Cam',
        'image'            => '/vendor/ugc-creator/images/camera-presets/stabilized-cam.webp',
        'prompt_injection' => 'Smooth gimbal-stabilized walking shot following the subject, steady framing, cinematic depth of field, vertical 9:16, outdoor or urban setting.',
    ],
    [
        'key'              => 'selfie',
        'label'            => 'Selfie',
        'image'            => '/vendor/ugc-creator/images/camera-presets/selfie.webp',
        'prompt_injection' => 'Selfie-style: arm-length handheld phone shot, slight downward angle, natural daylight, vlog-like intimate feel, vertical 9:16 framing.',
    ],
];
