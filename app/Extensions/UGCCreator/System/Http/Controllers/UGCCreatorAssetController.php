<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Http\Controllers;

use App\Extensions\UGCCreator\System\Http\Requests\StoreCreatorAssetRequest;
use App\Extensions\UGCCreator\System\Models\UGCCreatorAsset;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UGCCreatorAssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assets = UGCCreatorAsset::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (UGCCreatorAsset $asset) => $this->serialize($asset))
            ->groupBy('kind');

        return response()->json([
            'data' => [
                'product'   => $assets->get('product', collect())->values()->all(),
                'character' => $assets->get('character', collect())->values()->all(),
            ],
        ]);
    }

    public function store(StoreCreatorAssetRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'error' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $file = $request->file('image');
        $extension = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'png');
        $kind = (string) $request->input('kind');
        $path = $file->storeAs(
            'ugc-creator/assets/' . $kind,
            Str::uuid()->toString() . '.' . $extension,
            'public',
        );

        $asset = UGCCreatorAsset::create([
            'user_id'    => $request->user()->id,
            'kind'       => $kind,
            'title'      => $request->input('title'),
            'image_path' => $path,
            'mime'       => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return response()->json(['data' => $this->serialize($asset)], 201);
    }

    public function destroy(Request $request, int $asset): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['error' => __('This feature is disabled in demo mode.')], 403);
        }

        $model = UGCCreatorAsset::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($asset);

        $model->delete();

        return response()->json(['ok' => true]);
    }

    private function serialize(UGCCreatorAsset $asset): array
    {
        return [
            'id'         => $asset->id,
            'kind'       => $asset->kind,
            'title'      => $asset->title,
            'image_url'  => $asset->image_url,
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }
}
