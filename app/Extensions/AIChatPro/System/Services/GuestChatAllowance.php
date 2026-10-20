<?php

namespace App\Extensions\AIChatPro\System\Services;

use App\Models\OpenaiGeneratorChatCategory;
use App\Models\UserOpenaiChat;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GuestChatAllowance
{
    public const LIMIT = 3;

    private const TTL_SECONDS = 86400;

    public function status(Request $request): array
    {
        $used = (int) Cache::get($this->usageKey($request), 0);

        return [
            'limit' => self::LIMIT,
            'used' => min(self::LIMIT, $used),
            'remaining' => max(0, self::LIMIT - $used),
        ];
    }

    /**
     * Reserve one answer atomically, and return the guest chat for this session.
     *
     * @throws LockTimeoutException
     */
    public function reserveAndGetChat(Request $request): ?UserOpenaiChat
    {
        $key = $this->usageKey($request);

        return Cache::lock($key . ':lock', 10)->block(3, function () use ($key, $request) {
            Cache::add($key, 0, self::TTL_SECONDS);
            $used = (int) Cache::get($key, 0);

            if ($used >= self::LIMIT) {
                return null;
            }

            $chat = $this->getOrCreateGuestChat($request);

            if (Cache::increment($key) === false) {
                throw new RuntimeException('Unable to reserve a guest chat answer.');
            }

            return $chat;
        });
    }

    public function releaseReservation(Request $request): void
    {
        $key = $this->usageKey($request);

        if ((int) Cache::get($key, 0) > 0) {
            Cache::decrement($key);
        }
    }

    private function getOrCreateGuestChat(Request $request): UserOpenaiChat
    {
        $chatId = (int) $request->session()->get('garad_guest_chat_id', 0);
        $chat = $chatId > 0
            ? UserOpenaiChat::query()
                ->whereKey($chatId)
                ->whereNull('user_id')
                ->where('is_guest', true)
                ->first()
            : null;

        if ($chat) {
            return $chat;
        }

        $category = OpenaiGeneratorChatCategory::query()
            ->where('slug', 'ai-chat-bot')
            ->first();

        if (! $category) {
            throw new RuntimeException('The default Garad chat category is unavailable.');
        }

        $chat = new UserOpenaiChat;
        $chat->user_id = null;
        $chat->team_id = null;
        $chat->chatbot_id = $category->chatbot_id;
        $chat->openai_chat_category_id = $category->id;
        $chat->title = $category->name . ' Chat';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->is_guest = true;
        $chat->save();

        $request->session()->put('garad_guest_chat_id', $chat->id);
        $guestChatIds = array_map(
            'intval',
            (array) $request->session()->get('ai_chat_pro_guest_chat_ids', []),
        );
        $guestChatIds[] = (int) $chat->id;
        $request->session()->put(
            'ai_chat_pro_guest_chat_ids',
            array_values(array_unique(array_slice($guestChatIds, -10))),
        );

        return $chat;
    }

    private function usageKey(Request $request): string
    {
        return 'garad:guest-chat:usage:' . hash_hmac(
            'sha256',
            $request->session()->getId(),
            (string) config('app.key'),
        );
    }
}
