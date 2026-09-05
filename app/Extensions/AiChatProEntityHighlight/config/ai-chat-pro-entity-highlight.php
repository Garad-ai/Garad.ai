<?php

return [
    'enabled'                     => true,
    'max_highlights_per_response' => 3,
    'confidence_threshold'        => 0.8,
    'supported_entity_types'      => [
        'movie', 'app', 'place', 'celebrity', 'person',
        'product', 'company', 'tv_show', 'book', 'game',
        'album', 'song', 'music',
    ],
];
