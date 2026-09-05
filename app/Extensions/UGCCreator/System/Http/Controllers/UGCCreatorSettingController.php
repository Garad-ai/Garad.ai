<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Http\Controllers;

use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UGCCreatorSettingController extends Controller
{
    public function index(): View
    {
        return view('ugc-creator::admin.settings', [
            'currentDefaultModel'        => UGCCreatorRegistry::getDefaultModel(),
            'videoModels'                => UGCCreatorRegistry::VIDEO_MODELS,
            'enabledModelKeys'           => UGCCreatorRegistry::getEnabledModelKeys(),
            'currentQuality'             => UGCCreatorRegistry::getQuality(),
            'qualities'                  => UGCCreatorRegistry::QUALITIES,
            'currentMaxUploadSizeMb'     => UGCCreatorRegistry::getMaxUploadSizeMb(),
            'currentMaxReferenceImages'  => UGCCreatorRegistry::getMaxReferenceImages(),
            'cameraPresets'              => UGCCreatorRegistry::getAllCameraPresets(),
            'disabledCameraPresetKeys'   => UGCCreatorRegistry::getDisabledCameraPresetKeys(),
            'cameraPresetLabelOverrides' => UGCCreatorRegistry::getCameraPresetLabelOverrides(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ]);
        }

        $allowedModels = array_keys(UGCCreatorRegistry::VIDEO_MODELS);
        $allPresets = UGCCreatorRegistry::getAllCameraPresets();
        $allPresetKeys = array_column($allPresets, 'key');
        $defaultLabelsByKey = array_column($allPresets, 'default_label', 'key');

        $data = $request->validate([
            UGCCreatorRegistry::SETTING_DEFAULT_MODEL         => 'required|string|in:' . implode(',', $allowedModels),
            UGCCreatorRegistry::SETTING_QUALITY               => 'required|string|in:' . implode(',', UGCCreatorRegistry::QUALITIES),
            UGCCreatorRegistry::SETTING_MAX_UPLOAD_SIZE_MB    => 'required|integer|min:1|max:50',
            UGCCreatorRegistry::SETTING_MAX_REFERENCE_IMAGES  => 'required|integer|min:1|max:10',
            'enabled_models'                                  => 'nullable|array',
            'enabled_models.*'                                => 'string',
            'enabled_presets'                                 => 'nullable|array',
            'enabled_presets.*'                               => 'string',
            'preset_labels'                                   => 'nullable|array',
            'preset_labels.*'                                 => 'nullable|string|max:120',
        ]);

        // Enabled models: filter against the catalog.
        $enabledModels = collect($request->input('enabled_models', []))
            ->filter(fn ($m) => in_array($m, $allowedModels, true))
            ->values()
            ->all();

        if ($enabledModels === []) {
            $enabledModels = [$data[UGCCreatorRegistry::SETTING_DEFAULT_MODEL]];
        }

        // Default model must be enabled — fall back to first enabled otherwise.
        if (! in_array($data[UGCCreatorRegistry::SETTING_DEFAULT_MODEL], $enabledModels, true)) {
            $data[UGCCreatorRegistry::SETTING_DEFAULT_MODEL] = $enabledModels[0];
        }

        $data[UGCCreatorRegistry::SETTING_ENABLED_MODELS] = json_encode($enabledModels);

        // Disabled camera presets: everything we know about minus what is enabled.
        $enabledPresetKeys = collect($request->input('enabled_presets', []))
            ->filter(fn ($k) => in_array($k, $allPresetKeys, true))
            ->values()
            ->all();
        $disabledPresetKeys = array_values(array_diff($allPresetKeys, $enabledPresetKeys));
        $data[UGCCreatorRegistry::SETTING_DISABLED_CAMERA_PRESETS] = json_encode($disabledPresetKeys);

        // Label overrides: only store entries that differ from the default.
        $labelInput = (array) $request->input('preset_labels', []);
        $overrides = [];
        foreach ($labelInput as $key => $value) {
            if (! in_array($key, $allPresetKeys, true)) {
                continue;
            }
            $trimmed = trim((string) $value);
            $default = (string) ($defaultLabelsByKey[$key] ?? '');
            if ($trimmed !== '' && $trimmed !== $default) {
                $overrides[$key] = $trimmed;
            }
        }
        $data[UGCCreatorRegistry::SETTING_CAMERA_PRESET_LABEL_OVERRIDES] = json_encode((object) $overrides);

        unset($data['enabled_models'], $data['enabled_presets'], $data['preset_labels']);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => __('Settings updated successfully.'),
        ]);
    }
}
