<?php

namespace App\Extensions\SocialMediaAgent\System\Http\Controllers;

use App\Domains\Entity\Models\Entity;
use App\Extensions\SocialMedia\System\Models\SocialMediaAnalysis;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Chatbot\Chatbot;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonException;
use Random\RandomException;

class SocialMediaAgentChatController extends Controller
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
    public function index($id = null)
    {
        $gateway = $this->resolveSubscriptionGateway();
        $isPaid = $this->hasActivePaidSubscription($gateway);
        $category = $this->firstOpenaiGeneratorChatCategory();

        $defaultScreen = setting('ai_chat_pro_default_screen', 'new');
        $query = $this->openai(request())
            ->where('openai_chat_category_id', $category?->id)
            ->where('is_chatbot', 0);
        [$list, $chat] = $this->buildChatListAndActive($query, $category, $defaultScreen);

        [$apikeyPart1, $apikeyPart2, $apikeyPart3] = $this->prepareApiKeyParts();
        [$apiSearch, $apiSearchId] = $this->prepareSearchConfig();
        [$lastThreeMessage, $chat_completions, $category] = $this->prepareChatContext($chat, $category);

        $aiList = OpenaiGeneratorChatCategory::where('slug', '<>', 'ai_vision')->where('slug', '<>', 'ai_pdf')->get();
        $chatbots = Chatbot::query()->get();
        $models = Entity::planModels();
        $apiUrl = base64_encode('https://api.openai.com/v1/chat/completions');
        $tempChat = false;
        $generators = $this->availableGenerators();
        $agents = SocialMediaAgent::query()->where('user_id', Auth::id())->get();
        $defaultAgent = null;
        if ($id) {
            $defaultAgent = $agents->firstWhere('id', (int) $id);
        }
        if (! $defaultAgent) {
            $defaultAgent = $agents->first();
        }

        if (! $defaultAgent) {
            return redirect()->route('dashboard.user.social-media.agent.create')
                ->with([
                    'message' => __('Please create a Social Media Agent first.'),
                    'type'    => 'info',
                ]);
        }

        $platforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->get();

        $analysis = SocialMediaAnalysis::query()->where('id', request('analysis'))->where('user_id', Auth::id())->first();

        if (request('analysis') && $analysis) {

            $analysis->update(['read_at' => now()]);
            $chat = $this->openai(request())
                ->where('social_media_analysis_id', $analysis->id)
                ->first();

            if (! $chat) {
                $chat = $this->startNewChat($category, $analysis, false);

                $message = new UserOpenaiChatMessage;
                $message->user_openai_chat_id = $chat->id;
                $message->user_id = Auth::id();
                $message->output = 'Analysis Report Reset';
                $message->response = null;
                $message->credits = 0;
                $message->words = 0;
                $message->save();

                $this->createInitialChatMessage($chat, $category, $analysis);
            }

            $list->prepend($chat);

        }

        return view('social-media-agent::chat.index', compact(
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
            'agents',
            'defaultAgent',
            'platforms'
        ));
    }

    protected function openai(Request $request): Builder
    {
        $team = $request->user()?->getAttribute('team');
        $myCreatedTeam = $request->user()?->getAttribute('myCreatedTeam');

        return UserOpenaiChat::query()
            ->where('chat_type', 'social-media-agent')
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
    }

    private function firstOpenaiGeneratorChatCategory()
    {
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

    private function startNewChat($category, $analysis = null, bool $createInitialMessage = true)
    {
        Helper::clearEmptyConversations();

        $chat = new UserOpenaiChat;
        $chat->user_id = Auth::id();
        $chat->openai_chat_category_id = $category?->id;

        $chat->chat_type = 'social-media-agent';

        if ($analysis) {
            $chat->social_media_analysis_id = $analysis->id;
        }
        $chat->title = $analysis ? Str::limit($analysis->summary, 30) : $category->name . ' Chat';
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->save();
        $chat->refresh();

        if ($createInitialMessage) {
            $this->createInitialChatMessage($chat, $category, $analysis);
        }

        return $chat;
    }

    private function createInitialChatMessage(UserOpenaiChat $chat, $category, $analysis = null, ?string $prefix = null): void
    {
        $message = new UserOpenaiChatMessage;
        $message->user_openai_chat_id = $chat->id;
        $message->user_id = Auth::id();
        $message->response = 'First Initiation';
        if ($category->role == 'default') {
            $output = $analysis ? $analysis->report_text : __('Hi! I am') . ' ' . $category->name . __(', and I\'m here to answer all your questions');
        } else {
            $output = $analysis ? $analysis->report_text : __('Hi! I am') . ' ' . $category->human_name . __(', and I\'m') . ' ' . $category->role . '. ' . $category->helps_with;
        }

        if ($prefix !== null) {
            $output = trim($prefix . ' ' . $output);
        }

        $message->output = $output;
        $message->hash = Str::random(256);
        $message->credits = 0;
        $message->words = 0;
        $message->save();
    }

    private function resolveSubscriptionGateway(): ?string
    {
        $activeSubscription = getCurrentActiveSubscription();
        if ($activeSubscription !== null) {
            return $activeSubscription->paid_with;
        }

        $activeYokkasaSubscription = getCurrentActiveSubscriptionYokkasa();

        return $activeYokkasaSubscription?->paid_with;
    }

    private function hasActivePaidSubscription(?string $gateway): bool
    {
        if (! $gateway) {
            return false;
        }

        try {
            return GatewaySelector::selectGateway($gateway)::getSubscriptionStatus();
        } catch (Exception $e) {
            return false;
        }
    }

    private function buildChatListAndActive(Builder $query, $category, string $defaultScreen): array
    {
        switch ($defaultScreen) {
            case 'pinned':
                $list = $query->orderBy('is_pinned', 'desc')
                    ->orderBy('updated_at', 'desc')
                    ->get();
                $chat = $list->first(fn ($c) => $c->is_pinned) ?? $list->first();

                break;

            case 'new':
                $chat = $this->startNewChat($category);
                $list = $query->orderBy('is_pinned', 'desc')
                    ->orderBy('updated_at', 'desc')
                    ->get();

                break;

            case 'last':
            default:
                $list = $query->orderBy('updated_at', 'desc')->get();
                $chat = $list->first();

                break;
        }

        return [$list, $chat];
    }

    private function prepareApiKeyParts(): array
    {
        if ($this->settings_two->openai_default_stream_server !== 'frontend' && ! setting('realtime_voice_chat', 0)) {
            return [
                base64_encode(random_int(1, 100)),
                base64_encode(random_int(1, 100)),
                base64_encode(random_int(1, 100)),
            ];
        }

        $apiKey = $this->getOpenAiApiKey(Auth::user());
        $len = max(strlen($apiKey), 6);
        $parts[] = substr($apiKey, 0, $l[] = random_int(1, $len - 5));
        $parts[] = substr($apiKey, $l[0], $l[] = random_int(1, $len - $l[0] - 3));
        $parts[] = substr($apiKey, array_sum($l));

        return [
            base64_encode($parts[0]),
            base64_encode($parts[1]),
            base64_encode($parts[2]),
        ];
    }

    private function prepareSearchConfig(): array
    {
        return [
            base64_encode('https://google.serper.dev/search'),
            base64_encode($this->settings_two->serper_api_key),
        ];
    }

    private function prepareChatContext($chat, $category): array
    {
        $lastThreeMessage = null;
        $chatCompletions = null;

        if ($chat !== null) {
            $lastThreeMessageQuery = $chat->messages()
                ->whereNot('input', null)
                ->orderBy('created_at', 'desc')
                ->take(2);
            $lastThreeMessage = $lastThreeMessageQuery->get()->reverse();
            $category = OpenaiGeneratorChatCategory::where('id', $chat->openai_chat_category_id)->first();
            $chatCompletions = str_replace(["\r", "\n"], '', $category->chat_completions);

            if ($chatCompletions) {
                $chatCompletions = json_decode($chatCompletions, true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return [$lastThreeMessage, $chatCompletions, $category];
    }

    private function availableGenerators()
    {
        return OpenaiGeneratorChatCategory::query()
            ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
            ->when(Auth::user()?->isUser(), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('user_id')->orWhere('user_id', Auth::id());
                });
            })
            ->get();
    }
}
