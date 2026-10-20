<?php

namespace App\Extensions\AIChatPro\System\Http\Middleware;

use App\Extensions\AIChatPro\System\Services\GuestChatAllowance;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GuestChatGenerationMiddleware
{
    public function __construct(
        private readonly GuestChatAllowance $allowance,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return response()->json([
                'message' => 'This endpoint is for guest chat only.',
                'code' => 'guest_chat_only',
            ], 403);
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:12000'],
        ]);

        try {
            $chat = $this->allowance->reserveAndGetChat($request);
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Guest chat is busy. Please try again.',
                'code' => 'guest_chat_busy',
            ], 503);
        }

        if (! $chat) {
            return new JsonResponse([
                'message' => 'You have used all three free answers. Sign in to continue chatting.',
                'code' => 'guest_chat_limit',
                'limit' => GuestChatAllowance::LIMIT,
                'remaining' => 0,
            ], 429);
        }

        $request->merge([
            'prompt' => $validated['prompt'],
            'chat_id' => $chat->id,
            'template_type' => 'chatPro',
            'chatbot_front_model' => null,
            'chat_brand_voice' => null,
            'brand_voice_prod' => null,
            'skill_ids' => null,
            'realtime' => false,
            'images' => [],
            'pdfname' => null,
            'pdfpath' => null,
            'assistant' => null,
            'chat_open_ai_agent_id' => null,
            'temp_chat_button' => false,
        ]);

        try {
            return $next($request);
        } catch (Throwable $exception) {
            $this->allowance->releaseReservation($request);

            throw $exception;
        }
    }
}
