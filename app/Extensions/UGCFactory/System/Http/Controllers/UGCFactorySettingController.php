<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Controllers;

use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UGCFactorySettingController extends Controller
{
    public function index(): View
    {
        return view('ugc-factory::admin.settings', [
            'currentResolution'          => UGCFactoryRegistry::getResolution(),
            'resolutions'                => UGCFactoryRegistry::RESOLUTIONS,
            'currentMaxUploadSizeMb'     => UGCFactoryRegistry::getMaxUploadSizeMb(),
            'currentActorImageModel'     => UGCFactoryRegistry::getActorImageModel(),
            'actorImageModels'           => UGCFactoryRegistry::ACTOR_IMAGE_MODELS,
            'defaultActorPromptTemplate' => UGCFactoryRegistry::DEFAULT_ACTOR_PROMPT_TEMPLATE,
            'currentActorPromptTemplate' => UGCFactoryRegistry::getActorPromptTemplate(),
            'presets'                    => UGCFactoryRegistry::getAllPresets(),
            'disabledPresetKeys'         => UGCFactoryRegistry::getDisabledPresetKeys(),
            'presetLabelOverrides'       => UGCFactoryRegistry::getPresetLabelOverrides(),
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

        $allowedActorModels = array_keys(UGCFactoryRegistry::ACTOR_IMAGE_MODELS);
        $allPresets = UGCFactoryRegistry::getAllPresets();
        $allKeys = array_column($allPresets, 'key');
        $defaultLabelsByKey = array_column($allPresets, 'default_label', 'key');

        $data = $request->validate([
            UGCFactoryRegistry::SETTING_RESOLUTION            => 'required|string|in:' . implode(',', UGCFactoryRegistry::RESOLUTIONS),
            UGCFactoryRegistry::SETTING_MAX_UPLOAD_SIZE_MB    => 'required|integer|min:1|max:50',
            UGCFactoryRegistry::SETTING_ACTOR_IMAGE_MODEL     => 'required|string|in:' . implode(',', $allowedActorModels),
            UGCFactoryRegistry::SETTING_ACTOR_PROMPT_TEMPLATE => 'nullable|string|max:4000',
            'enabled_presets'                                 => 'nullable|array',
            'enabled_presets.*'                               => 'string',
            'preset_labels'                                   => 'nullable|array',
            'preset_labels.*'                                 => 'nullable|string|max:120',
        ]);

        if (empty($data[UGCFactoryRegistry::SETTING_ACTOR_PROMPT_TEMPLATE])) {
            $data[UGCFactoryRegistry::SETTING_ACTOR_PROMPT_TEMPLATE] = '';
        }

        // Compute disabled-preset set: everything we know about minus what the
        // form said is enabled. Filter out unknown keys to keep the stored
        // value bounded to keys we actually have data for.
        $enabledKeys = collect($request->input('enabled_presets', []))
            ->filter(fn ($k) => in_array($k, $allKeys, true))
            ->values()
            ->all();
        $disabledKeys = array_values(array_diff($allKeys, $enabledKeys));
        $data[UGCFactoryRegistry::SETTING_DISABLED_PRESETS] = json_encode($disabledKeys);

        // Label overrides: only store entries whose trimmed value is non-empty
        // AND differs from the built-in default. Anything else "reverts" to default.
        $labelInput = (array) $request->input('preset_labels', []);
        $overrides = [];
        foreach ($labelInput as $key => $value) {
            if (! in_array($key, $allKeys, true)) {
                continue;
            }
            $trimmed = trim((string) $value);
            $default = (string) ($defaultLabelsByKey[$key] ?? '');
            if ($trimmed !== '' && $trimmed !== $default) {
                $overrides[$key] = $trimmed;
            }
        }
        $data[UGCFactoryRegistry::SETTING_PRESET_LABEL_OVERRIDES] = json_encode((object) $overrides);

        unset($data['enabled_presets'], $data['preset_labels']);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => __('Settings updated successfully.'),
        ]);
    }
}
