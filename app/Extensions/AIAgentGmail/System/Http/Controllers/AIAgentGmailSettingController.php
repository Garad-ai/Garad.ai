<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIAgentGmailSettingController extends Controller
{
    public function index(Request $request): View
    {
        return view('ai-agent-gmail::admin.settings', [
            'clientId' => setting('google_client_id', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return to_route('dashboard.user.index')->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $data = $request->validate([
            'google_client_id'     => 'nullable|string',
            'google_client_secret' => 'nullable|string',
        ]);

        // Don't overwrite secret if left blank
        if (blank($data['google_client_secret'])) {
            unset($data['google_client_secret']);
        }

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Settings updated successfully.'),
        ]);
    }
}
