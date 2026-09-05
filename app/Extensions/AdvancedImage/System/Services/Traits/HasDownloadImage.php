<?php

namespace App\Extensions\AdvancedImage\System\Services\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasDownloadImage
{
    public function downloadAndSaveImageFromUrl($url): ?string
    {
        $response = Http::get($url);

        if ($response->successful()) {

            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

            $fileName = Str::uuid() . '.' . $extension;

            $path = 'image-editor/' . $fileName;

            Storage::disk('uploads')->put($path, $response->body());

            return $this->imagePath($path);
        }

        return null;
    }
}
