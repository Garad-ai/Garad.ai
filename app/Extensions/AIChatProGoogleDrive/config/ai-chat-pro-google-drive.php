<?php

return [
    'client_id'     => env('AI_CHAT_PRO_GOOGLE_DRIVE_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
    'client_secret' => env('AI_CHAT_PRO_GOOGLE_DRIVE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
    'redirect_uri'  => env('AI_CHAT_PRO_GOOGLE_DRIVE_REDIRECT_URI'),
    'scopes'        => [
        'https://www.googleapis.com/auth/drive.readonly',
        'openid',
        'email',
        'profile',
    ],
];
