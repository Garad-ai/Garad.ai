<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProEntityHighlight\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Common\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiChatProEntityHighlightSettingsController extends Controller
{
    public function index()
    {
        return view('ai-chat-pro-entity-highlight::settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsNotDemo()) {
            $request->validate([
                'ai_chat_pro_entity_highlight_max'             => 'required|integer|min:1|max:10',
                'ai_chat_pro_entity_highlight_threshold'       => 'required|numeric|min:0.5|max:1.0',
            ]);

            setting([
                'ai_chat_pro_entity_highlight_enabled'         => $request->boolean('ai_chat_pro_entity_highlight_enabled') ? '1' : '0',
                'ai_chat_pro_entity_highlight_max'             => $request->input('ai_chat_pro_entity_highlight_max', 3),
                'ai_chat_pro_entity_highlight_threshold'       => $request->input('ai_chat_pro_entity_highlight_threshold', 0.8),
            ])->save();

            app(MenuService::class)->regenerate();
        }

        return back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
