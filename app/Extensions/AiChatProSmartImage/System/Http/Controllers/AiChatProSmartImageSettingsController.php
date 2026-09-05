<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProSmartImage\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiChatProSmartImageSettingsController extends Controller
{
    public function index()
    {
        return view('ai-chat-pro-smart-image::settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsNotDemo()) {
            $request->validate([
                'ai_chat_pro_smart_image_max_images' => 'required|integer|min:1|max:10',
            ]);

            setting([
                'ai_chat_pro_smart_image_enabled'     => $request->boolean('ai_chat_pro_smart_image_enabled') ? '1' : '0',
                'ai_chat_pro_smart_image_max_images'  => $request->input('ai_chat_pro_smart_image_max_images', 5),
            ])->save();
        }

        return back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
