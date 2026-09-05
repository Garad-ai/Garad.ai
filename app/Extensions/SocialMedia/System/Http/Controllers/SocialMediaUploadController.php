<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialMediaUploadController extends Controller
{
    public function image(Request $request)
    {
        //        if (Helper::appIsDemo()) {
        //            return response()->json([
        //                'type'    => 'error',
        //                'message' => trans('This feature is disabled in demo mode.'),
        //            ], 400);
        //        }

        $request->validate(['upload_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:8192']);

        $path = $request->file('upload_image')?->store('social-media', 'public');

        return response()->json([
            'image_path' => '/uploads/' . $path,
            'url'        => url('uploads/' . $path),
        ]);
    }

    public function video(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ], 400);
        }

        $request->validate(['upload_video' => 'required']);

        $path = $request->file('upload_video')?->store('social-media', 'public');

        return response()->json([
            'video_path' => '/uploads/' . $path,
            'url'        => url('uploads/' . $path),
        ]);
    }
}
