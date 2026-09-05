<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\SettingTwo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhoneCallAgentSettingController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        if (Helper::appIsDemo()) {
            return to_route('dashboard.user.index')->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        return view('phone-call-agent::admin.settings', [
            'settingTwo' => SettingTwo::getCache(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone_call_agent_provider' => ['nullable', 'string', 'in:twilio,elevenlabs'],
            'elevenlabs_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Settings updated successfully.'),
        ]);
    }
}
