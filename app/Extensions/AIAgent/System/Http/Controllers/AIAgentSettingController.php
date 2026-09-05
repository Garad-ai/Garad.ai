<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIAgentSettingController extends Controller
{
    private function getCopilotModels(): array
    {
        return [
            ''              => __('GPT-5.4 Mini (Default)'),
            'gpt-5.4-nano'  => __('GPT-5.4 Nano'),
            'gpt-5-mini'    => __('GPT-5 Mini'),
            'gpt-5-nano'    => __('GPT-5 Nano'),
        ];
    }

    public function index(Request $request): View
    {
        return view('ai-agent::admin.settings', [
            'copilotModels'        => $this->getCopilotModels(),
            'currentCopilotModel'  => setting('ai-agent-copilot-model', ''),
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
            'ai-agent-copilot-model' => 'nullable|string',
        ]);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Settings updated successfully.'),
        ]);
    }
}
