<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Services\CrmAssistantChatService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Chatbot\Chatbot;
use App\Models\OpenaiGeneratorChatCategory;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonException;
use Random\RandomException;

/**
 * Renders the CRM assistant on top of the core chat engine, mirroring
 * SocialMediaAgentChatController. All conversation persistence, sidebar
 * behaviour and streaming is handled by the core stack via the
 * `crm-assistant` chat_type discriminator.
 */
class CrmAssistantChatController extends Controller
{
    public const CHAT_TYPE = 'crm-assistant';

    protected $settings;

    protected $settings_two;

    public function __construct()
    {
        $this->settings = Setting::getCache();
        $this->settings_two = SettingTwo::getCache();
        config(['openai.api_key' => ApiHelper::setOpenAiKey()]);
    }

    /**
     * @throws RandomException
     * @throws JsonException
     */
    public function index(Request $request): View
    {
        $category = $this->firstOpenaiGeneratorChatCategory();

        $query = $this->crmChats($request)
            ->where('openai_chat_category_id', $category?->id)
            ->where('is_chatbot', 0);

        $copilotInit = $this->resolveCopilotInit($request);

        [$list, $chat] = $this->buildChatListAndActive(
            $query,
            $category,
            $copilotInit !== null ? 'new' : setting('ai_chat_pro_default_screen', 'new')
        );

        [$apikeyPart1, $apikeyPart2, $apikeyPart3] = $this->prepareApiKeyParts();
        [$apiSearch, $apiSearchId] = $this->prepareSearchConfig();
        [$lastThreeMessage, $chat_completions, $category] = $this->prepareChatContext($chat, $category);

        return view('crm::assistant.index', [
            'generators'       => $this->availableGenerators(),
            'category'         => $category,
            'apiSearch'        => $apiSearch,
            'chatbots'         => Chatbot::query()->get(),
            'apiSearchId'      => $apiSearchId,
            'list'             => $list,
            'chat'             => $chat,
            'aiList'           => $this->availableGenerators(),
            'apikeyPart1'      => $apikeyPart1,
            'apikeyPart2'      => $apikeyPart2,
            'apikeyPart3'      => $apikeyPart3,
            'tempChat'         => false,
            'apiUrl'           => base64_encode('https://api.openai.com/v1/chat/completions'),
            'lastThreeMessage' => $lastThreeMessage,
            'chat_completions' => $chat_completions,
            'scopes'           => CrmAssistantChatService::scopes(),
            'defaultScope'     => CrmAssistantChatService::normalizeScope($request->get('scope')),
            'copilotInit'      => $copilotInit,
        ]);
    }

    protected function crmChats(Request $request): Builder
    {
        $team = $request->user()?->getAttribute('team');
        $myCreatedTeam = $request->user()?->getAttribute('myCreatedTeam');

        return UserOpenaiChat::query()
            ->where('chat_type', self::CHAT_TYPE)
            ->where(function (Builder $query) use ($team, $myCreatedTeam) {
                $query->where('user_id', Auth::id())
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
     * The CRM dashboard copilot widget deep-links here with `?copilot_init=<text>`.
     * The prompt is handed to the view which auto-submits it once the chat loads.
     */
    private function resolveCopilotInit(Request $request): ?string
    {
        $prompt = trim((string) $request->get('copilot_init'));

        return $prompt !== '' ? Str::limit($prompt, 2000, '') : null;
    }

    private function firstOpenaiGeneratorChatCategory(): ?OpenaiGeneratorChatCategory
    {
        $userGenerator = OpenaiGeneratorChatCategory::query()
            ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
            ->where('role', 'default')
            ->when(Auth::user()?->isUser(), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('user_id')->orWhere('user_id', Auth::id());
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

    private function startNewChat(?OpenaiGeneratorChatCategory $category): UserOpenaiChat
    {
        Helper::clearEmptyConversations();

        $chat = new UserOpenaiChat;
        $chat->user_id = Auth::id();
        $chat->team_id = Auth::user()?->team_id;
        $chat->openai_chat_category_id = $category?->id;
        $chat->chat_type = self::CHAT_TYPE;
        $chat->title = __('CRM Assistant');
        $chat->total_credits = 0;
        $chat->total_words = 0;
        $chat->save();
        $chat->refresh();

        $this->createInitialChatMessage($chat);

        return $chat;
    }

    private function createInitialChatMessage(UserOpenaiChat $chat): void
    {
        $message = new UserOpenaiChatMessage;
        $message->user_openai_chat_id = $chat->id;
        $message->user_id = Auth::id();
        $message->response = 'First Initiation';
        $message->output = __('Hi! I am your CRM Assistant. Ask me anything about your contacts, deals, tasks and invoices — or tell me what to create, update or delete.');
        $message->hash = Str::random(256);
        $message->credits = 0;
        $message->words = 0;
        $message->save();
    }

    private function buildChatListAndActive(Builder $query, ?OpenaiGeneratorChatCategory $category, string $defaultScreen): array
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

    /**
     * @throws RandomException
     */
    private function prepareApiKeyParts(): array
    {
        if ($this->settings_two?->openai_default_stream_server !== 'frontend' && ! setting('realtime_voice_chat', 0)) {
            return [
                base64_encode((string) random_int(1, 100)),
                base64_encode((string) random_int(1, 100)),
                base64_encode((string) random_int(1, 100)),
            ];
        }

        $apiKey = ApiHelper::setOpenAiKey();
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
            base64_encode((string) $this->settings_two?->serper_api_key),
        ];
    }

    /**
     * @throws JsonException
     */
    private function prepareChatContext(?UserOpenaiChat $chat, ?OpenaiGeneratorChatCategory $category): array
    {
        $lastThreeMessage = null;
        $chatCompletions = null;

        if ($chat !== null) {
            $lastThreeMessage = $chat->messages()
                ->whereNot('input', null)
                ->orderBy('created_at', 'desc')
                ->take(2)
                ->get()
                ->reverse();

            $category = OpenaiGeneratorChatCategory::where('id', $chat->openai_chat_category_id)->first();
            $chatCompletions = str_replace(["\r", "\n"], '', (string) $category?->chat_completions);

            if ($chatCompletions) {
                try {
                    $chatCompletions = json_decode($chatCompletions, true, 512, JSON_THROW_ON_ERROR);
                } catch (Exception) {
                    $chatCompletions = null;
                }
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
