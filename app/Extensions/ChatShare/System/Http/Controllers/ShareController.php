<?php

namespace App\Extensions\ChatShare\System\Http\Controllers;

use App\Extensions\ChatShare\System\Models\ShareLink;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\OpenaiGeneratorChatCategory;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    public function share($category, $chat, $message): View
    {
        $link = request()?->fullUrl();
        $alternateLink = Str::startsWith($link, 'https://')
            ? str_replace('https://', 'http://', $link)
            : str_replace('http://', 'https://', $link);

        abort_unless(
            ShareLink::query()->where('url', $link)->orWhere('url', $alternateLink)->exists(),
            404
        );

        $category = OpenaiGeneratorChatCategory::whereSlug($category)->firstOrFail();
        $chat = UserOpenaiChat::query()->findOrFail($chat);
        $list = $chat->toArray();
        $chatCompletions = str_replace(["\r", "\n"], '', $category->chat_completions);
        $lastThreeMessage = collect();

        if (filled($chatCompletions)) {
            $chatCompletions = json_decode($chatCompletions, true, 512, JSON_THROW_ON_ERROR);
        }

        // v2 share links intentionally expose only the selected message row
        // (the user's prompt + its Garad AI response), not the entire private chat.
        $messages = $chat->messages()
            ->whereKey((int) $message)
            ->get();

        return view('chat-share::index', [
            'category'         => $category,
            'chat'             => $chat,
            'list'             => $list,
            'chat_completions' => $chatCompletions,
            'lastThreeMessage' => $lastThreeMessage,
            'messages'         => $messages,
        ]);
    }

    public function createLink(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401);

        $categoryId = (int) $request->input('category_id');
        $chatId = (int) $request->input('chat_id');
        $requestedMessageId = (int) $request->input('message_id');

        $category = OpenaiGeneratorChatCategory::query()->findOrFail($categoryId);
        $chat = UserOpenaiChat::query()
            ->whereKey($chatId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $messageQuery = UserOpenaiChatMessage::query()
            ->where('user_openai_chat_id', $chat->id);

        $message = $requestedMessageId > 0
            ? (clone $messageQuery)->whereKey($requestedMessageId)->first()
            : (clone $messageQuery)->latest('id')->first();

        abort_unless($message, 404);

        $time = Carbon::now()->timestamp;
        $url = Helper::parseUrl(config('app.url'), 'share', $category->slug, $chatId, $message->id);
        $url .= '?time=' . $time;

        ShareLink::query()->create([
            'url'      => $url,
            'category' => $category->slug,
            'chat'     => $chatId,
            'message'  => $message->id,
            'time'     => $time,
        ]);

        return response()->json(['link' => $url]);
    }
}
