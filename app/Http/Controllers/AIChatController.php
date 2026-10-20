<?php

namespace App\Http\Controllers;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity as EntityFacade;
use App\Domains\Entity\Models\Entity;
use App\Enums\AccessType;
use App\Enums\AiImageStatusEnum;
use App\Enums\BedrockEngine;
use App\Extensions\ElevenLabsVoiceChat\System\Services\ElevenLabsVoiceChatService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\ChatAttachmentReferences;
use App\Helpers\Classes\DefaultOpenAiImageModel;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use App\Models\Chatbot\Chatbot;
use App\Models\ChatBotHistory;
use App\Models\ChatCategory;
use App\Models\Favourite;
use App\Models\OpenaiGeneratorChatCategory;
use App\Models\PdfData;
use App\Models\RateLimit;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Models\Usage;
use App\Models\User;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use App\Services\Ai\AiCompletionService;
use App\Services\Ai\OpenAI\FileSearchService;
use App\Services\Assistant\AssistantService;
use App\Services\Bedrock\BedrockRuntimeService;
use App\Services\GatewaySelector;
use App\Services\Stream\StreamService;
use App\Services\VectorService;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use OpenAI\Laravel\Facades\OpenAI;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AIChatController extends Controller
{
    private const SIDEBAR_HISTORY_LIMIT_DEFAULT = 100;

    protected $client;

    protected $settings;

    protected $settings_two;

    protected ?BedrockRuntimeService $bedrockService = null;

    public function __construct()
    {
        if (request()->route()?->getActionMethod() === 'startNewChat') {
            return;
        }

        $this->settings = Setting::getCache();
        $this->settings_two = SettingTwo::getCache();
        $apiKey = $this->getOpenAiApiKey(Auth::user());
        config(['openai.api_key' => $apiKey]);
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

    private function sidebarHistoryLimit(): int
    {
        $configuredLimit = (int) setting('ai_chat_sidebar_history_limit', self::SIDEBAR_HISTORY_LIMIT_DEFAULT);

        return max(20, min($configuredLimit, 500));
    }

    /**
     * Get paginated chats for the sidebar via AJAX.
     * Supports all extensions (chatpro, chatpro-image, social-media-agent, default).
     */
    public function getChats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_id'   => 'nullable|integer',
            'per_page'    => 'nullable|integer|min:1|max:100',
            'is_pinned'   => 'nullable|boolean',
            'category_id' => 'nullable|integer',
            'search'      => 'nullable|string|max:255',
            'scope'       => 'nullable|in:sidebar_all',
            'website_url' => 'nullable|string|max:255',
            'mode'        => 'nullable|in:chat,agents',
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $search = $validated['search'] ?? null;
        $websiteUrl = ! empty($validated['website_url']) ? $validated['website_url'] : null;
        $categoryId = $validated['category_id'] ?? null;
        $folderId = $validated['folder_id'] ?? null;

        $isChatProContext = in_array($websiteUrl, ['chatpro', 'chatpro-temp', 'chatPro', 'chatpro-image', 'social-media-agent', 'crm-assistant'], true);
        $foldersEnabled = $isChatProContext && MarketplaceHelper::isRegistered('ai-chat-pro-folders');

        $selectColumns = ['id', 'title', 'created_at', 'is_pinned', 'updated_at', 'reference_url', 'doc_name', 'website_url', 'openai_chat_category_id'];

        if ($foldersEnabled) {
            $selectColumns[] = 'folder_id';
        }

        $query = UserOpenaiChat::query()
            ->where('user_id', Auth::id())
            ->where('is_chatbot', 0)
            ->select($selectColumns)
            ->when(array_key_exists('is_pinned', $validated), fn ($query) => $query->where('is_pinned', (bool) $validated['is_pinned']))
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at');

        $this->applyWebsiteChatTypeScope($query, $websiteUrl);

        $agentConversationTableExists = Schema::hasTable('ext_ai_chat_pro_agent_conversations');
        if ($agentConversationTableExists && isset($validated['mode'])) {
            $agentChatIds = DB::table('ext_ai_chat_pro_agent_conversations')
                ->select('chat_id')
                ->where('user_id', Auth::id());

            if ($validated['mode'] === 'agents') {
                $query->whereIn('user_openai_chat.id', $agentChatIds);
            } else {
                $query->whereNotIn('user_openai_chat.id', $agentChatIds);
            }
        } elseif (($validated['mode'] ?? null) === 'agents') {
            $query->whereRaw('1 = 0');
        }

        if ($categoryId !== null) {
            $query->where('openai_chat_category_id', $categoryId);
        }

        if ($foldersEnabled && ($search === null || $search === '')) {
            if ($folderId !== null) {
                $query->where('folder_id', $folderId);
            } elseif (($validated['scope'] ?? null) !== 'sidebar_all') {
                $query->whereNull('folder_id');
            }
        }

        if ($search !== null && $search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $chats = $query->paginate($perPage);
        $agentChatIds = $agentConversationTableExists
            ? DB::table('ext_ai_chat_pro_agent_conversations')
                ->where('user_id', Auth::id())
                ->whereIn('chat_id', collect($chats->items())->pluck('id'))
                ->pluck('chat_id')
                ->map(static fn ($id): string => (string) $id)
                ->all()
            : [];

        $thumbnails = [];
        $isChatProImage = $websiteUrl === 'chatpro-image'
            && MarketplaceHelper::isRegistered('ai-chat-pro-image-chat');

        if ($isChatProImage) {
            $chatIds = collect($chats->items())->pluck('id')->filter()->values();

            if ($chatIds->isNotEmpty()) {
                $thumbnails = $this->buildChatImageThumbnails($chatIds);
            }
        }

        $chatsData = collect($chats->items())->map(function ($chat) use ($thumbnails, $foldersEnabled, $agentChatIds) {
            $data = [
                'id'            => $chat->id,
                'title'         => $chat->title,
                'mode'          => in_array((string) $chat->id, $agentChatIds, true) ? 'agents' : 'chat',
                'created_at'    => $chat->created_at?->toIso8601String(),
                'updated_at'    => $chat->updated_at?->toIso8601String(),
                'is_pinned'     => (bool) $chat->is_pinned,
                'reference_url' => $chat->reference_url,
                'doc_name'      => $chat->doc_name,
                'website_url'   => $chat->website_url,
                'openai_chat_category_id' => $chat->openai_chat_category_id,
                'folder_id'     => $foldersEnabled ? $chat->folder_id : null,
            ];

            if (isset($thumbnails[$chat->id])) {
                $data['thumbnail_url'] = $thumbnails[$chat->id];
            }

            return $data;
        })->values();

        return response()->json([
            'success'    => true,
            'user_id'    => Auth::id(),
            'chats'      => $chatsData,
            'pagination' => [
                'current_page' => $chats->currentPage(),
                'last_page'    => $chats->lastPage(),
                'per_page'     => $chats->perPage(),
                'total'        => $chats->total(),
                'has_more'     => $chats->hasMorePages(),
            ],
        ]);
    }

    /**
     * Build thumbnail URLs for image-pro chat items.
     *
     * @param  Collection<int, int>  $chatIds
     *
     * @return array<int, string>
     */
    private function buildChatImageThumbnails($chatIds): array
    {
        $latestImageIdsByChat = DB::table('ai_chat_pro_image as ai')
            ->join('user_openai_chat_messages as msg', 'msg.id', '=', 'ai.message_id')
            ->whereIn('msg.user_openai_chat_id', $chatIds)
            ->where('ai.status', AiImageStatusEnum::COMPLETED->value)
            ->whereNotNull('ai.generated_images')
            ->selectRaw('msg.user_openai_chat_id as chat_id, MAX(ai.id) as latest_ai_id')
            ->groupBy('msg.user_openai_chat_id');

        $latestRecords = DB::table('ai_chat_pro_image as ai')
            ->joinSub($latestImageIdsByChat, 'latest_ai', function ($join) {
                $join->on('ai.id', '=', 'latest_ai.latest_ai_id');
            })
            ->select('latest_ai.chat_id', 'ai.generated_images')
            ->get();

        $thumbnails = [];

        foreach ($latestRecords as $record) {
            $chatId = (int) ($record->chat_id ?? 0);

            if ($chatId <= 0) {
                continue;
            }

            $images = json_decode((string) ($record->generated_images ?? '[]'), true);

            if (! is_array($images)) {
                continue;
            }

            $firstImage = reset($images);

            if (! is_string($firstImage) || trim($firstImage) === '') {
                continue;
            }

            $firstImage = trim($firstImage);

            if (str_starts_with($firstImage, '//')) {
                $firstImage = request()->getScheme() . ':' . $firstImage;
            } elseif (
                ! str_starts_with($firstImage, 'http://') &&
                ! str_starts_with($firstImage, 'https://') &&
                ! str_starts_with($firstImage, 'data:') &&
                ! str_starts_with($firstImage, 'blob:')
            ) {
                $thumbnailSource = '/' . ltrim($firstImage, '/');
                $firstImage = ThumbImage($thumbnailSource, 160, 160);

                if (
                    ! str_starts_with($firstImage, 'http://') &&
                    ! str_starts_with($firstImage, 'https://') &&
                    ! str_starts_with($firstImage, '//') &&
                    ! str_starts_with($firstImage, 'data:') &&
                    ! str_starts_with($firstImage, 'blob:')
                ) {
                    $firstImage = url(ltrim($firstImage, '/'));
                }
            }

            $thumbnails[$chatId] = $firstImage;
        }

        return $thumbnails;
    }

    public function search(Request $request): JsonResponse
    {
        $categoryId = $request->category_id;
        $search = $request->search_word;
        $website_url = $request->website_url ?? null;

        $list = UserOpenaiChat::query()
            ->where('user_id', Auth::id())
            ->where('openai_chat_category_id', $categoryId)
            ->where('is_chatbot', 0)
            ->where('title', 'like', "%$search%")
            ->orderBy('updated_at', 'desc')
            ->limit($this->sidebarHistoryLimit());

        $this->applyWebsiteChatTypeScope($list, $website_url);

        $list = $list->get();
        $is_search = $search !== null && $search !== '';
        $html = view('panel.user.openai_chat.components.chat_sidebar_list', compact('list', 'website_url', 'is_search'))->render();

        return response()->json(compact('html'));
    }

    public function openAIChat($slug = null)
    {
        if (MarketplaceHelper::isRegistered('ai-chat-pro')) {
            if (in_array(setting('ai_chat_display_type', 'menu'), ['ai_chat', 'both']) || str_contains(url()->previous(), 'chat/pro/chat')) {
                if (auth()->user()?->isAdmin()) {
                    return redirect()->route('dashboard.user.openai.chat.pro.index', ['slug' => $slug]);
                }

                return redirect()->route('chat.pro', ['slug' => $slug]);
            }
        }

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
        $requiredType = AccessType::tryFrom($category?->plan) ?? AccessType::REGULAR;
        $user = auth()->user();
        // If the category requires a non-regular plan
        if ($requiredType !== AccessType::REGULAR && ! $user?->isAdmin()) {

            // Check if the user has an active plan
            $activePlan = $user?->activePlan();

            $userPlanType = $activePlan
                ? (AccessType::tryFrom($activePlan->plan_type) ?? AccessType::REGULAR)
                : AccessType::REGULAR;

            // Deny access if user plan does not match the required type
            if ($userPlanType !== $requiredType) {
                return back()->with([
                    'message' => 'You need to be on a ' . $requiredType->label() . ' plan to access this chat.',
                    'type'    => 'warning',
                ]);
            }
        }

        $list = $this->openai(\request())
            ->where('openai_chat_category_id', $category?->id)
            ->where('is_chatbot', 0)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->limit($this->sidebarHistoryLimit());
        $list = $list->get();
        $chat = $list->first();
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
        $apiSearchId = base64_encode($this->settings_two->serper_api_key ?? '');
        $lastThreeMessage = null;
        $chat_completions = null;
        if ($chat !== null) {
            $lastThreeMessageQuery = $chat->messages()->whereNot('input', null)->orderBy('created_at', 'desc')->take(2);
            $lastThreeMessage = $lastThreeMessageQuery->get()->reverse();
            $category = OpenaiGeneratorChatCategory::where('id', $chat->openai_chat_category_id)->first();
            $chat_completions = str_replace(["\r", "\n"], '', $category->chat_completions ?? '');

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

        $elevenlabsAgentId = null;
        if ($slug === 'ai_realtime_voice_chat' && MarketplaceHelper::isRegistered('elevenlabs-voice-chat')) {
            $elevenlabsAgentId = app(ElevenLabsVoiceChatService::class)?->fetchVoiceChatbot()?->agent_id ?? null;
        }

        return view('panel.user.openai_chat.chat', compact(
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
            'apiUrl',
            'lastThreeMessage',
            'chat_completions',
            'models',
            'elevenlabsAgentId'
        ));
    }

    protected function openai(Request $request): Builder
    {
        $team = Helper::appIsDemo() ? null : $request->user()->getAttribute('team');
        $myCreatedTeam = Helper::appIsDemo() ? null : $request->user()->getAttribute('myCreatedTeam');

        return UserOpenaiChat::query()
            ->where(function (Builder $query) use ($team, $myCreatedTeam) {
                $query->where('user_id', auth()->id())
                    ->when($team || $myCreatedTeam, function ($query) use ($team, $myCreatedTeam) {
                        if ($team && $team?->is_shared) {
                            $query->orWhere('team_id', $team->id);
                        }
                        if ($myCreatedTeam) {
                            $query->orWhere('team_id', $myCreatedTeam->id);
                        }
                    });
            });
    }

    /**
     * @throws Throwable
     * @throws RandomException
     */
    public function openChatAreaContainer(Request $request): JsonResponse
    {
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
        $website_url = $request->website_url ?? null;
        $chatQuery = $this->openai($request)->whereKey($request->chat_id);
        $this->applyWebsiteChatTypeScope($chatQuery, $website_url);
        $chat = $chatQuery->first();

        if (! $chat) {
            return response()->json([
                'message' => __('Chat not found for this chat type.'),
            ], 404);
        }

        $category = $chat->category;
        if (setting('realtime_voice_chat', 0)) {
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

        $elevenlabsAgentId = null;
        if (setting('default_voice_chat_engine', 'openai') == 'elevenlabs' && MarketplaceHelper::isRegistered('elevenlabs-voice-chat')) {
            $elevenlabsAgentId = app(ElevenLabsVoiceChatService::class)?->fetchVoiceChatbot()?->agent_id ?? null;
        }

        $chatView = 'panel.user.openai_chat.components.chat_area_container';
        $tempChat = false;

        if (in_array($website_url, ['chatpro', 'chatpro-temp']) && MarketplaceHelper::isRegistered('ai-chat-pro')) {
            $tempChat = $website_url === 'chatpro-temp';
            if ($tempChat) {
                app(StreamService::class)->clearTempChatHistory();
            }

            if (! auth()->check()) {
                $generators = [];
            }
            $chatView = MarketplaceHelper::isRegistered('canvas') ? 'canvas::includes.chat_area_container' : 'ai-chat-pro::includes.chat_area_container';
        }

        if ($website_url === 'chatpro-image' && MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            $tempChat = false;

            if (! auth()->check()) {
                $generators = [];
            }
            $chatView = 'ai-chat-pro-image-chat::includes.chat_area_container';
        }

        if (in_array($website_url, ['social-media-agent']) && MarketplaceHelper::isRegistered('social-media-agent')) {
            $chatView = 'social-media-agent::chat.includes.chat_area_container';
        }

        if ($website_url === 'crm-assistant' && MarketplaceHelper::isRegistered('crm')) {
            $chatView = 'crm::assistant.includes.chat_area_container';
        }

        $html = view($chatView, compact(
            'chat',
            'category',
            'apikeyPart1',
            'apikeyPart2',
            'apikeyPart3',
            'generators',
            'website_url',
            'tempChat',
            'elevenlabsAgentId'
        ))->render();
        $lastThreeMessageQuery = $chat->messages()->whereNot('input', null)->orderBy('created_at', 'desc')->take(2);
        $lastThreeMessage = $lastThreeMessageQuery->get()->toArray();

        return response()->json(compact('html', 'tempChat', 'lastThreeMessage'));
    }

    public function openAIChatList()
    {
        abort_if(Helper::setting('feature_ai_chat') === 0, 404);

        $aiList = OpenaiGeneratorChatCategory::query()
            ->whereNotIn('slug', [
                'ai_vision', 'ai_webchat', 'ai_pdf',
            ])
            ->when(Auth::user()?->isUser(), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('user_id')->orWhere('user_id', Auth::id());
                });
            })
            ->get();

        $categoryList = ChatCategory::where('user_id', 1)->orWhere('user_id', auth()->user()->id)->get();
        $favData = Favourite::where('type', 'chat')
            ->where('user_id', auth()->user()->id)
            ->get();
        $message = false;

        return view('panel.user.openai_chat.list', compact('aiList', 'categoryList', 'favData', 'message'));
    }

    public function openChatBotArea(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id'     => 'required|integer|min:1',
            'website_url' => 'nullable|string|max:255',
            'messages_only' => 'nullable|boolean',
        ]);
        $chatQuery = UserOpenaiChat::query()
            ->whereKey($validated['chat_id'])
            ->where('user_id', Auth::id())
            ->where('is_chatbot', 0);
        if (! empty($validated['website_url'])) {
            $this->applyWebsiteChatTypeScope($chatQuery, $validated['website_url']);
        }
        $chat = $chatQuery->first();

        if (! $chat) {
            return response()->json(['message' => __('Chat not found.')], 404);
        }

        if ($request->boolean('messages_only')) {
            $messages = $chat->messages()
                ->where('user_id', Auth::id())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'input',
                    'response',
                    'output',
                    'images',
                    'pdfName',
                    'pdfPath',
                    'created_at',
                    'updated_at',
                ]);

            return response()->json([
                'messages' => $this->attachChatArtifacts($messages, (int) $chat->id),
            ]);
        }

        $category = $chat->category;
        $html = view('panel.user.openai_chat.components.chat_area', compact('chat', 'category'))->render();
        $lastThreeMessage = $chat->messages()
            ->whereNotNull('input')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $lastThreeMessage = $this->attachChatArtifacts($lastThreeMessage, (int) $chat->id)
            ->toArray();
        $conversation = [
            'id'         => $chat->id,
            'title'      => $chat->title,
            'mode'       => Schema::hasTable('ext_ai_chat_pro_agent_conversations')
                && DB::table('ext_ai_chat_pro_agent_conversations')
                    ->where('chat_id', $chat->id)
                    ->where('user_id', Auth::id())
                    ->exists()
                    ? 'agents'
                    : 'chat',
            'created_at' => $chat->created_at?->toIso8601String(),
            'updated_at' => $chat->updated_at?->toIso8601String(),
            'is_pinned'  => (bool) $chat->is_pinned,
            'folder_id'  => $chat->folder_id,
        ];

        return response()->json(compact('html', 'lastThreeMessage', 'conversation'));
    }

    private function attachChatArtifacts(Collection $messages, int $chatId): Collection
    {
        $artifactsByMessage = [];
        if (
            $messages->isNotEmpty() &&
            MarketplaceHelper::isRegistered('ai-chat-pro') &&
            Schema::hasTable('ai_chat_pro_artifacts') &&
            Schema::hasColumn('ai_chat_pro_artifacts', 'user_openai_chat_message_id')
        ) {
            $artifactRows = DB::table('ai_chat_pro_artifacts')
                ->where('user_id', Auth::id())
                ->where('user_openai_chat_id', $chatId)
                ->whereIn('user_openai_chat_message_id', $messages->pluck('id'))
                ->orderBy('id')
                ->get();

            foreach ($artifactRows as $artifact) {
                $type = strtolower((string) $artifact->type);
                $kind = match ($type) {
                    'pdf' => 'pdf',
                    'doc', 'docx' => 'document',
                    'xls', 'xlsx' => 'spreadsheet',
                    'ppt', 'pptx' => 'presentation',
                    default => 'generated',
                };
                $url = route('dashboard.user.openai.chat.pro.artifacts.download', [
                    'artifact' => $artifact->id,
                ]);
                $metadata = json_decode((string) ($artifact->metadata ?? ''), true) ?: [];

                $artifactsByMessage[(string) $artifact->user_openai_chat_message_id][] = [
                    'id' => (string) $artifact->id,
                    'name' => (string) $artifact->original_name,
                    'kind' => $kind,
                    'description' => (string) $artifact->title,
                    'status' => 'ready',
                    'url' => $url,
                    'downloadUrl' => $url,
                    'previewUrl' => in_array($type, ['pdf', 'doc', 'docx', 'xlsx', 'xls'], true)
                        ? $url . '?preview=1'
                        : null,
                    'slideCount' => isset($metadata['slide_count']) ? (int) $metadata['slide_count'] : null,
                    'externalUrl' => $metadata['external_url'] ?? null,
                    'createdAt' => optional($artifact->created_at)->toIso8601String(),
                    'updatedAt' => optional($artifact->updated_at)->toIso8601String(),
                ];
            }
        }

        foreach ($messages as $message) {
            $message->setAttribute(
                'artifacts',
                $artifactsByMessage[(string) $message->id] ?? [],
            );
        }

        return $messages;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws RandomException
     */
    public function startNewChat(Request $request): JsonResponse
    {
        $reactChat = $request->boolean('react_chat');
        if (! $reactChat) {
            Helper::clearEmptyConversations();
        }

        $validated = $request->validate([
            'category_id' => 'nullable|integer|min:1',
            'website_url' => 'nullable|string|max:255',
            'react_chat'  => 'nullable|boolean',
        ]);
        $user = Auth::user();
        $categoryQuery = OpenaiGeneratorChatCategory::query()
            ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
            ->when($user?->isUser(), function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->whereNull('user_id')->orWhere('user_id', $user->id);
                });
            });
        $category = isset($validated['category_id'])
            ? (clone $categoryQuery)->whereKey($validated['category_id'])->firstOrFail()
            : $this->firstOpenaiGeneratorChatCategory();
        abort_if(! $category, 404, __('No chat category is available for this account.'));
        $chatbot = Chatbot::query()->where('id', $category->chatbot_id)->first();
        $thread = null;
        if ($category->assistant !== null && ! $reactChat) {
            $service = new AssistantService;
            $thread = $service->createThread();
        }
        $chat = new UserOpenaiChat;
        $website_url = $validated['website_url'] ?? null;

        if (in_array($website_url, ['social-media-agent', 'chatpro-image', 'crm-assistant'], true)) {
            $chat->chat_type = $website_url;
        }

        $chat->user_id = $user?->id;
        $chat->team_id = $user?->team_id;
        $chat->chatbot_id = $category->chatbot_id;
        $chat->openai_chat_category_id = $category?->id;
        $chat->title = $chat->chat_type === 'crm-assistant' ? __('CRM Assistant') : $category->name . ' Chat';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->thread_id = $thread['id'] ?? null;
        $chat->save();

        $message = new UserOpenaiChatMessage;
        $message->user_openai_chat_id = $chat?->id;
        $message->user_id = $user?->id;
        $message->response = 'First Initiation';
        if ($category?->slug !== 'ai_vision' || $category?->slug !== 'ai_pdf') {
            if ($category->role === 'default') {
                $output = __('Hi! I am') . ' ' . $category->name . __(', and I\'m here to answer all your questions');
            } else {
                $output = __('Hi! I am') . ' ' . $category->human_name . __(', and I\'m') . ' ' . $category->role . '. ' . $category->helps_with;
            }
        } else {
            $output = null;
        }

        if ($chatbot) {
            if ($chatbot->first_message !== null) {
                $output = $chatbot->first_message;
            }
        }

        if ($category) {
            if ($category->first_message !== null) {
                $output = $category->first_message;
            }
        }

        if ($chat->chat_type === 'crm-assistant') {
            $output = __('Hi! I am your CRM Assistant. Ask me anything about your contacts, deals, tasks and invoices — or tell me what to create, update or delete.');
        }

        $message->output = $output;
        $message->hash = Str::random(256);
        $message->credits = 0;
        $message->words = 0;
        $message->save();
        if ($reactChat) {
            return new JsonResponse([
                'chat' => [
                    'id'                       => $chat->id,
                    'openai_chat_category_id' => $category->id,
                ],
            ]);
        }

        if (setting('realtime_voice_chat', 0)) {
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
        $list = UserOpenaiChat::query()
            ->where('user_id', $user?->id)
            ->where('openai_chat_category_id', $category?->id)
            ->where('is_chatbot', 0)
            ->orderBy('updated_at', 'desc')
            ->limit($this->sidebarHistoryLimit());

        $this->applyWebsiteChatTypeScope($list, $website_url);
        $list = $list->get();

        $chatView = 'panel.user.openai_chat.components.chat_area_container';
        $tempChat = false;
        if (in_array($website_url, ['chatpro', 'chatpro-temp']) && MarketplaceHelper::isRegistered('ai-chat-pro')) {
            $tempChat = $website_url === 'chatpro-temp';
            if ($tempChat) {
                app(StreamService::class)->clearTempChatHistory();
            }

            if (! auth()->check()) {
                $generators = [];
            }
            $chatView = MarketplaceHelper::isRegistered('canvas') ? 'canvas::includes.chat_area_container' : 'ai-chat-pro::includes.chat_area_container';
        }

        if ($website_url === 'chatpro-image' && MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            $tempChat = false;

            if (! auth()->check()) {
                $generators = [];
            }
            $chatView = 'ai-chat-pro-image-chat::includes.chat_area_container';
        }

        if (in_array($website_url, ['social-media-agent']) && MarketplaceHelper::isRegistered('social-media-agent')) {
            $chatView = 'social-media-agent::chat.includes.chat_area_container';
        }

        if ($website_url === 'crm-assistant' && MarketplaceHelper::isRegistered('crm')) {
            $chatView = 'crm::assistant.includes.chat_area_container';
        }

        $elevenlabsAgentId = null;
        if (setting('default_voice_chat_engine', 'openai') == 'elevenlabs' && MarketplaceHelper::isRegistered('elevenlabs-voice-chat')) {
            $voiceChatbot = app(ElevenLabsVoiceChatService::class)?->fetchVoiceChatbot();

            if (is_array($voiceChatbot)) {
                $elevenlabsAgentId = $voiceChatbot['agent_id'] ?? null;
            } elseif (is_object($voiceChatbot)) {
                $elevenlabsAgentId = $voiceChatbot->agent_id ?? null;
            }
        }

        $html = view($chatView, compact(
            'chat',
            'category',
            'apikeyPart1',
            'apikeyPart2',
            'apikeyPart3',
            'generators',
            'website_url',
            'tempChat',
            'elevenlabsAgentId'
        ))->render();
        $html2 = view('panel.user.openai_chat.components.chat_sidebar_list', compact('list', 'tempChat', 'chat', 'generators', 'website_url'))->render();

        return response()->json(compact('html', 'html2', 'chat'));
    }

    public function startNewDocChat(Request $request): JsonResponse
    {
        if ((int) setting('openai_file_search', 0) === 1) {
            return $this->startNewDocChatResponseApi($request);
        }

        return $this->startNewDocChatPdfData($request);
    }

    public function startNewDocChatPdfData(Request $request): JsonResponse
    {
        $category = OpenaiGeneratorChatCategory::where('id', $request->category_id)->firstOrFail();
        $chat = new UserOpenaiChat;
        $chat->user_id = Auth::id();
        $chat->team_id = Auth::user()->team_id;
        $chat->openai_chat_category_id = $category?->id;
        $chat->title = $category->name . ' Chat';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->save();

        try {
            $filePath = $this->uploadDoc($request, $chat->id, $request->type);

            $chat->reference_url = $filePath;
            $chat->doc_name = $request->file('doc')?->getClientOriginalName();
            $chat->save();

            $message = new UserOpenaiChatMessage;
            $message->user_openai_chat_id = $chat?->id;
            $message->user_id = Auth::id();
            $message->response = 'First Initiation';
            if ($category?->slug !== 'ai_vision' || $category?->slug !== 'ai_pdf') {
                if ($category->role === 'default') {
                    $output = __('Hi! I am') . ' ' . $category->name . __(', and I\'m here to answer all your questions');
                } else {
                    $output = __('Hi! I am') . ' ' . $category->human_name . __(', and I\'m') . ' ' . $category->role . '. ' . $category->helps_with;
                }
            } else {
                $output = null;
            }
            $message->output = $output;
            $message->hash = Str::random(256);
            $message->credits = 0;
            $message->words = 0;
            $message->save();

            if (setting('realtime_voice_chat', 0)) {
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

            $list = UserOpenaiChat::where('user_id', Auth::id())
                ->where('openai_chat_category_id', $category?->id)
                ->where('is_chatbot', 0)
                ->orderBy('updated_at', 'desc')
                ->limit($this->sidebarHistoryLimit())
                ->get();
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

            $html = view('panel.user.openai_chat.components.chat_area_container', compact(
                'chat',
                'category',
                'generators',
                'apikeyPart1',
                'apikeyPart2',
                'apikeyPart3',
            ))->render();
            $html2 = view('panel.user.openai_chat.components.chat_sidebar_list', compact('list', 'chat'))->render();

            return response()->json(compact('html', 'html2', 'chat'));
        } catch (Exception $e) {
            $chat->delete();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function startNewDocChatResponseApi(Request $request): JsonResponse
    {
        $user = Auth::user();
        $category = OpenaiGeneratorChatCategory::where('id', $request->category_id)->firstOrFail();
        $chat = new UserOpenaiChat;
        $chat->user_id = $user?->id;
        $chat->team_id = $user?->team_id;
        $chat->openai_chat_category_id = $category?->id;
        $chat->title = $category->name . ' Chat';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->save();

        try {
            $fileSearchService = new FileSearchService;
            $filePath = $this->storeFile($request, $request->type);
            $fileId = $fileSearchService->uploadFile($filePath);
            $vectors = $fileSearchService->createVectorStore(basename($filePath), $fileId);

            $chat->openai_vector_id = $vectors?->id;
            $chat->openai_file_id = $fileId;
            $chat->reference_url = $filePath;

            if ($request->hasFile('doc')) {
                $file = $request->file('doc');
                $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->guessExtension();
                $chat->doc_name = $fileName . ($extension ? '.' . $extension : '');
            }
            $chat->save();

            $message = new UserOpenaiChatMessage;
            $message->user_openai_chat_id = $chat?->id;
            $message->user_id = Auth::id();
            $message->response = 'First Initiation';
            if ($category?->slug !== 'ai_vision' || $category?->slug !== 'ai_pdf') {
                if ($category->role === 'default') {
                    $output = __('Hi! I am') . ' ' . $category->name . __(', and I\'m here to answer all your questions');
                } else {
                    $output = __('Hi! I am') . ' ' . $category->human_name . __(', and I\'m') . ' ' . $category->role . '. ' . $category->helps_with;
                }
            } else {
                $output = null;
            }
            $message->output = $output;
            $message->hash = Str::random(256);
            $message->credits = 0;
            $message->words = 0;
            $message->save();

            if (setting('realtime_voice_chat', 0)) {
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

            $list = UserOpenaiChat::where('user_id', Auth::id())
                ->where('openai_chat_category_id', $category?->id)
                ->where('is_chatbot', 0)
                ->orderBy('updated_at', 'desc')
                ->limit($this->sidebarHistoryLimit())
                ->get();
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

            $html = view('panel.user.openai_chat.components.chat_area_container', compact(
                'chat',
                'category',
                'generators',
                'apikeyPart1',
                'apikeyPart2',
                'apikeyPart3',
            ))->render();
            $html2 = view('panel.user.openai_chat.components.chat_sidebar_list', compact('list', 'chat'))->render();

            return response()->json(compact('html', 'html2', 'chat'));
        } catch (Exception|Throwable $e) {
            $chat->delete();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store file to local storage
     */
    public function storeFile(Request $request, $type): string
    {
        $request->validate([
            'doc' => 'required|file|max:10240',
        ]);
        $mimeToExtension = [
            'text/x-c'                                                                  => 'c',
            'text/x-c++'                                                                => 'cpp',
            'text/x-csharp'                                                             => 'cs',
            'text/css'                                                                  => 'css',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'text/x-golang'                                                             => 'go',
            'text/html'                                                                 => 'html',
            'text/x-java'                                                               => 'java',
            'text/javascript'                                                           => 'js',
            'application/json'                                                          => 'json',
            'text/markdown'                                                             => 'md',
            'application/pdf'                                                           => 'pdf',
            'text/x-php'                                                                => 'php',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/x-python'                                                             => 'py',
            'text/x-script.python'                                                      => 'py',
            'text/x-ruby'                                                               => 'rb',
            'application/x-sh'                                                          => 'sh',
            'text/x-tex'                                                                => 'tex',
            'application/typescript'                                                    => 'ts',
            'text/plain'                                                                => 'txt',
        ];
        if (! isset($mimeToExtension[$type])) {
            throw new InvalidArgumentException("Unsupported MIME type: $type");
        }
        $extension = $mimeToExtension[$type];
        $doc = $request->file('doc');
        if (str_starts_with($type, 'text/')) {
            $content = file_get_contents($doc->getRealPath());
            $encoding = mb_detect_encoding($content, ['UTF-8', 'UTF-16', 'ASCII'], true);
            if (! in_array($encoding, ['UTF-8', 'UTF-16', 'ASCII'])) {
                throw new InvalidArgumentException('Invalid encoding for text-based file. Must be UTF-8, UTF-16, or ASCII.');
            }
        }
        $fileName = $doc->hashName($extension);
        Storage::disk('public')->put($fileName, fopen($doc->getRealPath(), 'rb'));
        $resPath = public_path('/uploads/' . $fileName);
        if ($this->settings_two->ai_image_storage === 's3') {
            try {
                $aws_path = Storage::disk('s3')->put('', $doc);
                Storage::disk('public')->delete($fileName); // Clean up local file
                $resPath = Storage::disk('s3')->url($aws_path);
            } catch (Exception $e) {
                throw new RuntimeException('AWS Error - ' . $e->getMessage());
            }
        }

        return $resPath;
    }

    public function startNewChatBot(Request $request): JsonResponse
    {
        $chatbot = Chatbot::query()->where('id', $this->settings_two->chatbot_template)->firstOrFail();
        $category = $chatbot;
        $chat = new UserOpenaiChat;
        $chat->user_id = Auth::id();
        $chat->chatbot_id = $chatbot->id;
        //        $chat->openai_chat_category_id = $category?->id;
        $chat->title = 'ChatBot';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->is_chatbot = 1;
        $chat->save();

        $message = new UserOpenaiChatMessage;
        $message->user_openai_chat_id = $chat?->id;
        $message->user_id = Auth::id();
        $message->response = 'First Initiation';
        $output = $category->first_message ?: 'How can I help you?';
        $message->output = $output;
        $message->hash = Str::random(256);
        $message->credits = 0;
        $message->words = 0;
        $message->is_chatbot = 1;
        $message->save();

        $chatbot_history = new ChatBotHistory;
        $chatbot_history->user_id = Auth::id();
        $chatbot_history->ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : request()?->ip();
        $chatbot_history->user_openai_chat_id = $chat->id;
        $chatbot_history->openai_chat_category_id = $category?->id;
        $chatbot_history->save();

        $html = view('panel.user.openai_chat.components.chat_area', compact('chat', 'category'))->render();

        return response()->json(compact('html', 'chat'));
    }

    private function applyWebsiteChatTypeScope(Builder $query, ?string $websiteUrl): Builder
    {
        if ($websiteUrl === 'chatpro-image') {
            return $query->where('chat_type', 'chatpro-image');
        }

        if (in_array($websiteUrl, ['chatpro', 'chatpro-temp', 'chatPro'], true)) {
            $query->where(function ($q) {
                $q->whereNull('chat_type')
                    ->orWhereIn('chat_type', ['chatpro', 'chatpro-temp', 'chatPro']);
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

        if ($websiteUrl === 'social-media-agent') {
            return $query->where('chat_type', 'social-media-agent');
        }

        if ($websiteUrl === 'crm-assistant') {
            return $query->where('chat_type', 'crm-assistant');
        }

        // Normal chat: exclude extension-specific chat types
        return $query->where(function ($q) {
            $q->whereNull('chat_type')
                ->orWhere('chat_type', '');
        });
    }

    /**
     * @throws JsonException
     * @throws GuzzleException
     */
    public function chatOutput(Request $request): StreamedResponse|JsonResponse
    {
        $user = Auth::user();
        $chat_id = $request->get('chat_id');
        $chat = $this->openai($request)->whereKey($chat_id)->first();
        if (! $chat) {
            return response()->json(['message' => __('Chat not found.')], 404);
        }

        if ($request->isMethod('post')) {
            $prompt = $request->get('prompt');
            if (! is_string($prompt)) {
                return response()->json(['message' => __('A text message is required.')], 422);
            }
            // if ($chat->category->prompt_prefix != null && !str_starts_with($chat->category->slug, 'ai_')) {
            //     $prompt = "You will now play a character and respond as that character (You will never break character). Your name is". $chat->category->human_name. ". I want you to act as a". $chat->category->role . ". ". $chat->category->prompt_prefix . ' ' . $prompt;
            // }
            $realtime = $request->get('realtime');
            $total_used_tokens = 0;
            $entry = new UserOpenaiChatMessage;
            $entry->user_id = $user?->id;
            $entry->user_openai_chat_id = $chat->id;
            $entry->input = $prompt;
            if ($request->get('highlight_context') && Schema::hasColumn('user_openai_chat_messages', 'highlight_context')) {
                $entry->highlight_context = $request->get('highlight_context');
            }
            $entry->response = null;
            $entry->realtime = $realtime ?? 0;
            $entry->output = __("(If you encounter this message, please attempt to send your message again. If the error persists beyond multiple attempts, please don't hesitate to contact us for assistance!)");
            $entry->hash = Str::random(256);
            $entry->credits = $total_used_tokens;
            $entry->words = 0;
            $entry->save();
            $user?->save();
            $chat->total_credits += $total_used_tokens;
            $chat->save();
            $message_id = $entry->id;

            return response()->json(compact('message_id'));
        }
        if ($request->isMethod('get')) {
            $type = $request->get('type');
            $message = $chat->messages()->whereKey($request->get('message_id'))->first();
            if (! $message) {
                return response()->json(['message' => __('Message not found.')], 404);
            }
            if ($chat->category->slug === 'ai_pdf') {
                return $this->pdfStream($request);
            }
            if ($chat->category->slug === 'ai_webchat') {
                return $this->webChatStream($request);
            }

            return match ($type) {
                'vision' => $this->visionStream($request),
                default  => $this->chatbotsStream($request),
            };
        }

        return response()->json(['message' => 'Method not allowed'], 405);
    }

    /**
     * @throws JsonException
     */
    private function pdfStream(Request $request): StreamedResponse
    {
        $openaiApiKey = ApiHelper::setOpenAiKey();

        if (setting('default_ai_engine', EngineEnum::OPEN_AI->value) === EngineEnum::ANTHROPIC->value) {
            $openaiApiKey = ApiHelper::setAnthropicKey();
        }

        $chat_id = $request->get('chat_id');
        $message_id = $request->get('message_id');
        // $prompt = $request->get('prompt');
        $message = UserOpenaiChatMessage::whereId($message_id)->first();
        $prompt = $message->input;

        $chat_bot = $this->settings?->openai_default_model ?? EntityEnum::GPT_5_MINI->value;
        $history = [];

        if (setting('default_ai_engine', EngineEnum::OPEN_AI->value) === EngineEnum::ANTHROPIC->value) {
            $chat_bot = setting('anthropic_default_model', EntityEnum::CLAUDE_OPUS_4_8->value);
        }

        $chat = UserOpenaiChat::whereId($chat_id)->first();
        // check if there completions for the template
        $category = $chat->category;
        if ($category->chat_completions) {
            $chat_completions = json_decode($category->chat_completions, true, 512, JSON_THROW_ON_ERROR);
            foreach ($chat_completions as $item) {
                $history[] = [
                    'role'    => $item['role'],
                    'content' => $item['content'] ?? '',
                ];
            }
        } else {
            $history[] = ['role' => 'system', 'content' => 'You are a helpful assistant.'];
        }

        // follow the context of the last 5 messages
        $lastThreeMessageQuery = $chat->messages()
            ->whereNotNull('input')
            ->where('id', '!=', $message_id)
            ->orderBy('created_at', 'desc')
            ->take((int) setting('chat_history_limit', 4))
            ->get()
            ->reverse();

        $vectorService = new VectorService;

        $extra_prompt = $vectorService->getMostSimilarText($prompt, $chat_id, 5, $chat->chatbot_id);
        $count = count($lastThreeMessageQuery);
        if ($count >= 1) {
            $lastThreeMessageQuery[$count - 1]->input = "'this file' means file content. Must not reference previous chats if user asking about pdf. Must reference file content if only user is asking about file content. Else just response as an assistant shortly and professionaly without must not referencing file content. \n\n\n\n\nUser qusetion: $prompt \n\n\n\n\n Document Content: \n $extra_prompt";
            foreach ($lastThreeMessageQuery as $threeMessage) {
                $history[] = ['role' => 'user', 'content' => $threeMessage->input ?? ''];
                $output = $threeMessage->output;
                if ($output) {
                    $history[] = ['role' => 'assistant', 'content' => $output];
                } else {
                    $history[] = ['role' => 'assistant', 'content' => ''];
                }
            }
        } else {
            $history[] = ['role' => 'user', 'content' => "'this file' means file content. Must not reference previous chats if user asking about pdf. Must reference file content if only user is asking about file content. Else just response as an assistant shortly and professionaly without must not referencing file content. . User: $prompt \n\n\n\n\n Document Content: \n $extra_prompt"];
        }

        return $this->openaiChatStream($request, $openaiApiKey, $chat_bot, $history, $message_id);
    }

    private function chatbotsStream(Request $request)
    {
        $openaiApiKey = ApiHelper::setOpenAiKey();

        if (setting('default_ai_engine', EngineEnum::OPEN_AI->value) === EngineEnum::ANTHROPIC->value) {
            $openaiApiKey = ApiHelper::setAnthropicKey();
        }

        $chat_id = $request->get('chat_id');

        $message_id = $request->get('message_id');
        $message = UserOpenaiChatMessage::whereId($message_id)->first();
        $prompt = $message->input;

        $realtime = $request->get('realtime');
        $chat_bot = EntityEnum::fromSlug($this->settings?->openai_default_model);
        if ($realtime && setting('default_realtime') === 'openai') {
            $chat_bot = EntityEnum::fromSlug(setting('openai_realtime_model', EntityEnum::GPT_4_O_SEARCH_PREVIEW->value));
        }
        $history = [];
        $realtimePrompt = $prompt;
        $chat = UserOpenaiChat::whereId($chat_id)->first();
        // check if there completions for the template
        $category = $chat->category;
        if ($category->chat_completions) {
            $chat_completions = json_decode($category->chat_completions, true, 512, JSON_THROW_ON_ERROR);
            foreach ($chat_completions as $item) {
                $history[] = [
                    'role'    => $item['role'],
                    'content' => $item['content'] ?? '',
                ];
            }
        } else {
            $history[] = ['role' => 'system', 'content' => 'You are a helpful assistant.'];
        }

        if ($category && $category?->instructions) {
            $history[] = ['role' => 'system', 'content' => $category->instructions];
        }
        $extra_prompt = null;
        if ($category->chatbot_id) {
            try {
                $extra_prompt = (new VectorService)->getMostSimilarText($prompt, $chat_id, 2, $category->chatbot_id);
                if ($extra_prompt) {
                    $history[] = ['role' => 'user', 'content' => "'this file' means file content. Must not reference previous chats if user asking about pdf. Must reference file content if only user is asking about file content. Else just response as an assistant shortly and professionaly without must not referencing file content. . User: $prompt \n\n\n\n\n Document Content: \n $extra_prompt"];
                }
            } catch (Throwable $th) {
            }
        }

        // follow the context of the last 5 messages
        $lastThreeMessageQuery = $chat->messages()
            ->whereNotNull('input')
            ->where('id', '!=', $message_id)
            ->orderBy('created_at', 'desc')
            ->take((int) setting('chat_history_limit', 4))
            ->get()
            ->reverse();

        $count = count($lastThreeMessageQuery);
        if ($count >= 1) {
            foreach ($lastThreeMessageQuery as $threeMessage) {
                $userInput = $threeMessage->input ?? '';
                if (Schema::hasColumn('user_openai_chat_messages', 'highlight_context') && ! empty($threeMessage->highlight_context)) {
                    $userInput = "The user selected the following quote from your previous response and is asking a follow-up question about it.\nQuoted text: \"{$threeMessage->highlight_context}\"\nUser's follow-up question:\n" . $userInput;
                }
                $history[] = ['role' => 'user', 'content' => $userInput];
                if ($threeMessage->output !== null) {
                    $history[] = ['role' => 'assistant', 'content' => $threeMessage->output ?? ''];
                } else {
                    $history[] = ['role' => 'assistant', 'content' => ''];
                }
            }
            if ($realtime) {
                if (setting('default_realtime', 'serper') == 'serper' &&
                    ! is_null($this->settings_two->serper_api_key)) {
                    $driver = EntityFacade::driver(EntityEnum::SERPER);
                    $driver->redirectIfNoCreditBalance();

                    $sclient = new Client;
                    $headers = [
                        'X-API-KEY'    => $this->settings_two->serper_api_key,
                        'Content-Type' => 'application/json',
                    ];
                    $body = [
                        'q' => $realtimePrompt,
                    ];
                    $response = $sclient->post('https://google.serper.dev/search', [
                        'headers' => $headers,
                        'json'    => $body,
                    ]);
                    $toGPT = $response->getBody()->getContents();

                    try {
                        $toGPT = json_decode($toGPT);
                    } catch (Throwable $th) {
                    }

                    $driver->input($realtimePrompt)->calculateCredit()->decreaseCredit();
                    Usage::getSingle()->updateWordCounts($driver->calculate());

                    $final_prompt =
                        'Prompt: ' . $realtimePrompt .
                        '\n\nWeb search json results: '
                        . json_encode($toGPT) .
                        '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                } elseif (MarketplaceHelper::isRegistered('perplexity') && setting('default_realtime') == 'perplexity' && ! is_null(setting('perplexity_key'))) {

                    $url = 'https://api.perplexity.ai/chat/completions';
                    $token = setting('perplexity_key');

                    $payload = [
                        'model'    => 'sonar',
                        'messages' => [
                            [
                                'role'    => 'user',
                                'content' => $realtimePrompt,
                            ],
                        ],
                    ];

                    try {
                        $response = Http::withToken($token)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                            ])
                            ->post($url, $payload);

                        if ($response->successful()) {
                            $data = $response->json();
                            $response = $data['choices'][0]['message']['content'];
                            $final_prompt = 'Prompt: ' . $realtimePrompt .
                                '\n\nWeb search results: '
                                . $response .
                                '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                        } else {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $response->body(),
                            ], 500);
                        }
                    } catch (Exception $e) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => $e->getMessage(),
                        ], 500);
                    }
                }
                if (! empty($final_prompt)) {
                    $history[] = ['role' => 'system', 'content' => $final_prompt];
                }
                $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
            } else {
                $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
            }
        } else {
            if ($realtime) {
                if (setting('default_realtime', 'serper') == 'serper' &&
                    ! is_null($this->settings_two->serper_api_key)) {
                    $driver = EntityFacade::driver(EntityEnum::SERPER);
                    $driver->redirectIfNoCreditBalance();

                    $sclient = new Client;
                    $headers = [
                        'X-API-KEY'    => $this->settings_two->serper_api_key,
                        'Content-Type' => 'application/json',
                    ];
                    $body = [
                        'q' => $realtimePrompt,
                    ];
                    $response = $sclient->post('https://google.serper.dev/search', [
                        'headers' => $headers,
                        'json'    => $body,
                    ]);
                    $toGPT = $response->getBody()->getContents();

                    try {
                        $toGPT = json_decode($toGPT);
                    } catch (Throwable $th) {
                    }
                    $driver->input($realtimePrompt)->calculateCredit()->decreaseCredit();
                    Usage::getSingle()->updateWordCounts($driver->calculate());

                    $final_prompt =
                        'Prompt: ' . $realtimePrompt .
                        '\n\nWeb search json results: '
                        . json_encode($toGPT) .
                        '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                } elseif (MarketplaceHelper::isRegistered('perplexity') && setting('default_realtime') == 'perplexity' &&
                    ! is_null(setting('perplexity_key'))) {

                    $url = 'https://api.perplexity.ai/chat/completions';
                    $token = setting('perplexity_key');

                    $payload = [
                        'model'    => 'sonar',
                        'messages' => [
                            [
                                'role'    => 'user',
                                'content' => $realtimePrompt,
                            ],
                        ],
                    ];

                    try {
                        $response = Http::withToken($token)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                            ])
                            ->post($url, $payload);

                        if ($response->successful()) {
                            $data = $response->json();
                            $response = $data['choices'][0]['message']['content'];
                            $final_prompt = 'Prompt: ' . $realtimePrompt .
                                '\n\nWeb search results: '
                                . $response .
                                '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                        } else {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $response->body(),
                            ], 500);
                        }
                    } catch (Exception $e) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => $e->getMessage(),
                        ], 500);
                    }
                }
                if (! empty($final_prompt)) {
                    $history[] = ['role' => 'system', 'content' => $final_prompt];
                }
                $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
            } else {
                $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
            }
        }

        // Inject highlight-to-ask context into the last user message (not stored in DB)
        $highlightContext = $request->get('highlight_context');
        if ($highlightContext && ! empty($history)) {
            $lastIndex = count($history) - 1;
            if ($history[$lastIndex]['role'] === 'user') {
                $history[$lastIndex]['content'] = "The user selected the following quote from your previous response and is asking a follow-up question about it.\nQuoted text: \"" . $highlightContext . "\"\nUser's follow-up question:\n" . $history[$lastIndex]['content'];
            }
        }

        return $this->openaiChatStream($request, $openaiApiKey, $chat_bot, $history, $message_id, null, [], $category);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    private function webChatStream(Request $request): StreamedResponse
    {
        $openaiApiKey = ApiHelper::setOpenAiKey();

        if (setting('default_ai_engine', EngineEnum::OPEN_AI->value) === EngineEnum::ANTHROPIC->value) {
            $openaiApiKey = ApiHelper::setAnthropicKey();
        }
        $chat_id = $request->get('chat_id');
        $message_id = $request->get('message_id');
        $message = UserOpenaiChatMessage::whereId($message_id)->first();
        $prompt = $message->input;
        $realtime = $request->get('realtime');
        $chat_bot = $this->settings?->openai_default_model;
        if ($realtime && setting('default_realtime') === 'openai') {
            $chat_bot = setting('openai_realtime_model', EntityEnum::GPT_4_O_SEARCH_PREVIEW->value);
        }
        $history = [];
        $realtimePrompt = $prompt;

        $chat = UserOpenaiChat::whereId($chat_id)->first();
        $category = $chat->category;
        if ($category->chat_completions) {
            $chat_completions = json_decode($category->chat_completions, true, 512, JSON_THROW_ON_ERROR);
            foreach ($chat_completions as $item) {
                $history[] = [
                    'role'    => $item['role'],
                    'content' => $item['content'] ?? '',
                ];
            }
        } else {
            $history[] = ['role' => 'system', 'content' => 'You are a helpful assistant.'];
        }

        if ($category && $category?->instructions) {
            $history[] = ['role' => 'system', 'content' => $category->instructions];
        }

        try {
            $extra_prompt = (new VectorService)->getMostSimilarText($prompt, $chat_id, 2);
            if ($extra_prompt) {
                $history[] = ['role' => 'user', 'content' => "'this file' means file content. Must not reference previous chats if user asking about pdf. Must reference file content if only user is asking about file content. Else just response as an assistant shortly and professionaly without must not referencing file content. . User: $prompt \n\n\n\n\n Document Content: \n $extra_prompt"];
            }
        } catch (Throwable $th) {

        }

        // follow the context of the last 5 messages
        $lastThreeMessageQuery = $chat->messages()
            ->whereNotNull('input')
            ->where('id', '!=', $message_id)
            ->orderBy('created_at', 'desc')
            ->take((int) setting('chat_history_limit', 4))
            ->get()
            ->reverse();

        $count = count($lastThreeMessageQuery);
        if ($count >= 1) {
            foreach ($lastThreeMessageQuery as $threeMessage) {
                $userInput = $threeMessage->input ?? '';
                if (Schema::hasColumn('user_openai_chat_messages', 'highlight_context') && ! empty($threeMessage->highlight_context)) {
                    $userInput = "The user selected the following quote from your previous response and is asking a follow-up question about it.\nQuoted text: \"{$threeMessage->highlight_context}\"\nUser's follow-up question:\n" . $userInput;
                }
                $history[] = ['role' => 'user', 'content' => $userInput];
                if ($threeMessage->output !== null) {
                    $history[] = ['role' => 'assistant', 'content' => $threeMessage->output ?? ''];
                } else {
                    $history[] = ['role' => 'assistant', 'content' => ''];
                }
            }
            if ($realtime) {
                if (setting('default_realtime', 'serper') == 'serper' &&
                    ! is_null($this->settings_two->serper_api_key)) {
                    $driver = EntityFacade::driver(EntityEnum::SERPER);
                    $driver->redirectIfNoCreditBalance();

                    $sclient = new Client;
                    $headers = [
                        'X-API-KEY'    => $this->settings_two->serper_api_key,
                        'Content-Type' => 'application/json',
                    ];
                    $body = [
                        'q' => $realtimePrompt,
                    ];
                    $response = $sclient->post('https://google.serper.dev/search', [
                        'headers' => $headers,
                        'json'    => $body,
                    ]);
                    $toGPT = $response->getBody()->getContents();

                    try {
                        $toGPT = json_decode($toGPT);
                    } catch (Throwable $th) {
                    }
                    $driver->input($realtimePrompt)->calculateCredit()->decreaseCredit();
                    Usage::getSingle()->updateWordCounts($driver->calculate());

                    $final_prompt =
                        'Prompt: ' . $realtimePrompt .
                        '\n\nWeb search json results: '
                        . json_encode($toGPT) .
                        '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                } elseif (MarketplaceHelper::isRegistered('perplexity') && setting('default_realtime') == 'perplexity' &&
                    ! is_null(setting('perplexity_key'))) {

                    $url = 'https://api.perplexity.ai/chat/completions';
                    $token = setting('perplexity_key');

                    $payload = [
                        'model'    => 'sonar',
                        'messages' => [
                            [
                                'role'    => 'user',
                                'content' => $realtimePrompt,
                            ],
                        ],
                    ];

                    try {
                        $response = Http::withToken($token)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                            ])
                            ->post($url, $payload);

                        if ($response->successful()) {
                            $data = $response->json();
                            $response = $data['choices'][0]['message']['content'];
                            $final_prompt = 'Prompt: ' . $realtimePrompt .
                                '\n\nWeb search results: '
                                . $response .
                                '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                        } else {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $response->body(),
                            ], 500);
                        }
                    } catch (Exception $e) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => $e->getMessage(),
                        ], 500);
                    }
                }
                if (! empty($final_prompt)) {
                    $history[] = ['role' => 'system', 'content' => $final_prompt];
                }
                $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
            } else {
                $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
            }
        } elseif ($realtime) {
            if (setting('default_realtime', 'serper') == 'serper' &&
                ! is_null($this->settings_two->serper_api_key)) {
                $driver = EntityFacade::driver(EntityEnum::SERPER);
                $driver->redirectIfNoCreditBalance();

                $sclient = new Client;
                $headers = [
                    'X-API-KEY'    => $this->settings_two->serper_api_key,
                    'Content-Type' => 'application/json',
                ];
                $body = [
                    'q' => $realtimePrompt,
                ];
                $response = $sclient->post('https://google.serper.dev/search', [
                    'headers' => $headers,
                    'json'    => $body,
                ]);
                $toGPT = $response->getBody()->getContents();

                try {
                    $toGPT = json_decode($toGPT);
                } catch (Throwable $th) {
                }
                $driver->input($realtimePrompt)->calculateCredit()->decreaseCredit();
                Usage::getSingle()->updateWordCounts($driver->calculate());

                $final_prompt =
                    'Prompt: ' . $realtimePrompt .
                    '\n\nWeb search json results: '
                    . json_encode($toGPT) .
                    '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

            } elseif (MarketplaceHelper::isRegistered('perplexity') && setting('default_realtime') == 'perplexity' &&
                ! is_null(setting('perplexity_key'))) {

                $url = 'https://api.perplexity.ai/chat/completions';
                $token = setting('perplexity_key');

                $payload = [
                    'model'    => 'sonar',
                    'messages' => [
                        [
                            'role'    => 'user',
                            'content' => $realtimePrompt,
                        ],
                    ],
                ];

                try {
                    $response = Http::withToken($token)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                        ])
                        ->post($url, $payload);

                    if ($response->successful()) {
                        $data = $response->json();
                        $response = $data['choices'][0]['message']['content'];
                        $final_prompt = 'Prompt: ' . $realtimePrompt .
                            '\n\nWeb search results: '
                            . $response .
                            '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                    } else {
                        return response()->stream(function () use ($response) {
                            echo 'data: ' . json_encode([
                                'status'  => 'error',
                                'message' => $response->body(),
                            ], JSON_THROW_ON_ERROR);
                            echo "\n\n";
                            flush();
                        });
                    }
                } catch (Exception $e) {
                    return response()->stream(function () use ($e) {
                        echo 'data: ' . json_encode([
                            'status'  => 'error',
                            'message' => $e->getMessage(),
                        ], JSON_THROW_ON_ERROR);
                        echo "\n\n";
                        flush();
                    });
                }
            }
            if (! empty($final_prompt)) {
                $history[] = ['role' => 'system', 'content' => $final_prompt];
            }
            $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
        } else {
            $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
        }

        return $this->openaiChatStream($request, $openaiApiKey, $chat_bot, $history, $message_id, null, [], $category);
    }

    private function visionStream(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $openaiApiKey = $this->getOpenAiApiKey($user);
        $chat_id = $request->get('chat_id');

        $message_id = $request->get('message_id');
        $message = UserOpenaiChatMessage::whereId($message_id)->first();
        $prompt = $message->input;
        $chat_bot = EntityEnum::GPT_5_MINI->value;
        $history = [];

        $chat = UserOpenaiChat::whereId($chat_id)->first();
        $history[] = [
            'role'    => 'system',
            'content' => 'You will now play a character and respond as that character (You will never break character). Your name is Vision AI. Must not introduce by yourself as well as greetings. Help also with asked questions based on previous responses and images if exists.',
        ];
        $lastThreeMessageQuery = $chat->messages()
            ->whereNotNull('input')
            ->where('id', '!=', $message_id)
            ->orderBy('created_at', 'desc')
            ->take((int) setting('chat_history_limit', 4))
            ->get()
            ->reverse();
        $images = explode(',', $request->images);
        $count = count($lastThreeMessageQuery);
        if ($count >= 1) {
            foreach ($lastThreeMessageQuery as $threeMessage) {
                $history[] = [
                    'role'    => 'user',
                    'content' => array_merge(
                        [
                            [
                                'type' => 'text',
                                'text' => $threeMessage->input,
                            ],
                        ],
                        collect($threeMessage->images)->map(static function ($item) {
                            $images = explode(',', $item);
                            $imageResults = [];
                            if (! empty($images)) {
                                foreach ($images as $image) {
                                    if (Str::startsWith($image, 'http')) {
                                        $imageData = file_get_contents($image);
                                    } else {
                                        $imageData = file_get_contents(ltrim($image, '/'));
                                    }
                                    $base64Image = base64_encode($imageData ?? '');
                                    $imageResults[] = [
                                        'type'      => 'image_url',
                                        'image_url' => [
                                            'url' => 'data:image/png;base64,' . $base64Image,
                                        ],
                                    ];
                                }
                            }

                            return $imageResults;
                        })->reject(fn ($value) => empty($value))->flatten(1)->toArray()
                    ),
                ];
                if ($threeMessage->response !== null) {
                    $history[] = ['role' => 'assistant', 'content' => $threeMessage->response];
                }
            }
        }
        $history[] =
            [
                'role'    => 'user',
                'content' => array_merge(
                    [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                    collect($images)->map(function ($item) {
                        if (Str::startsWith($item, 'http')) {
                            $imageData = file_get_contents($item);
                        } else {
                            $imageData = file_get_contents(substr($item, 1));
                        }
                        $base64Image = base64_encode($imageData ?? '');

                        return [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url' => 'data:image/png;base64,' . $base64Image,
                            ],
                        ];
                    })->toArray()
                ),
            ];

        return $this->openaiChatStream($request, $openaiApiKey, $chat_bot, $history, $message_id, 2000, $images);
    }

    private function openaiChatStream($request, $openaiApiKey, string|EntityEnum $chat_bot, $history, $message_id, $ai_max_tokens = null, $images = [], $category = null): StreamedResponse
    {
        $driver = EntityFacade::driver(EntityEnum::fromSlug($chat_bot));

        return response()->stream(function () use ($request, $openaiApiKey, $ai_max_tokens, $history, $driver, $message_id, $images, $category) {
            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                flush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                flush();

                return null;
            }
            $openaiUse = setting('default_ai_engine', EngineEnum::OPEN_AI->value) === EngineEnum::OPEN_AI->value;
            if ($category && $category?->chatbot_id) {
                $openaiUse = true;
            }
            $openaiToolWebSearch = $request->get('realtime')
                && setting('default_realtime') === 'tool_calling';

            if ($ai_max_tokens !== null) {
                if ($openaiUse) {
                    $gclient = new Client;
                    $url = 'https://api.openai.com/v1/chat/completions';
                    $headers = [
                        'Authorization' => 'Bearer ' . $openaiApiKey,
                    ];

                    $chatParams = [
                        'model'      => $driver->enum()->value,
                        'messages'   => $history,
                        'max_tokens' => $ai_max_tokens,
                        'stream'     => true,
                    ];

                    if ($openaiToolWebSearch) {
                        $chatParams['tools'] = [['type' => 'web_search_preview']];
                        $chatParams['tool_choice'] = 'auto';
                    }

                    $postData = [
                        'headers' => $headers,
                        'json'    => OpenAiParamHelper::sanitizeChatParams($chatParams),
                    ];

                    $response = $gclient->post($url, $postData);
                    $total_used_tokens = 0;
                    $output = '';
                    $responsedText = '';

                    foreach (explode("\n", $response->getBody()->getContents()) as $chunk) {
                        if (strlen($chunk) > 5 && $chunk !== 'data: [DONE]' && isset(json_decode(substr($chunk, 6), false, 512, JSON_THROW_ON_ERROR)->choices[0]->delta->content)) {

                            $message = json_decode(substr($chunk, 6), false, 512, JSON_THROW_ON_ERROR)->choices[0]->delta->content;

                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message ?? '');
                            $output .= $messageFix;

                            $responsedText .= $message;
                            $total_used_tokens += countWords($message);

                            $string_length = Str::length($messageFix);
                            $needChars = 6000 - $string_length;
                            $random_text = Str::random($needChars);

                            echo PHP_EOL;
                            echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                            flush();
                            usleep(1000);
                        }
                    }
                } else {
                    $historyMessages = array_filter($history, function ($item) {
                        return $item['role'] !== 'system';
                    });
                    $system = Arr::first(array_filter($history, function ($item) {
                        return $item['role'] === 'system';
                    }));

                    foreach ($historyMessages as $message) {
                        if (isset($message['content'])) {
                            $content = $message['content'];
                        }
                    }

                    if (setting('anthropic_default_model') === BedrockEngine::BEDROCK->value) {
                        $responseBody = $this->bedrockService()->invokeClaude($content);
                        $completion = $responseBody['completion'];
                        foreach (explode("\n", $completion) as $chunk) {
                            $words = explode(' ', $chunk);
                            foreach ($words as $word) {
                                $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $word ?? '') . ' ';
                                $output .= $messageFix;
                                $responsedText .= $word . ' ';

                                $total_used_tokens += countWords($word);
                                $string_length = Str::length($messageFix);
                                $needChars = 6000 - $string_length;
                                $random_text = Str::random($needChars);

                                echo PHP_EOL;
                                echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                                flush();
                                usleep(1000);
                            }
                            if (connection_aborted()) {
                                break;
                            }
                        }

                    } else {
                        $client = app(AnthropicService::class);

                        $system = data_get($system, 'content');

                        $data = $client->setStream(true)
                            ->setSystem($system)
                            ->setMessages(array_values($historyMessages))
                            ->stream()
                            ->body();

                        $total_used_tokens = 0;
                        $output = '';
                        $responsedText = '';

                        foreach (explode("\n", $data) as $chunk) {

                            if (strlen($chunk) < 6) {
                                continue;
                            }

                            if (! Str::contains($chunk, 'data: ')) {

                                continue;
                            }

                            $chunk = str_replace('data: {', '{', $chunk ?? '');

                            if (isset(json_decode($chunk, false, 512, JSON_THROW_ON_ERROR)->delta->text)) {
                                $message = json_decode($chunk, false, 512, JSON_THROW_ON_ERROR)->delta->text;

                                $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message ?? '');

                                $output .= $messageFix;
                                $responsedText .= $message;

                                $total_used_tokens += countWords($message);
                                $string_length = Str::length($messageFix);
                                $needChars = 6000 - $string_length;
                                $random_text = Str::random($needChars);

                                echo PHP_EOL;
                                echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                                flush();
                                usleep(1000);
                            }
                            if (connection_aborted()) {
                                break;
                            }
                        }
                    }
                }
            } elseif ($openaiUse) {
                $chatParams = [
                    'model'             => $driver->enum()->value,
                    'messages'          => $history,
                    'presence_penalty'  => 0.6,
                    'frequency_penalty' => 0,
                ];

                if ($openaiToolWebSearch) {
                    $chatParams['tools'] = [['type' => 'web_search_preview']];
                    $chatParams['tool_choice'] = 'auto';
                }

                $stream = OpenAI::chat()->createStreamed(OpenAiParamHelper::sanitizeChatParams($chatParams));
                $total_used_tokens = 0;
                $output = '';
                $responsedText = '';
                foreach ($stream as $response) {
                    if (isset($response['choices'][0]['delta']['content'])) {
                        $message = $response['choices'][0]['delta']['content'];
                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message ?? '');

                        $output .= $messageFix;
                        $responsedText .= $message;

                        $total_used_tokens += countWords($message);
                        $string_length = Str::length($messageFix);
                        $needChars = 6000 - $string_length;
                        $random_text = Str::random($needChars);

                        echo PHP_EOL;
                        echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                        flush();
                        // ob_flush();
                        usleep(1000);
                    }
                    if (connection_aborted()) {
                        break;
                    }
                }
            } else {
                $historyMessages = array_filter($history, function ($item) {
                    return $item['role'] !== 'system';
                });
                $system = Arr::first(array_filter($history, function ($item) {
                    return $item['role'] === 'system';
                }));
                foreach ($historyMessages as $message) {
                    if (isset($message['content'])) {
                        $content = $message['content'];
                    }
                }

                if (setting('anthropic_default_model') === BedrockEngine::BEDROCK->value) {
                    $responseBody = $this->bedrockService()->invokeClaude($content);
                    $completion = $responseBody['completion'];
                    foreach (explode("\n", $completion) as $chunk) {
                        $words = explode(' ', $chunk);
                        foreach ($words as $word) {
                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $word ?? '') . ' ';
                            $output .= $messageFix;
                            $responsedText .= $word . ' ';

                            $total_used_tokens += countWords($word);
                            $string_length = Str::length($messageFix);
                            $needChars = 6000 - $string_length;
                            $random_text = Str::random($needChars);

                            echo PHP_EOL;
                            echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                            flush();
                            usleep(1000);
                        }
                        if (connection_aborted()) {
                            break;
                        }
                    }
                } else {
                    $client = app(AnthropicService::class);
                    $system = data_get($system, 'content');
                    $data = $client->setStream(true)
                        ->setSystem($system)
                        ->setMessages(array_values($historyMessages))
                        ->stream()
                        ->body();
                    $total_used_tokens = 0;
                    $output = '';
                    $responsedText = '';
                    foreach (explode("\n", $data) as $chunk) {
                        if (strlen($chunk) < 6) {
                            continue;
                        }

                        if (! Str::contains($chunk, 'data: ')) {
                            continue;
                        }

                        $chunk = str_replace('data: {', '{', $chunk ?? '');
                        if (isset(json_decode($chunk)->delta->text)) {
                            $message = json_decode($chunk)->delta->text;

                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message ?? '');

                            $output .= $messageFix;
                            $responsedText .= $message;

                            $total_used_tokens += countWords($message);
                            $string_length = Str::length($messageFix);
                            $needChars = 6000 - $string_length;
                            $random_text = Str::random($needChars);

                            echo PHP_EOL;
                            echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                            flush();
                            usleep(1000);
                        }

                        if (connection_aborted()) {
                            break;
                        }
                    }
                }
            }
            $message = UserOpenaiChatMessage::whereId($message_id)->first();
            $chat_id = $message->user_openai_chat_id;
            $chat = UserOpenaiChat::whereId($chat_id)->first();

            $message->response = $responsedText;
            $message->output = $output;
            $message->hash = Str::random(256);
            $message->credits = $total_used_tokens;
            $message->words = 0;
            $message->images = ChatAttachmentReferences::normalize($images);
            $message->pdfName = ChatAttachmentReferences::normalize($request->input('pdfname'));
            $message->pdfPath = ChatAttachmentReferences::normalize($request->input('pdfpath'));
            $message->save();
            $driver
                ->input($responsedText)
                ->calculateCredit()
                ->decreaseCredit();
            Usage::getSingle()->updateWordCounts($driver->calculate());

            $chat->total_credits += $total_used_tokens;
            $chat->save();
            echo 'data: [DONE]';
            echo "\n\n";
            flush();
            usleep(1000);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Content-Type'      => 'text/event-stream',
        ]);

    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function chatbotOutput(Request $request): StreamedResponse|JsonResponse
    {
        $chat = UserOpenaiChat::query()
            ->whereKey($request->input('chat_id'))
            ->where('user_id', Auth::id())
            ->where('is_chatbot', 1)
            ->first();
        if (! $chat) {
            return response()->json(['message' => __('Chat not found.')], 404);
        }

        $chatbot = Chatbot::where('id', $this->settings_two->chatbot_template)->firstOrFail();

        if ($request->isMethod('get')) {

            $type = $request->type;
            $images = explode(',', $request->images);
            $model = $chatbot->model;
            $chat_id = $request->chat_id;
            $message_id = $request->message_id;
            $message = UserOpenaiChatMessage::whereId($message_id)->first();
            $prompt = $message->input;
            $realtime = $message->realtime;
            if ($realtime && setting('default_realtime') === 'openai') {
                $model = setting('openai_realtime_model', EntityEnum::GPT_4_O_SEARCH_PREVIEW->value);
            }
            $realtimePrompt = $prompt;
            $chat = UserOpenaiChat::whereId($chat_id)->first();

            $lastThreeMessageQuery = $chat->messages()
                ->whereNotNull('input')
                ->orderBy('created_at', 'desc')
                ->take((int) setting('chat_history_limit', 4))
                ->get()
                ->reverse();
            $i = 0;

            $history[] = ['role' => 'system', 'content' => $chatbot->instructions ?: 'You are a helpful assistant.'];

            $vectorService = new VectorService;

            $extra_prompt = $vectorService->getMostSimilarText(
                $prompt,
                $chat_id,
                5,
                $this->settings_two->chatbot_template
            );

            if (count($lastThreeMessageQuery) > 1 && ! $realtime) {
                if ($extra_prompt !== '') {
                    $lastThreeMessageQuery[count($lastThreeMessageQuery) - 1]->input = "'this file' means file content. Must not reference previous chats if user asking about pdf. Must reference file content if only user is asking about file content. Else just response as an assistant shortly and professionaly without must not referencing file content. \n\n\n\n\nUser qusetion: $prompt \n\n\n\n\n Document Content: \n $extra_prompt";
                }

                foreach ($lastThreeMessageQuery as $threeMessage) {
                    $history[] = ['role' => 'user', 'content' => $threeMessage->input];
                    if ($threeMessage->response !== null) {
                        $history[] = ['role' => 'assistant', 'content' => $threeMessage->response];
                    }
                }
            } elseif ($extra_prompt === '') {
                if ($realtime) {
                    if (setting('default_realtime', 'serper') == 'serper' &&
                        ! is_null($this->settings_two->serper_api_key)) {
                        $driver = EntityFacade::driver(EntityEnum::SERPER);
                        $driver->redirectIfNoCreditBalance();

                        $sclient = new Client;
                        $headers = [
                            'X-API-KEY'    => $this->settings_two->serper_api_key,
                            'Content-Type' => 'application/json',
                        ];
                        $body = [
                            'q' => $realtimePrompt,
                        ];
                        $response = $sclient->post('https://google.serper.dev/search', [
                            'headers' => $headers,
                            'json'    => $body,
                        ]);
                        $toGPT = $response->getBody()->getContents();

                        try {
                            $toGPT = json_decode($toGPT);
                        } catch (Throwable $th) {
                        }

                        $driver->input($realtimePrompt)->calculateCredit()->decreaseCredit();
                        Usage::getSingle()->updateWordCounts($driver->calculate());

                        $final_prompt =
                            'Prompt: ' . $realtimePrompt .
                            '\n\nWeb search json results: '
                            . json_encode($toGPT) .
                            '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                    } elseif (MarketplaceHelper::isRegistered('perplexity') && setting('default_realtime') == 'perplexity' &&
                        ! is_null(setting('perplexity_key'))) {

                        $url = 'https://api.perplexity.ai/chat/completions';
                        $token = setting('perplexity_key');

                        $payload = [
                            'model'    => 'sonar',
                            'messages' => [
                                [
                                    'role'    => 'user',
                                    'content' => $realtimePrompt,
                                ],
                            ],
                        ];

                        try {
                            $response = Http::withToken($token)
                                ->withHeaders([
                                    'Content-Type' => 'application/json',
                                ])
                                ->post($url, $payload);

                            if ($response->successful()) {
                                $data = $response->json();
                                $response = $data['choices'][0]['message']['content'];
                                $final_prompt = 'Prompt: ' . $realtimePrompt .
                                    '\n\nWeb search results: '
                                    . $response .
                                    '\n\nInstructions: Based on the Prompt generate a proper response with help of Web search results(if the Web search results in the same context). Only if the prompt require links: (make curated list of links and descriptions using only the <a target="_blank">, write links with using <a target="_blank"> with mrgin Top of <a> tag is 5px and start order as number and write link first and then write description). Must not write links if its not necessary. Must not mention anything about the prompt text. Never reveal or display search queries, search parameters, or any internal metadata from the search results.';

                            } else {
                                return response()->json([
                                    'status'  => 'error',
                                    'message' => $response->body(),
                                ], 500);
                            }
                        } catch (Exception $e) {
                            return response()->json([
                                'status'  => 'error',
                                'message' => $e->getMessage(),
                            ], 500);
                        }
                    }
                    if (! empty($final_prompt)) {
                        $history[] = ['role' => 'system', 'content' => $final_prompt];
                    }
                    $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
                } else {
                    $history[] = ['role' => 'user', 'content' => $prompt ?? ''];
                }
            } else {
                $history[] = ['role' => 'user', 'content' => "'this file' means file content. Must not reference previous chats if user asking about pdf. Must reference file content if only user is asking about file content. Else just response as an assistant shortly and professionaly without must not referencing file content. . User: $prompt \n\n\n\n\n Document Content: \n $extra_prompt"];
            }

            $driver = EntityFacade::driver(EntityEnum::fromSlug($model));

            return response()->stream(function () use ($prompt, $request, $chat_id, $message_id, $history, $driver, $type, $images) {
                if (! $driver->hasCreditBalance()) {
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                    echo "\n\n";
                    flush();
                    echo "event: stop\n";
                    echo 'data: [DONE]';
                    echo "\n\n";
                    flush();

                    return null;
                }
                if ($type === 'chat') {
                    try {
                        $chatParams = [
                            'model'             => $driver->enum()->value,
                            'messages'          => $history,
                            'presence_penalty'  => 0.6,
                            'frequency_penalty' => 0,
                        ];

                        if ($request->get('realtime')
                            && setting('default_realtime') === 'tool_calling') {
                            $chatParams['tools'] = [['type' => 'web_search_preview']];
                            $chatParams['tool_choice'] = 'auto';
                        }

                        $stream = OpenAI::chat()->createStreamed(OpenAiParamHelper::sanitizeChatParams($chatParams));
                        $total_used_tokens = 0;
                        $output = '';
                        $responsedText = '';
                        foreach ($stream as $response) {

                            if (isset($response['choices'][0]['delta']['content'])) {

                                $message = $response['choices'][0]['delta']['content'];
                                $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message ?? '');
                                $output .= $messageFix;
                                $responsedText .= $message;
                                $total_used_tokens += countWords($message);
                                $string_length = Str::length($messageFix);
                                $needChars = 6000 - $string_length;
                                $random_text = Str::random($needChars);

                                echo PHP_EOL;
                                echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                                flush();
                                usleep(5000);
                            }
                            if (connection_aborted()) {
                                break;
                            }
                        }
                    } catch (Exception $exception) {
                        $output = '';
                        $total_used_tokens = 0;
                        $responsedText = $exception->getMessage();
                        $messageError = 'Error from API call. Please try again. If error persists again please contact system administrator with this message ' . $exception->getMessage();
                        echo "data: $messageError";
                        echo "\n\n";
                        flush();
                        echo 'data: [DONE]';
                        echo "\n\n";
                        flush();
                        usleep(50000);
                    }
                } elseif ($type === 'vision') {
                    try {
                        $driver = EntityFacade::driver(EntityEnum::GPT_5_MINI);
                        $gclient = new Client;
                        $openaiApiKey = $this->getOpenAiApiKey(Auth::user());
                        $url = 'https://api.openai.com/v1/chat/completions';

                        $response = $gclient->post(
                            $url,
                            [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $openaiApiKey,
                                ],
                                'json' => OpenAiParamHelper::sanitizeChatParams([
                                    'model'    => $driver->enum()->value,
                                    'messages' => [
                                        [
                                            'role'    => 'user',
                                            'content' => array_merge(
                                                [
                                                    [
                                                        'type' => 'text',
                                                        'text' => $prompt,
                                                    ],
                                                ],
                                                collect($images)->map(function ($item) {
                                                    if (Str::startsWith($item, 'http')) {
                                                        $imageData = file_get_contents($item);
                                                    } else {
                                                        $imageData = file_get_contents(substr($item, 1));
                                                    }
                                                    $base64Image = base64_encode($imageData ?? '');

                                                    return [
                                                        'type'      => 'image_url',
                                                        'image_url' => [
                                                            'url' => 'data:image/png;base64,' . $base64Image,
                                                        ],
                                                    ];
                                                })->toArray()
                                            ),
                                        ],
                                    ],
                                    'max_tokens' => 2000,
                                    'stream'     => true,
                                ]),
                            ],
                        );
                    } catch (Exception $exception) {
                        $messageError = 'Error from API call. Please try again. If error persists again please contact system administrator with this message ' . $exception->getMessage();
                        echo "data: $messageError";
                        echo "\n\n";
                        // ob_flush();
                        flush();
                        echo 'data: [DONE]';
                        echo "\n\n";
                        // ob_flush();
                        flush();
                        usleep(50000);
                    }

                    $total_used_tokens = 0;
                    $output = '';
                    $responsedText = '';

                    foreach (explode("\n", $response->getBody()->getContents()) as $chunk) {
                        if (strlen($chunk) > 5 && $chunk !== 'data: [DONE]' && isset(json_decode(substr($chunk, 6), false, 512, JSON_THROW_ON_ERROR)->choices[0]->delta->content)) {

                            $message = json_decode(substr($chunk, 6))->choices[0]->delta->content;

                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message ?? '');
                            $output .= $messageFix;

                            $responsedText .= $message;
                            $total_used_tokens += countWords($message);

                            $string_length = Str::length($messageFix);
                            $needChars = 6000 - $string_length;
                            $random_text = Str::random($needChars);

                            echo PHP_EOL;
                            echo 'data: ' . $messageFix . '/**' . $random_text . "\n\n";
                            flush();
                            usleep(5000);
                        }
                    }
                }
                $message = UserOpenaiChatMessage::whereId($message_id)->first();
                $chat = UserOpenaiChat::whereId($chat_id)->first();
                $message->response = $responsedText;
                $message->output = $output;
                $message->hash = Str::random(256);
                $message->credits = $total_used_tokens;
                $message->words = 0;
                $message->images = ChatAttachmentReferences::normalize($images);
                $message->pdfName = ChatAttachmentReferences::normalize($request->input('pdfname'));
                $message->pdfPath = ChatAttachmentReferences::normalize($request->input('pdfpath'));
                $message->save();
                $driver
                    ->input($responsedText)
                    ->calculateCredit()
                    ->decreaseCredit();
                Usage::getSingle()->updateWordCounts($driver->calculate());
                $chat->total_credits += $total_used_tokens;
                $chat->save();
                echo 'data: [DONE]';
                echo "\n\n";
                flush();
                usleep(50000);
            }, 200, [
                'Cache-Control'     => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Content-Type'      => 'text/event-stream',
            ]);
        }

        $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? request()?->ip();
        $db_ip_address = RateLimit::where('ip_address', $ipAddress)->where('type', 'chatbot')->first();
        if ($db_ip_address) {
            if (now()->diffInDays(Carbon::parse($db_ip_address->last_attempt_at)->format('Y-m-d')) > 0) {
                $db_ip_address->attempts = 0;
            }
        } else {
            $db_ip_address = new RateLimit(['ip_address' => $ipAddress, 'type' => 'chatbot']);
        }

        if ($db_ip_address->attempts >= $this->settings_two->chatbot_rate_limit) {
            $data = [
                'errors' => [__('You have reached the maximum number of ask to chatbot allowed.')],
            ];

            return response()->json($data, 429);
        }

        $db_ip_address->attempts++;
        $db_ip_address->last_attempt_at = now();
        $db_ip_address->save();

        if (! is_string($request->input('prompt'))) {
            return response()->json(['message' => __('A text message is required.')], 422);
        }
        $realtime = $request->realtime;
        $user = Auth::user();
        $total_used_tokens = 0;
        $entry = new UserOpenaiChatMessage;
        $entry->user_id = $user?->id;
        $entry->user_openai_chat_id = $chat->id;
        $entry->is_chatbot = 1;
        $entry->input = $request->prompt;
        $entry->response = null;
        $entry->realtime = $realtime ?? 0;
        $entry->output = "(If you encounter this message, please attempt to send your message again. If the error persists beyond multiple attempts, please don't hesitate to contact us for assistance!)";
        $entry->hash = Str::random(256);
        $entry->credits = $total_used_tokens;
        $entry->words = 0;
        $entry->save();

        $chat->total_credits += $total_used_tokens;
        $chat->save();
        $chat_id = $chat->id;
        $message_id = $entry->id;

        return response()->json(compact('chat_id', 'message_id'));
    }

    private function voiceCreditsUnavailableMessage(): string
    {
        return __('Voice is unavailable because your current Garad plan has no Whisper/STT credits remaining. Upgrade your plan or add credits to continue.');
    }

    private function isChatVoiceRequest(Request $request): bool
    {
        return trim((string) $request->input('voice_context', '')) === 'chat_voice';
    }

    private function resolveVoiceTranscriptionBillingDriver(Request $request, User $user, string $inputText)
    {
        $defaultDriver = EntityFacade::driverForUser($user, EntityEnum::WHISPER_1)
            ->input($inputText)
            ->calculateCredit();

        if (! $this->isChatVoiceRequest($request)) {
            return $defaultDriver;
        }

        $realtimeDriver = EntityFacade::driverForUser($user, EntityEnum::GPT_REALTIME)
            ->input($inputText)
            ->calculateCredit();
        if ($realtimeDriver->hasCreditBalanceForInput()) {
            return $realtimeDriver;
        }

        $reasoningModelId = trim((string) $request->input('reasoning_model', ''));
        $reasoningModel = EntityEnum::tryFrom($reasoningModelId);
        if ($reasoningModel) {
            $reasoningDriver = EntityFacade::driverForUser($user, $reasoningModel)
                ->input($inputText)
                ->calculateCredit();

            if ($reasoningDriver->hasCreditBalanceForInput()) {
                return $reasoningDriver;
            }
        }

        return $defaultDriver;
    }

    public function voiceStatus(): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'available' => false,
                'reason' => 'plan',
                'message' => __('Please sign in to use Garad Voice.'),
            ], 401);
        }

        $reasoningModelId = trim((string) request()->query('reasoning_model', ''));
        $requestedReasoningModel = EntityEnum::tryFrom($reasoningModelId);
        $voiceMetadata = [
            'reasoningModelId' => $reasoningModelId,
            'reasoningModelName' => $reasoningModelId,
            'reasoningProvider' => '',
            'transcriptionModel' => EntityEnum::WHISPER_1->value,
        ];

        try {
            $reasoningModelRecords = Entity::query()
                ->isEnabled()
                ->whereHas('tokens', fn ($query) => $query->where('type', 'word'))
                ->get();

            $reasoningModelMap = $reasoningModelRecords->keyBy(
                function ($record) {
                    $key = $record->key;

                    return $key instanceof EntityEnum ? $key->value : (string) $key;
                }
            );

            $candidateModels = collect();
            if ($requestedReasoningModel) {
                $candidateModels->push($requestedReasoningModel);
            }
            $candidateModels = $candidateModels
                ->merge(
                    $reasoningModelRecords
                        ->map(function ($record) {
                            $key = $record->key;
                            $value = $key instanceof EntityEnum ? $key->value : (string) $key;

                            return EntityEnum::tryFrom($value);
                        })
                        ->filter()
                )
                ->unique(fn (EntityEnum $model) => $model->value)
                ->values();

            $resolvedReasoningModel = null;
            $resolvedReasoningModelRecord = null;
            foreach ($candidateModels as $candidateModel) {
                $candidateRecord = $reasoningModelMap->get($candidateModel->value);
                if (! $candidateRecord) {
                    continue;
                }
                $resolvedReasoningModel = $candidateModel;
                $resolvedReasoningModelRecord = $candidateRecord;
                break;
            }

            if (! $resolvedReasoningModel || ! $resolvedReasoningModelRecord) {
                return response()->json([
                    'available' => false,
                    'reason' => $user->activePlan() ? 'credits' : 'plan',
                    'message' => __('Voice is temporarily unavailable for this account right now.'),
                    ...$voiceMetadata,
                ], 419);
            }

            $voiceMetadata['reasoningModelId'] = $resolvedReasoningModel->value;
            $voiceMetadata['reasoningModelName'] =
                trim((string) ($resolvedReasoningModelRecord->selected_title ?: $resolvedReasoningModelRecord->title))
                ?: $resolvedReasoningModel->value;
            $voiceMetadata['reasoningProvider'] = $resolvedReasoningModel->engine()->label();

            return response()->json([
                'available' => true,
                'reason' => null,
                'message' => __('Garad Voice is ready.'),
                ...$voiceMetadata,
            ]);
        } catch (Throwable $e) {
            Log::error('Garad voice access check failed.', [
                'user_id' => $user->id,
                'reasoning_model' => $reasoningModelId,
                'exception' => get_class($e),
                'message' => Str::limit($e->getMessage(), 500),
            ]);

            return response()->json([
                'available' => false,
                'reason' => 'plan',
                'message' => __('Garad Voice access could not be verified. Please try again.'),
                ...$voiceMetadata,
            ], 503);
        }
    }

    public function transAudio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimes:mp3,mp4,mpeg,mpga,m4a,wav,webm'],
        ]);
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => __('Please sign in to use voice transcription.')], 401);
        }

        $stream = null;
        $audioPath = null;

        try {
            $driver = EntityFacade::driverForUser($user, EntityEnum::WHISPER_1);
            if (! $this->isChatVoiceRequest($request)) {
                $driver->redirectIfNoCreditBalance();
            }

            $realPath = $validated['file']->getRealPath();
            if (! $realPath) {
                throw new RuntimeException('The uploaded audio file is unavailable.');
            }

            $extension = strtolower((string) $validated['file']->guessExtension());
            if (! in_array($extension, ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm'], true)) {
                throw new RuntimeException('The uploaded audio file extension is not supported.');
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'garad-voice-');
            if ($temporaryPath === false) {
                throw new RuntimeException('A temporary audio file could not be created.');
            }

            $audioPath = $temporaryPath . '.' . $extension;
            if (! rename($temporaryPath, $audioPath)) {
                $audioPath = $temporaryPath;
                throw new RuntimeException('A temporary audio file could not be named.');
            }
            if (! copy($realPath, $audioPath)) {
                throw new RuntimeException('The uploaded audio file could not be prepared.');
            }

            $stream = fopen($audioPath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('The uploaded audio file could not be opened.');
            }

            $response = OpenAI::audio()->transcribe([
                'file'            => $stream,
                'model'           => $driver->enum()->value,
                'response_format' => 'verbose_json',
            ]);

            $text = trim((string) $response->text);
            if ($text === '') {
                return response()->json(['message' => __('No speech was recognized. Please try again.')], 422);
            }

            $billingDriver = $this->resolveVoiceTranscriptionBillingDriver($request, $user, $text);
            if (! $billingDriver->hasCreditBalanceForInput() || ! $billingDriver->decreaseCredit()) {
                return response()->json([
                    'message' => $this->voiceCreditsUnavailableMessage(),
                    'reason' => 'credits',
                ], 419);
            }

            Usage::getSingle()->updateWordCounts($billingDriver->calculate());

            return response()->json($text);
        } catch (Throwable $e) {
            if ($e->getCode() === 419) {
                return response()->json([
                    'message' => $this->voiceCreditsUnavailableMessage(),
                    'reason' => 'credits',
                    'status' => 'error',
                ], 419);
            }

            Log::error('Garad voice transcription failed.', [
                'user_id' => $user->id,
                'exception' => get_class($e),
                'message' => Str::limit($e->getMessage(), 500),
            ]);

            return response()->json(['message' => __('Speech transcription failed. Please try again.')], 502);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_string($audioPath) && is_file($audioPath) && ! unlink($audioPath)) {
                Log::warning('Could not remove the temporary Garad voice audio file.', [
                    'user_id' => $user->id,
                ]);
            }
        }
    }

    public function deleteChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => ['required', 'regex:/^(chat_)?[0-9]+$/'],
        ]);
        $chatId = (int) preg_replace('/^chat_/', '', $validated['chat_id']);
        $chat = UserOpenaiChat::query()
            ->whereKey($chatId)
            ->where('user_id', Auth::id())
            ->where('is_chatbot', 0)
            ->firstOrFail();
        $chat->delete();

        return response()->json([
            'success' => true,
            'chat'    => ['id' => $chat->id, 'title' => $chat->title],
            'message' => __('Chat deleted successfully'),
        ]);
    }

    public function clearChats(Request $request): JsonResponse
    {
        // clear all chats for related chat category slug
        if (Helper::appIsNotDemo()) {
            $user = Auth::user();
            $category_id = $request->category_id;
            $website_url = $request->website_url ?? null;

            $chatsQuery = UserOpenaiChat::query()
                ->where('user_id', $user?->id)
                ->where('openai_chat_category_id', $category_id);

            $this->applyWebsiteChatTypeScope($chatsQuery, $website_url);
            $chats = $chatsQuery->get();

            if ($chats) {
                foreach ($chats as $chat) {
                    $chat->delete();
                }
            }
        }

        return response()->json(['error' => __('This action is disabled in the demo.')]);
    }

    public function renameChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => ['required', 'regex:/^(chat_)?[0-9]+$/'],
            'title'   => 'required|string|max:255',
        ]);
        $chatId = (int) preg_replace('/^chat_/', '', $validated['chat_id']);
        $chat = UserOpenaiChat::query()
            ->whereKey($chatId)
            ->where('user_id', Auth::id())
            ->where('is_chatbot', 0)
            ->firstOrFail();
        $chat->title = trim($validated['title']);
        $chat->save();

        return response()->json([
            'success' => true,
            'chat'    => ['id' => $chat->id, 'title' => $chat->title],
            'message' => __('Chat renamed successfully'),
        ]);
    }

    public function pinConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => ['required', 'regex:/^(chat_)?[0-9]+$/'],
            'pinned'  => 'required|boolean',
        ]);
        $chatId = (int) preg_replace('/^chat_/', '', $validated['chat_id']);
        $chat = UserOpenaiChat::query()
            ->whereKey($chatId)
            ->where('user_id', Auth::id())
            ->where('is_chatbot', 0)
            ->firstOrFail();
        $chat->is_pinned = (bool) $validated['pinned'];
        $chat->save();

        return response()->json([
            'success' => true,
            'chat'    => ['id' => $chat->id, 'is_pinned' => $chat->is_pinned],
            'message' => __('Chat pin changed successfully'),
        ]);
    }

    // Low
    public function lowChatSave(Request $request): JsonResponse
    {
        $chat = $this->openai($request)->with('category')->whereKey($request->input('chat_id'))->first();
        if (! $chat) {
            return response()->json(['message' => __('Chat not found.')], 404);
        }

        $validated = $request->validate([
            'input' => ['nullable', 'string'],
            'response' => ['required', 'string'],
        ]);
        $chat_bot = EntityEnum::fromSlug(empty($request->model) ? $this->settings?->openai_default_model : $request->model) ?? EntityEnum::GPT_5_MINI;

        if (! empty($chat->category->slug) && $chat->category->slug === 'ai_chat_image') {
            $chat_bot = $this->getDefaultOpenAiImageModel();
            $driver = EntityFacade::driver($chat_bot)->inputImageCount(1);
            Usage::getSingle()->updateImageCounts($driver->calculate());
        } else {
            $driver = EntityFacade::driver($chat_bot)->input($validated['response']);
            Usage::getSingle()->updateWordCounts($driver->calculate());
        }

        try {
            if (Helper::appIsNotDemo()) {
                $driver->redirectIfNoCreditBalance();
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 'error',
            ], 419);
        }

        $message = new UserOpenaiChatMessage;
        $message->user_openai_chat_id = $chat?->id;
        $message->user_id = Auth::id();
        $message->input = $validated['input'] ?? null;
        $message->response = $validated['response'];
        $message->output = $validated['response'];
        $message->hash = Str::random(256);
        $message->credits = countWords($validated['response']);
        $message->words = countWords($validated['response']);
        $message->images = ChatAttachmentReferences::normalize($request->input('images'));
        $message->pdfPath = ChatAttachmentReferences::normalize($request->input('pdfPath'));
        $message->pdfName = ChatAttachmentReferences::normalize($request->input('pdfName'));
        $message->outputImage = ChatAttachmentReferences::normalize($request->input('outputImage'));
        $message->save();

        $driver->calculateCredit()->decreaseCredit();

        return response()->json([
            'message' => $message->makeHidden(['hash']),
        ]);
    }

    public function changeChatTitle(Request $request): JsonResponse
    {
        $changed = false;
        $newTitle = '';
        $streamed_message_id = $request->streamed_message_id;
        $message = UserOpenaiChatMessage::whereId($streamed_message_id)->first();
        $chat_id = $message?->user_openai_chat_id;
        $chat = $chat_id ? $this->openai($request)->whereKey($chat_id)->first() : null;
        if ($chat && $message && (int) $message->user_openai_chat_id === (int) $chat->id) {
            if ($chat->messages()->count() <= 2) {
                $systemPrompt = $this->applyPromptRules('You are a chatbot. Generate a title for a chat based on provided conversation. You must return a title only.');
                $userContent = "Generate a title for a chat based on the following conversation: \n\n\n\n\n"
                    . 'User Input: ' . $message->input . "\n\n\n\n\n"
                    . 'Assistant Response: ' . $message->response;

                $newTitle = app(AiCompletionService::class)->complete($systemPrompt, $userContent);
                $chat->title = $newTitle;
                $chat->save();
                $changed = true;
            }
        }

        return response()->json(['chat_id' => $chat_id, 'changed' => $changed, 'new_title' => $newTitle]);
    }

    private function getOpenAiApiKey(?User $user): string
    {
        return ApiHelper::setOpenAiKey();
    }

    private function bedrockService(): BedrockRuntimeService
    {
        if ($this->bedrockService === null) {
            $this->bedrockService = app(BedrockRuntimeService::class);
        }

        return $this->bedrockService;
    }

    private function applyPromptRules(string $prompt): string
    {
        $prompt .= '
        here some rules you must follow:
            1. Dont use double quotation ".." marks in answers.
        ';

        return $prompt;
    }

    private function getDefaultOpenAiImageModel(): EntityEnum
    {
        return DefaultOpenAiImageModel::resolve($this->settings_two);
    }

    // file upload bug fixed

    /**
     * @throws JsonException
     */
    public function uploadDoc(Request $request, $chat_id, $type): string
    {
        ApiHelper::setOpenAiKey();

        $allowedExtensions = ['pdf', 'doc', 'docx', 'csv', 'xls', 'xlsx'];

        $doc = $request->file('doc');

        if (! $doc || ! $doc->isValid()) {
            throw new RuntimeException('Invalid file');
        }

        // Use Symfony’s mime guesser
        $realExtension = strtolower($doc->guessExtension());

        if (! in_array($realExtension, $allowedExtensions)) {
            throw new RuntimeException('File type not allowed');
        }

        $originalName = $doc->getClientOriginalName();

        if (substr_count($originalName, '.') > 1) {
            throw new RuntimeException(trans('There cannot be more than one dot in the file name.'));
        }

        if ($doc->getSize() > 10 * 1024 * 1024) {
            throw new RuntimeException('File too large');
        }

        $docContent = file_get_contents($doc->getRealPath());
        if ($this->containsMaliciousContent($docContent, $realExtension)) {
            throw new RuntimeException('Malicious content detected');
        }

        // Generate safe filename
        $safeFileName = Str::random(12) . '.' . $realExtension;

        $tempFileName = 'temp_' . Str::random(8) . '.' . $realExtension;
        Storage::disk('public')->put($tempFileName, $docContent);
        Storage::disk('public')->put($safeFileName, $docContent);

        $resPath = "/uploads/$safeFileName";
        $uploadedFile = new File(public_path("$resPath"));

        if ($this->settings_two->ai_image_storage === 's3') {
            try {
                $aws_path = Storage::disk('s3')->put('', $uploadedFile);
                unlink(public_path($resPath));
                $resPath = Storage::disk('s3')->url($aws_path);
            } catch (Exception $e) {
                throw new RuntimeException('AWS Error - ' . $e->getMessage());
            }
        }

        $page = processFileContent($realExtension, $tempFileName);

        Storage::disk('public')->delete($tempFileName);

        $this->processEmbeddings($page, $chat_id);

        return $resPath;
    }

    private function containsMaliciousContent(string $content, string $extension): bool
    {
        $phpPatterns = [
            '/<\?php/',
            '/<\?=/',
            '/system\s*\(/',
            '/exec\s*\(/',
            '/shell_exec\s*\(/',
            '/passthru\s*\(/',
            '/eval\s*\(/',
            '/base64_decode\s*\(/',
            '/file_get_contents\s*\(.*?http/',
            '/curl_exec\s*\(/',
            '/fopen\s*\(.*?(http|ftp)/',
        ];

        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        if ($extension === 'csv') {
            $jsPatterns = [
                '/<script/i',
                '/javascript:/i',
                '/on\w+\s*=/i',
            ];

            foreach ($jsPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function processEmbeddings(string $content, $chatId): void
    {
        $countwords = strlen($content) / 1001 + 1;
        $driver = EntityFacade::driver(EntityEnum::TEXT_EMBEDDING_ADA_002);

        for ($i = 0; $i < $countwords; $i++) {
            try {
                $startPos = 1001 * $i;
                $length = min(2000, strlen($content) - $startPos);

                if ($length <= 10) {
                    continue;
                }

                $subtxt = substr($content, $startPos, $length);
                $subtxt = mb_convert_encoding($subtxt, 'UTF-8', 'UTF-8');
                $subtxt = iconv('UTF-8', 'UTF-8//IGNORE', $subtxt);

                $response = OpenAI::embeddings()->create([
                    'model' => $driver->enum()->value,
                    'input' => $subtxt,
                ]);

                $chatpdf = new PdfData;
                $chatpdf->chat_id = $chatId;
                $chatpdf->content = $subtxt;
                $chatpdf->vector = json_encode($response->embeddings[0]->embedding, JSON_THROW_ON_ERROR);
                $chatpdf->save();

                $driver->input($subtxt)->calculateCredit()->decreaseCredit();
                Usage::getSingle()->updateWordCounts($driver->calculate());

            } catch (Exception $e) {
                Log::error('Embedding processing failed: ' . $e->getMessage());
            }
        }
    }
}
