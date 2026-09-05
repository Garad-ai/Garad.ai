<?php

namespace App\Extensions\AiChatProImageChat\System\Http\Controllers;

use App\Domains\Entity\Models\Entity;
use App\Enums\AiImageStatusEnum;
use App\Extensions\AiChatProImageChat\System\Services\AIChatImageService;
use App\Extensions\AiChatProImageChat\System\Services\AIChatImageToolsService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Chatbot\Chatbot;
use App\Models\ChatCategory;
use App\Models\Favourite;
use App\Models\OpenaiGeneratorChatCategory;
use App\Models\SettingTwo;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use App\Services\Common\MenuService;
use App\Services\GatewaySelector;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiChatProImageChatController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (! auth()->check()) {
            $this->deleteOldGuestChat();
            $category = OpenaiGeneratorChatCategory::where('slug', 'image-assistant')->first()
                ?? OpenaiGeneratorChatCategory::whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
                    ->where('slug', 'like', '%ai-chat-bot%')
                    ->first()
                ?? OpenaiGeneratorChatCategory::whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
                    ->first();
            $aiList = null;
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
            $category = $this->firstOpenaiGeneratorChatCategory();
            if (! $isPaid && $category?->plan === 'premium' && ! auth()->user()?->isAdmin()) {
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

            $aiList = OpenaiGeneratorChatCategory::where('slug', '<>', 'ai_vision')->where('slug', '<>', 'ai_pdf')->get();
            $apiUrl = base64_encode('https://api.openai.com/v1/chat/completions');
            if (SettingTwo::getCache()->openai_default_stream_server === 'frontend' || setting('realtime_voice_chat', 0)) {
                $apiKey = ApiHelper::setOpenAiKey();
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
            $apiSearchId = base64_encode(SettingTwo::getCache()->serper_api_key);
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

        $activeImageModels = AIChatImageService::getActiveImageModels();

        return view('ai-chat-pro-image-chat::index', compact(
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
            'activeImageModels'
        ));
    }

    private function startNewChat($category)
    {
        Helper::clearEmptyConversations();

        $chat = new UserOpenaiChat;
        $chat->user_id = Auth::id();
        $chat->chat_type = 'chatpro-image';
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

    protected function openai(Request $request): Builder
    {
        $team = $request->user()?->getAttribute('team');
        $myCreatedTeam = $request->user()?->getAttribute('myCreatedTeam');

        return UserOpenaiChat::query()
            ->where('chat_type', 'chatpro-image')
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
        $preferredGenerator = OpenaiGeneratorChatCategory::query()
            ->where('slug', 'image-assistant')
            ->when(Auth::user()?->isUser(), function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', Auth::id());
                });
            })
            ->first();

        if ($preferredGenerator) {
            return $preferredGenerator;
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

    private function startNewGuestChat($category): UserOpenaiChat
    {
        $chat = new UserOpenaiChat;
        $chat->user_id = null;
        $chat->chat_type = 'chatpro-image';
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

    public function checkImageStatus(int $recordId): ?JsonResponse
    {
        $status = AIChatImageToolsService::checkImageStatus($recordId);

        if (! $status) {
            return response()->json(['error' => 'Image record not found'], 404);
        }

        return response()->json($status);
    }

    public function getMessageImageData(int $messageId): JsonResponse
    {
        $message = UserOpenaiChatMessage::find($messageId);

        if (! $message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        // Check if user owns this message
        if (Auth::check() && $message->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get the associated image record (the one that was successfully completed)
        $imageRecord = $message->aiChatProImages()
            ->where('status', AiImageStatusEnum::COMPLETED)
            ->latest()
            ->first();

        if (! $imageRecord) {
            // Fallback: try to get any image record for this message
            $imageRecord = $message->aiChatProImages()->latest()->first();
        }

        if (! $imageRecord) {
            return response()->json(['error' => 'No image data found for this message'], 404);
        }

        $params = $imageRecord->params ?? [];

        // Get the first generated image URL for reimagine
        $generatedImages = $imageRecord->generated_images ?? [];
        $firstGeneratedImage = ! empty($generatedImages) ? $generatedImages[0] : null;

        // Convert to absolute URL if relative
        if ($firstGeneratedImage && ! str_starts_with($firstGeneratedImage, 'http')) {
            $firstGeneratedImage = url(ltrim($firstGeneratedImage, '/'));
        }

        return response()->json([
            'prompt'           => $imageRecord->prompt,
            'model'            => $imageRecord->model,
            'engine'           => $imageRecord->engine,
            'style'            => $params['style'] ?? null,
            'aspect_ratio'     => $params['aspect_ratio'] ?? null,
            'image_count'      => $params['image_count'] ?? 1,
            'image_reference'  => $params['image_reference'] ?? null,
            'generated_images' => $generatedImages,
            'generated_image'  => $firstGeneratedImage,
            'message_input'    => $message->input,
            'message_images'   => $message->images,
        ]);
    }

    public function edit(): View
    {
        $models = AIChatImageService::getModelsForTagInput();
        $selectedSlugs = AIChatImageService::getSelectedModelSlugs();

        return view('ai-chat-pro-image-chat::settings', compact('models', 'selectedSlugs'));
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'active_models'                                 => 'required|array',
            'active_models.*'                               => 'string|in:' . implode(',', array_column(AIChatImageService::getModelsForTagInput(), 'value')),
            'ai_chat_pro_image_chat:guest_daily_limit'      => 'required|integer|min:-1',
        ]);
        $activeModels = $request->input('active_models');
        setting([
            'ai_chat_pro_image_chat_selected_models'        => json_encode($activeModels, JSON_THROW_ON_ERROR),
            'ai_chat_pro_image_chat:guest_daily_limit'      => $request->input('ai_chat_pro_image_chat:guest_daily_limit', 2),
        ]);
        setting()->save();
        app(MenuService::class)->regenerate();

        return redirect()->back()->with(['message' => __('Active AI image models updated.'), 'type' => 'success']);
    }
}
