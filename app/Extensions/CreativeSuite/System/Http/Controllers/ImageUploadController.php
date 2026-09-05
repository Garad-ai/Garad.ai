<?php

namespace App\Extensions\CreativeSuite\System\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'image' => ['required', 'mimes:jpeg,jpg,png,webp,svg', 'max:8192'], // 6MB
        ]);

        $image = $request->file('image');

        $path = $image->store('', [
            'disk' => 'uploads',
        ]);

        return response()->json([
            'data' => [
                'path' => 'uploads/' . $path,
                'url'  => Storage::disk('uploads')->url($path),
            ],
        ]);
    }
}
