<?php

namespace App\Extensions\AiPresentation\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GammaAiSettingsController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        if (Helper::appIsDemo()) {
            return to_route('dashboard.user.index')->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        return view('ai-presentation::setting.gamma-ai');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gamma_api_secret'   => 'required|array|min:1',
            'gamma_api_secret.*' => 'required|string',
        ]);

        $apiKeys = [];
        foreach ($data['gamma_api_secret'] as $submittedKey) {
            foreach (explode(',', $submittedKey) as $apiKey) {
                $apiKey = trim($apiKey);
                if ($apiKey !== '' && strtolower($apiKey) !== 'empty' && $apiKey !== '*********************') {
                    $apiKeys[] = $apiKey;
                }
            }
        }

        $apiKeys = array_values(array_unique($apiKeys));
        if ($apiKeys === []) {
            throw ValidationException::withMessages([
                'gamma_api_secret' => trans('Enter at least one valid Gamma API key.'),
            ]);
        }

        setting(['gamma_api_secret' => implode(',', $apiKeys)])->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Settings updated successfully.'),
        ]);
    }
}
