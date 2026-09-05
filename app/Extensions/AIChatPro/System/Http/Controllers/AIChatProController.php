<?php

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Domains\Entity\Models\Entity;
use App\Extensions\AIChatPro\System\Jobs\GenerateChatSuggestionsJob;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use App\Models\Chatbot\Chatbot;
use App\Models\ChatCategory;
use App\Models\Favourite;
use App\Models\OpenaiGeneratorChatCategory;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Models\User;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use App\Services\Bedrock\BedrockRuntimeService;
use App\Services\GatewaySelector;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonException;
use Random\RandomException;

class AIChatProController extends Controller
{
    protected $client;

    protected $settings;

    protected $settings_two;

    protected BedrockRuntimeService $bedrockService;

    public function __construct(BedrockRuntimeService $bedrockService)
    {
        $this->bedrockService = $bedrockService;
        $this->settings = Setting::getCache();
        $this->settings_two = SettingTwo::getCache();
        $apiKey = $this->getOpenAiApiKey(Auth::user());
        config(['openai.api_key' => $apiKey]);
    }

    /**
     * @throws RandomException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function index($slug = null)
    {
        if (! auth()->check()) {
            $this->deleteOldGuestChat();
            $category = $aiList = OpenaiGeneratorChatCategory::whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
                ->where('slug', 'like', '%ai-chat-bot%')
                ->first()
                ??
                OpenaiGeneratorChatCategory::whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
                    ->first();
            $chat = $this->startNewGuestChat($category);
            $list = [$chat];
            $generators = $chat_completions = $lastThreeMessage = $apiUrl = $apiSearch = $apiSearchId = $apikeyPart1 = $apikeyPart2 = $apikeyPart3 = $chatbots = $models = null;
        } else {
            $activeSub = getCurrentActiveSubscription();
            if ($activeSub !== null) {
                $gateway = $activeSub->paid_with;
            } else {
                $activeSubY = getCurrentActiveSubscriptionYokkasa();
                if ($activeSubY !== null) {
                    $gateway = $activeSubY->paid_with;
                }
            }

            try {
                $isPaid = GatewaySelector::selectGateway($gateway)::getSubscriptionStatus();
            } catch (Exception $e) {
                $isPaid = false;
            }
            $category = $this->firstOpenaiGeneratorChatCategory($slug);
            if (! $isPaid && $category?->plan === 'premium' && ! auth()->user()?->isAdmin()) {
                // $aiList = OpenaiGeneratorChatCategory::all();
                $aiList = OpenaiGeneratorChatCategory::where('slug', '<>', 'ai_vision')->where('slug', '<>', 'ai_pdf')->get();
                $categoryList = ChatCategory::all();
                $favData = Favourite::where('type', 'chat')
                    ->where('user_id', auth()->user()->id)
                    ->get();
                $message = true;

                return redirect()->route('dashboard.user.openai.chat.chat')->with(compact('aiList', 'categoryList', 'favData', 'message'));
            }

            $defaultScreen = setting('ai_chat_pro_default_screen', 'new');

            $query = $this->openai(request())
                ->where('openai_chat_category_id', $category?->id)
                ->where('is_chatbot', 0);

            switch ($defaultScreen) {
                case 'pinned':
                    $list = $query
                        ->orderBy('is_pinned', 'desc')
                        ->orderBy('updated_at', 'desc')
                        ->get();

                    // Pick first pinned chat or fallback to first
                    $chat = $list->first(fn ($c) => $c->is_pinned) ?? $list->first();

                    break;

                case 'new':
                    $chat = $this->startNewChat($category);
                    $list = $query
                        ->orderBy('is_pinned', 'desc')
                        ->orderBy('updated_at', 'desc')
                        ->get();

                    break;

                case 'last':
                default:

                    $list = $query
                        ->orderBy('updated_at', 'desc')->get();
                    $chat = $list->first();

                    break;
            }

            $aiList = OpenaiGeneratorChatCategory::where('slug', '<>', 'ai_vision')->where('slug', '<>', 'ai_pdf')->get();
            $apiUrl = base64_encode('https://api.openai.com/v1/chat/completions');
            if ($this->settings_two->openai_default_stream_server === 'frontend' || setting('realtime_voice_chat', 0)) {
                $apiKey = $this->getOpenAiApiKey(Auth::user());
                $len = strlen($apiKey);
                $len = max($len, 6);
                $parts[] = substr($apiKey, 0, $l[] = random_int(1, $len - 5));
                $parts[] = substr($apiKey, $l[0], $l[] = random_int(1, $len - $l[0] - 3));
                $parts[] = substr($apiKey, array_sum($l));
                $apikeyPart1 = base64_encode($parts[0]);
                $apikeyPart2 = base64_encode($parts[1]);
                $apikeyPart3 = base64_encode($parts[2]);
            } else {
                $apikeyPart1 = base64_encode(random_int(1, 100));
                $apikeyPart2 = base64_encode(random_int(1, 100));
                $apikeyPart3 = base64_encode(random_int(1, 100));
            }

            $apiSearch = base64_encode('https://google.serper.dev/search');
            $apiSearchId = base64_encode($this->settings_two->serper_api_key);
            $lastThreeMessage = null;
            $chat_completions = null;
            if ($chat !== null) {
                $lastThreeMessageQuery = $chat->messages()->whereNot('input', null)->orderBy('created_at', 'desc')->take(2);
                $lastThreeMessage = $lastThreeMessageQuery->get()->reverse();
                $category = OpenaiGeneratorChatCategory::where('id', $chat->openai_chat_category_id)->first();
                $chat_completions = str_replace(["\r", "\n"], '', $category->chat_completions);

                if ($chat_completions) {
                    $chat_completions = json_decode($chat_completions, true, 512, JSON_THROW_ON_ERROR);
                }
            }
            $chatbots = Chatbot::query()->get();
            $models = Entity::planModels();

            $generators = OpenaiGeneratorChatCategory::query()
                ->whereNotIn('slug', [
                    'ai_vision', 'ai_webchat', 'ai_pdf',
                ])
                ->when(Auth::user()?->isUser(), function ($query) {
                    $query->where(function ($query) {
                        $query->whereNull('user_id')->orWhere('user_id', Auth::id());
                    });
                })
                ->get();
        }
        $tempChat = false;

        return view('ai-chat-pro::index', compact(
            'generators',
            'category',
            'apiSearch',
            'chatbots',
            'apiSearchId',
            'list',
            'chat',
            'aiList',
            'apikeyPart1',
            'apikeyPart2',
            'apikeyPart3',
            'tempChat',
            'apiUrl',
            'lastThreeMessage',
            'chat_completions',
            'models',
        ));

    }

    public function getMessageSuggestions(int $messageId): JsonResponse
    {
        $message = UserOpenaiChatMessage::find($messageId);

        if (! $message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        // Verify ownership: authenticated users must own the message, guests can access guest messages
        if (Auth::check()) {
            if ($message->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        } elseif ($message->user_id !== null) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Return cached suggestions if already generated
        if ($message->suggestions_response) {
            return response()->json($message->suggestions_response);
        }

        // Generate suggestions synchronously
        GenerateChatSuggestionsJob::dispatchSync($messageId);

        $message->refresh();

        return response()->json($message->suggestions_response ?? []);
    }

    protected function openai(Request $request): Builder
    {
        $team = $request->user()?->getAttribute('team');
        $myCreatedTeam = $request->user()?->getAttribute('myCreatedTeam');

        $query = UserOpenaiChat::query()
            ->when(Schema::hasColumn('user_openai_chat', 'social_media_analysis_id'), fn (Builder $q) => $q->whereNull('social_media_analysis_id'))
            ->whereNull('chat_type')
            ->where(function (Builder $query) use ($team, $myCreatedTeam) {
                $query->where('user_id', auth()?->id())
                    ->when($team || $myCreatedTeam, function ($query) use ($team, $myCreatedTeam) {
                        if ($team && $team?->is_shared) {
                            $query->orWhere('team_id', $team->id);
                        }
                        if ($myCreatedTeam) {
                            $query->orWhere('team_id', $myCreatedTeam->id);
                        }
                    });
            });

        if (MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            $query->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('user_openai_chat_messages as msg')
                    ->join('ai_chat_pro_image as ai', 'ai.message_id', '=', 'msg.id')
                    ->whereColumn('msg.user_openai_chat_id', 'user_openai_chat.id');
            });
        }

        return $query;
    }

    private function firstOpenaiGeneratorChatCategory(?string $slug = null)
    {
        if ($slug) {
            return OpenaiGeneratorChatCategory::query()
                ->where('slug', $slug)
                ->first();
        }

        $userGenerator = OpenaiGeneratorChatCategory::query()
            ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
            ->where('role', 'default')
            ->when(Auth::user()?->isUser(), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', Auth::id());
                });
            })
            ->first();

        if ($userGenerator) {
            return $userGenerator;
        }

        return OpenaiGeneratorChatCategory::query()
            ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
            ->where('role', 'default')
            ->firstOr(function () {
                return OpenaiGeneratorChatCategory::query()
                    ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
                    ->first();
            });
    }

    private function getOpenAiApiKey(?User $user): string
    {
        return ApiHelper::setOpenAiKey();
    }

    private function deleteOldGuestChat(): void
    {
        $chats = UserOpenaiChat::where('is_guest', true)
            ->where('created_at', '<', now()->subDays(1))
            ->get();
        foreach ($chats as $chat) {
            $chat->messages()->delete();
            $chat->delete();
        }
    }

    private function startNewGuestChat($category)
    {
        $chat = new UserOpenaiChat;
        $chat->user_id = null;
        $chat->team_id = null;
        $chat->chatbot_id = $category->chatbot_id;
        $chat->openai_chat_category_id = $category?->id;
        $chat->title = $category->name . ' Chat';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->thread_id = $thread['id'] ?? null;
        $chat->is_guest = true;
        $chat->save();

        return $chat;
    }

    private function startNewChat($category)
    {
        Helper::clearEmptyConversations();

        $chat = new UserOpenaiChat;
        $chat->user_id = Auth::id();
        $chat->openai_chat_category_id = $category?->id;
        $chat->title = $category->name . ' Chat';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->save();
        $chat->refresh();

        $message = new UserOpenaiChatMessage;
        $message->user_openai_chat_id = $chat->id;
        $message->user_id = Auth::id();
        $message->response = 'First Initiation';
        if ($category->role == 'default') {
            $output = __('Hi! I am') . ' ' . $category->name . __(', and I\'m here to answer all your questions');
        } else {
            $output = __('Hi! I am') . ' ' . $category->human_name . __(', and I\'m') . ' ' . $category->role . '. ' . $category->helps_with;
        }
        $message->output = $output;
        $message->hash = Str::random(256);
        $message->credits = 0;
        $message->words = 0;
        $message->save();

        return $chat;
    }
}
