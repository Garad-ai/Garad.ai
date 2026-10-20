<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AIChatProDocument;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Support\Facades\Auth;

final class GaradReferenceAssetService
{
    public function bestImageForPrompt(string $prompt): ?string
{
    $prompt = mb_strtolower(trim($prompt));

    $wantsBrand = preg_match(
        '/\b(brand|branding|logo|official logo|brand identity|astaanta|summad|brand voice)\b/u',
        $prompt
    ) === 1;

    $wantsUploadedReference = preg_match(
        '/\b(reference|attached|uploaded|this image|this logo|edit|modify|restyle|use this|kan|kaan|sawir|sawirka|adeegso|isticmaal)\b/u',
        $prompt
    ) === 1;

    /*
     * If the user explicitly asks for the selected brand,
     * official logo or brand identity, the selected
     * Brand Voice logo MUST have first priority.
     */
    if ($wantsBrand) {
        $brandLogo = $this->brandLogo();

        if ($brandLogo) {
            return $brandLogo;
        }
    }

    /*
     * If the request specifically refers to an uploaded
     * or attached image, use the latest owned image
     * from the current conversation.
     */
    if ($wantsUploadedReference) {
        $uploaded = $this->latestChatImage();

        if ($uploaded) {
            return $uploaded;
        }
    }

    /*
     * Final safe fallback.
     */
    return $this->brandLogo();
}

    public function latestChatImage(): ?string
    {
        if (! Auth::check()) {
            return null;
        }

        $chatId = (int) request()?->input('chat_id', 0);
        if ($chatId <= 0) {
            return null;
        }

        $ownedChat = UserOpenaiChat::query()->whereKey($chatId)->where('user_id', Auth::id())->first();
        if (! $ownedChat) {
            return null;
        }

        // 1. Prefer the actual most recent image uploaded in this conversation.
        // MagicAI stores these paths in user_openai_chat_messages.images.
        try {
            $message = UserOpenaiChatMessage::query()
                ->where('user_openai_chat_id', $chatId)
                ->where('user_id', Auth::id())
                ->whereNotNull('images')
                ->where('images', '<>', '')
                ->latest('id')
                ->first();

            $images = trim((string) ($message?->images ?? ''));
            if ($images !== '') {
                $candidates = array_values(array_filter(array_map('trim', explode(',', $images))));
                foreach (array_reverse($candidates) as $candidate) {
                    if ($this->looksLikeImage($candidate)) {
                        return $this->absolute($candidate);
                    }
                }
            }
        } catch (\Throwable) {
            // Continue through the multimodal repository fallbacks.
        }

        // 2. New multimodal repository path.
        try {
            if (class_exists(AIChatProDocument::class)) {
                $doc = AIChatProDocument::query()
                    ->where('user_openai_chat_id', $chatId)
                    ->where(function ($q) {
                        $q->where('user_id', Auth::id())->orWhereNull('user_id');
                    })
                    ->where('status', 'ready')
                    ->where(function ($q) {
                        $q->where('mime_type', 'like', 'image/%')
                            ->orWhereIn('file_extension', ['png', 'jpg', 'jpeg', 'webp']);
                    })
                    ->latest('id')
                    ->first();

                if ($doc?->reference_url) {
                    return $this->absolute((string) $doc->reference_url);
                }
            }
        } catch (\Throwable) {
        }

        // 3. Legacy chat-level reference.
        $ref = trim((string) ($ownedChat->reference_url ?? ''));
        if ($ref !== '' && $this->looksLikeImage($ref)) {
            return $this->absolute($ref);
        }

        return null;
    }

    public function brandLogo(): ?string
    {
        try {
            $brand = app(GaradBrandContextService::class)->resolve();
            return $brand['public_logo_url'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function looksLikeImage(string $value): bool
    {
        return preg_match('/\.(png|jpe?g|webp|gif)(\?.*)?$/i', $value) === 1
            || str_starts_with($value, 'data:image/');
    }

    private function absolute(string $value): string
    {
        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return url('/' . ltrim($value, '/'));
    }
}
