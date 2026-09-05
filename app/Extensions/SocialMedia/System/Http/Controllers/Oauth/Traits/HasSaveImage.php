<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits;

use App\Models\SettingTwo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait HasSaveImage
{
    public static function downloadImageToStorage($url = null, $filename = null)
    {
        if (! $url) {
            return null;
        }

        $response = Http::get($url);

        if ($response->successful()) {
            $fileContent = $response->body();

            $filename = uniqid('image_') . '.jpg';

            //			$extension = pathinfo($url, PATHINFO_EXTENSION);
            //			if (! $filename) {
            //				$filename = uniqid('image_') . '.' . $extension;
            //			} else {
            //				$filename = uniqid('image_').'.jpg';
            //			}

            $image_storage = SettingTwo::getCache()?->ai_image_storage;

            if ($image_storage === 'r2') {
                Storage::disk('r2')->put($filename, $fileContent);

                return Storage::disk('r2')->url($filename);
            } elseif ($image_storage === 's3') {

                Storage::disk('s3')->put($filename, $fileContent);

                return Storage::disk('s3')->url($filename);
            }

            // save file on local storage or aws s3
            Storage::disk('thumbs')->put($filename, $fileContent);

            $dump = Storage::disk('public')->put($filename, $fileContent);

            if ($dump) {
                return '/uploads/' . $filename;
            }

            return 'error';
        }

        // return false when fail
        return null;
    }
}
