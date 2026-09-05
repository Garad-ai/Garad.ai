<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Console\Commands\FluxProQueueCheck;
use App\Http\Controllers\Controller;
use App\Models\UserOpenai;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImageStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $request->get('request_id')) {
            return response()->json([
                'status' => 'error',
            ]);
        }

        $data = UserOpenai::query()
            ->where('status', 'IN_QUEUE')
            ->where('response', 'FL')
            ->where('request_id', $request->input('request_id'))
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'error',
            ]);
        }

        FluxProQueueCheck::updateFluxProImage($request->input('request_id'));

        // Since each image is now generated individually, we expect only one item per requestId
        $item = UserOpenai::query()
            ->whereIn('id', $data->pluck('id')->toArray())
            ->where('request_id', $request->input('request_id'))
            ->where('status', '<>', 'IN_QUEUE')
            ->first();

        if ($item) {
            $item->setAttribute('nameOfImage', Str::replace('/uploads/', ' ', $item->output));
            $item->setAttribute('imgId', 'img-' . $item->response . '-' . $item->id);
            $item->setAttribute('payloadId', 'img-' . $item->response . '-' . $item->id . '-payload');
            $item->setAttribute('img', ThumbImage($item->output));

            return response()->json([
                'status' => 'success',
                'data'   => $item,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'data'   => [],
        ]);
    }
}
