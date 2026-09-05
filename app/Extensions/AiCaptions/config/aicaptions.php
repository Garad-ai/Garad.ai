<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Captions API
    |--------------------------------------------------------------------------
    |
    | Captions API endpoints used to submit videos for captioning and poll
    | their status. The API key itself is configured through the admin
    | settings page (stored in the `settings` table as
    | `ai_captions_api_key`).
    |
    */

    'api' => [
        'base_url' => env('AI_CAPTIONS_BASE_URL', 'https://api.mirage.app'),
        'timeout'  => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default monthly minutes allowance
    |--------------------------------------------------------------------------
    */

    'default_monthly_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Curated template fallback
    |--------------------------------------------------------------------------
    |
    | Used when the Captions API template-list endpoint is unavailable. Each
    | template exposes a preview video URL hosted by Captions that the UI can
    | play on hover in the template-picker modal.
    |
    */

    'fallback_templates' => [
        [
            'id'           => 'line-by-line',
            'name'         => 'Line by Line',
            'description'  => 'Clean white text appearing one line at a time.',
            'preview_text' => 'did you hear about',
            'preview_url'  => null,
        ],
        [
            'id'           => 'word-by-word',
            'name'         => 'Word by Word',
            'description'  => 'Bold one-word-at-a-time captions.',
            'preview_text' => 'WORD',
            'preview_url'  => null,
        ],
        [
            'id'           => 'pop-out',
            'name'         => 'Pop Out',
            'description'  => 'Energetic pop-out animation per word.',
            'preview_text' => 'POP!',
            'preview_url'  => null,
        ],
        [
            'id'           => 'highlight',
            'name'         => 'Highlight',
            'description'  => 'Yellow highlight that follows the spoken word.',
            'preview_text' => 'highlight',
            'preview_url'  => null,
        ],
        [
            'id'           => 'karaoke',
            'name'         => 'Karaoke',
            'description'  => 'Karaoke-style colored word progression.',
            'preview_text' => 'sing along',
            'preview_url'  => null,
        ],
        [
            'id'           => 'bold-sunshine',
            'name'         => 'Bold Sunshine',
            'description'  => 'Bold yellow captions with a strong outline.',
            'preview_text' => 'SUNSHINE',
            'preview_url'  => null,
        ],
    ],
];
