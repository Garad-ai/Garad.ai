<?php

declare(strict_types=1);

return [
    'client_id'     => env('AI_CHAT_PRO_OUTLOOK_CLIENT_ID', env('MICROSOFT_CLIENT_ID')),
    'client_secret' => env('AI_CHAT_PRO_OUTLOOK_CLIENT_SECRET', env('MICROSOFT_CLIENT_SECRET')),
    'tenant_id'     => env('AI_CHAT_PRO_OUTLOOK_TENANT_ID', env('MICROSOFT_TENANT_ID', 'common')),
    'redirect_uri'  => env('AI_CHAT_PRO_OUTLOOK_REDIRECT_URI'),
    'scopes'        => [
        'https://graph.microsoft.com/Mail.Read',
        'offline_access',
        'openid',
        'profile',
        'email',
    ],
];
