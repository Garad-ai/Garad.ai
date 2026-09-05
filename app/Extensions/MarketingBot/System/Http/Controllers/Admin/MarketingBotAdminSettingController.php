<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Admin;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingBotAdminSettingController extends Controller
{
    public function index(): View
    {
        return view('marketing-bot::admin.settings', [
            'enabledProviders' => $this->getEnabledProviders(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'whatsapp_providers'   => 'required|array|min:1',
            'whatsapp_providers.*' => 'string|in:twilio,meta',
        ]);

        setting(['marketing-bot:whatsapp_providers' => json_encode($request->input('whatsapp_providers'))]);
        setting()->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Marketing Bot settings updated.'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function getEnabledProviders(): array
    {
        $raw = setting('marketing-bot:whatsapp_providers', '["twilio"]');

        return json_decode($raw, true) ?? ['twilio'];
    }
}
