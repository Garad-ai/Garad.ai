<?php

return [
    'version'   => 1.0,

    'facebook'  => [
        'app_id'       => 'FACEBOOK_APP_ID', // FACEBOOK_APP_ID
        'app_secret'   => 'FACEBOOK_APP_SECRET', // FACEBOOK_APP_SECRET
        'redirect_uri' => '/social-media/oauth/callback/facebook',
        'base_url'     => 'https://www.facebook.com',
        'api_url'      => 'https://graph.facebook.com',
        'api_version'  => 'v18.0',
        'scopes'       => [
            'pages_manage_posts',
            'pages_show_list',
            'pages_read_user_content',
            'pages_read_engagement',
            'pages_messaging',
            'pages_manage_metadata',
            'read_insights',
            'business_management',
        ],
        'requirements' => [
            'text' => [
                'limit' => 1000,
            ],
            'images' => [
                'limit'  => 10,
                'width'  => 640,
                'height' => 640,
                'size'   => 1024, // in kb
            ],
            'videos' => [
                'limit'  => 10,
                'width'  => 640,
                'height' => 640,
                'size'   => 1024, // in kb
            ],
        ],
        'access_token_expire_at' => now()->addMonths(2),
    ],

    'instagram' => [
        'app_id'       => 'INSTAGRAM_APP_ID', // INSTAGRAM_APP_ID
        'app_secret'   => 'INSTAGRAM_APP_SECRET', // INSTAGRAM_APP_SECRET
        'base_url'     => 'https://www.facebook.com',
        'api_url'      => 'https://graph.facebook.com',
        'redirect_uri' => '/social-media/oauth/callback/instagram',
        'api_version'  => 'v18.0',
        'scopes'       => [
            'pages_manage_posts',
            'pages_show_list',
            'pages_read_user_content',
            'pages_read_engagement',
            'read_insights',
            'ads_management',
            'business_management',
            'instagram_basic',
            'instagram_content_publish',
            'instagram_manage_comments',
            'instagram_manage_messages',
        ],
        'requirements' => [
            'text' => [
                'limit' => 1000,
            ],
            'images' => [
                'limit'  => 10,
                'width'  => 640,
                'height' => 640,
                'size'   => 1024, // in kb
            ],
            'videos' => [
                'limit'  => 10,
                'width'  => 640,
                'height' => 640,
                'size'   => 1024, // in kb
            ],
        ],
    ],

    'linkedin' => [
        'app_id'       => 'LINKEDIN_APP_ID', // LINKEDIN_APP_ID
        'app_secret'   => 'LINKEDIN_APP_SECRET', // LINKEDIN_APP_SECRET
        'base_url'     => 'https://linkedin.com',
        'api_url'      => 'https://api.linkedin.com',
        'redirect_uri' => '/social-media/oauth/callback/linkedin',
        'scopes'       => ['openid profile email w_member_social'],
        // 'api_version' => 'v2',
        'header_version'          => '202408',
        'restli_protocol_version' => '2.0.0',
        'options'                 => [
            'visibility'       => 'PUBLIC',
            'feedDistribution' => 'MAIN_FEED',
        ],
        'requirements' => [
            'text' => [
                'limit' => 1300,
            ],
            'images' => [
                'limit' => 9,
            ],
        ],
    ],

    'x' => [
        'app_id'              => 'X_CLIENT_ID',
        'client_secret'       => 'X_CLIENT_SECRET',
        'consumer_api_key'    => 'X_API_KEY',
        'consumer_api_secret' => 'X_API_KEY_SECRET',
        'access_token'        => 'X_ACCESS_TOKEN',
        'access_token_secret' => 'X_ACCESS_TOKEN_SECRET',

        'base_url'     => 'https://x.com',
        'api_url'      => 'https://api.x.com',
        'redirect_uri' => '/social-media/oauth/callback/x',
        'api_version'  => '2',

        'options'      => [],
        'requirements' => [
            'text' => [
                'limit' => 280,
            ],
            'images' => [
                'limit' => 1,
            ],
        ],
    ],
    'tiktok' => [
        'app_id'       => env('TIKTOK_APP_ID'),
        'app_key'      => env('TIKTOK_APP_KEY'),
        'app_secret'   => env('TIKTOK_APP_SECRET'),
        'redirect_uri' => env('APP_URL') . '/social-media/oauth/callback/tiktok',

        'base_url'    => 'https://tiktok.com',
        'api_url'     => 'https://open.tiktokapis.com',
        'api_version' => 'v2',

        'scope' => [
            'user.info.basic',
            'user.info.profile',
            'user.info.stats',
            'video.list',
            'video.publish',
            'video.upload',
            'comment.list',
            'comment.create',
        ],

        'requirements' => [
            'text' => [
                'limit' => 150,
            ],
            'images' => [
                'limit'  => 10,
                'width'  => 640,
                'height' => 640,
                'size'   => 1024, // in kb
            ],
            'videos' => [
                'limit'  => 10,
                'width'  => 640,
                'height' => 640,
                'size'   => 1024, // in kb
            ],
        ],

        'options' => [
            'privacy_level'            => 'SELF_ONLY',
            'disable_comment'          => false,
            'disable_duet'             => false,
            'disable_stitch'           => false,
            'video_cover_timestamp_ms' => 1000,
            'auto_add_music'           => true,
        ],
    ],

    'youtube' => [
        'client_id'     => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'redirect_uri'  => env('APP_URL') . '/social-media/oauth/callback/youtube',

        'auth_url'   => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url'  => 'https://oauth2.googleapis.com/token',
        'api_url'    => 'https://www.googleapis.com/youtube/v3',
        'upload_url' => 'https://www.googleapis.com/upload/youtube/v3',

        'scope' => [
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/youtube.upload',
        ],

        'requirements' => [
            'text' => [
                'limit' => 5000,
            ],
            'videos' => [
                'limit'   => 1,
                'width'   => 3840,
                'height'  => 2160,
                'size'    => 1024 * 512, // ~512 MB
                'formats' => ['mp4', 'mov', 'webm'],
            ],
        ],

        'options' => [
            'default_privacy_status' => 'unlisted',
            'category_id'            => '22',
            'notify_subscribers'     => false,
        ],
    ],

    'youtube-shorts' => [
        'client_id'     => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'redirect_uri'  => env('APP_URL') . '/social-media/oauth/callback/youtube-shorts',

        'auth_url'   => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url'  => 'https://oauth2.googleapis.com/token',
        'api_url'    => 'https://www.googleapis.com/youtube/v3',
        'upload_url' => 'https://www.googleapis.com/upload/youtube/v3',

        'scope' => [
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/youtube.upload',
        ],

        'requirements' => [
            'text' => [
                'limit' => 5000,
            ],
            'videos' => [
                'limit'       => 1,
                'width'       => 1920,
                'height'      => 1080,
                'size'        => 1024 * 256, // ~256 MB
                'duration'    => 60,
                'orientation' => '9:16',
                'formats'     => ['mp4', 'mov'],
            ],
        ],

        'options' => [
            'default_privacy_status' => 'unlisted',
            'shorts_mode'            => true,
            'category_id'            => '22',
            'notify_subscribers'     => false,
        ],
    ],
];
