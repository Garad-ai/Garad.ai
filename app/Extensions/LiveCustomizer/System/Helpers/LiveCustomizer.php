<?php

namespace App\Extensions\LiveCustomizer\System\Helpers;

class LiveCustomizer
{
    public static function getFontSetting(): array
    {
        $fonts = setting(setting('dash_theme') . '_' . 'live_customizer_fonts');

        if (empty($fonts)) {
            return [];
        }

        $fontArray = [];

        $fontBody = data_get($fonts, 'fontBody');

        if ($fontBody && $fontBody !== null) {
            $fontArray[$fontBody] = ['400', '500', '600'];
        }

        $fontHeading = data_get($fonts, 'fontHeading');

        if ($fontHeading && $fontHeading !== null) {
            $fontArray[$fontHeading] = ['500', '600', '700'];
        }

        return $fontArray ?? [];
    }
}
