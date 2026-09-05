<?php

declare(strict_types=1);

namespace App\Extensions\VideoDubbing\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Common\MenuService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VideoDubbingSettingController extends Controller
{
    public function index(): View
    {
        return view('video-dubbing::settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'video_dubbing_default_provider' => 'required|string|in:heygen,elevenlabs',
        ]);

        if (Helper::appIsNotDemo()) {
            setting([
                'video_dubbing_default_provider' => $request->input('video_dubbing_default_provider'),
            ])->save();

            app(MenuService::class)->regenerate();
        }

        return back()->with([
            'type'    => 'success',
            'message' => __('Updated Successfully'),
        ]);
    }
}
