<?php

declare(strict_types=1);

/**
 * Built-in premade UGC actor presets.
 *
 * Each entry: { key, label, image }
 *  - `key`   : machine identifier, also used as the preset_key on a UGCFactoryVideo.
 *  - `label` : default (built-in) display label; admins can override via settings.
 *  - `image` : path under public/, resolved at runtime through custom_theme_url().
 *
 * Add new presets here; thumbnails go under app/Extensions/UGCFactory/resources/assets/images/actors/.
 */
return [
    ['key' => 'skin-care-routine',          'label' => 'Skin care routine',           'image' => '/vendor/ugc-factory/images/actors/skin-care-routine.webp'],
    ['key' => 'at-the-cafe',                'label' => 'At the café',                 'image' => '/vendor/ugc-factory/images/actors/at-the-cafe.webp'],
    ['key' => 'while-doing-makeup',         'label' => 'While doing makeup',          'image' => '/vendor/ugc-factory/images/actors/while-doing-makeup.webp'],
    ['key' => 'traveling',                  'label' => 'Traveling',                   'image' => '/vendor/ugc-factory/images/actors/traveling.webp'],
    ['key' => 'chatting-in-the-car',        'label' => 'Chatting in the car',         'image' => '/vendor/ugc-factory/images/actors/chatting-in-the-car.webp'],
    ['key' => 'in-the-kitchen',             'label' => 'In the kitchen',              'image' => '/vendor/ugc-factory/images/actors/in-the-kitchen.webp'],
    ['key' => 'on-the-way-to-work',         'label' => 'On the way to work',          'image' => '/vendor/ugc-factory/images/actors/on-the-way-to-work.webp'],
    ['key' => 'recording-a-podcast',        'label' => 'Recording a podcast',         'image' => '/vendor/ugc-factory/images/actors/recording-a-podcast.webp'],
    ['key' => 'walking-on-the-street',      'label' => 'Walking on the street',       'image' => '/vendor/ugc-factory/images/actors/walking-on-the-street.webp'],
    ['key' => 'top-view',                   'label' => 'Top view',                    'image' => '/vendor/ugc-factory/images/actors/top-view.webp'],
    ['key' => 'taking-a-break-at-the-office', 'label' => 'Taking a break at the office', 'image' => '/vendor/ugc-factory/images/actors/taking-a-break-at-the-office.webp'],
    ['key' => 'in-a-cozy-home',             'label' => 'In a cozy home',              'image' => '/vendor/ugc-factory/images/actors/in-a-cozy-home.webp'],
    ['key' => 'professional',               'label' => 'Professional',                'image' => '/vendor/ugc-factory/images/actors/professional.webp'],
    ['key' => 'online-meeting',             'label' => 'Online Meeting',              'image' => '/vendor/ugc-factory/images/actors/online-meeting.webp'],
    ['key' => 'in-the-office-building',     'label' => 'In the office building',      'image' => '/vendor/ugc-factory/images/actors/in-the-office-building.webp'],
    ['key' => 'in-the-living-room',         'label' => 'In the living room',          'image' => '/vendor/ugc-factory/images/actors/in-the-living-room.webp'],
    ['key' => 'working-out-at-the-gym',     'label' => 'Working out at the gym',      'image' => '/vendor/ugc-factory/images/actors/working-out-at-the-gym.webp'],
    ['key' => 'cozy-in-the-kitchen',        'label' => 'Cozy in the kitchen',         'image' => '/vendor/ugc-factory/images/actors/cozy-in-the-kitchen.webp'],
    ['key' => 'dev-in-the-office',          'label' => 'Dev in the office',           'image' => '/vendor/ugc-factory/images/actors/dev-in-the-office.webp'],
    ['key' => 'gaming-session',             'label' => 'Gaming Session',              'image' => '/vendor/ugc-factory/images/actors/gaming-session.webp'],
    ['key' => 'talking-to-the-camera',      'label' => 'Talking to the camera',       'image' => '/vendor/ugc-factory/images/actors/talking-to-the-camera.webp'],
];
