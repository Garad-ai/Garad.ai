<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JsonException;

class CrmAssistantChatSettingsController extends Controller
{
    public function index(): View
    {
        return view('crm::assistant.settings.index');
    }

    /**
     * @throws JsonException
     */
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

            setting([
                'crm_assistant_example_prompts' => json_encode($suggestions, JSON_THROW_ON_ERROR),
            ])->save();

            Setting::forgetCache();
        }

        return back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
