<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProHighlightToAsk\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Common\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiChatProHighlightToAskSettingsController extends Controller
{
    public function index()
    {
        return view('ai-chat-pro-highlight-to-ask::settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsNotDemo()) {
            setting([
                'ai_chat_pro_highlight_to_ask_enabled' => $request->boolean('ai_chat_pro_highlight_to_ask_enabled') ? '1' : '0',
            ])->save();

            app(MenuService::class)->regenerate();
        }

        return back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
