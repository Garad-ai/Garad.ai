<?php

namespace App\Extensions\SocialMediaAgent\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialMediaAgentChatSettingsController extends Controller
{
    public function index()
    {
        return view('social-media-agent::chat.settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsNotDemo()) {
            $suggestions = collect($request->input('input_name', []))
                ->zip($request->input('input_prompt', []))
                ->map(fn ($pair) => [
                    'name'   => trim($pair[0] ?? ''),
                    'prompt' => trim($pair[1] ?? ''),
                ])
                ->filter(fn ($item) => $item['name'] && $item['prompt'])
                ->values()
                ->all();

            $encodedSuggestions = json_encode($suggestions, JSON_THROW_ON_ERROR);

            setting([
                'social_media_agent_example_prompts' => $encodedSuggestions,
            ])->save();

            Setting::forgetCache();
        }

        return back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
