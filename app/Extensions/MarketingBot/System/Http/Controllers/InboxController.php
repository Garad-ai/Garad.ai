<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers;

use App\Extensions\Chatbot\System\Http\Resources\Api\ChatbotHistoryResource;
use App\Extensions\MarketingBot\System\Http\Resources\MarketingConversationResource;
use App\Extensions\MarketingBot\System\Http\Resources\MarketingMessageResource;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Models\MarketingMessageHistory;
use App\Extensions\MarketingBot\System\Services\InboxService;
use App\Extensions\MarketingBot\System\Services\Telegram\TelegramSenderService;
use App\Extensions\MarketingBot\System\Services\Whatsapp\WhatsappSenderService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Random\RandomException;
use Throwable;

class InboxController extends Controller
{
    public function __construct(
        public InboxService $service,
        public TelegramSenderService $telegramSenderService,
        public WhatsappSenderService $whatsappSenderService,
    ) {}

    public function index(Request $request)
    {
        return view('marketing-bot::inbox.index');
    }

    public function notification(Request $request): JsonResponse
    {
        $count = MarketingConversation::query()
            ->whereHas('histories', function ($query) {
                $query->whereNull('read_at');
            })
            ->where('user_id', Auth::id())
            ->count();

        return response()->json([
            'class'  => 'hidden',
            'count'  => $count,
            'status' => 'success',
        ]);
    }

    public function name(Request $request): MarketingConversationResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $request->validate([
            'conversation_id'   => 'required',
            'conversation_name' => 'required|string',
        ]);

        $conversation = MarketingConversation::query()->find($request['conversation_id']);

        $this->authorize('update', $conversation);

        $conversation->update(['conversation_name' => $request['conversation_name']]);

        return MarketingConversationResource::make($conversation);
    }

    /**
     * @throws RandomException
     */
    public function store(Request $request): ChatbotHistoryResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $request->validate([
            'conversation_id' => 'required|integer',
            'message'         => 'required|string',
        ]);

        $marketingConversation = MarketingConversation::query()
            ->find($request['conversation_id']);

        $this->authorize('update', $marketingConversation);

        $history = MarketingMessageHistory::query()->create([
            'conversation_id' => $marketingConversation->getKey(),
            'message_id'      => random_int(0, 999999999),
            'model'           => null,
            'role'            => 'assistant',
            'message'         => $request['message'],
            'type'            => 'default',
            'message_type'    => 'text',
            'content_type'    => 'text',
            'read_at'         => now(),
            'created_at'      => now(),
        ]);

        if ($marketingConversation->getAttribute('type') === 'telegram') {
            try {
                $this
                    ->telegramSenderService
                    ->setBot(Auth::id())
                    ->sendText(message: $request['message']);
            } catch (Exception $exception) {
            }
        }

        if ($marketingConversation->getAttribute('type') === 'whatsapp') {
            try {
                $this
                    ->whatsappSenderService
                    ->setWhatsappChannel(Auth::id())
                    ->sendText(
                        receiver: $marketingConversation->getAttribute('session_id'),
                        message: $request['message']
                    );
            } catch (Exception $exception) {
            }
        }

        return ChatbotHistoryResource::make($history);
    }

    public function conversations(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->service->agentConversations('updated_at');

        return MarketingConversationResource::collection($conversations);
    }

    public function conversationsWithPaginate(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->service->agentConversationsWithPaginate();

        return MarketingConversationResource::collection($conversations);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $request->validate(['conversation_id' => 'required|integer']);

        MarketingMessageHistory::query()
            ->where('conversation_id', request('conversation_id'))
            ->update(['read_at' => now()]);

        $conversation = MarketingConversation::query()->find(request('conversation_id'));

        return MarketingMessageResource::collection($conversation->getAttribute('histories'));
    }

    public function searchConversation(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->service->agentConversationsBySearch($request->search ?? '');

        return MarketingConversationResource::collection($conversations);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        try {
            $request->validate(['conversation_id' => 'required|integer']);

            MarketingConversation::query()->find(request('conversation_id'))?->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Successfully removed conversation',
            ]);
        } catch (Throwable $th) {
            return response()->json([
                'status'       => 'error',
                'message'      => 'Something went wrong',
                'errorMessage' => $th->getMessage(),
            ]);
        }
    }
}
