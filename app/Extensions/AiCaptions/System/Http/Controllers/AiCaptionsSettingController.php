<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\System\Http\Controllers;

use App\Extensions\AiCaptions\System\Http\Requests\UpdateAiCaptionsSettingsRequest;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Common\MenuService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AiCaptionsSettingController extends Controller
{
    public function index(): View
    {
        return view('ai-captions::settings.index');
    }

    public function update(UpdateAiCaptionsSettingsRequest $request): RedirectResponse
    {
        if (Helper::appIsNotDemo()) {
            setting([
                'ai_captions_api_key'         => (string) $request->input('ai_captions_api_key', ''),
                'ai_captions_default_minutes' => (int) $request->input('ai_captions_default_minutes'),
            ])->save();

            app(MenuService::class)->regenerate();
        }

        return back()->with([
            'type'    => 'success',
            'message' => __('Settings updated successfully.'),
        ]);
    }
}
