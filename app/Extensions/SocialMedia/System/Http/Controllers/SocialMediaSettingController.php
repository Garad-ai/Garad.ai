<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialMediaSettingController extends Controller
{
    public function index()
    {
        return view('social-media::setting.index', [
            'platforms' => PlatformEnum::all(),
        ]);
    }

    public function update(Request $request, PlatformEnum $platform): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $platformCredentials = $platform->credentials();

        foreach ($platformCredentials as $key => $value) {
            $credentials[strtoupper($key)] = $value;
        }

        $data = $request->validate(
            array_map(fn () => 'required', $credentials)
        );

        setting($data)->save();

        if ($platform === PlatformEnum::tiktok && $request->hasFile('TIKTOK_VERIFICATION_FILE')) {

            $request->validate([
                'TIKTOK_VERIFICATION_FILE' => 'required|file|max:2048',
            ]);

            setting([
                'TIKTOK_VERIFICATION_FILE' => '/uploads/' . $request->file('TIKTOK_VERIFICATION_FILE')?->store('', ['disk' => 'uploads']),
            ])->save();
        }

        return back()->with([
            'type'    => 'success',
            'message' => ucfirst($platform->value) . ' ' . __('Setting update'),
        ]);
    }
}
