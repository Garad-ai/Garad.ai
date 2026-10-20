<?php

return [
    'secret' => env('GARAD_STAGING_SSO_SECRET'),
    'bridge_url' => env(
        'GARAD_STAGING_SSO_BRIDGE_URL',
        'https://auth-staging.garad.ai'
    ),
];
