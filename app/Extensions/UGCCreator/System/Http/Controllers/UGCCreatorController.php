<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Http\Controllers;

use App\Extensions\UGCCreator\System\Models\UGCCreatorAsset;
use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UGCCreatorController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $assets = UGCCreatorAsset::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (UGCCreatorAsset $asset) => [
                'id'         => $asset->id,
                'kind'       => $asset->kind,
                'title'      => $asset->title,
                'image_url'  => $asset->image_url,
                'created_at' => $asset->created_at?->toIso8601String(),
            ])
            ->groupBy('kind')
            ->map(fn ($items) => $items->values()->all())
            ->all();

        $enabledModels = collect(UGCCreatorRegistry::getEnabledModels())
            ->map(fn (array $meta, string $key) => [
                'value'          => $key,
                'label'          => $meta['label'],
                'supports_audio' => $meta['supports_audio'],
                'max_refs'       => $meta['max_refs'] ?? 1,
                'entity'         => $meta['entity']?->value,
            ])
            ->values()
            ->all();

        return view('ugc-creator::index', [
            'productAssets'      => $assets['product'] ?? [],
            'characterAssets'    => $assets['character'] ?? [],
            'isDemo'             => Helper::appIsDemo(),
            'cameraPresets'      => UGCCreatorRegistry::getEnabledCameraPresets(),
            'models'             => $enabledModels,
            'defaultModel'       => UGCCreatorRegistry::getDefaultModel(),
            'maxUploadMb'        => UGCCreatorRegistry::getMaxUploadSizeMb(),
        ]);
    }
}
