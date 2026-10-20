<?php

namespace App\Services\Stream;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Engine\Services\GeminiService;
use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\BedrockEngine;
use App\Extensions\AIChatPro\System\Connectors\ConnectorToolService;
use App\Extensions\AIChatPro\System\Services\AiChatProService;
use App\Extensions\AiChatProEntityHighlight\System\Services\EntityHighlightService;
use App\Extensions\AIChatProFileChat\System\Services\AIFileChatService;
use App\Extensions\AiChatProImageChat\System\Services\AIChatImageService;
use App\Extensions\AIChatProSkills\System\Services\SkillToolService;
use App\Extensions\AiChatProSmartImage\System\Services\SmartImageService;
use App\Extensions\AzureOpenai\System\Services\AzureOpenaiService;
use App\Extensions\OpenRouter\System\Services\RouterAiService;
use App\Extensions\SocialMediaAgent\System\Services\Chat\SocialMediaAgentChatService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\ChatAttachmentReferences;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Models\Usage;
use App\Models\UserOpenai;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use App\Services\Assistant\AssistantService;
use App\Services\Bedrock\BedrockRuntimeService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use JsonException;
use OpenAI as OpenAIMain;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\Output\OutputFunctionToolCall;
use OpenAI\Responses\Responses\Output\OutputMessage;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

class StreamService
{
    private const MAX_ANTHROPIC_CONTINUATIONS = 4;

    public bool $guest = false;

    private string $tempChatSessionKey = 'temp_chat_history_';

    public bool $tempChatActive = false;

    public bool $isFirstMessage = false;

    public bool $shouldGenerateSuggestions = false;

    private bool $entityHighlightsEnabled = false;

    private bool $isCouncilSubRequest = false;

    private bool $entityBlockSuppressed = false;

    private bool $titleEmitted = false;

    private string $titleBuffer = '';

    private string $suggestionsBuffer = '';

    private Collection $autoSkills;

    private array $usedSkills = [];

    private Collection $autoConnectors;

    private Collection $pausedConnectors;

    private array $usedConnectors = [];

    /** @var array<string, bool> tracks connector keys whose badge has been streamed to the client */
    private array $emittedConnectorKeys = [];

    public function __construct(
        Setting $setting,
        SettingTwo $settingTwo,
    ) {
        $this->autoSkills = collect();
        $this->autoConnectors = collect();
        $this->pausedConnectors = collect();
        match (setting('default_ai_engine', EngineEnum::OPEN_AI->value)) {
            EngineEnum::ANTHROPIC->value => ApiHelper::setAnthropicKey($setting),
            EngineEnum::GEMINI->value    => ApiHelper::setGeminiKey($setting),
            EngineEnum::X_AI->value      => ApiHelper::setXAiKey($setting),
            default                      => ApiHelper::setOpenAiKey($setting),
        };
    }

    public function prepareStreamEnvironment(): void
    {
        if (function_exists('ini_set')) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
        }

        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush(true);
        }

        $minLevel = config('octane.enabled') ? 1 : 0;
        while (ob_get_level() > $minLevel) {
            if (! @ob_end_flush()) {
                break;
            }
        }
    }

    public function safeFlush(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        if (function_exists('flush')) {
            @flush();
        }
    }

    private function takeAgentPendingApprovalId(): ?string
    {
        $attributes = request()->attributes;
        $approvalId = $attributes->get('garad_agent_pending_approval_id');
        $attributes->remove('garad_agent_pending_approval_id');

        return is_string($approvalId) ? $approvalId : null;
    }

    private function continueAnthropicTextResponse(
        AnthropicService $client,
        ?string $system,
        array $messages,
        bool $usedTools,
        ?string &$stopReason,
        string &$responseText,
        string &$output,
        int &$totalUsedTokens,
    ): bool {
        if ($stopReason !== 'max_tokens') {
            return true;
        }

        if ($usedTools) {
            return $this->reportIncompleteAnthropicResponse('The response stopped while using a tool.');
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $continuationMessages = $messages;
        $continuationMessages[] = ['role' => 'assistant', 'content' => $responseText];
        $continuationPrompt = 'Continue the response from exactly where it stopped. Do not repeat any text already written. Finish all remaining sections and include a clear conclusion.';
        $continuationCount = 0;

        while ($stopReason === 'max_tokens' && $continuationCount < self::MAX_ANTHROPIC_CONTINUATIONS) {
            $request = request();
            if ($request?->attributes->has('garad_provider_request_max')) {
                $maximumRequests = min(
                    \App\Services\Ai\ProviderRequestBudgetService::MAX_PROVIDER_REQUESTS,
                    (int) $request->attributes->get('garad_provider_request_max'),
                );
                if ((int) $request->attributes->get('garad_provider_request_count', 0) >= $maximumRequests) {
                    return $this->reportIncompleteAnthropicResponse('The provider-request safety budget is exhausted.');
                }
            }

            echo "event: progress\n";
            echo 'data: ' . json_encode(['message' => 'Continuing the response after the output limit…'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
            $this->safeFlush();

            $continuationMessages[] = ['role' => 'user', 'content' => $continuationPrompt];
            $eventTypes = [];
            $providerError = null;
            $continuationStopReason = null;
            $segment = '';

            foreach ($this->anthropicStreamLines($client->setStream(true)
                ->setSystem($system)
                ->setTools([])
                ->setToolChoice(null)
                ->setMessages($continuationMessages)
                ->stream()) as $chunk) {
                if (strlen($chunk) < 6 || ! Str::contains($chunk, 'data: ')) {
                    continue;
                }

                $chunk = str_replace('data: {', '{', $chunk);
                $jsonData = json_decode($chunk, false, 512, JSON_THROW_ON_ERROR);
                $eventType = (string) ($jsonData->type ?? 'unknown');
                $eventTypes[$eventType] = ($eventTypes[$eventType] ?? 0) + 1;

                if ($eventType === 'error') {
                    $providerError = $jsonData->error->message ?? 'The provider returned an error event.';
                } elseif ($eventType === 'message_delta') {
                    $continuationStopReason = $jsonData->delta->stop_reason ?? null;
                } elseif (isset($jsonData->delta->text)) {
                    $text = (string) $jsonData->delta->text;
                    $segment .= $text;
                    $responseText .= $text;
                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                    $output .= $messageFix;
                    $totalUsedTokens += countWords($text);
                    $this->emitStreamChunk($messageFix);
                }

                if (connection_aborted()) {
                    return true;
                }
            }

            if ($providerError !== null) {
                Log::warning('Anthropic continuation stream returned an error', [
                    'message' => $providerError,
                    'stream_events' => $eventTypes,
                ]);

                return $this->reportIncompleteAnthropicResponse('The provider could not continue the response.');
            }

            if (! isset($eventTypes['message_stop'])) {
                Log::warning('Anthropic continuation stream ended before completion', [
                    'stream_events' => $eventTypes,
                    'stop_reason' => $continuationStopReason,
                    'response_chars' => strlen($responseText),
                ]);

                return $this->reportIncompleteAnthropicResponse('The provider ended the continuation before completing it.');
            }

            $stopReason = is_string($continuationStopReason) ? $continuationStopReason : null;
            $continuationCount++;

            if ($stopReason === 'max_tokens') {
                if ($segment === '') {
                    return $this->reportIncompleteAnthropicResponse('The provider reached its output limit without producing more text.');
                }

                $continuationMessages[] = ['role' => 'assistant', 'content' => $segment];
            }
        }

        if ($stopReason === 'max_tokens') {
            return $this->reportIncompleteAnthropicResponse('The response is still incomplete after multiple continuation attempts.');
        }

        return true;
    }

    private function reportIncompleteAnthropicResponse(string $detail): bool
    {
        Log::warning('Anthropic response remained incomplete after continuation', ['detail' => $detail]);

        echo "event: error\n";
        echo 'data: ' . json_encode([
            'type' => 'error',
            'message' => $detail . ' Please ask the agent to continue or shorten the task.',
            'code' => 'response_incomplete',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        echo "event: stop\n";
        echo "data: [DONE]\n\n";
        $this->safeFlush();

        return false;
    }

    /**
     * Emit the skills_used SSE event if any skills were used.
     */
    private bool $skillsEmitted = false;

    private function emitUsedSkills(): void
    {
        if (empty($this->usedSkills) || $this->skillsEmitted) {
            return;
        }

        $this->skillsEmitted = true;

        echo PHP_EOL;
        echo "event: skills_used\n";
        echo 'data: ' . json_encode(['skills' => $this->usedSkills]);
        echo "\n\n";
        $this->safeFlush();
    }

    private function emitUsedConnectors(): void
    {
        if (empty($this->usedConnectors)) {
            return;
        }

        $newlyEmitted = [];

        foreach ($this->usedConnectors as $meta) {
            $key = (string) ($meta['key'] ?? '');
            if ($key === '' || isset($this->emittedConnectorKeys[$key])) {
                continue;
            }
            $this->emittedConnectorKeys[$key] = true;
            $newlyEmitted[] = $meta;
        }

        if (empty($newlyEmitted)) {
            return;
        }

        $payload = array_map(function (array $meta): array {
            $iconName = $meta['icon'] ?? null;
            $iconSvg = '';

            if (is_string($iconName) && $iconName !== '') {
                try {
                    $iconSvg = (string) svg($iconName, 'size-3.5 opacity-70')->toHtml();
                } catch (Throwable) {
                    $iconSvg = '';
                }
            }

            return $meta + ['icon_svg' => $iconSvg];
        }, $newlyEmitted);

        echo PHP_EOL;
        echo "event: connectors_used\n";
        echo 'data: ' . json_encode(['connectors' => $payload]);
        echo "\n\n";
        $this->safeFlush();
    }

    /**
     * Emit a stream data chunk, filtering out title/suggestions JSON.
     * Entity-highlight blocks are streamed through and stripped by the frontend.
     */
    private function emitStreamChunk(string $messageFix): void
    {
        if (! $this->titleEmitted && $this->isFirstMessage) {
            $this->titleBuffer .= $messageFix;

            if (preg_match('/\{"title"\s*:\s*"[^"]*"[^}]*\}/', $this->titleBuffer)) {
                $this->titleEmitted = true;
                $clean = preg_replace('/^\s*(```[\w]*\s*(<br\s*\/?>|\s)*)?\{"title"\s*:\s*"[^"]*"[^}]*\}\s*(```\s*)?(<br\s*\/?>|\s)*/i', '', $this->titleBuffer);
                $this->titleBuffer = '';
                if ($clean === '') {
                    return;
                }
                $messageFix = $clean;
            } else {
                return;
            }
        }

        // Entity block tail suppression: once triggered, swallow ALL remaining chunks.
        // The entity block is always the LAST thing before [DONE], so no exit conditions needed.
        if ($this->entityBlockSuppressed) {
            return;
        }

        if ($this->shouldGenerateSuggestions || $this->entityHighlightsEnabled) {
            $this->suggestionsBuffer .= $messageFix;

            // Detect :::meta (or legacy :::entity-highlights) marker — suppress all remaining output
            if ($this->entityHighlightsEnabled && str_contains($this->suggestionsBuffer, ':::') && preg_match('/:::\s*(?:meta|entity[- ]?highlights?)/i', $this->suggestionsBuffer)) {
                $clean = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*:::\s*(?:meta|entity[- ]?highlights?)[\s\S]*$/si', '', $this->suggestionsBuffer);
                $this->entityBlockSuppressed = true;
                $this->suggestionsBuffer = '';
                if ($clean === '') {
                    return;
                }
                $messageFix = $clean;
            }
            // Early detection: model dropped ':::' prefix but emitted a bare `meta`
            // marker followed by the entity JSON opener (`{` ... `"entities"`). Detect
            // as soon as the START of the block is visible, strip from `meta` onward,
            // and suppress all remaining chunks — same lifecycle as the `:::meta` path.
            // Without this, chunks that split mid-JSON (e.g. ending at `"suggestions`)
            // fall through to the generic suggestions branches, which only strip the
            // closing JSON and leave `meta<br/>{"entities":[...]}` in the output.
            elseif ($this->entityHighlightsEnabled
                && preg_match('/(?:^|\s|<br\s*\/?>|>)meta\b\s*(?:<br\s*\/?>|\n)*\s*\{[\s\S]*?"entities"\s*:/si', $this->suggestionsBuffer)) {
                $clean = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*(?:\*{3,}|-{3,}|_{3,})?\s*(<br\s*\/?>|\n)*\s*\bmeta\b\s*(?:<br\s*\/?>|\n)*\s*\{[\s\S]*$/si', '', $this->suggestionsBuffer);
                $this->entityBlockSuppressed = true;
                $this->suggestionsBuffer = '';
                if ($clean === '') {
                    return;
                }
                $messageFix = $clean;
            }
            // Fallback: bare `meta` tail that closes with `"suggestions":...}` shape
            // without a preceding `{...entities` opener (legacy/partial variant).
            elseif ($this->entityHighlightsEnabled
                && preg_match('/(?:^|\s|<br\s*\/?>|>)meta\b\s*(?:<br\s*\/?>|\n)*\s*[\[\{\],}][\s\S]*?"suggestions"\s*:[\s\S]*?\}/si', $this->suggestionsBuffer)) {
                $clean = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*\bmeta\b\s*(?:<br\s*\/?>|\n)*\s*[\[\{\],}][\s\S]*$/si', '', $this->suggestionsBuffer);
                $this->entityBlockSuppressed = true;
                $this->suggestionsBuffer = '';
                if ($clean === '') {
                    return;
                }
                $messageFix = $clean;
            }
            // Check if buffer contains a complete suggestions JSON block ending with } — strip it
            elseif ($this->shouldGenerateSuggestions && preg_match('/\{\s*"[^"]+"/s', $this->suggestionsBuffer) && str_contains($this->suggestionsBuffer, '}')) {
                $clean = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*(?:\*{3,}|-{3,}|_{3,})?\s*(<br\s*\/?>|\n)*\s*,*\s*(?:\bmeta\b\s*(?:<br\s*\/?>|\n)*\s*)?(```[\w]*\s*(<br\s*\/?>|\s)*)?\[?\s*\{[\s\S]*\}\s*\]?\s*,*\s*(```\s*)?(<br\s*\/?>|\s)*$/si', '', $this->suggestionsBuffer);
                $this->suggestionsBuffer = '';
                if ($clean === '') {
                    return;
                }
                $messageFix = $clean;
            }
            // Fallback: `"suggestions":` key appears without a leading `{` (malformed tail)
            // and the buffer is closed by `}`. Strip the tail from the key onward.
            elseif ($this->shouldGenerateSuggestions && preg_match('/["\']suggestions["\']\s*:/i', $this->suggestionsBuffer) && str_contains($this->suggestionsBuffer, '}')) {
                $clean = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*[\[\{,]?\s*["\']?suggestions["\']?\s*:[\s\S]*$/si', '', $this->suggestionsBuffer);
                $this->suggestionsBuffer = '';
                if ($clean === '') {
                    return;
                }
                $messageFix = $clean;
            }
            // Check if buffer ends with partial patterns worth waiting for — keep buffering
            elseif (preg_match('/:::\s*$/s', $this->suggestionsBuffer)
                || ($this->entityHighlightsEnabled && preg_match('/^\s*(?:<br\s*\/?>|\n)*\s*\bmeta\b/si', $this->suggestionsBuffer) && ! str_contains($this->suggestionsBuffer, '}'))
                || ($this->shouldGenerateSuggestions && preg_match('/\{\s*"?\s*$/s', $this->suggestionsBuffer))
                || ($this->shouldGenerateSuggestions && str_contains($this->suggestionsBuffer, '{"') && ! str_contains($this->suggestionsBuffer, '}'))
                || ($this->shouldGenerateSuggestions && preg_match('/["\']sugg[a-z]*$/i', $this->suggestionsBuffer))
                // Markdown horizontal-rule separator (***, ---, ___) at end of buffer —
                // the model sometimes emits this just before the suggestions/meta JSON tail.
                // Wait one more chunk to see whether JSON follows; if not, the separator
                // gets flushed as legitimate content on the next non-matching chunk.
                || (($this->shouldGenerateSuggestions || $this->entityHighlightsEnabled)
                    && preg_match('/(?:^|<br\s*\/?>|\n|\s)(?:\*{3,}|-{3,}|_{3,})\s*(?:<br\s*\/?>|\n|\s)*$/s', $this->suggestionsBuffer))) {
                return;
            }
            // No pattern detected — flush buffer as normal content
            else {
                $messageFix = $this->suggestionsBuffer;
                $this->suggestionsBuffer = '';
            }
        }

        echo PHP_EOL;
        echo "event: data\n";
        echo 'data: ' . $messageFix;
        echo "\n\n";
        $this->safeFlush();
    }

    private function resetStreamChunkState(): void
    {
        $this->titleEmitted = false;
        $this->titleBuffer = '';
    }

    public function createDriver(EntityEnum $model): ?BaseDriver
    {
        if ($this->guest) {
            return Entity::driverForGuest($model);
        }

        return Entity::driver($model);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function ChatStream(string $chat_bot, $history, $main_message, $chatParams, $ai_engine = null, $fileChat = false, $tempChatActive = false): ?StreamedResponse
    {
        $chat_type = $chatParams['chat_type'];
        $contain_images = $chatParams['contain_images'];
        $assistant = $chatParams['assistant'];
        $openRouter = $chatParams['openRouter'];
        if ($chat_type === 'chatPro') {
            request()?->attributes->set('garad_selected_model', $chat_bot);
        }

        // Initialize skill tracking
        $this->autoSkills = collect($chatParams['auto_skills'] ?? []);
        $this->usedSkills = $chatParams['used_skills'] ?? [];

        // Initialize connector tracking
        $this->autoConnectors = collect($chatParams['auto_connectors'] ?? []);
        $this->pausedConnectors = collect($chatParams['paused_connectors'] ?? []);
        $this->usedConnectors = $chatParams['used_connectors'] ?? [];

        if ($this->autoConnectors->isNotEmpty() && class_exists(ConnectorToolService::class)) {
            $summary = app(ConnectorToolService::class)->systemPromptSummary($this->autoConnectors);
            if ($summary !== null && $summary !== '') {
                array_unshift($history, ['role' => 'system', 'content' => $summary]);
            }
        }

        if ($this->pausedConnectors->isNotEmpty()) {
            $pausedLabels = $this->pausedConnectors
                ->map(fn ($c) => ucfirst((string) $c->type))
                ->unique()
                ->values()
                ->implode(', ');

            $pausedInstruction = 'The user has paused the following connectors: ' . $pausedLabels . '. '
                . 'You MUST NOT reference, summarize, or surface any data previously fetched from these connectors in your replies, '
                . 'even if it appears earlier in this conversation. If the user asks about data from a paused connector, '
                . 'reply that the connector is paused and ask them to resume it. Do not call any tools belonging to these connectors.';

            array_unshift($history, ['role' => 'system', 'content' => $pausedInstruction]);
        }

        $this->tempChatActive = $tempChatActive;

        // If temp chat is active, merge with session history
        if ($this->tempChatActive && $main_message->user_openai_chat_id) {
            $tempHistory = $this->getTempChatHistory($main_message->user_openai_chat_id);

            // Merge temp history with current history, avoiding duplicates
            if (! empty($tempHistory)) {
                // Keep system message at the beginning if it exists
                $systemMessages = array_filter($history, fn ($msg) => $msg['role'] === 'system');
                $nonSystemHistory = array_filter($history, fn ($msg) => $msg['role'] !== 'system');

                // Combine: system messages + temp history + current non-system messages
                $history = array_merge($systemMessages, $tempHistory, $nonSystemHistory);

                // Remove duplicates based on content and role
                $history = $this->removeDuplicateMessages($history);
            }

            // Store the current user message immediately for context
            $this->addToTempHistory($main_message->user_openai_chat_id, [
                'role'    => 'user',
                'content' => $main_message->input,
            ]);
        }

        if (! $ai_engine) {
            $ai_engine = setting('default_ai_engine', EngineEnum::OPEN_AI->value);
        }
        if ($chat_type === 'chatPro') {
            request()?->attributes->set(
                'garad_selected_engine',
                $ai_engine instanceof EngineEnum ? $ai_engine->value : (string) $ai_engine,
            );

            if (AiChatProService::requiresWebSearchForPrompt((string) ($main_message->input ?? ''))
                && setting('default_realtime') === 'tool_calling') {
                request()?->merge(['realtime' => 1]);
            }
        }

        $this->isFirstMessage = ! empty($chatParams['is_first_message']) && ! $this->tempChatActive && ! $this->guest;

        if ($this->isFirstMessage) {
            $history = $this->injectTitleInstruction($history);
        }

        $isCouncilSubRequest = (bool) request()?->input('council_sub_request', false);
        $this->isCouncilSubRequest = $isCouncilSubRequest;

        $this->shouldGenerateSuggestions = $chat_type === 'chatPro'
            && MarketplaceHelper::isRegistered('ai-chat-pro')
            && ! $this->tempChatActive
            && ! $isCouncilSubRequest;

        $entityHighlightActive = $chat_type === 'chatPro'
            && ! $isCouncilSubRequest
            && MarketplaceHelper::isRegistered('ai-chat-pro-entity-highlight')
            && EntityHighlightService::isEnabled();

        // When the entity highlight extension is active, suggestions are emitted
        // inside the same :::meta block — skip the standalone suggestions prompt to
        // avoid two competing "tail blocks" that the model often resolves by dropping one.
        if ($this->shouldGenerateSuggestions && ! $entityHighlightActive) {
            $history = $this->injectSuggestionsInstruction($history);
        }

        // Inject smart image system prompt when feature is enabled (OpenAI and Gemini)
        if ($chat_type === 'chatPro'
            && ! $isCouncilSubRequest
            && in_array($ai_engine, [
                EngineEnum::OPEN_AI->value,
                EngineEnum::GEMINI->value,
                EngineEnum::ANTHROPIC->value,
            ], true)
            && MarketplaceHelper::isRegistered('ai-chat-pro-smart-image')
            && SmartImageService::isEnabled()) {
            $history = $this->injectSmartImageInstruction($history);
        }

        // Inject entity highlight system prompt when feature is enabled. When suggestions
        // would also be active, fold them into the same metadata block.
        if ($entityHighlightActive) {
            $history = $this->injectEntityHighlightInstruction($history, $this->shouldGenerateSuggestions);
            $this->entityHighlightsEnabled = true;
        }

        $this->resetStreamChunkState();

        if ($chat_bot === EntityEnum::AZURE_OPENAI->slug() && MarketplaceHelper::isRegistered('azure-openai')) {
            return AzureOpenaiService::azureOpenaiStream($chat_bot, $history, $main_message, $chat_type, $contain_images);
        }

        // Pre-build skill function tools early so they can be merged with extension tools
        $earlySkillTools = [];
        if ($this->autoSkills->isNotEmpty() && class_exists(SkillToolService::class)) {
            if ($ai_engine === EngineEnum::OPEN_AI->value) {
                $earlySkillTools = SkillToolService::openAiTools($this->autoSkills);
            }
        }

        // Pre-build connector tools early so they can be merged with extension tools
        if ($this->autoConnectors->isNotEmpty() && class_exists(ConnectorToolService::class) && $ai_engine === EngineEnum::OPEN_AI->value) {
            $earlySkillTools = array_merge($earlySkillTools, app(ConnectorToolService::class)->openAiTools($this->autoConnectors));
        }

        if ($chat_type === 'chatPro' && MarketplaceHelper::isRegistered('ai-chat-pro')) {
            // GARAD_SAFE_V4_SYSTEM_INSTRUCTION_START
            $garadSafeV4Selection = strtolower(trim((string) request()?->input('garad_selected_tool', '')));
            $garadSafeV4Selected = match ($garadSafeV4Selection) {
                'image' => 'generate_image',
                'video' => 'generate_video',
                'pdf' => 'create_pdf',
                'word', 'docx' => 'create_docx',
                'excel', 'xlsx' => 'create_xlsx',
                'slides', 'presentation', 'ppt', 'pptx' => 'create_presentation',
                default => null,
            };
            $garadSafeV4Instruction = method_exists(AiChatProService::class, 'systemInstruction')
                ? AiChatProService::systemInstruction()
                : 'Use the available Chat Pro tools whenever the user explicitly asks to create the corresponding file or media.';
            if ($garadSafeV4Selected) {
                $garadSafeV4Instruction .= "\n\nThe user explicitly selected the create tool `{$garadSafeV4Selected}`. If that tool is available, you MUST use it for this request rather than only writing instructions or an outline.";
            }
            $history[] = ['role' => 'system', 'content' => $garadSafeV4Instruction];
            // GARAD_SAFE_V4_SYSTEM_INSTRUCTION_END
            if (! auth()->check()) {
                $this->guest = true;
            }

            $fileSearchTools = [];
            $hasGaradAssets = false;

            if ($ai_engine === EngineEnum::OPEN_AI->value) {
                try {
                    $hasGaradAssets = app(\App\Extensions\AIChatPro\System\Services\GaradMultimodalService::class)
                        ->hasConversationAssets((int) request()?->input('chat_id'));
                } catch (Throwable $e) {
                    Log::warning('Unable to inspect Chat Pro multimodal assets', [
                        'chat_id' => request()?->input('chat_id'),
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            // New Garad multimodal documents are already injected into chat history.
            // Keep the legacy OpenAI File Search path only as a fallback for older chats.
            if ($ai_engine === EngineEnum::OPEN_AI->value
                && ! $hasGaradAssets
                && MarketplaceHelper::isRegistered('ai-chat-pro-file-chat')
                && ((int) setting('chatpro_file_chat_allowed', 1) === 1)) {
                $service = new AIFileChatService(
                    ChatAttachmentReferences::normalize(request()?->input('pdfpath')),
                    request()?->input('chat_id')
                );

                $fileChat = $service->validateAndAnalyzeFile();
                if ($fileChat) {
                    $contain_images = false;
                    $chat = UserOpenaiChat::query()->find((int) request()?->input('chat_id'));

                    if ($chat?->openai_vector_id) {
                        $fileSearchTools[] = [
                            'type'             => 'file_search',
                            'vector_store_ids' => [$chat->openai_vector_id],
                            'max_num_results'  => 5,
                        ];
                    }
                }
            } else {
                $fileChat = false;
            }

            // Chat Pro is now a unified tool surface. Tools are not conditional on
            // image-generation settings; AiChatProService itself decides which
            // capabilities are currently available to this user/install.
            if ($ai_engine === EngineEnum::OPEN_AI->value) {
                $mergedTools = array_merge(
                    AiChatProService::toolsForPrompt((string) ($main_message->input ?? '')),
                    $earlySkillTools,
                    $fileSearchTools,
                );

                return $this->openaiChatStream($chat_bot, $history, $main_message, $chat_type, $contain_images, tools: $mergedTools);
            }
        }

        if ($chat_type === 'chatPro-image' && MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            return AIChatImageService::chatImageStream($chat_bot, $history, $main_message, $chatParams);
        }

        if ($chat_type === 'socialMediaAgent' && MarketplaceHelper::isRegistered('social-media-agent')) {
            $mergedTools = array_merge(SocialMediaAgentChatService::tools(), $earlySkillTools);

            return $this->openaiChatStream($chat_bot, $history, $main_message, $chat_type, $contain_images, tools: $mergedTools);
        }

        if ($fileChat) {
            return $this->openaiFileChat($chat_bot, $history, $main_message, $chat_type, $contain_images);
        }

        // Chat Pro is model-driven and must never use the legacy Assistants Threads API.
        if (! is_null($assistant) && $chat_type !== 'chatPro' && $ai_engine === EngineEnum::OPEN_AI->value) {
            return $this->assistantStream($chat_bot, $history, $main_message, $assistant);
        }
        if (! is_null($openRouter) && setting('open_router_status') == 1) {
            return $this->guardProviderStream($this->openRouterChatStream($chat_bot, $history, $main_message, $contain_images, $openRouter), 'openrouter', (string) $openRouter);
        }

        // Build skill tools for auto-use skills if any exist
        $skillTools = [];
        if ($this->autoSkills->isNotEmpty() && class_exists(SkillToolService::class)) {
            $skillTools = match ($ai_engine) {
                EngineEnum::OPEN_AI->value   => SkillToolService::openAiTools($this->autoSkills),
                EngineEnum::ANTHROPIC->value => SkillToolService::anthropicTools($this->autoSkills),
                EngineEnum::GEMINI->value    => SkillToolService::geminiTools($this->autoSkills),
                default                      => [],
            };
        }

        // Merge connector tools alongside skill tools
        if ($this->autoConnectors->isNotEmpty() && class_exists(ConnectorToolService::class)) {
            $connectorService = app(ConnectorToolService::class);
            $connectorTools = match ($ai_engine) {
                EngineEnum::OPEN_AI->value   => $connectorService->openAiTools($this->autoConnectors),
                EngineEnum::ANTHROPIC->value => $connectorService->anthropicTools($this->autoConnectors),
                EngineEnum::GEMINI->value    => $connectorService->geminiTools($this->autoConnectors),
                default                      => [],
            };

            if ($ai_engine === EngineEnum::GEMINI->value) {
                // Gemini wraps declarations in a single block; merge functionDeclarations together.
                if (! empty($connectorTools) && ! empty($skillTools)) {
                    $skillTools[0]['functionDeclarations'] = array_merge(
                        $skillTools[0]['functionDeclarations'] ?? [],
                        $connectorTools[0]['functionDeclarations'] ?? []
                    );
                } elseif (! empty($connectorTools)) {
                    $skillTools = $connectorTools;
                }
            } else {
                $skillTools = array_merge($skillTools, $connectorTools);
            }
        }

        if ($chat_type === 'chatPro') {
            $history = app(\App\Services\Ai\ProviderRequestBudgetService::class)
                ->fitHistoryToContextWindow($history, (string) $chat_bot);
        }

        $streamResponse = match ($ai_engine) {
            EngineEnum::OPEN_AI->value   => $this->openaiChatStream($chat_bot, $history, $main_message, $chat_type, $contain_images, tools: ! empty($skillTools) ? $skillTools : []),
            EngineEnum::ANTHROPIC->value => $this->anthropicChatStream($chat_bot, $history, $main_message, $chat_type, $contain_images),
            EngineEnum::GEMINI->value    => $this->geminiChatStream($chat_bot, $history, $main_message, $chat_type, $contain_images),
            EngineEnum::DEEP_SEEK->value => $this->deepseekChatStream($chat_bot, $history, $main_message, $contain_images),
            EngineEnum::X_AI->value      => $this->xAiChatStream($chat_bot, $history, $main_message, $chat_type, $contain_images),
            default                      => throw new Exception('Invalid AI Engine'),
        };

        return $chat_type === 'chatPro'
            ? $this->guardProviderStream($streamResponse, (string) $ai_engine, (string) $chat_bot)
            : $streamResponse;
    }

    /**
     * Provider-independent safety net for every Chat Pro stream: an exception thrown after
     * streaming started is logged with its provider details and surfaced as an explicit SSE error
     * instead of silently ending the stream.
     */
    private function guardProviderStream(?StreamedResponse $response, string $engine, string $model): ?StreamedResponse
    {
        $callback = $response?->getCallback();
        if ($callback === null) {
            return $response;
        }

        $response->setCallback(function () use ($callback, $engine, $model) {
            try {
                return $callback();
            } catch (Throwable $e) {
                $this->emitProviderStreamFailure($e, $engine, $model);

                return null;
            }
        });

        return $response;
    }

    /**
     * Document-creation tool call for providers that use OpenAI Chat Completions function calling
     * (DeepSeek, OpenRouter). The selected model decides the document content; the shared artifact
     * pipeline (grounding, review, rendering, persistence) is then applied unchanged.
     * Returns true when the request was handled here (artifact flow), false for ordinary chat.
     */
    private function runChatCompletionsArtifactTool(
        string $label,
        string $url,
        array $headers,
        string $model,
        array $history,
        $main_message,
        $chat,
        $driver,
        string &$responsedText,
        string &$output,
        int &$total_used_tokens,
    ): bool {
        $prompt = (string) ($main_message->input ?? '');
        $requestedTool = Auth::check() ? AiChatProService::requestedDocumentTool($prompt) : null;
        $tools = $requestedTool ? AiChatProService::chatCompletionsTools($prompt) : [];
        if ($requestedTool === null || $tools === []) {
            return false;
        }

        echo "event: progress\n";
        echo 'data: ' . json_encode(['message' => "Sending request to {$label}…"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->safeFlush();

        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->connectTimeout(15)
            ->timeout(300)
            ->post($url, [
                'model' => $model,
                'messages' => array_values($history),
                'tools' => $tools,
                'tool_choice' => 'auto',
                'stream' => false,
            ]);
        if ($response->failed()) {
            Log::error('Chat Completions artifact request was rejected', [
                'provider' => $label,
                'model' => $model,
                'status' => $response->status(),
                'provider_error' => $response->json('error.message') ?? $response->json('error'),
            ]);
        }
        $response->throw();

        $toolCall = null;
        foreach ((array) $response->json('choices.0.message.tool_calls', []) as $candidate) {
            if (($candidate['function']['name'] ?? null) === $requestedTool) {
                $toolCall = $candidate['function'];
                break;
            }
        }

        if ($toolCall === null) {
            Log::warning('Selected model did not call the requested document tool', [
                'provider' => $label,
                'model' => $model,
                'requested_tool' => $requestedTool,
                'finish_reason' => $response->json('choices.0.finish_reason'),
            ]);
            $fallback = "The selected model ({$model}) did not produce the requested file, and no file was created. Please retry or choose another model.";
            $responsedText = $fallback;
            $output = $fallback;
            $total_used_tokens = countWords($fallback);
            echo PHP_EOL . "event: data\ndata: " . $fallback . "\n\n";
            $this->safeFlush();
            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);

            return true;
        }

        echo PHP_EOL;
        echo "event: tool_status\n";
        echo 'data: ' . json_encode(AiChatProService::progressPayload($requestedTool, 'working'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->safeFlush();

        $functionResponse = AiChatProService::callFunction($requestedTool, (string) ($toolCall['arguments'] ?? '{}'))
            ?? 'The requested action could not be completed. The capability did not return a result.';
        $toolState = str_starts_with($functionResponse, 'The requested action could not be completed.') ? 'failed' : 'completed';

        echo PHP_EOL;
        echo "event: tool_status\n";
        echo 'data: ' . json_encode(AiChatProService::progressPayload($requestedTool, $toolState), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->safeFlush();

        if ($this->stopAfterFailedArtifactTool($requestedTool, $toolState, $main_message, $chat, $responsedText, $output, $total_used_tokens, $driver)) {
            return true;
        }
        $this->emitCreatedArtifactEvent();

        $responsedText .= $functionResponse;
        $output .= str_replace(["\r\n", "\r", "\n"], '<br/>', $functionResponse);
        $total_used_tokens += countWords($functionResponse);
        echo PHP_EOL . "event: data\ndata: " . str_replace(["\r\n", "\r", "\n"], '<br/>', $functionResponse) . "\n\n";
        $this->safeFlush();
        $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);

        return true;
    }

    private function emitProviderStreamFailure(Throwable $e, string $engine, string $model): void
    {
        $detail = $e->getMessage();
        $status = 502;
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            $providerMessage = $e->response->json('error.message') ?? $e->response->json('error.type');
            $detail = is_string($providerMessage) && $providerMessage !== '' ? $providerMessage : $detail;
            $status = $e->response->status();
        } elseif ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            $detail = 'The provider connection timed out or was interrupted.';
        }

        Log::error('Chat Pro provider stream failed', [
            'engine' => $engine,
            'model' => $model,
            'status' => $status,
            'detail' => $detail,
            'exception' => $e,
        ]);

        echo PHP_EOL;
        echo "event: error\n";
        echo 'data: ' . json_encode([
            'type' => 'error',
            'message' => "The selected model ({$model}) did not finish the response: {$detail}",
            'code' => 'provider_stream_failed',
            'status' => $status,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        echo "event: stop\n";
        echo "data: [DONE]\n\n";
        $this->safeFlush();
    }

    private function openRouterChatStream($chat_bot, $history, $main_message, $contain_images, $openRouter)
    {
        if ($contain_images) {
            return $this->streamInputError(
                $main_message,
                __('The selected OpenRouter model cannot process image attachments through its configured provider. Choose a vision-capable model.'),
                'model_image_unsupported'
            );
        }

        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';

        $driver = $this->createDriver(EntityEnum::fromSlug($openRouter));

        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($openRouter, $driver, $chat_bot, $history, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images) {

            $chat_id = $main_message->user_openai_chat_id;
            $chat = UserOpenaiChat::whereId($chat_id)->first();

            echo "event: message\n";
            echo 'data: ' . $main_message->id . "\n\n";

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            if (! $contain_images) {
                if ($this->runChatCompletionsArtifactTool(
                    'OpenRouter',
                    RouterAiService::BASE_URL,
                    (new RouterAiService)->headers() + ['Content-Type' => 'application/json'],
                    (string) RouterAiService::resolveModel((string) $openRouter),
                    $history,
                    $main_message,
                    $chat,
                    $driver,
                    $responsedText,
                    $output,
                    $total_used_tokens,
                )) {
                    return null;
                }

                $service = new RouterAiService;
                $response = $service->responseWithHistory($history, (string) $openRouter);
                $finished = false;

                foreach (explode("\n", $response) as $line) {
                    if (str_starts_with($line, 'data:')) {
                        $data = trim(substr($line, 5));
                        if ($data === '[DONE]') {
                            $finished = true;
                            break;
                        }

                        $json = json_decode($data, true);
                        if (isset($json['error'])) {
                            throw new \RuntimeException('OpenRouter stream error: ' . json_encode($json['error'], JSON_UNESCAPED_UNICODE));
                        }
                        if (! empty($json['choices'][0]['finish_reason'])) {
                            $finished = true;
                        }

                        if (isset($json['choices'][0]['delta']['content'])) {
                            $content = $json['choices'][0]['delta']['content'];

                            if (! empty($content)) {
                                $output .= $content;
                                $responsedText .= $content;
                                $total_used_tokens += str_word_count($content);

                                $content = str_replace(["\r\n", "\r", "\n"], '<br/>', $content);
                                $this->emitStreamChunk($content);

                                if (connection_aborted()) {
                                    break;
                                }
                            }
                        }
                    }
                }

                if (! $finished && ! connection_aborted()) {
                    throw new \RuntimeException('OpenRouter stream ended before the model finished.');
                }
            } else {
                ApiHelper::setOpenAiKey();
                $chat_bot = EntityEnum::GPT_5_MINI->value;
                $stream = OpenAI::responses()->createStreamed([
                    'model'             => $chat_bot,
                    'input'             => $history,
                    'max_output_tokens' => 2000,
                    'temperature'       => 1.0,
                    'stream'            => true,
                ]);
                foreach ($stream as $response) {
                    if (($response->event === 'response.output_text.delta') && isset($response->response->delta)) {
                        $text = $response->response->delta;
                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                        $output .= $messageFix;
                        $responsedText .= $text;
                        $total_used_tokens += countWords($text);
                        if (connection_aborted()) {
                            break;
                        }
                        $this->emitStreamChunk($messageFix);
                    }
                }
            }

            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    public function fixMessageHistory(array $history): array
    {
        $fixedHistory = [];
        $firstMessage = null;
        foreach ($history as $message) {
            if ($firstMessage === null) {
                $firstMessage = $message;
            } else {
                if ($firstMessage['role'] === $message['role']) {
                    if (is_array($message['content'])) {
                        $firstMessage['content'] = $message['content'];
                    } else {
                        $firstMessage['content'] .= ' ' . $message['content'];
                    }
                } else {
                    // Add the current message to the fixed history
                    $fixedHistory[] = $firstMessage;
                    // Start a new message
                    $firstMessage = $message;
                }
            }
        }
        if ($firstMessage !== null) {
            $fixedHistory[] = $firstMessage;
        }

        return $fixedHistory;
    }

    private function deepseekChatStream($chat_bot, $history, $main_message, $contain_images): StreamedResponse
    {
        if ($contain_images) {
            return $this->streamInputError(
                $main_message,
                __('The selected DeepSeek model cannot process image attachments. Choose a vision-capable model.'),
                'model_image_unsupported'
            );
        }

        ini_set('max_execution_time', 440);
        set_time_limit(0);

        $history = $this->fixMessageHistory($history);
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';

        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));
        $this->prepareStreamEnvironment();

        return response()->stream(
            function () use ($driver, $chat_bot, $history, $main_message, $contain_images, &$total_used_tokens, &$output, &$responsedText) {
                $chat_id = $main_message->user_openai_chat_id;
                $chat = UserOpenaiChat::whereId($chat_id)->first();
                echo "event: message\n";
                echo 'data: ' . $main_message->id . "\n\n";
                if (! $driver->hasCreditBalance()) {
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                    echo "\n\n";
                    $this->safeFlush();
                    echo "event: stop\n";
                    echo 'data: [DONE]';
                    echo "\n\n";
                    $this->safeFlush();

                    return null;
                }
                if (! $contain_images) {
                    ini_set('max_execution_time', 3000);
                    set_time_limit(3000);
                    $client = new Client;
                    ApiHelper::setDeepseekKey();
                    $url = 'https://api.deepseek.com/chat/completions';
                    $apikey = config('deepseek.api_key');
                    if ($this->runChatCompletionsArtifactTool(
                        'DeepSeek',
                        $url,
                        ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $apikey],
                        (string) $chat_bot,
                        $history,
                        $main_message,
                        $chat,
                        $driver,
                        $responsedText,
                        $output,
                        $total_used_tokens,
                    )) {
                        return null;
                    }
                    $headers = [
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                        'Authorization' => "Bearer $apikey",
                    ];

                    $body = [
                        'messages'          => $history,
                        'model'             => $chat_bot,
                        'max_tokens'        => max(8000, (int) setting('deepseek_max_output_length', 8000)),
                        'response_format'   => [
                            'type' => 'text',
                        ],
                        'stop'           => null,
                        'stream'         => true,
                        'stream_options' => null,
                        'temperature'    => 1,
                        'top_p'          => 1,
                        'tools'          => null,
                        'tool_choice'    => 'none',
                        'logprobs'       => false,
                        'top_logprobs'   => null,
                    ];
                    $requestBudget = app(\App\Services\Ai\ProviderRequestBudgetService::class);
                    $body = $requestBudget->preparePayload($body);
                    $requestBudget->recordProviderRequest();
                    $response = $client->post($url, [
                        'headers' => $headers,
                        'json'    => $body,
                    ]);
                    $bodyStream = $response->getBody();
                    $buffer = '';
                    $emptyLinesAdded = false;
                    while (! $bodyStream->eof()) {
                        $chunk = $bodyStream->read(1024);
                        $buffer .= $chunk;

                        while (($pos = strpos($buffer, "\n")) !== false) {
                            $line = substr($buffer, 0, $pos);
                            $buffer = substr($buffer, $pos + 1);

                            if (str_starts_with(trim($line), 'data: ')) {
                                $json = trim(substr($line, 5)); // Remove "data: "
                                if (! empty($json)) {
                                    $decoded = json_decode($json, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $delta = $decoded['choices'][0]['delta'] ?? [];
                                        if (! empty($decoded['choices'][0]['finish_reason'])) {
                                            $deepseekFinished = true;
                                        }
                                        if (isset($decoded['error'])) {
                                            throw new \RuntimeException('DeepSeek stream error: ' . json_encode($decoded['error'], JSON_UNESCAPED_UNICODE));
                                        }
                                        if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== null) {
                                            // Add start signal if this is the first reasoning content
                                            if (! isset($reasoningStarted)) {
                                                $reasoningStarted = true;
                                                $startSignal = '[START_REASONING]';
                                                $output .= $startSignal;
                                                $responsedText .= '[START_REASONING]';

                                                echo PHP_EOL;
                                                echo "event: data\n";
                                                echo 'data: ' . $startSignal;
                                                echo "\n\n";
                                                $this->safeFlush();
                                            }

                                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $delta['reasoning_content']);
                                            $output .= $messageFix;
                                            $responsedText .= $messageFix;
                                            // $total_used_tokens += countWords($messageFix); do we calculate reasoning content?

                                            echo PHP_EOL;
                                            echo "event: data\n";
                                            echo 'data: ' . $messageFix;
                                            echo "\n\n";
                                            $this->safeFlush();
                                        }

                                        // Handle regular content
                                        if (isset($delta['content']) && $delta['content'] !== null) {
                                            // Add end signal if we were in reasoning mode
                                            if (isset($reasoningStarted)) {
                                                $endSignal = '[END_REASONING]<br/><br/>';
                                                $output .= $endSignal;
                                                $responsedText .= "[END_REASONING]\n\n";

                                                echo PHP_EOL;
                                                echo "event: data\n";
                                                echo 'data: ' . $endSignal;
                                                echo "\n\n";
                                                $this->safeFlush();

                                                unset($reasoningStarted);
                                            }

                                            if (! $emptyLinesAdded) {
                                                echo "event: data\n";
                                                echo 'data: <br/><br/><br/>';
                                                echo "\n\n";
                                                $this->safeFlush();
                                                $emptyLinesAdded = true;
                                            }

                                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $delta['content']);
                                            $output .= $messageFix;
                                            $responsedText .= $messageFix;
                                            $total_used_tokens += countWords($messageFix);
                                            $this->emitStreamChunk($messageFix);
                                        }
                                    }
                                }
                            }
                        }

                        if (connection_aborted()) {
                            break;
                        }
                    }

                    if (! ($deepseekFinished ?? false) && ! connection_aborted()) {
                        throw new \RuntimeException('DeepSeek stream ended before the model finished (no finish_reason received).');
                    }
                } else {
                    ApiHelper::setOpenAiKey();
                    $chat_bot = EntityEnum::GPT_5_MINI->value;
                    $stream = OpenAI::responses()->createStreamed([
                        'model'                    => $chat_bot,
                        'input'                    => $history,
                        'max_output_tokens'        => 2000,
                        'temperature'              => 1.0,
                        'stream'                   => true,
                    ]);
                    foreach ($stream as $response) {
                        if (($response->event === 'response.output_text.delta') && isset($response->response->delta)) {
                            $text = $response->response->delta;
                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                            $output .= $messageFix;
                            $responsedText .= $text;
                            $total_used_tokens += countWords($text);
                            if (connection_aborted()) {
                                break;
                            }
                            $this->emitStreamChunk($messageFix);
                        }
                    }
                }
                $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
            },
            200,
            [
                'Cache-Control'     => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection'        => 'keep-alive',
                'Content-Type'      => 'text/event-stream',
            ]
        );
    }

    private function deepseekOtherStream(Request $request, $chat_bot)
    {
        ini_set('max_execution_time', 440);
        set_time_limit(0);

        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';

        $prompt = $request->get('prompt');
        $attachment = session('writer_attachment');
        if ($attachment && file_exists($attachment)) {

    $parser = new Parser();

    $pdf = $parser->parseFile($attachment);

    $prompt .= "\n\nAttached Document:\n";

    $prompt .= $pdf->getText();

    session()->forget('writer_attachment');

}
        $message_id = $request->get('message_id');
        $openai_id = $request->get('openai_id');
        $title = $request->get('title');

        $history[] = ['role' => 'user', 'content' => $prompt];

        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));

        $this->prepareStreamEnvironment();

        return response()->stream(function () use (&$total_used_tokens, &$output, &$responsedText, $driver, $message_id, $title, $openai_id, $prompt, $history, $chat_bot) {

            $user = Auth::user();
            $entry = UserOpenai::firstOrCreate(
                [
                    'id' => $message_id,
                ],
                [
                    'user_id'   => $user->id,
                    'input'     => $prompt,
                    'hash'      => str()->random(256),
                    'team_id'   => $user->team_id,
                    'slug'      => str()->random(7) . str($user?->fullName())->slug() . '-workbook',
                    'openai_id' => $openai_id ?? 1,
                ]);

            echo "event: message\n";
            echo 'data: ' . $message_id . "\n\n";

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $client = new Client;

            ApiHelper::setDeepseekKey();

            $url = 'https://api.deepseek.com/chat/completions';
            $apikey = config('deepseek.api_key');
            $headers = [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => "Bearer $apikey",
            ];

            $body = [
                'messages'          => $history,
                'model'             => $chat_bot,
                'max_tokens'        => max(8000, (int) setting('deepseek_max_output_length', 8000)),
                'response_format'   => [
                    'type' => 'text',
                ],
                'stop'           => null,
                'stream'         => true,
                'stream_options' => null,
                'temperature'    => 1,
                'top_p'          => 1,
                'tools'          => null,
                'tool_choice'    => 'none',
                'logprobs'       => false,
                'top_logprobs'   => null,
            ];

            $response = $client->post($url, [
                'headers' => $headers,
                'json'    => $body,
            ]);

            $bodyStream = $response->getBody();
            $buffer = '';
            $emptyLinesAdded = false;
            while (! $bodyStream->eof()) {
                $chunk = $bodyStream->read(1024);
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (str_starts_with(trim($line), 'data: ')) {
                        $json = trim(substr($line, 5)); // Remove "data: "
                        if (! empty($json)) {
                            $decoded = json_decode($json, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $delta = $decoded['choices'][0]['delta'] ?? [];
                                if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== null) {
                                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $delta['reasoning_content']);
                                    $output .= $messageFix;
                                    $responsedText .= $messageFix;
                                    // $total_used_tokens += countWords($messageFix); do we calculate reasoning content?
                                    echo PHP_EOL;
                                    echo "event: data\n";
                                    echo 'data: ' . $messageFix;
                                    echo "\n\n";
                                    $this->safeFlush();
                                }

                                if (isset($delta['content']) && $delta['content'] !== null) {
                                    if (! $emptyLinesAdded) {
                                        echo "event: data\n";
                                        echo 'data: <br/><br/><br/>';
                                        echo "\n\n";
                                        $this->safeFlush();
                                        $emptyLinesAdded = true;
                                    }
                                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $delta['content']);

                                    $output .= $messageFix;
                                    $responsedText .= $messageFix;
                                    $total_used_tokens += countWords($messageFix);

                                    echo "event: data\n";
                                    echo 'data: ' . $messageFix;
                                    echo "\n\n";
                                    $this->safeFlush();
                                }
                            }
                        }
                    }
                }

                if (connection_aborted()) {
                    break;
                }
            }

            echo "event: stop\n";
            echo 'data: [DONE]';
            echo "\n\n";
            $this->safeFlush();

            $entry->update([
                'title'    => $title ?: null,
                'credits'  => $total_used_tokens,
                'words'    => $total_used_tokens,
                'response' => $responsedText,
                'output'   => $output,
            ]);

            $driver->input($responsedText)
                ->calculateCredit()
                ->decreaseCredit();
            Usage::getSingle()->updateWordCounts($driver->calculate());

        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    public function assistantStream(string $chat_bot, $history, $main_message, $assistant): ?StreamedResponse
    {
        $chat = UserOpenaiChat::query()->where('id', $main_message->user_openai_chat_id)->first();
        $threadId = $chat?->thread_id;
        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));

        $assistantService = new AssistantService;

        $tmp = $assistantService->createMessage($threadId, $history);

        return $assistantService->createRun($chat_bot, $assistant, $threadId, $main_message, $driver);
    }

    public function OtherStream(Request $request, string $chat_bot, $ai_engine = null): StreamedResponse
    {
        if (! $ai_engine) {
            $ai_engine = setting('default_ai_engine', EngineEnum::OPEN_AI->value);
        }

        if ($chat_bot === EntityEnum::AZURE_OPENAI->slug() && MarketplaceHelper::isRegistered('azure-openai')) {
            return AzureOpenaiService::azureOpenaiOtherStream($request, $chat_bot);
        }

        if (setting('open_router_status') == 1 && $request->open_router_model !== 'undefined' && ! empty($request->open_router_model)) {
            return $this->openRouterStream($request);
        }

        return match ($ai_engine) {
            EngineEnum::ANTHROPIC->value => $this->anthropicOtherStream($request, $chat_bot),
            EngineEnum::GEMINI->value    => $this->geminiOtherStream($request, $chat_bot),
            EngineEnum::DEEP_SEEK->value => $this->deepseekOtherStream($request, $chat_bot),
            EngineEnum::X_AI->value 	    => $this->xAiOtherStream($request, $chat_bot),
            default                      => $this->openaiOtherStream($request, $chat_bot),
        };
    }

    private function openRouterStream(Request $request)
    {
        $prompt = $request->get('prompt');
        $message_id = $request->get('message_id');
        $openai_id = $request->get('openai_id');
        $title = $request->get('title');
        $open_router_model = $request->get('open_router_model');
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';
        $driver = $this->createDriver(EntityEnum::fromSlug($open_router_model));

        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($driver, &$total_used_tokens, &$output, &$responsedText, $message_id, $title, $openai_id, $prompt, $open_router_model) {
            $user = Auth::user();
            $entry = UserOpenai::find($message_id);
            if (! $entry) {
                $entry = new UserOpenai;
                $entry->user_id = $user?->id;
                $entry->input = $prompt;
                $entry->hash = str()->random(256);
                $entry->team_id = $user?->team_id;
                $entry->slug = str()->random(7) . str($user?->fullName())->slug() . '-workbook';
                $entry->openai_id = $openai_id ?? 1;
            }
            echo "event: message\n";
            echo 'data: ' . $message_id . "\n\n";

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $service = new RouterAiService;
            $response = $service->response($entry->input, $open_router_model);

            foreach (explode("\n", $response) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        break;
                    }

                    $json = json_decode($data, true);

                    if (isset($json['choices'][0]['delta']['content'])) {
                        $content = $json['choices'][0]['delta']['content'];

                        // Boş içerik varsa atla
                        if (! empty($content)) {
                            $output .= $content;
                            $responsedText .= $content;
                            $total_used_tokens += str_word_count($content);

                            $content = str_replace(["\r\n", "\r", "\n"], '<br/>', $content);

                            echo PHP_EOL;
                            echo "event: data\n";
                            echo 'data: ' . $content;
                            echo "\n\n";
                            $this->safeFlush();

                            if (connection_aborted()) {
                                break;
                            }
                        }
                    }
                }
            }

            echo "event: stop\n";
            echo 'data: [DONE]';
            echo "\n\n";
            $this->safeFlush();

            $this->saveOtherStreamResponse($entry, $title, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    public function reduceTokensWhenIntterruptStream(Request $request, $type): void
    {
        $model = Helper::setting('openai_default_model') ?: EntityEnum::GPT_5_MINI->value;
        $streamed_text = $request->get('streamed_text');
        $streamed_text = preg_replace('/search_images\s*\(\s*\{[^}]*\}\s*\)/', '', $streamed_text);
        $message_id = $request->get('streamed_message_id');
        if ($streamed_text) {
            $total_used_tokens = countWords($streamed_text);
            $this->createDriver(EntityEnum::fromSlug($model))->input($streamed_text)->calculateCredit()->decreaseCredit();
            if (! empty($message_id)) {
                if ($type === 'writer') {
                    $entry = UserOpenai::find($message_id);
                    if ($entry) {
                        $entry->title = null;
                        $entry->credits = $total_used_tokens;
                        $entry->words = $total_used_tokens;
                        $entry->response = $streamed_text;
                        $entry->output = $streamed_text;
                        $entry->save();
                    }
                } else { // chat
                    $main_message = UserOpenaiChatMessage::find($message_id);
                    if ($main_message) {
                        $chat = UserOpenaiChat::find($main_message->user_openai_chat_id);
                        $main_message->response = $streamed_text;
                        $main_message->output = $streamed_text;
                        $main_message->credits = $total_used_tokens;
                        $main_message->words = $total_used_tokens;
                        $main_message->save();

                        if ($chat) {
                            $chat->total_credits += $total_used_tokens;
                            $chat->save();
                        }
                    }
                }
            }
        }
    }

    // X-AI Stream
    /**
     * @throws Exception
     */
    private function xAiChatStream(string $chat_bot, $history, $main_message, $chat_type, $contain_images): ?StreamedResponse
    {
        if ($contain_images) {
            return $this->streamInputError(
                $main_message,
                __('The selected xAI model cannot process image attachments through its configured provider. Choose a vision-capable model.'),
                'model_image_unsupported'
            );
        }

        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';

        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));
        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($driver, $history, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images) {
            $chat_id = $main_message->user_openai_chat_id;
            $chat = UserOpenaiChat::whereId($chat_id)->first();

            echo "event: message\n";
            echo 'data: ' . $main_message->id . "\n\n";

            // Emit "Used X Skill" as the very first event
            if (! empty($this->usedSkills)) {
                $this->emitUsedSkills();
            }

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $model = $driver->enum()->value;
            if ($contain_images) {
                $options = OpenAiParamHelper::sanitizeChatParams([
                    'model'                       => EntityEnum::GPT_5_MINI->value,
                    'messages'                    => $history,
                    'temperature'                 => 1.0,
                    'stream'                      => true,
                    'max_output_tokens'           => 2000,
                ]);
                $stream = OpenAI::chat()->createStreamed($options);
            } else {
                $api = ApiHelper::setXAiKey();

                try {
                    $cli = OpenAIMain::factory()->withBaseUri('https://api.x.ai/v1')
                        ->withHttpHeader('Authorization', 'Bearer ' . $api)
                        ->withApiKey($api)
                        ->make();
                    $stream = $cli->chat()->createStreamed([
                        'model'             => $model,
                        'messages'          => $history,
                        'stream'            => true,
                        'temperature'       => 1.0,
                    ]);
                } catch (Exception|Throwable $e) {
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . __('Something went wrong. Please try again later.');
                    echo "\n\n";
                    $this->safeFlush();
                    echo "event: stop\n";
                    echo 'data: [DONE]';
                    echo "\n\n";
                    $this->safeFlush();

                    return null;
                }
            }

            foreach ($stream as $response) {
                if (isset($response->choices[0]->delta->content)) {
                    $text = $response->choices[0]->delta->content;
                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                    $output .= $messageFix;
                    $responsedText .= $text;
                    $total_used_tokens += countWords($text);
                    if (connection_aborted()) {
                        break;
                    }
                    $this->emitStreamChunk($messageFix);
                }
            }
            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function xAiOtherStream(Request $request, $chat_bot): ?StreamedResponse
    {
        $apiKey = ApiHelper::setXAiKey();
        $xai = OpenAIMain::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('https://api.x.ai/v1')
            ->make();

        $prompt = $request->get('prompt');
        $message_id = $request->get('message_id');
        $openai_id = $request->get('openai_id');
        $title = $request->get('title');

        $history[] = ['role' => 'user', 'content' => $prompt];
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';
        $user = Auth::user();
        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));

        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($user, $driver, $history, &$total_used_tokens, &$output, &$responsedText, $message_id, $title, $openai_id, $prompt) {
            $entry = UserOpenai::find($message_id);
            if (! $entry) {
                $entry = new UserOpenai;
                $entry->user_id = $user->id;
                $entry->input = $prompt;
                $entry->hash = str()->random(256);
                $entry->team_id = $user->team_id;
                $entry->slug = str()->random(7) . str($user?->fullName())->slug() . '-workbook';
                $entry->openai_id = $openai_id ?? 1;
            }

            echo "event: message\n";
            echo 'data: ' . $message_id . "\n\n";

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $api = ApiHelper::setXAiKey();

            try {
                $cli = OpenAIMain::factory()->withBaseUri('https://api.x.ai/v1')
                    ->withHttpHeader('Authorization', 'Bearer ' . $api)
                    ->withApiKey($api)
                    ->make();
                $stream = $cli->chat()->createStreamed([
                    'model'             => $driver->enum()->value,
                    'messages'          => $history,
                    'stream'            => true,
                    'temperature'       => 1.0,
                ]);
            } catch (Exception|Throwable $e) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('Something went wrong. Please try again later.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            foreach ($stream as $response) {
                if (isset($response->choices[0]->delta->content)) {
                    $text = $response->choices[0]->delta->content;
                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                    $output .= $messageFix;
                    $responsedText .= $text;
                    $total_used_tokens += countWords($text);
                    if (connection_aborted()) {
                        break;
                    }
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . $messageFix;
                    echo "\n\n";
                    $this->safeFlush();
                }
            }
            echo "event: stop\n";
            echo 'data: [DONE]';
            echo "\n\n";
            $this->safeFlush();

            $this->saveOtherStreamResponse($entry, $title, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    // OpenAI Stream
    /**
     * Resolve the configured reasoning effort against the model's supported values,
     * downgrading to the closest accepted effort when the configured value isn't allowed
     * (e.g. 'none' on GPT-5 original or O-series, 'minimal' on O-series).
     */
    private function resolveReasoningEffort(EntityEnum $modelEnum): string
    {
        $requested = strtolower(trim((string) request()?->input('reasoning_level', '')));
        $requested = match ($requested) {
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'max' => 'xhigh',
            default => (string) setting('openai_reasoning_models_effort', 'medium'),
        };

        return $modelEnum->resolveReasoningEffort($requested);
    }

    /**
     * @throws Exception
     */
    private function openaiChatStream(string $chat_bot, $history, $main_message, $chat_type, $contain_images, ?array $tools = []): ?StreamedResponse
    {
        // When manual skills are already injected, remove auto-skill function tools — they're not needed
        if (! empty($this->usedSkills)) {
            $tools = array_filter($tools ?? [], fn ($t) => ! str_starts_with($t['name'] ?? '', 'use_skill_'));
            $tools = array_values($tools);
        }

        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';

        if ($contain_images) {
            $driver = $this->createDriver(EntityEnum::GPT_5_MINI);
        } else {
            if ($tools && EntityEnum::fromSlug($chat_bot) === EntityEnum::GPT_5_CHAT) {
                $chat_bot = EntityEnum::GPT_5->slug();
            }

            $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));
        }
        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($driver, $history, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images, $tools, $chat_type) {
            try {
                $this->runOpenaiChatStream($driver, $history, $total_used_tokens, $output, $responsedText, $main_message, $contain_images, $tools, $chat_type);
            } catch (Throwable $e) {
                Log::error('OpenAI chat stream failed: ' . $e->getMessage(), ['exception' => $e]);
                echo "event: error\n";
                echo 'data: ' . json_encode([
                    'type'    => 'error',
                    'message' => 'The model provider failed before the response completed: ' . $e->getMessage(),
                    'code'    => 'provider_stream_failed',
                    'status'  => 502,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                echo "event: stop\n";
                echo "data: [DONE]\n\n";
                $this->safeFlush();
            }
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function runOpenaiChatStream($driver, $history, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images, $tools, $chat_type): void
    {
        $__unused = (function () use ($driver, $history, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images, $tools, $chat_type) {

            $chat_id = $main_message->user_openai_chat_id;
            $chat = UserOpenaiChat::whereId($chat_id)->first();

            echo "event: message\n";
            echo 'data: ' . $main_message->id . "\n\n";

            // Emit "Used X Skill" as the very first event
            if (! empty($this->usedSkills)) {
                $this->emitUsedSkills();
            }

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $model = $driver->enum()->value;
            $isSearchModel = in_array($model, [EntityEnum::GPT_4_O_MINI_SEARCH_PREVIEW->value, EntityEnum::GPT_4_O_SEARCH_PREVIEW->value], true);

            if ($isSearchModel) {
                // Use chat() endpoint for search models
                $options = [
                    'model'    => $model,
                    'messages' => $history,
                    'stream'   => true,
                ];

                if ($contain_images) {
                    $options['max_tokens'] = 2000;
                    $options['model'] = EntityEnum::GPT_5_MINI->value;
                }

                $options = OpenAiParamHelper::sanitizeChatParams($options);
                $options = $this->prepareChatProProviderPayload($options);
                $stream = OpenAI::chat()->createStreamed($options);

                foreach ($stream as $response) {
                    if (connection_aborted()) {
                        break;
                    }

                    // Handle regular content
                    if (isset($response->choices[0]->delta->content)) {
                        $text = $response->choices[0]->delta->content;
                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                        $output .= $messageFix;
                        $responsedText .= $text;
                        $total_used_tokens += countWords($text);
                        $this->emitStreamChunk($messageFix);
                    }
                }
            } else {
                // Use responses() endpoint for non-search models
                $options = [
                    'model'  => $model,
                    'stream' => true,
                ];

                // The user's selected model is kept for image requests as well; every OpenAI model in
                // the selector accepts image input, and a fixed output cap would starve reasoning models.
                // Responses API `instructions` field so they get top-level priority.
                $skillParts = [];
                $otherSystemParts = [];
                $nonSystemHistory = [];
                foreach ($history as $msg) {
                    if (($msg['role'] ?? '') === 'system') {
                        $content = $msg['content'] ?? '';
                        if (str_starts_with($content, '[Skill:')) {
                            $skillParts[] = $content;
                        } else {
                            $otherSystemParts[] = $content;
                        }
                    } else {
                        $nonSystemHistory[] = $msg;
                    }
                }

                $preferredChatProTool = null;
                if ($chat_type === 'chatPro') {
                    $otherSystemParts[] = AiChatProService::systemInstruction();
                    $preferredChatProTool = AiChatProService::preferredToolForPrompt((string) ($main_message->input ?? ''));
                }

                if (! empty($tools) && (empty($skillParts) || $preferredChatProTool)) {
                    // Manual skills normally suppress auto tools, but an explicit Chat Pro
                    // creation request must still be able to produce the requested file/media.
                    $options['tools'] = $tools;
                    $options['tool_choice'] = 'auto';
                    $argumentsString = '';

                    // GARAD_SAFE_V4_FORCE_TOOL_START
                    if ($chat_type === 'chatPro') {
                        $garadSafeV4Selection = strtolower(trim((string) request()?->input('garad_selected_tool', '')));
                        $garadSafeV4Tool = match ($garadSafeV4Selection) {
                            'image' => 'generate_image',
                            'video' => 'generate_video',
                            'pdf' => 'create_pdf',
                            'word', 'docx' => 'create_docx',
                            'excel', 'xlsx' => 'create_xlsx',
                            'slides', 'presentation', 'ppt', 'pptx' => 'create_presentation',
                            default => null,
                        };
                        if (! $garadSafeV4Tool && method_exists(AiChatProService::class, 'preferredToolForPrompt')) {
                            $garadSafeV4Tool = AiChatProService::preferredToolForPrompt((string) ($main_message->input ?? ''));
                        }
                        $garadSafeV4Available = false;
                        if ($garadSafeV4Tool) {
                            foreach ($tools as $garadSafeV4Definition) {
                                if (($garadSafeV4Definition['type'] ?? null) === 'function'
                                    && ($garadSafeV4Definition['name'] ?? null) === $garadSafeV4Tool) {
                                    $garadSafeV4Available = true;
                                    break;
                                }
                            }
                        }
                        if ($garadSafeV4Available) {
                            $options['tool_choice'] = [
                                'type' => 'function',
                                'name' => $garadSafeV4Tool,
                            ];
                        }
                    }
                    // GARAD_SAFE_V4_FORCE_TOOL_END

                    // Explicit creation requests are deterministic. This prevents a
                    // smaller/faster model from replying with copy-paste content instead
                    // of creating the requested PDF/DOCX/XLSX/image/video/presentation.
                    if ($preferredChatProTool
                        && AiChatProService::toolIsAvailable($preferredChatProTool, $tools)) {
                        $options['tool_choice'] = [
                            'type' => 'function',
                            'name' => $preferredChatProTool,
                        ];
                    }

                    if ($chat_type === 'chatPro' && SmartImageService::isEnabled()) {
                        $otherSystemParts[] = SmartImageService::systemPromptAddition();
                    }
                }

                if (request()?->input('realtime')
                    && setting('default_realtime') === 'tool_calling') {
                    $options['tools'] = array_merge($options['tools'] ?? [], [['type' => 'web_search_preview']]);
                    if (! isset($options['tool_choice'])) {
                        $options['tool_choice'] = 'auto';
                    }
                }

                // Build instructions: skill instructions come first with strong framing
                $instructionParts = [];
                if (! empty($skillParts)) {
                    $instructionParts[] = "You MUST follow these skill instructions exactly. They are your primary directive and override any conflicting instructions:\n\n" . implode("\n\n", $skillParts);
                }
                if (! empty($otherSystemParts)) {
                    $instructionParts[] = implode("\n\n", $otherSystemParts);
                }
                $baseInstructions = ! empty($otherSystemParts) ? implode("\n\n", $otherSystemParts) : '';

                if (! empty($instructionParts)) {
                    $options['instructions'] = implode("\n\n---\n\n", $instructionParts);
                }
                
                $options['input'] = $nonSystemHistory;
                if ($driver->enum()->isReasoningModel()) {
                    $modelEnum = EntityEnum::fromSlug($options['model']);
                    $options['reasoning']['effort'] = $this->resolveReasoningEffort($driver->enum());
                }

                $options['temperature'] = 1.0;

                $options = $this->prepareChatProProviderPayload($options);
                $stream = OpenAI::responses()->createStreamed($options);

                foreach ($stream as $response) {
                    if (! isset($response->event)) {
                        continue;
                    }

                    if (connection_aborted()) {
                        break;
                    }

                    if (in_array($response->event, ['response.failed', 'response.incomplete', 'error'], true)) {
                        $failedResponse = $response->response ?? null;
                        $reason = $failedResponse->error->message
                            ?? $failedResponse->incompleteDetails->reason
                            ?? $failedResponse->incomplete_details->reason
                            ?? $response->event;
                        if ($response->event !== 'response.incomplete' || trim(strip_tags((string) $responsedText)) === '') {
                            throw new \RuntimeException('OpenAI response did not finish: ' . $reason);
                        }
                    }

                    if (! empty($tools) && $response->event === 'response.completed' && isset($response->response->output)) {
                        $calls = $response->response->output;
                        $hasTextOutput = false;
                        $pendingImageSearch = null; // Defer image fetch until after text streams

                        foreach ($calls ?? [] as $call) {
                            if ($call instanceof OutputMessage) {
                                $hasTextOutput = true;
                            }

                            if ($call instanceof OutputFunctionToolCall) {
                                $functionName = (string) ($call?->name ?? '');
                                $argumentsString = $call?->arguments;

                                if (str_starts_with($functionName, 'connector_') && class_exists(ConnectorToolService::class)) {
                                    $connectorService = app(ConnectorToolService::class);
                                    $connectorArgs = json_decode($argumentsString ?: '{}', true) ?: [];

                                    if (Helper::appIsDemo()) {
                                        $connectorResult = ['error' => 'This feature is disabled in demo mode.'];
                                    } else {
                                        try {
                                            $connectorResult = $connectorService->handle($functionName, $connectorArgs, $this->autoConnectors) ?? ['error' => 'Connector not available.'];
                                        } catch (Throwable $e) {
                                            Log::error('[connectors] StreamService(OpenAI): tool call threw', [
                                                'function' => $functionName,
                                                'message'  => $e->getMessage(),
                                            ]);
                                            $connectorResult = ['error' => $e->getMessage()];
                                        }
                                    }

                                    $connectorMeta = $connectorService->getMeta($functionName, $this->autoConnectors);
                                    if ($connectorMeta) {
                                        $this->usedConnectors[] = $connectorMeta;
                                        $this->emitUsedConnectors();
                                    }

                                    $resultJson = json_encode($connectorResult, JSON_UNESCAPED_SLASHES);
                                    $history[] = [
                                        'role'    => 'system',
                                        'content' => '[Connector: ' . ($connectorMeta['name'] ?? $functionName) . "]\nThe user called this connector. Use the data below to answer their question. Do not invent facts that are not in the data.\n\nResult:\n" . $resultJson,
                                    ];
                                    $history = array_values(array_filter($history, function ($msg) {
                                        return ! str_contains($msg['content'] ?? '', '{"title":"short descriptive title');
                                    }));
                                    $this->isFirstMessage = false;

                                    $connectorFollowUpParts = [];
                                    if ($baseInstructions !== '') {
                                        $connectorFollowUpParts[] = $baseInstructions;
                                    }
                                    $connectorFollowUpParts[] = '[Connector: ' . ($connectorMeta['name'] ?? $functionName) . "]\n"
                                        . "The user invoked this connector. Use ONLY the JSON result below to answer their question — do not invent data, and do not say the data is unavailable. Summarize clearly. Do not call any tools.\n\n"
                                        . "Result JSON:\n" . $resultJson;
                                    $connectorFollowUpInstructions = implode("\n\n---\n\n", $connectorFollowUpParts);

                                    $connectorNonSystemInput = array_values(array_filter($history, fn ($msg) => ($msg['role'] ?? '') !== 'system'));

                                    $connectorOptions = [
                                        'model'        => $model,
                                        'stream'       => true,
                                        'instructions' => $connectorFollowUpInstructions,
                                        'input'        => $connectorNonSystemInput,
                                        'temperature'  => 1.0,
                                    ];
                                    if ($driver->enum()->isReasoningModel()) {
                                        $connectorOptions['reasoning']['effort'] = $this->resolveReasoningEffort($driver->enum());
                                    }

                                    $connectorOptions = $this->prepareChatProProviderPayload($connectorOptions);
                                    $connectorStream = OpenAI::responses()->createStreamed($connectorOptions);

                                    foreach ($connectorStream as $connectorResponse) {
                                        if (! isset($connectorResponse->event)) {
                                            continue;
                                        }
                                        if (connection_aborted()) {
                                            break;
                                        }
                                        if (isset($connectorResponse->response->delta) && $connectorResponse->event === 'response.output_text.delta') {
                                            $text = $connectorResponse->response->delta;
                                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                                            $output .= $messageFix;
                                            $responsedText .= $text;
                                            $total_used_tokens += countWords($text);
                                            $this->emitStreamChunk($messageFix);
                                        }
                                    }

                                    $hasTextOutput = true;

                                    continue;
                                }

                                // Skill tool calls: inject instructions and stream a second response
                                if (str_starts_with($functionName, 'use_skill_') && class_exists(SkillToolService::class)) {
                                    $skillInstructions = SkillToolService::handleSkillCall($functionName, $this->autoSkills);
                                    $skillMeta = SkillToolService::getSkillMeta($functionName, $this->autoSkills);

                                    if ($skillMeta) {
                                        $this->usedSkills[] = $skillMeta;
                                        $this->emitUsedSkills();
                                    }

                                    if ($skillInstructions) {
                                        $cleanInstructions = rtrim($skillInstructions, " \t\n\r-");
                                        $history[] = ['role' => 'system', 'content' => '[Skill: ' . ($skillMeta['name'] ?? '') . "]\n{$cleanInstructions}"];

                                        // Remove title instruction — skill responses shouldn't include title JSON
                                        $history = array_values(array_filter($history, function ($msg) {
                                            return ! str_contains($msg['content'] ?? '', '{"title":"short descriptive title');
                                        }));
                                        $this->isFirstMessage = false;

                                        // Stream a second response with skill instructions injected
                                        // Build instructions: base chat instructions + skill instructions
                                        $skillFollowUpParts = [];
                                        if ($baseInstructions !== '') {
                                            $skillFollowUpParts[] = $baseInstructions;
                                        }
                                        $skillFollowUpParts[] = "You MUST follow these skill instructions exactly. They are your primary directive and override any conflicting instructions:\n\n[Skill: " . ($skillMeta['name'] ?? '') . "]\n{$cleanInstructions}";
                                        $skillFollowUpInstructions = implode("\n\n---\n\n", $skillFollowUpParts);

                                        // Separate non-system messages for input (system messages go in instructions)
                                        $skillNonSystemInput = array_values(array_filter($history, fn ($msg) => ($msg['role'] ?? '') !== 'system'));

                                        $skillOptions = [
                                            'model'        => $model,
                                            'stream'       => true,
                                            'instructions' => $skillFollowUpInstructions,
                                            'input'        => $skillNonSystemInput,
                                            'temperature'  => 1.0,
                                        ];
                                        if ($driver->enum()->isReasoningModel()) {
                                            $skillOptions['reasoning']['effort'] = $this->resolveReasoningEffort($driver->enum());
                                        }

                                        $skillOptions = $this->prepareChatProProviderPayload($skillOptions);
                                        $skillStream = OpenAI::responses()->createStreamed($skillOptions);

                                        foreach ($skillStream as $skillResponse) {
                                            if (! isset($skillResponse->event)) {
                                                continue;
                                            }
                                            if (connection_aborted()) {
                                                break;
                                            }
                                            if (isset($skillResponse->response->delta) && $skillResponse->event === 'response.output_text.delta') {
                                                $text = $skillResponse->response->delta;
                                                $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                                                $output .= $messageFix;
                                                $responsedText .= $text;
                                                $total_used_tokens += countWords($text);
                                                $this->emitStreamChunk($messageFix);
                                            }
                                        }

                                        $hasTextOutput = true;
                                    }

                                    continue;
                                }

                                // For search_images: defer the actual fetch, just notify frontend
                                if ($functionName === 'search_images') {
                                    echo PHP_EOL;
                                    echo "event: function_call\n";
                                    echo 'data: search_images' . "\n\n";
                                    $this->safeFlush();

                                    // Save for later — fetch images AFTER text starts streaming
                                    $pendingImageSearch = [
                                        'functionName'    => $functionName,
                                        'argumentsString' => $argumentsString,
                                        'chat_type'       => $chat_type,
                                    ];
                                } else {
                                    // Non-image tool calls: execute immediately. Chat Pro emits a visible
                                    // status event before the potentially slow provider/file work starts.
                                    $functionResponse = null;
                                    if ($chat_type === 'chatPro') {
                                        echo PHP_EOL;
                                        echo "event: tool_status\n";
                                        echo 'data: ' . json_encode(AiChatProService::progressPayload($functionName, 'working'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                                        $this->safeFlush();

                                        $functionResponse = AiChatProService::callFunction($functionName, $argumentsString);
                                    } elseif ($chat_type === 'socialMediaAgent') {
                                        $functionResponse = SocialMediaAgentChatService::callFunction($functionName, $argumentsString);
                                    }

                                    if (isset($functionResponse)) {
                                        if ($chat_type === 'chatPro') {
                                            $pendingApprovalId = $this->takeAgentPendingApprovalId();
                                            $toolExecution = AiChatProService::toolExecutionPayload(
                                                $functionName,
                                                (string) $functionResponse,
                                                request()->boolean('agent_mode'),
                                                $pendingApprovalId,
                                            );
                                            $toolState = $toolExecution['state'];
                                            $toolStatusPayload = AiChatProService::progressPayload($functionName, $toolState);
                                            if (isset($toolExecution['approvalId'])) {
                                                $toolStatusPayload['approvalId'] = $toolExecution['approvalId'];
                                            }
                                            echo PHP_EOL;
                                            echo "event: tool_status\n";
                                            echo 'data: ' . json_encode($toolStatusPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                                            $this->safeFlush();
                                        }
                                        if ($chat_type === 'chatPro' && $this->stopAfterFailedArtifactTool(
                                            $functionName,
                                            $toolState,
                                            $main_message,
                                            $chat,
                                            $responsedText,
                                            $output,
                                            $total_used_tokens,
                                            $driver,
                                        )) {
                                            return;
                                        }
                                        if ($chat_type === 'chatPro') {
                                            $this->emitCreatedArtifactEvent();
                                        }
                                        // When generating a social post, clear any AI conversational text
                                        // that was streamed before the tool call so only the post card shows.
                                        if ($functionName === 'generate_social_post') {
                                            $output = '';
                                            $responsedText = '';
                                            echo PHP_EOL;
                                            echo "event: clear_content\n";
                                            echo "data: \n\n";
                                            $this->safeFlush();
                                        }

                                        if (AiChatProService::shouldExposeToolResult($functionName, $toolState)) {
                                            // Keep tool responses valid inside SSE. Raw newlines break a single data payload.
                                            $functionResponseForStream = str_replace(
                                                ["\r\n", "\r", "\n"],
                                                '<br/>',
                                                $functionResponse
                                            );

                                            $output .= $functionResponseForStream;

                                            echo PHP_EOL;
                                            echo "event: data\n";
                                            echo 'data: ' . $functionResponseForStream;
                                            echo "\n\n";

                                            $this->safeFlush();
                                            $hasTextOutput = true;
                                        }
                                        if ($functionName === 'run_agent' && $toolState === 'failed') {
                                            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
                                            $this->emitAgentExecutionError((string) $functionResponse);

                                            return;
                                        }
                                    }
                                }
                            }
                        }

                        // Send image search query to frontend IMMEDIATELY for parallel async fetch
                        // Frontend fetches images independently while text streams below
                        if ($pendingImageSearch) {
                            $searchArgs = json_decode($pendingImageSearch['argumentsString'], true);
                            $searchQuery = $searchArgs['query'] ?? '';
                            if ($searchQuery !== '') {
                                echo PHP_EOL;
                                echo "event: smart_image_search\n";
                                echo 'data: ' . json_encode([
                                    'query'      => $searchQuery,
                                    'message_id' => $main_message->id ?? null,
                                ]) . "\n\n";
                                $this->safeFlush();
                            }
                        }

                        // Now start text streaming
                        if (! $hasTextOutput) {
                            $fallbackOptions = [
                                'model'        => $model,
                                'stream'       => true,
                                'input'        => $nonSystemHistory,
                                'temperature'  => 1.0,
                            ];
                            // Pass tools with tool_choice=none so the model knows tools exist
                            // but won't try to call them again. Without this, the model leaks
                            // tool-routing tokens (e.g. "to=search_images …") as visible text
                            // because the instructions still describe the tool but no tools
                            // are advertised on this request.
                            $fallbackOptions['tools'] = $tools;
                            $fallbackOptions['tool_choice'] = 'none';
                            if ($baseInstructions !== '') {
                                $fallbackOptions['instructions'] = $baseInstructions;
                            }
                            if ($driver->enum()->isReasoningModel()) {
                                $fallbackOptions['reasoning']['effort'] = $this->resolveReasoningEffort($driver->enum());
                            }

                            $fallbackOptions = $this->prepareChatProProviderPayload($fallbackOptions);
                            $fallbackStream = OpenAI::responses()->createStreamed($fallbackOptions);

                            foreach ($fallbackStream as $fallbackResponse) {
                                if (! isset($fallbackResponse->event)) {
                                    continue;
                                }

                                if (connection_aborted()) {
                                    break;
                                }

                                if (isset($fallbackResponse->response->delta) && $fallbackResponse->event === 'response.output_text.delta') {
                                    $text = $fallbackResponse->response->delta;
                                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                                    $output .= $messageFix;
                                    $responsedText .= $text;
                                    $total_used_tokens += countWords($text);
                                    $this->emitStreamChunk($messageFix);
                                }
                            }
                        }
                    }
                    if ((isset($response->response->delta) && $response->event === 'response.output_text.delta')) {
                        $text = $response->response->delta;
                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                        $output .= $messageFix;
                        $responsedText .= $text;
                        $total_used_tokens += countWords($text);
                        $this->emitStreamChunk($messageFix);
                    }
                }
            }

            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
        })();
    }

    /**
     * @throws Exception
     */
    private function openaiFileChat(string $chat_bot, $history, $main_message, $chat_type, $contain_images): ?StreamedResponse
    {
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';

        if ($contain_images) {
            $driver = $this->createDriver(EntityEnum::GPT_5_MINI);
        } else {
            $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));
        }
        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($driver, $history, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images) {
            $chat_id = $main_message->user_openai_chat_id;
            $chat = UserOpenaiChat::whereId($chat_id)->first();

            echo "event: message\n";
            echo 'data: ' . $main_message->id . "\n\n";

            // Emit "Used X Skill" as the very first event
            if (! empty($this->usedSkills)) {
                $this->emitUsedSkills();
            }

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $model = $driver->enum()->value;
            $options = [
                'model'             => $model,
                'input'             => $history,
                'stream'            => true,
            ];

            if (! in_array($model, [EntityEnum::GPT_4_O_MINI_SEARCH_PREVIEW->value, EntityEnum::GPT_4_O_SEARCH_PREVIEW->value], true)) {
                $options['temperature'] = 1.0;
            }

            if ($contain_images) {
                $options['max_output_tokens'] = 2000;
                $options['model'] = EntityEnum::GPT_5_MINI->value;
            } else {
                $vectorId = $chat?->openai_vector_id ?? '';
                if ($vectorId === '') {
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . __('File search is not ready. Please try attaching the document again.');
                    echo "\n\n";
                    $this->safeFlush();
                    echo "event: stop\n";
                    echo 'data: [DONE]';
                    echo "\n\n";
                    $this->safeFlush();

                    return null;
                }
                $options['tools'] = [
                    [
                        'type'             => 'file_search',
                        'vector_store_ids' => [$vectorId],
                        'max_num_results'  => 1,
                    ],
                ];
            }
            ApiHelper::setOpenAiKey();
            $stream = OpenAI::responses()->createStreamed($options);
            foreach ($stream as $response) {
                if (($response->event === 'response.output_text.delta') && isset($response->response->delta)) {
                    $text = $response->response->delta;
                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                    $output .= $messageFix;
                    $responsedText .= $text;
                    $total_used_tokens += countWords($text);
                    if (connection_aborted()) {
                        break;
                    }
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . $messageFix;
                    echo "\n\n";
                    $this->safeFlush();
                }
            }

            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function openaiOtherStream(Request $request, $chat_bot): ?StreamedResponse
    {
        $prompt = $request->get('prompt');
        if ($request->hasFile('attachments')) {

    foreach ($request->file('attachments') as $file) {

        $ext = strtolower($file->getClientOriginalExtension());

        $text = '';

        switch ($ext) {

            case 'txt':
                $text = file_get_contents($file->getRealPath());
                break;

            case 'pdf':
                $parser = new Parser();
                $pdf = $parser->parseFile($file->getRealPath());
                $text = $pdf->getText();
                break;

            case 'docx':

                $phpWord = IOFactory::load($file->getRealPath());

                foreach ($phpWord->getSections() as $section) {

                    foreach ($section->getElements() as $element) {

                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . "\n";
                        }

                    }

                }

                break;

        }

        if (!empty($text)) {

            $prompt .= "\n\n=========================\n";
            $prompt .= "Attached Document:\n";
            $prompt .= $text;
            $prompt .= "\n=========================\n";

        }

    }

}
        $message_id = $request->get('message_id');
        $openai_id = $request->get('openai_id');
        $title = $request->get('title');

        $history[] = ['role' => 'user', 'content' => $prompt];
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';
        $user = Auth::user();
        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));

        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($user, $driver, $history, &$total_used_tokens, &$output, &$responsedText, $message_id, $title, $openai_id, $prompt) {
            $entry = UserOpenai::find($message_id);
            if (! $entry) {
                $entry = new UserOpenai;
                $entry->user_id = $user->id;
                $entry->input = $prompt;
                $entry->hash = str()->random(256);
                $entry->team_id = $user->team_id;
                $entry->slug = str()->random(7) . str($user?->fullName())->slug() . '-workbook';
                $entry->openai_id = $openai_id ?? 1;
            }

            echo "event: message\n";
            echo 'data: ' . $message_id . "\n\n";

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $reasoningOptions = [];
            if ($driver->enum()->isReasoningModel()) {
                $reasoningOptions['reasoning']['effort'] = $this->resolveReasoningEffort($driver->enum());
            }

            $stream = OpenAI::responses()->createStreamed([
                'model'             => $driver->enum()->value,
                'input'             => $history,
                ...$reasoningOptions,
                ...(! in_array($driver->enum()->value, [EntityEnum::GPT_4_O_MINI_SEARCH_PREVIEW->value, EntityEnum::GPT_4_O_SEARCH_PREVIEW->value], true) ? [
                    'temperature' => 1.0,
                ] : []),
                'stream'            => true,
            ]);

            foreach ($stream as $response) {
                if (($response->event === 'response.output_text.delta') && isset($response->response->delta)) {
                    $text = $response->response->delta;
                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                    $output .= $messageFix;
                    $responsedText .= $text;
                    $total_used_tokens += countWords($text);
                    if (connection_aborted()) {
                        break;
                    }
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . $messageFix;
                    echo "\n\n";
                    $this->safeFlush();
                }
            }
            echo "event: stop\n";
            echo 'data: [DONE]';
            echo "\n\n";
            $this->safeFlush();

            $this->saveOtherStreamResponse($entry, $title, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function streamInputError($main_message, string $message, string $code): StreamedResponse
    {
        $messageId = $main_message->id;

        return response()->stream(function () use ($messageId, $message, $code) {
            echo "event: message\n";
            echo 'data: ' . $messageId . "\n\n";
            echo "event: error\n";
            echo 'data: ' . json_encode([
                'type' => 'error',
                'message' => $message,
                'code' => $code,
                'status' => 422,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "event: stop\n";
            echo "data: [DONE]\n\n";
            $this->safeFlush();
        }, 200, [
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
            'Content-Type' => 'text/event-stream',
        ]);
    }

    private function prepareChatProProviderPayload(array $payload): array
    {
        if (request()?->attributes->has('garad_provider_request_max')) {
            if (array_key_exists('messages', $payload)) {
                $payload['max_tokens'] = min(
                    \App\Services\Ai\ProviderRequestBudgetService::MAX_OUTPUT_TOKENS,
                    max(1, (int) ($payload['max_tokens'] ?? \App\Services\Ai\ProviderRequestBudgetService::MAX_OUTPUT_TOKENS)),
                );
            } else {
                $payload['max_output_tokens'] = min(
                    \App\Services\Ai\ProviderRequestBudgetService::MAX_OUTPUT_TOKENS,
                    max(1, (int) ($payload['max_output_tokens'] ?? \App\Services\Ai\ProviderRequestBudgetService::MAX_OUTPUT_TOKENS)),
                );
            }
        }

        $budget = app(\App\Services\Ai\ProviderRequestBudgetService::class);
        $payload = $budget->preparePayload($payload);
        $budget->recordProviderRequest();

        return $payload;
    }

    private function emitAgentExecutionError(string $message): void
    {
        echo "event: error\n";
        echo 'data: ' . json_encode([
            'type' => 'error',
            'message' => $message,
            'code' => 'agent_execution_failed',
            'status' => 500,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        $this->safeFlush();
    }

    private function stopAfterFailedArtifactTool(
        string $functionName,
        string $toolState,
        $mainMessage,
        $chat,
        string &$responsedText,
        string &$output,
        int &$totalUsedTokens,
        $driver,
    ): bool {
        $labels = [
            'create_pdf' => 'PDF',
            'create_docx' => 'Word document',
            'create_xlsx' => 'spreadsheet',
            'create_presentation' => 'presentation',
            'generate_image' => 'image',
            'generate_video' => 'video',
        ];

        if ($toolState !== 'failed' || ! isset($labels[$functionName])) {
            return false;
        }

        AiChatProService::takeCreatedArtifactPayload();
        $message = "I couldn't create the requested {$labels[$functionName]}. No file was created. Please retry.";
        $responsedText = $message;
        $output = $message;
        $totalUsedTokens = countWords($message);

        echo PHP_EOL;
        echo "event: clear_content\n";
        echo "data: \n\n";
        echo "event: data\n";
        echo 'data: ' . $message . "\n\n";
        $this->safeFlush();

        $this->saveStreamResponse(
            $mainMessage,
            $chat,
            $responsedText,
            $output,
            $totalUsedTokens,
            $driver,
        );

        echo "event: stop\n";
        echo "data: [DONE]\n\n";
        $this->safeFlush();

        return true;
    }

    private function emitCreatedArtifactEvent(): void
    {
        $artifact = AiChatProService::takeCreatedArtifactPayload();
        if ($artifact === null) {
            return;
        }

        echo PHP_EOL;
        echo "event: artifact\n";
        echo 'data: ' . json_encode(
            ['artifact' => $artifact],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n\n";
        $this->safeFlush();
    }

    private function anthropicMessagesWithImages(array $messages): array
    {
        return array_map(static function (array $message): array {
            if (! is_array($message['content'] ?? null)) {
                return $message;
            }

            $content = [];
            foreach ($message['content'] as $part) {
                if (! is_array($part)) {
                    continue;
                }

                if (($part['type'] ?? null) === 'input_text') {
                    $content[] = [
                        'type' => 'text',
                        'text' => (string) ($part['text'] ?? ''),
                    ];

                    continue;
                }

                if (($part['type'] ?? null) !== 'input_image') {
                    $content[] = $part;

                    continue;
                }

                $imageUrl = (string) ($part['image_url'] ?? '');
                if (! preg_match('#^data:(image/(?:jpeg|png|gif|webp));base64,([A-Za-z0-9+/=]+)$#i', $imageUrl, $matches)) {
                    throw new \InvalidArgumentException(__('The attached image format is not supported by the selected Anthropic model.'));
                }

                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => strtolower($matches[1]),
                        'data' => $matches[2],
                    ],
                ];
            }

            return [...$message, 'content' => $content];
        }, $messages);
    }

    /**
     * Read Anthropic SSE data lines without buffering the provider's full response.
     */
    private function anthropicStreamLines(PromiseInterface|Response $response): \Generator
    {
        if ($response instanceof PromiseInterface) {
            $response = $response->wait();
        }

        if ($response->failed()) {
            Log::error('Anthropic stream request was rejected', [
                'status' => $response->status(),
                'provider_error_type' => $response->json('error.type'),
                'provider_error_message' => $response->json('error.message'),
            ]);
        }
        $response->throw();
        $bodyStream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $bodyStream->eof()) {
            if (connection_aborted()) {
                $bodyStream->close();

                return;
            }

            $buffer .= $bodyStream->read(1024);

            while (($lineEnd = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $lineEnd));
                $buffer = substr($buffer, $lineEnd + 1);

                if (str_starts_with($line, 'data:')) {
                    $data = trim(substr($line, 5));
                    if ($data !== '' && $data !== '[DONE]') {
                        yield 'data: ' . $data;
                    }
                }
            }
        }

        $line = trim($buffer);
        if (str_starts_with($line, 'data:')) {
            $data = trim(substr($line, 5));
            if ($data !== '' && $data !== '[DONE]') {
                yield 'data: ' . $data;
            }
        }
    }

    // AnthropicService Stream
    private function anthropicChatStream(string $chat_bot, $history, $main_message, $chat_type, $contain_images): ?StreamedResponse
    {
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';
        $client = app(AnthropicService::class);
        $client->setModel($chat_bot);
        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));

        if ($chat_type === 'chatPro') {
            try {
                $history = $this->anthropicMessagesWithImages($history);
            } catch (\InvalidArgumentException $e) {
                return $this->streamInputError($main_message, $e->getMessage(), 'unsupported_image_format');
            }
        }

        $this->prepareStreamEnvironment();

        return new StreamedResponse(function () use ($driver, $client, $history, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images, $chat_type) {
            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }

            $chat_id = $main_message->user_openai_chat_id;
            $chat = UserOpenaiChat::whereId($chat_id)->first();

            echo "event: message\n";
            echo 'data: ' . $main_message->id . "\n\n";

            if ($chat_type === 'chatPro') {
                echo "event: progress\n";
                echo 'data: ' . json_encode(['message' => 'Sending request to Claude…'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                $this->safeFlush();
            }

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            if (! $contain_images || $chat_type === 'chatPro') {
                $historyMessages = array_filter($history, function ($item) {
                    return $item['role'] !== 'system';
                });
                $systemParts = array_map(
                    fn ($item) => $item['content'] ?? '',
                    array_filter($history, fn ($item) => ($item['role'] ?? '') === 'system')
                );
                $system = implode("\n\n", array_filter($systemParts)) ?: null;

                if ($chat_type !== 'chatPro' && setting('anthropic_default_model') === BedrockEngine::BEDROCK->value) {
                    $bedrockService = new BedrockRuntimeService([
                        'region'      => config('filesystems.disks.s3.region'),
                        'version'     => 'latest',
                        'credentials' => [
                            'key'    => config('filesystems.disks.s3.key'),
                            'secret' => config('filesystems.disks.s3.secret'),
                        ],
                    ]);
                    $responseBody = $bedrockService->invokeClaude($main_message->input);
                    $driver = $this->createDriver(EntityEnum::CLAUDE_2_1);
                    if (! $driver->hasCreditBalance()) {
                        echo PHP_EOL;
                        echo "event: data\n";
                        echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                        echo "\n\n";
                        $this->safeFlush();
                        echo "event: stop\n";
                        echo 'data: [DONE]';
                        echo "\n\n";
                        $this->safeFlush();

                        return null;
                    }

                    if ($responseBody) {
                        $response = $this->anthropicBedrockResponse($responseBody);
                        $output = $response['output'];
                        $responsedText = $response['responsedText'];
                        $total_used_tokens = $response['total_used_tokens'];
                    }
                } else {
                    // Add skill tools if available (skip when manual skills are already injected)
                    $skillTools = [];
                    if (empty($this->usedSkills) && $this->autoSkills->isNotEmpty() && class_exists(SkillToolService::class)) {
                        $skillTools = SkillToolService::anthropicTools($this->autoSkills);
                    }

                    $chatProTools = [];
                    $preferredChatProTool = null;
                    $chatProToolChoice = null;
                    if ($chat_type === 'chatPro' && Auth::check()) {
                        $preferredChatProTool = AiChatProService::preferredToolForPrompt((string) ($main_message->input ?? ''));
                        $chatProTools = AiChatProService::toolsForPrompt((string) ($main_message->input ?? ''));
                        $skillTools = array_merge(
                            $skillTools,
                            AiChatProService::anthropicTools((string) ($main_message->input ?? '')),
                        );
                        if ($preferredChatProTool
                            && AiChatProService::toolIsAvailable($preferredChatProTool, $chatProTools)) {
                            $chatProToolChoice = [
                                'type' => 'tool',
                                'name' => $preferredChatProTool,
                            ];
                        }

                        if ($preferredChatProTool === 'search_images') {
                            $imageQuery = SmartImageService::searchQueryFromPrompt((string) ($main_message->input ?? ''));
                            if ($imageQuery !== '') {
                                echo PHP_EOL;
                                echo "event: smart_image_search\n";
                                echo 'data: ' . json_encode([
                                    'query' => $imageQuery,
                                    'message_id' => $main_message->id ?? null,
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                                $this->safeFlush();
                            }

                            $chatProTools = array_values(array_filter(
                                $chatProTools,
                                static fn (array $tool): bool => ($tool['name'] ?? null) !== 'search_images',
                            ));
                            $skillTools = array_values(array_filter(
                                $skillTools,
                                static fn (array $tool): bool => ($tool['name'] ?? null) !== 'search_images',
                            ));
                            $preferredChatProTool = null;
                            $chatProToolChoice = null;
                        }
                    }

                    // Anthropic native web_search server tool when realtime + tool_calling.
                    // Uses the broad-compat 20250305 version (works on Claude 3+). Skip on
                    // Claude 2.x where the tool would 4xx.
                    if (request()?->input('realtime')
                        && setting('default_realtime') === 'tool_calling'
                        && ! str_starts_with($driver->enum()->value, 'claude-2')) {
                        $skillTools[] = [
                            'type' => 'web_search_20250305',
                            'name' => 'web_search',
                            'max_uses' => 3,
                        ];

                        if ($chat_type === 'chatPro'
                            && AiChatProService::requiresWebSearchForPrompt((string) ($main_message->input ?? ''))) {
                            $skillTools[] = [
                                'type' => 'web_fetch_20250910',
                                'name' => 'web_fetch',
                                'max_uses' => 3,
                            ];
                        }
                    }

                    $toolUseBlock = null;
                    $toolUseInput = '';
                    $anthropicEventTypes = [];
                    $anthropicProviderError = null;
                    $anthropicStopReason = null;
                    $preferredToolCalled = false;
                    $anthropicToolWasUsed = false;

                    foreach ($this->anthropicStreamLines($client->setStream(true)
                        ->setSystem($system)
                        ->setTools($skillTools)
                        ->setToolChoice($chatProToolChoice)
                        ->setMessages(array_values($historyMessages))
                        ->stream()) as $chunk) {
                        if (strlen($chunk) < 6) {
                            continue;
                        }
                        if (! Str::contains($chunk, 'data: ')) {
                            continue;
                        }
                        $chunk = str_replace('data: {', '{', $chunk);
                        $jsonData = json_decode($chunk, false, 512, JSON_THROW_ON_ERROR);
                        $eventType = (string) ($jsonData->type ?? 'unknown');
                        $anthropicEventTypes[$eventType] = ($anthropicEventTypes[$eventType] ?? 0) + 1;
                        if ($eventType === 'error') {
                            $anthropicProviderError = [
                                'type' => $jsonData->error->type ?? null,
                                'message' => $jsonData->error->message ?? null,
                            ];
                        }
                        if ($eventType === 'message_delta') {
                            $anthropicStopReason = $jsonData->delta->stop_reason ?? null;
                        }
                        if ($eventType === 'content_block_start') {
                            $serverToolName = (string) ($jsonData->content_block->name ?? '');
                            if (in_array($serverToolName, ['web_search', 'web_fetch'], true)) {
                                echo PHP_EOL;
                                echo "event: tool_status\n";
                                echo 'data: ' . json_encode(
                                    AiChatProService::progressPayload($serverToolName, 'working'),
                                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                                ) . "\n\n";
                                $this->safeFlush();
                            }
                        }
                        if ($eventType === 'content_block_start'
                            && in_array((string) ($jsonData->content_block->type ?? ''), ['web_search_tool_result', 'web_fetch_tool_result'], true)) {
                            $serverToolName = (string) ($jsonData->content_block->type ?? '') === 'web_search_tool_result'
                                ? 'web_search'
                                : 'web_fetch';
                            $serverToolState = ($jsonData->content_block->is_error ?? false) ? 'failed' : 'completed';
                            echo PHP_EOL;
                            echo "event: tool_status\n";
                            echo 'data: ' . json_encode(
                                AiChatProService::progressPayload($serverToolName, $serverToolState),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ) . "\n\n";
                            $this->safeFlush();
                        }

                        // Detect tool_use blocks
                        if (isset($jsonData->type) && $jsonData->type === 'content_block_start' && isset($jsonData->content_block->type) && $jsonData->content_block->type === 'tool_use') {
                            $anthropicToolWasUsed = true;
                            $toolUseBlock = $jsonData->content_block;
                            $toolUseInput = '';
                        } elseif (isset($jsonData->type) && $jsonData->type === 'content_block_delta' && isset($jsonData->delta->type) && $jsonData->delta->type === 'input_json_delta') {
                            $toolUseInput .= $jsonData->delta->partial_json ?? '';
                        } elseif (isset($jsonData->type) && $jsonData->type === 'content_block_stop' && $toolUseBlock !== null) {
                            // Tool use complete — handle skill call
                            $functionName = $toolUseBlock->name ?? '';
                            if ($functionName === $preferredChatProTool) {
                                $preferredToolCalled = true;
                            }
                            if ($chat_type === 'chatPro'
                                && AiChatProService::toolIsAvailable($functionName, $chatProTools)) {
                                echo PHP_EOL;
                                echo "event: tool_status\n";
                                echo 'data: ' . json_encode(AiChatProService::progressPayload($functionName, 'working'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                                $this->safeFlush();

                                $functionResponse = AiChatProService::callFunction($functionName, $toolUseInput)
                                    ?? 'The requested action could not be completed. The capability did not return a result.';
                                $pendingApprovalId = $this->takeAgentPendingApprovalId();
                                $toolExecution = AiChatProService::toolExecutionPayload(
                                    $functionName,
                                    $functionResponse,
                                    request()->boolean('agent_mode'),
                                    $pendingApprovalId,
                                );
                                $toolState = $toolExecution['state'];
                                $toolStatusPayload = AiChatProService::progressPayload($functionName, $toolState);
                                if (isset($toolExecution['approvalId'])) {
                                    $toolStatusPayload['approvalId'] = $toolExecution['approvalId'];
                                }

                                echo PHP_EOL;
                                echo "event: tool_status\n";
                                echo 'data: ' . json_encode($toolStatusPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                                $this->safeFlush();

                                if ($this->stopAfterFailedArtifactTool(
                                    $functionName,
                                    $toolState,
                                    $main_message,
                                    $chat,
                                    $responsedText,
                                    $output,
                                    $total_used_tokens,
                                    $driver,
                                )) {
                                    return;
                                }
                                $this->emitCreatedArtifactEvent();

                                if (AiChatProService::shouldExposeToolResult($functionName, $toolState)) {
                                    $functionResponseForStream = str_replace(["\r\n", "\r", "\n"], '<br/>', $functionResponse);
                                    $output .= $functionResponseForStream;
                                    $responsedText .= $functionResponse;
                                    $total_used_tokens += countWords($functionResponse);
                                    $this->emitStreamChunk($functionResponseForStream);
                                }

                                if ($functionName === 'run_agent' && $toolState === 'failed') {
                                    $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
                                    $this->emitAgentExecutionError($functionResponse);

                                    return;
                                }

                                $followUpMessages = array_values($historyMessages);
                                $followUpMessages[] = [
                                    'role' => 'assistant',
                                    'content' => [[
                                        'type' => 'tool_use',
                                        'id' => $toolUseBlock->id,
                                        'name' => $functionName,
                                        'input' => json_decode($toolUseInput ?: '{}', true) ?: new stdClass,
                                    ]],
                                ];
                                $followUpMessages[] = [
                                    'role' => 'user',
                                    'content' => [[
                                        'type' => 'tool_result',
                                        'tool_use_id' => $toolUseBlock->id,
                                        'content' => $functionResponse,
                                    ]],
                                ];

                                foreach ($this->anthropicStreamLines($client->setStream(true)
                                    ->setSystem($system)
                                    ->setTools([])
                                    ->setToolChoice(null)
                                    ->setMessages($followUpMessages)
                                    ->stream()) as $followChunk) {
                                    if (strlen($followChunk) < 6 || ! Str::contains($followChunk, 'data: ')) {
                                        continue;
                                    }
                                    $followChunk = str_replace('data: {', '{', $followChunk);
                                    try {
                                        $followJson = json_decode($followChunk, false, 512, JSON_THROW_ON_ERROR);
                                    } catch (JsonException) {
                                        continue;
                                    }
                                    if (isset($followJson->delta->text)) {
                                        $message = $followJson->delta->text;
                                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message);
                                        $output .= $messageFix;
                                        $responsedText .= $message;
                                        $total_used_tokens += countWords($message);
                                        $this->emitStreamChunk($messageFix);
                                    }
                                    if (connection_aborted()) {
                                        break;
                                    }
                                }

                                $toolUseBlock = null;

                                continue;
                            }

                            if (str_starts_with($functionName, 'connector_') && class_exists(ConnectorToolService::class)) {
                                $connectorService = app(ConnectorToolService::class);
                                $connectorArgs = json_decode($toolUseInput ?: '{}', true) ?: [];

                                if (Helper::appIsDemo()) {
                                    $connectorResult = ['error' => 'This feature is disabled in demo mode.'];
                                } else {
                                    try {
                                        $connectorResult = $connectorService->handle($functionName, $connectorArgs, $this->autoConnectors) ?? ['error' => 'Connector not available.'];
                                    } catch (Throwable $e) {
                                        $connectorResult = ['error' => $e->getMessage()];
                                    }
                                }

                                $connectorMeta = $connectorService->getMeta($functionName, $this->autoConnectors);
                                if ($connectorMeta) {
                                    $this->usedConnectors[] = $connectorMeta;
                                    $this->emitUsedConnectors();
                                }

                                $resultJson = json_encode($connectorResult, JSON_UNESCAPED_SLASHES);

                                $followUpMessages = array_values($historyMessages);
                                $followUpMessages[] = [
                                    'role'    => 'assistant',
                                    'content' => [
                                        ['type' => 'tool_use', 'id' => $toolUseBlock->id, 'name' => $functionName, 'input' => json_decode($toolUseInput ?: '{}', true) ?: new stdClass],
                                    ],
                                ];
                                $followUpMessages[] = [
                                    'role'    => 'user',
                                    'content' => [
                                        ['type' => 'tool_result', 'tool_use_id' => $toolUseBlock->id, 'content' => $resultJson],
                                    ],
                                ];

                                foreach ($this->anthropicStreamLines($client->setStream(true)
                                    ->setSystem($system)
                                    ->setTools([])
                                    ->setMessages($followUpMessages)
                                    ->stream()) as $followChunk) {
                                    if (strlen($followChunk) < 6) {
                                        continue;
                                    }
                                    if (! Str::contains($followChunk, 'data: ')) {
                                        continue;
                                    }
                                    $followChunk = str_replace('data: {', '{', $followChunk);

                                    try {
                                        $followJson = json_decode($followChunk, false, 512, JSON_THROW_ON_ERROR);
                                    } catch (JsonException) {
                                        continue;
                                    }
                                    if (isset($followJson->delta->text)) {
                                        $message = $followJson->delta->text;
                                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message);
                                        $output .= $messageFix;
                                        $responsedText .= $message;
                                        $total_used_tokens += countWords($message);
                                        $this->emitStreamChunk($messageFix);
                                    }
                                    if (connection_aborted()) {
                                        break;
                                    }
                                }

                                $toolUseBlock = null;

                                continue;
                            }

                            if (str_starts_with($functionName, 'use_skill_') && class_exists(SkillToolService::class)) {
                                $skillInstructions = SkillToolService::handleSkillCall($functionName, $this->autoSkills);
                                $skillMeta = SkillToolService::getSkillMeta($functionName, $this->autoSkills);
                                if ($skillMeta) {
                                    $this->usedSkills[] = $skillMeta;
                                }

                                if ($skillInstructions) {
                                    $this->emitUsedSkills();

                                    // Build follow-up with tool result

                                    $followUpMessages = array_values($historyMessages);
                                    $followUpMessages[] = [
                                        'role'    => 'assistant',
                                        'content' => [
                                            ['type' => 'tool_use', 'id' => $toolUseBlock->id, 'name' => $functionName, 'input' => json_decode($toolUseInput ?: '{}', true) ?: new stdClass],
                                        ],
                                    ];
                                    $followUpMessages[] = [
                                        'role'    => 'user',
                                        'content' => [
                                            ['type' => 'tool_result', 'tool_use_id' => $toolUseBlock->id, 'content' => $skillInstructions],
                                        ],
                                    ];

                                    foreach ($this->anthropicStreamLines($client->setStream(true)
                                        ->setSystem($system)
                                        ->setTools([])
                                        ->setMessages($followUpMessages)
                                        ->stream()) as $followChunk) {
                                        if (strlen($followChunk) < 6) {
                                            continue;
                                        }
                                        if (! Str::contains($followChunk, 'data: ')) {
                                            continue;
                                        }
                                        $followChunk = str_replace('data: {', '{', $followChunk);
                                        $followJson = json_decode($followChunk, false, 512, JSON_THROW_ON_ERROR);
                                        if (isset($followJson->delta->text)) {
                                            $message = $followJson->delta->text;
                                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message);
                                            $output .= $messageFix;
                                            $responsedText .= $message;
                                            $total_used_tokens += countWords($message);
                                            $this->emitStreamChunk($messageFix);
                                        }
                                        if (connection_aborted()) {
                                            break;
                                        }
                                    }
                                }
                            }
                            $toolUseBlock = null;
                        } elseif (isset($jsonData->delta->text)) {
                            $message = $jsonData->delta->text;
                            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message);
                            $output .= $messageFix;
                            $responsedText .= $message;
                            $total_used_tokens += countWords($message);
                            $this->emitStreamChunk($messageFix);
                        }

                        if (connection_aborted()) {
                            break;
                        }
                    }
                    if ($anthropicProviderError !== null) {
                        $providerMessage = $anthropicProviderError['message'] ?? null;
                        $providerMessage = is_string($providerMessage) && trim($providerMessage) !== ''
                            ? trim($providerMessage)
                            : 'The provider returned an error event.';

                        throw new \RuntimeException('Anthropic stream error: ' . $providerMessage);
                    }

                    $refusalMessage = AnthropicService::refusalFallback($anthropicStopReason, $responsedText);
                    if ($refusalMessage !== null) {
                        $responsedText = $refusalMessage;
                        $output = $refusalMessage;
                        $this->emitStreamChunk($refusalMessage);
                    }

                    if (! isset($anthropicEventTypes['message_stop']) && ! connection_aborted()) {
                        Log::warning('Anthropic stream ended before completion', [
                            'model' => $driver?->enum()->value,
                            'stream_events' => $anthropicEventTypes,
                            'stop_reason' => $anthropicStopReason,
                            'response_chars' => strlen($responsedText),
                        ]);

                        throw new \RuntimeException('Anthropic stream ended before message_stop.');
                    }
                    if (! $this->continueAnthropicTextResponse(
                        $client,
                        $system,
                        array_values($historyMessages),
                        $anthropicToolWasUsed,
                        $anthropicStopReason,
                        $responsedText,
                        $output,
                        $total_used_tokens,
                    )) {
                        return;
                    }
                    if ($chat_type === 'chatPro' && $preferredChatProTool !== null && ! $preferredToolCalled) {
                        Log::warning('Anthropic Chat Pro did not call the requested artifact tool', [
                            'model' => $driver?->enum()->value,
                            'preferred_tool' => $preferredChatProTool,
                            'stream_events' => $anthropicEventTypes,
                            'message_id' => $main_message->id,
                        ]);
                        $toolFallback = 'Codsiga fayl-samaynta lama dhammaystirin, mana jiro fayl la sameeyey. Fadlan mar kale isku day ama dooro model kale.';
                        $responsedText = $toolFallback;
                        $output = $toolFallback;
                        $total_used_tokens = countWords($toolFallback);
                        echo PHP_EOL;
                        echo "event: clear_content\n";
                        echo "data: \n\n";
                        echo "event: data\n";
                        echo 'data: ' . $toolFallback;
                        echo "\n\n";
                        $this->safeFlush();
                    }
                }
            } else {
                ApiHelper::setOpenAiKey();
                $driver = $this->createDriver(EntityEnum::GPT_5_MINI);
                $stream = OpenAI::responses()->createStreamed([
                    'model'                    => $driver->enum()->value,
                    'input'                    => $history,
                    'max_output_tokens'        => 2000,
                    'temperature'              => 1.0,
                    'stream'                   => true,
                ]);
                foreach ($stream as $response) {
                    if (($response->event === 'response.output_text.delta') && isset($response->response->delta)) {
                        $text = $response->response->delta;
                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                        $output .= $messageFix;
                        $responsedText .= $text;
                        $total_used_tokens += countWords($text);
                        if (connection_aborted()) {
                            break;
                        }
                        $this->emitStreamChunk($messageFix);
                    }
                }
            }

            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function anthropicOtherStream(Request $request, $chat_bot): StreamedResponse
    {
        $prompt = $request->get('prompt');
        $message_id = $request->get('message_id');
        $openai_id = $request->get('openai_id');
        $title = $request->get('title');
        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));
        $history[] = ['role' => 'user', 'content' => $prompt];
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';

        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($driver, $history, &$total_used_tokens, &$output, &$responsedText, $message_id, $title, $openai_id, $prompt) {
            echo "event: message\n";
            echo 'data: ' . $message_id . "\n\n";

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $user = Auth::user();
            $entry = UserOpenai::find($message_id);
            if (is_null($entry)) {
                $entry = new UserOpenai;
                $entry->user_id = $user?->id;
                $entry->input = $prompt;
                $entry->hash = str()->random(256);
                $entry->team_id = $user?->team_id;
                $entry->slug = str()->random(7) . str($user?->fullName())->slug() . '-workbook';
                $entry->openai_id = $openai_id ?? 1;
            }

            $client = app(AnthropicService::class);
            $historyMessages = array_filter($history, function ($item) {
                return $item['role'] !== 'system';
            });
            $system = Arr::first(array_filter($history, function ($item) {
                return $item['role'] === 'system';
            }));

            $system = data_get($system, 'content');
            if (setting('anthropic_default_model') === BedrockEngine::BEDROCK->value) {
                $bedrockService = new BedrockRuntimeService([
                    'region'      => config('filesystems.disks.s3.region'),
                    'version'     => 'latest',
                    'credentials' => [
                        'key'    => config('filesystems.disks.s3.key'),
                        'secret' => config('filesystems.disks.s3.secret'),
                    ],
                ]);
                $driver = $this->createDriver(EntityEnum::CLAUDE_2_1);
                if (! $driver->hasCreditBalance()) {
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                    echo "\n\n";
                    $this->safeFlush();
                    echo "event: stop\n";
                    echo 'data: [DONE]';
                    echo "\n\n";
                    $this->safeFlush();

                    return null;
                }
                $responseBody = $bedrockService->invokeClaude($entry->input);
                if ($responseBody) {
                    $response = self::anthropicBedrockResponse($responseBody);
                    $output = $response['output'];
                    $responsedText = $response['responsedText'];
                    $total_used_tokens = $response['total_used_tokens'];
                    echo "event: stop\n";
                    echo 'data: [DONE]';
                    echo "\n\n";
                    $this->safeFlush();
                }

            } else {
                $anthropicStopReason = null;
                foreach ($this->anthropicStreamLines($client->setStream(true)
                    ->setSystem($system)
                    ->setMessages(array_values($historyMessages))
                    ->stream()) as $chunk) {
                    if (strlen($chunk) < 6) {
                        continue;
                    }
                    if (! Str::contains($chunk, 'data: ')) {
                        continue;
                    }
                    $chunk = str_replace('data: {', '{', $chunk);
                    $jsonData = json_decode($chunk, false, 512, JSON_THROW_ON_ERROR);
                    if (($jsonData->type ?? null) === 'message_delta') {
                        $anthropicStopReason = $jsonData->delta->stop_reason ?? null;
                    }
                    if (isset($jsonData->delta->text)) {
                        $message = $jsonData->delta->text;
                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $message);
                        $output .= $messageFix;
                        $responsedText .= $message;
                        $total_used_tokens += countWords($message);

                        echo PHP_EOL;
                        echo "event: data\n";
                        echo 'data: ' . $messageFix;
                        echo "\n\n";
                        $this->safeFlush();
                    }
                    if (connection_aborted()) {
                        break;
                    }
                }
                $refusalMessage = AnthropicService::refusalFallback($anthropicStopReason, $responsedText);
                if ($refusalMessage !== null) {
                    $responsedText = $refusalMessage;
                    $output = $refusalMessage;
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . $refusalMessage;
                    echo "\n\n";
                    $this->safeFlush();
                }
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

            }

            $this->saveOtherStreamResponse($entry, $title, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    // GeminiService Stream
    private function geminiChatStream(string $chat_bot, $history, $main_message, $chat_type, $contain_images): StreamedResponse
    {
        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';
        $newhistory = convertHistoryToGemini($history);
        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));

        if ($contain_images) {
            // I will improve later
            $newhistory = $this->getLastMessageAndImage($newhistory);
            if (count($newhistory['parts']) === 1) {
                $newhistory['parts'][0] = [
                    'text' => $newhistory['parts'][0]['text'],
                ];

                $contain_images = false;
            }

            $newhistory = [$newhistory];
        }
        $chatProTools = $chat_type === 'chatPro' && Auth::check()
            ? AiChatProService::toolsForPrompt((string) ($main_message->input ?? ''))
            : [];
        $preferredChatProTool = $chat_type === 'chatPro'
            ? AiChatProService::preferredToolForPrompt((string) ($main_message->input ?? ''))
            : null;
        $chatProToolChoice = $preferredChatProTool
            && AiChatProService::toolIsAvailable($preferredChatProTool, $chatProTools)
                ? [
                    'functionCallingConfig' => [
                        'mode' => 'ANY',
                        'allowedFunctionNames' => [$preferredChatProTool],
                    ],
                ]
                : [];

        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($driver, $newhistory, &$total_used_tokens, &$output, &$responsedText, $main_message, $contain_images, $chat_type, $chatProTools, $chatProToolChoice) {

            $chat_id = $main_message->user_openai_chat_id;
            $chat = UserOpenaiChat::whereId($chat_id)->first();
            echo "event: message\n";
            echo 'data: ' . $main_message->id . "\n\n";

            if (! $driver->hasCreditBalance()) {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('You have no credits left. Please buy more credits to continue.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return null;
            }

            $client = app(GeminiService::class);

            // Add skill tools if available (skip when manual skills are already injected)
            $geminiSkillTools = [];
            if (empty($this->usedSkills) && $this->autoSkills->isNotEmpty() && class_exists(SkillToolService::class)) {
                $geminiSkillTools = SkillToolService::geminiTools($this->autoSkills);
            }

            // Add smart image tool if enabled (only for chatPro, skip for council sub-requests)
            if ($chat_type === 'chatPro'
                && ! $this->isCouncilSubRequest
                && MarketplaceHelper::isRegistered('ai-chat-pro-smart-image')
                && SmartImageService::isEnabled()) {
                $geminiSkillTools[] = SmartImageService::geminiToolDefinition();
            }

            if ($chat_type === 'chatPro' && ! $this->isCouncilSubRequest) {
                foreach (AiChatProService::geminiTools() as $chatProToolGroup) {
                    $declarationIndex = null;
                    foreach ($geminiSkillTools as $index => $toolGroup) {
                        if (isset($toolGroup['functionDeclarations'])) {
                            $declarationIndex = $index;
                            break;
                        }
                    }

                    if ($declarationIndex === null) {
                        $geminiSkillTools[] = $chatProToolGroup;
                    } else {
                        $geminiSkillTools[$declarationIndex]['functionDeclarations'] = array_merge(
                            $geminiSkillTools[$declarationIndex]['functionDeclarations'],
                            $chatProToolGroup['functionDeclarations'] ?? [],
                        );
                    }
                }
            }

            // Gemini native Google Search grounding when realtime + tool_calling mode is on.
            // Gemini 3.x can combine grounding with function declarations; older Gemini
            // versions cannot, so for them we drop function tools while grounding is active.
            if (! $chatProToolChoice && request()?->input('realtime')
                && setting('default_realtime') === 'tool_calling') {
                $modelValue = $driver->enum()->value;
                $isGemini3Plus = str_starts_with($modelValue, 'gemini-3');
                $isGemini15 = str_starts_with($modelValue, 'gemini-1.5');

                $groundingTool = $isGemini15
                    ? ['google_search_retrieval' => new stdClass]
                    : ['google_search' => new stdClass];

                if ($isGemini3Plus) {
                    $geminiSkillTools[] = $groundingTool;
                } else {
                    $geminiSkillTools = [$groundingTool];
                }
            }

            try {
                $response = $client
                    ->setHistory($newhistory)
                    ->setTools($geminiSkillTools)
                    ->setToolConfig($chatProToolChoice)
                    ->streamGenerateContent($driver->enum()->value);
            } catch (Throwable $e) {
                Log::error('[StreamDebug] Gemini connection failed', ['error' => $e->getMessage()]);

                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . __('An error occurred while connecting to the AI service. Please try again.');
                echo "\n\n";
                $this->safeFlush();
                echo "event: stop\n";
                echo 'data: [DONE]';
                echo "\n\n";
                $this->safeFlush();

                return;
            }

            while (! $response->getBody()->eof()) {
                $line = trim($client->readLine($response->getBody()));

                if ($line === '' || $line === '[' || $line === ']' || $line === ',') {
                    continue;
                }

                try {
                    $decodedLine = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    if (str_starts_with(trim($line), '{')) {
                        Log::error('JSON decoding error: ' . $e->getMessage());
                        Log::error('Offending line: ' . $line);
                    }

                    continue;
                }

                if (isset($decodedLine['error'])) {
                    $errorMessage = $decodedLine['error']['message'] ?? 'Unknown error occurred.';
                    $formattedMessage = '⚠️ ' . $errorMessage;

                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . $formattedMessage;
                    echo "\n\n";
                    $this->safeFlush();

                    break;
                }

                if (! isset($decodedLine['candidates'])) {
                    continue;
                }

                foreach ($decodedLine['candidates'] as $candidate) {
                    $parts = $candidate['content']['parts'] ?? [];
                    foreach ($parts as $part) {
                        // Handle function calls from Gemini
                        if (isset($part['functionCall'])) {
                            $functionName = $part['functionCall']['name'] ?? '';
                            $functionId = $part['functionCall']['id'] ?? '';

                            // Handle search_images — emit SSE for frontend async fetch, then do follow-up for text
                            if ($functionName === 'search_images') {
                                $searchArgs = $part['functionCall']['args'] ?? [];
                                $searchQuery = $searchArgs['query'] ?? '';

                                if ($searchQuery !== '') {
                                    // Emit SSE events for frontend
                                    echo PHP_EOL;
                                    echo "event: function_call\n";
                                    echo 'data: search_images' . "\n\n";
                                    $this->safeFlush();

                                    echo PHP_EOL;
                                    echo "event: smart_image_search\n";
                                    echo 'data: ' . json_encode([
                                        'query'      => $searchQuery,
                                        'message_id' => $main_message->id ?? null,
                                    ]) . "\n\n";
                                    $this->safeFlush();
                                }

                                // Build follow-up history with function response so Gemini generates text
                                // Ensure args is an object (not array) for Gemini API compatibility
                                $imgFunctionCallData = $part['functionCall'];
                                $imgFunctionCallData['args'] = ! empty($imgFunctionCallData['args']) && is_array($imgFunctionCallData['args'])
                                    ? (object) $imgFunctionCallData['args']
                                    : new stdClass;
                                // Preserve the entire part (includes thoughtSignature required by Gemini 3)
                                $modelPart = ['functionCall' => $imgFunctionCallData];
                                if (isset($part['thoughtSignature'])) {
                                    $modelPart['thoughtSignature'] = $part['thoughtSignature'];
                                }

                                $followUpHistory = $newhistory;
                                $followUpHistory[] = [
                                    'role'  => 'model',
                                    'parts' => [$modelPart],
                                ];
                                $followUpHistory[] = [
                                    'role'  => 'user',
                                    'parts' => [['functionResponse' => [
                                        'name'     => $functionName,
                                        'id'       => $functionId,
                                        'response' => ['status' => 'success', 'message' => 'Images are being loaded by the frontend. Now write your text response about the topic.'],
                                    ]]],
                                ];

                                try {
                                    $followUpClient = app(GeminiService::class);
                                    $followUpResponse = $followUpClient
                                        ->setHistory($followUpHistory)
                                        ->setTools([])
                                        ->streamGenerateContent($driver->enum()->value);

                                    while (! $followUpResponse->getBody()->eof()) {
                                        $followLine = trim($followUpClient->readLine($followUpResponse->getBody()));
                                        if ($followLine === '' || $followLine === '[' || $followLine === ']' || $followLine === ',') {
                                            continue;
                                        }

                                        try {
                                            $followDecoded = json_decode($followLine, true, 512, JSON_THROW_ON_ERROR);
                                        } catch (JsonException) {
                                            continue;
                                        }

                                        if (isset($followDecoded['error'])) {
                                            break;
                                        }

                                        if (isset($followDecoded['candidates'])) {
                                            foreach ($followDecoded['candidates'] as $followCandidate) {
                                                $followText = $followCandidate['content']['parts'][0]['text'] ?? '';
                                                if ($followText !== '') {
                                                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $followText);
                                                    $output .= $messageFix;
                                                    $responsedText .= $followText;
                                                    $total_used_tokens += countWords($followText);
                                                    $this->emitStreamChunk($messageFix);
                                                }
                                            }
                                        }
                                        if (connection_aborted()) {
                                            break;
                                        }
                                    }
                                } catch (Throwable) {
                                    // Follow-up failed — images still load, text is skipped
                                }

                                continue;
                            }

                            if ($chat_type === 'chatPro'
                                && AiChatProService::toolIsAvailable($functionName, $chatProTools)) {
                                echo PHP_EOL;
                                echo "event: function_call\n";
                                echo 'data: ' . $functionName . "\n\n";
                                echo PHP_EOL;
                                echo "event: tool_status\n";
                                echo 'data: ' . json_encode(AiChatProService::progressPayload($functionName, 'working'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                                $this->safeFlush();

                                $toolArguments = is_array($part['functionCall']['args'] ?? null)
                                    ? $part['functionCall']['args']
                                    : [];
                                $functionResponse = AiChatProService::callFunction(
                                    $functionName,
                                    json_encode($toolArguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                ) ?? 'The requested action could not be completed. The capability did not return a result.';
                                $pendingApprovalId = $this->takeAgentPendingApprovalId();
                                $toolExecution = AiChatProService::toolExecutionPayload(
                                    $functionName,
                                    $functionResponse,
                                    request()->boolean('agent_mode'),
                                    $pendingApprovalId,
                                );
                                $toolState = $toolExecution['state'];
                                $toolStatusPayload = AiChatProService::progressPayload($functionName, $toolState);
                                if (isset($toolExecution['approvalId'])) {
                                    $toolStatusPayload['approvalId'] = $toolExecution['approvalId'];
                                }

                                echo PHP_EOL;
                                echo "event: tool_status\n";
                                echo 'data: ' . json_encode($toolStatusPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                                $this->safeFlush();

                                if ($this->stopAfterFailedArtifactTool(
                                    $functionName,
                                    $toolState,
                                    $main_message,
                                    $chat,
                                    $responsedText,
                                    $output,
                                    $total_used_tokens,
                                    $driver,
                                )) {
                                    return;
                                }
                                $this->emitCreatedArtifactEvent();

                                if (AiChatProService::shouldExposeToolResult($functionName, $toolState)) {
                                    $functionResponseForStream = str_replace(["\r\n", "\r", "\n"], '<br/>', $functionResponse);
                                    $output .= $functionResponseForStream;
                                    $responsedText .= $functionResponse;
                                    $total_used_tokens += countWords($functionResponse);
                                    $this->emitStreamChunk($functionResponseForStream);
                                }

                                if ($functionName === 'run_agent' && $toolState === 'failed') {
                                    $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
                                    $this->emitAgentExecutionError($functionResponse);

                                    return;
                                }

                                $functionCallData = $part['functionCall'];
                                $functionCallData['args'] = $toolArguments !== [] ? (object) $toolArguments : new stdClass;
                                $modelPart = ['functionCall' => $functionCallData];
                                if (isset($part['thoughtSignature'])) {
                                    $modelPart['thoughtSignature'] = $part['thoughtSignature'];
                                }

                                $followUpHistory = $newhistory;
                                $followUpHistory[] = [
                                    'role' => 'model',
                                    'parts' => [$modelPart],
                                ];
                                $followUpHistory[] = [
                                    'role' => 'user',
                                    'parts' => [['functionResponse' => [
                                        'name' => $functionName,
                                        'id' => $functionId,
                                        'response' => ['result' => $functionResponse],
                                    ]]],
                                ];

                                $followUpClient = app(GeminiService::class);
                                $followUpResponse = $followUpClient
                                    ->setHistory($followUpHistory)
                                    ->setTools([])
                                    ->setToolConfig([])
                                    ->streamGenerateContent($driver->enum()->value);

                                while (! $followUpResponse->getBody()->eof()) {
                                    $followLine = trim($followUpClient->readLine($followUpResponse->getBody()));
                                    if ($followLine === '' || $followLine === '[' || $followLine === ']' || $followLine === ',') {
                                        continue;
                                    }
                                    try {
                                        $followDecoded = json_decode($followLine, true, 512, JSON_THROW_ON_ERROR);
                                    } catch (JsonException) {
                                        continue;
                                    }
                                    if (isset($followDecoded['error'])) {
                                        break;
                                    }
                                    foreach ($followDecoded['candidates'] ?? [] as $followCandidate) {
                                        foreach ($followCandidate['content']['parts'] ?? [] as $followPart) {
                                            $followText = $followPart['text'] ?? '';
                                            if ($followText !== '') {
                                                $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $followText);
                                                $output .= $messageFix;
                                                $responsedText .= $followText;
                                                $total_used_tokens += countWords($followText);
                                                $this->emitStreamChunk($messageFix);
                                            }
                                        }
                                    }
                                    if (connection_aborted()) {
                                        break;
                                    }
                                }

                                continue;
                            }

                            if (str_starts_with($functionName, 'connector_') && class_exists(ConnectorToolService::class)) {
                                $connectorService = app(ConnectorToolService::class);
                                $connectorArgs = is_array($part['functionCall']['args'] ?? null) ? $part['functionCall']['args'] : [];

                                if (Helper::appIsDemo()) {
                                    $connectorResult = ['error' => 'This feature is disabled in demo mode.'];
                                } else {
                                    try {
                                        $connectorResult = $connectorService->handle($functionName, $connectorArgs, $this->autoConnectors) ?? ['error' => 'Connector not available.'];
                                    } catch (Throwable $e) {
                                        $connectorResult = ['error' => $e->getMessage()];
                                    }
                                }

                                $connectorMeta = $connectorService->getMeta($functionName, $this->autoConnectors);
                                if ($connectorMeta) {
                                    $this->usedConnectors[] = $connectorMeta;
                                    $this->emitUsedConnectors();
                                }

                                $functionCallData = $part['functionCall'];
                                $functionCallData['args'] = ! empty($functionCallData['args']) && is_array($functionCallData['args'])
                                    ? (object) $functionCallData['args']
                                    : new stdClass;
                                $connectorModelPart = ['functionCall' => $functionCallData];
                                if (isset($part['thoughtSignature'])) {
                                    $connectorModelPart['thoughtSignature'] = $part['thoughtSignature'];
                                }

                                $followUpHistory = $newhistory;
                                $followUpHistory[] = [
                                    'role'  => 'model',
                                    'parts' => [$connectorModelPart],
                                ];
                                $followUpHistory[] = [
                                    'role'  => 'user',
                                    'parts' => [['functionResponse' => [
                                        'name'     => $functionName,
                                        'id'       => $functionId,
                                        'response' => ['result' => $connectorResult],
                                    ]]],
                                ];

                                $followUpClient = app(GeminiService::class);
                                $followUpResponse = $followUpClient
                                    ->setHistory($followUpHistory)
                                    ->setTools([])
                                    ->streamGenerateContent($driver->enum()->value);

                                while (! $followUpResponse->getBody()->eof()) {
                                    $followLine = trim($followUpClient->readLine($followUpResponse->getBody()));
                                    if ($followLine === '' || $followLine === '[' || $followLine === ']' || $followLine === ',') {
                                        continue;
                                    }

                                    try {
                                        $followDecoded = json_decode($followLine, true, 512, JSON_THROW_ON_ERROR);
                                    } catch (JsonException) {
                                        continue;
                                    }
                                    if (isset($followDecoded['error'])) {
                                        break;
                                    }
                                    if (isset($followDecoded['candidates'])) {
                                        foreach ($followDecoded['candidates'] as $followCandidate) {
                                            $followParts = $followCandidate['content']['parts'] ?? [];
                                            foreach ($followParts as $followPart) {
                                                $followText = $followPart['text'] ?? '';
                                                if ($followText !== '') {
                                                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $followText);
                                                    $output .= $messageFix;
                                                    $responsedText .= $followText;
                                                    $total_used_tokens += countWords($followText);
                                                    $this->emitStreamChunk($messageFix);
                                                }
                                            }
                                        }
                                    }
                                    if (connection_aborted()) {
                                        break;
                                    }
                                }

                                continue;
                            }

                            if (str_starts_with($functionName, 'use_skill_') && class_exists(SkillToolService::class)) {
                                $skillInstructions = SkillToolService::handleSkillCall($functionName, $this->autoSkills);
                                $skillMeta = SkillToolService::getSkillMeta($functionName, $this->autoSkills);
                                if ($skillMeta) {
                                    $this->usedSkills[] = $skillMeta;
                                }

                                if ($skillInstructions) {
                                    $this->emitUsedSkills();

                                    // Build follow-up history with function response
                                    // Preserve thoughtSignature required by Gemini 3

                                    // Ensure args is an object (not array) for Gemini API compatibility
                                    $functionCallData = $part['functionCall'];
                                    $functionCallData['args'] = ! empty($functionCallData['args']) && is_array($functionCallData['args'])
                                        ? (object) $functionCallData['args']
                                        : new stdClass;
                                    $skillModelPart = ['functionCall' => $functionCallData];
                                    if (isset($part['thoughtSignature'])) {
                                        $skillModelPart['thoughtSignature'] = $part['thoughtSignature'];
                                    }

                                    $followUpHistory = $newhistory;
                                    $followUpHistory[] = [
                                        'role'  => 'model',
                                        'parts' => [$skillModelPart],
                                    ];
                                    $followUpHistory[] = [
                                        'role'  => 'user',
                                        'parts' => [['functionResponse' => [
                                            'name'     => $functionName,
                                            'id'       => $functionId,
                                            'response' => ['instructions' => $skillInstructions],
                                        ]]],
                                    ];

                                    $followUpClient = app(GeminiService::class);
                                    $followUpResponse = $followUpClient
                                        ->setHistory($followUpHistory)
                                        ->setTools([])
                                        ->streamGenerateContent($driver->enum()->value);

                                    while (! $followUpResponse->getBody()->eof()) {
                                        $followLine = trim($followUpClient->readLine($followUpResponse->getBody()));
                                        if ($followLine === '' || $followLine === '[' || $followLine === ']' || $followLine === ',') {
                                            continue;
                                        }

                                        try {
                                            $followDecoded = json_decode($followLine, true, 512, JSON_THROW_ON_ERROR);
                                        } catch (JsonException) {
                                            continue;
                                        }

                                        if (isset($followDecoded['error'])) {
                                            break;
                                        }

                                        if (isset($followDecoded['candidates'])) {
                                            foreach ($followDecoded['candidates'] as $followCandidate) {
                                                $followParts = $followCandidate['content']['parts'] ?? [];
                                                foreach ($followParts as $followPart) {
                                                    $followText = $followPart['text'] ?? '';
                                                    if ($followText !== '') {
                                                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $followText);
                                                        $output .= $messageFix;
                                                        $responsedText .= $followText;
                                                        $total_used_tokens += countWords($followText);
                                                        $this->emitStreamChunk($messageFix);
                                                    }
                                                }
                                            }
                                        }

                                        if (connection_aborted()) {
                                            break;
                                        }
                                    }
                                }
                            }

                            continue;
                        }

                        // Handle regular text
                        $text = $part['text'] ?? '';
                        if ($text === '') {
                            continue;
                        }
                        $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                        $output .= $messageFix;
                        $responsedText .= $text;
                        $total_used_tokens += countWords($text);
                        $this->emitStreamChunk($messageFix);
                    }

                    if (connection_aborted()) {
                        break;
                    }
                }
            }

            $this->saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    public function getLastMessageAndImage($newhistory)
    {
        return Arr::last($newhistory);
    }

    private function geminiOtherStream(Request $request, string $chat_bot): StreamedResponse
    {
        $driver = $this->createDriver(EntityEnum::fromSlug($chat_bot));
        $prompt = $request->get('prompt');
        $message_id = $request->get('message_id');
        $openai_id = $request->get('openai_id');
        $title = $request->get('title');

        $history[] = [
            'parts' => [
                [
                    'text' => $prompt,
                ],
            ],
            'role' => 'user',
        ];

        $total_used_tokens = 0;
        $output = '';
        $responsedText = '';
        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($driver, $history, &$total_used_tokens, &$output, &$responsedText, $message_id, $title, $openai_id, $prompt) {
            $user = Auth::user();
            $entry = UserOpenai::find($message_id);
            if (is_null($entry)) {
                $entry = new UserOpenai;
                $entry->user_id = $user->id;
                $entry->input = $prompt;
                $entry->hash = str()->random(256);
                $entry->team_id = $user->team_id;
                $entry->slug = str()->random(7) . str($user?->fullName())->slug() . '-workbook';
                $entry->openai_id = $openai_id ?? 1;
            }

            echo "event: message\n";
            echo 'data: ' . $message_id . "\n\n";

            $client = app(GeminiService::class);
            $response = $client
                ->setHistory($history)
                ->streamGenerateContent($driver->enum()->value);

            while (! $response->getBody()->eof()) {

                $line = trim($client->readLine($response->getBody()));

                if ($line === '' || $line === '[' || $line === ']' || $line === ',') {
                    continue;
                }

                try {
                    $decodedLine = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                    if ($decodedLine === null || ! isset($decodedLine['candidates'])) {
                        continue;
                    }
                } catch (JsonException $e) {
                    if (str_starts_with($line, '{')) {
                        Log::error('JSON decoding error: ' . $e->getMessage());
                        Log::error('Offending line: ' . $line);
                    }

                    continue;
                }
                if ($decodedLine === null || ! isset($decodedLine['candidates'])) {
                    continue;
                }

                foreach ($decodedLine['candidates'] as $candidate) {
                    $text = $candidate['content']['parts'][0]['text'];
                    $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $text);
                    $output .= $messageFix;
                    $responsedText .= $text;
                    $total_used_tokens += countWords($text);
                    if (connection_aborted()) {
                        break;
                    }
                    echo PHP_EOL;
                    echo "event: data\n";
                    echo 'data: ' . $messageFix;
                    echo "\n\n";
                    $this->safeFlush();
                }
            }

            echo "event: stop\n";
            echo 'data: [DONE]';
            echo "\n\n";
            $this->safeFlush();

            $this->saveOtherStreamResponse($entry, $title, $responsedText, $output, $total_used_tokens, $driver);
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
            'Content-Type'      => 'text/event-stream',
        ]);
    }

    private function anthropicBedrockResponse($responseBody): array
    {
        $completion = $responseBody['completion'];
        $parts = explode(':', $completion, 2);
        if (isset($parts[1])) {
            $completion = trim($parts[1]);
        }

        $words = explode(' ', $completion);
        $output = $completion;
        $responsedText = $completion;
        $total_used_tokens = count($words);
        foreach ($words as $word) {
            $messageFix = str_replace(["\r\n", "\r", "\n"], '<br/>', $word) . ' ';

            echo PHP_EOL;
            echo "event: data\n";
            echo 'data: ' . $messageFix;
            echo "\n\n";
            $this->safeFlush();

            if (connection_aborted()) {
                break;
            }
        }

        return [
            'output'            => $output,
            'responsedText'     => $responsedText,
            'total_used_tokens' => $total_used_tokens,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function injectTitleInstruction(array $history): array
    {
        $instruction = 'RESPONSE FORMAT — your reply has TWO parts:'
            . "\n" . '1. FIRST LINE: one raw JSON object on a single line: {"title":"short descriptive title based on the user message"}'
            . "\n" . '2. BLANK LINE, then your FULL ANSWER to the user. The answer is the main content of your reply and MUST ALWAYS be written — it is never optional and never empty.'
            . "\n"
            . "\n" . 'The title JSON is metadata only; it does NOT replace the answer. Even for simple, casual, or ambiguous messages, you must still write a normal helpful answer after the title line.'
            . "\n" . 'Do NOT wrap the JSON in code fences, backticks, or markdown. Do not wrap the answer in JSON. Only the first line is raw JSON.';

        foreach ($history as $index => $message) {
            if (in_array($message['role'], ['system', 'user']) && is_string($message['content'] ?? null)) {
                $history[$index]['content'] = $message['content'] . "\n\n" . $instruction;

                break;
            }
        }

        return $history;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function parseTitleFromBuffer(string $buffer): array
    {
        $cleaned = preg_replace('/^```[\w]*\s*\n?/', '', trim($buffer));
        $cleaned = preg_replace('/\n```\s*$/', '', $cleaned);
        $cleaned = trim($cleaned);

        if (preg_match('/^\s*(\{"title"\s*:\s*"[^"]*"[^}]*\})/', $cleaned, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && ! empty($decoded['title']) && is_string($decoded['title'])) {
                $remainder = trim(substr($cleaned, strlen($matches[0])));

                return [$decoded['title'], $remainder];
            }
        }

        return [null, $buffer];
    }

    private function emitChatTitle(?UserOpenaiChat $chat, string $title): void
    {
        if (! $chat || $title === '') {
            return;
        }

        $chat->title = $title;
        $chat->save();

        echo PHP_EOL;
        echo "event: title\n";
        echo 'data: ' . json_encode(['chat_id' => $chat->id, 'title' => $title]);
        echo "\n\n";
        $this->safeFlush();
    }

    private function injectSmartImageInstruction(array $history): array
    {
        $instruction = SmartImageService::systemPromptAddition();

        foreach ($history as $index => $message) {
            if (in_array($message['role'], ['system', 'user']) && is_string($message['content'] ?? null)) {
                $history[$index]['content'] = $instruction . "\n\n" . $message['content'];

                break;
            }
        }

        return $history;
    }

    private function injectEntityHighlightInstruction(array $history, bool $withSuggestions = false): array
    {
        $instruction = EntityHighlightService::systemPromptAddition($withSuggestions);

        foreach ($history as $index => $message) {
            if (in_array($message['role'], ['system', 'user']) && is_string($message['content'] ?? null)) {
                $history[$index]['content'] = $instruction . "\n\n" . $message['content'];

                break;
            }
        }

        return $history;
    }

    /**
     * Parse, persist, and strip entity highlight annotations from the stream output.
     */
    private function persistEntityHighlights(string $output, int $messageId, int $userId): string
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-entity-highlight')) {
            return $output;
        }

        try {
            $entities = EntityHighlightService::parseAnnotations($output);

            if (! empty($entities)) {
                EntityHighlightService::saveForMessage(
                    $userId,
                    $messageId,
                    $entities
                );

                // Emit entity highlights as SSE event so frontend can apply them
                echo PHP_EOL;
                echo "event: entity_highlights\n";
                echo 'data: ' . json_encode($entities);
                echo "\n\n";
                $this->safeFlush();
            }
        } catch (Throwable $e) {
            // Don't let entity highlight failures break stream completion
        }

        try {
            return EntityHighlightService::stripAnnotationBlock($output);
        } catch (Throwable $e) {
            return $output;
        }
    }

    private function injectSuggestionsInstruction(array $history): array
    {
        $instruction = 'FOLLOW-UP SUGGESTIONS METADATA:'
            . "\n" . 'Your PRIMARY task is to write a full, helpful answer to the user. The answer is the main content and MUST ALWAYS be written — never skip it, never leave it empty, never replace it with the JSON block below.'
            . "\n"
            . "\n" . 'AFTER you have finished writing your complete answer, append a blank line then ONE raw JSON object on a single line:'
            . "\n" . '{"suggestions":["2-6 word item","2-6 word item","2-6 word item","2-6 word item"]}'
            . "\n" . 'The 4 items should be diverse, actionable follow-up prompts related to the topic.'
            . "\n" . 'If the final sentences contains any suggestions, make sure to include them as well.'
            . "\n"
            . "\n" . 'STRICT FORMAT RULES:'
            . "\n" . '- The JSON block comes LAST, after the full answer. It is metadata, not a replacement for the answer.'
            . "\n" . '- The JSON MUST start with {"suggestions": and end with }.'
            . "\n" . '- The line immediately before the JSON must be a BLANK LINE — nothing else.'
            . "\n" . '- Do NOT write ANY preamble, heading, label, or introductory text before the JSON. Forbidden examples (never output these): "Suggestions for next steps:", "Here are some suggestions:", "Follow-ups:", "Next steps:", "You might also try:", "Related:", or any similar phrase.'
            . "\n" . '- Do NOT output a bare array [...], bullet points, or any other format — only the raw JSON object.'
            . "\n" . '- Do NOT wrap in code fences, backticks.'
            . "\n" . '- Do NOT mention, reference, announce, or acknowledge the suggestions/JSON/metadata anywhere in your visible answer. The answer must read as if the JSON does not exist — the JSON is handled invisibly by the system.'
            . "\n" . '- Do NOT mention the title JSON either. Never write phrases like "Title:", "Here is the title", or refer to it in any way.'
            . "\n"
            . "\n" . 'Skip ONLY the JSON block (still write the answer!) if the last user message is trivial (greetings, ok, thanks, yes/no, bye, short reactions).';

        foreach ($history as $index => $message) {
            if (in_array($message['role'], ['system', 'user']) && is_string($message['content'] ?? null)) {
                $history[$index]['content'] = $message['content'] . "\n\n" . $instruction;

                break;
            }
        }

        return $history;
    }

    /**
     * Strip suggestions JSON block from saved output/response so it doesn't appear on reload or copy.
     */
    private function stripSuggestionsJson(string $text): string
    {
        // Handle both raw newlines and <br/> line separators — standard format {"suggestions":[...]}
        $text = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*\{[\s\n]*(?:<br\s*\/?>|\n)*\s*"suggestions"\s*:\s*\[[\s\S]*?\]\s*(?:<br\s*\/?>|\n)*\s*\}\s*$/i', '', $text);

        // Also strip mid-text occurrences (in case entity block comes after)
        $text = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*\{[\s\n]*(?:<br\s*\/?>|\n)*\s*"suggestions"\s*:\s*\[[\s\S]*?\]\s*(?:<br\s*\/?>|\n)*\s*\}/i', '', $text);

        // Strip malformed suggestions JSON — model sometimes outputs {"item1","item2"} without "suggestions": key
        $text = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*\{\s*"[^"]{2,60}"\s*,\s*"[^"]{2,60}"[\s\S]*?\}\s*$/i', '', $text);

        // Strip malformed entity-meta tail where the model dropped the ':::' prefix and
        // wrote a bare `meta], "suggestions":[...]}` fragment at the end of the response.
        // Only matches the full malformed shape (meta + JSON terminator + "suggestions":
        // key + closing }) so legitimate uses of the word "meta" in chat are preserved.
        $text = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*(?:\*{3,}|-{3,}|_{3,})?\s*(?:<br\s*\/?>|\n)*\s*\bmeta\b\s*(?:<br\s*\/?>|\n)*\s*[\[\{\],}][\s\S]*?"suggestions"\s*:\s*\[[\s\S]*?\]\s*\}?\s*$/i', '', $text);

        // Strip a trailing markdown horizontal rule (***, ---, ___) that the model
        // sometimes emits as a visual separator before the (now-stripped) JSON tail.
        $text = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*(?:\*{3,}|-{3,}|_{3,})\s*(?:<br\s*\/?>|\n)*\s*$/', '', $text);

        // Strip a bare "suggestions":[...]} tail (no leading {) when present at end.
        $text = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*[\[\{,]?\s*"suggestions"\s*:\s*\[[\s\S]*?\]\s*\}?\s*$/i', '', $text);

        // Strip bare "entity-highlights" text leaked from AI
        $text = preg_replace('/\s*(?:<br\s*\/?>|\n)*\s*entity[- ]?highlights?\s*$/i', '', $text);

        return rtrim($text);
    }

    /**
     * @return array{0: ?array<string, mixed>, 1: string}
     */
    private function parseSuggestionsFromBuffer(string $buffer): array
    {
        $cleaned = preg_replace('/\s*```\s*$/', '', rtrim($buffer));

        if (preg_match('/(\{"suggestions"\s*:\s*\[.*\]\s*\})\s*$/s', $cleaned, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (
                json_last_error() === JSON_ERROR_NONE &&
                ! empty($decoded['suggestions']) &&
                is_array($decoded['suggestions'])
            ) {
                $beforeJson = rtrim(substr($cleaned, 0, strrpos($cleaned, $matches[1])));

                return [$decoded, $beforeJson];
            }
        }

        return [null, $buffer];
    }

    private function emitSuggestions(array $payload, $main_message): void
    {
        echo PHP_EOL;
        echo "event: suggestions\n";
        echo 'data: ' . json_encode($payload);
        echo "\n\n";
        $this->safeFlush();

        $main_message->suggestions_response = $payload;
        $main_message->save();
    }

    public function saveStreamResponse($main_message, $chat, $responsedText, $output, $total_used_tokens, $driver): void
    {
        if ($this->isFirstMessage && $chat) {
            $bufferedContent = $this->titleBuffer;
            $this->titleBuffer = '';

            // Try to extract a title JSON from the full response text
            $fullText = ($bufferedContent !== '' ? strip_tags(str_replace('<br/>', "\n", $bufferedContent)) : '') . $responsedText;
            [$title, $cleanText] = $this->parseTitleFromBuffer($fullText);

            if ($title !== null) {
                $responsedText = $cleanText;
                $output = (string) preg_replace('/^\s*(```[\w]*\s*(<br\s*\/?>|\s)*)?\{"title"\s*:\s*"[^"]*"[^}]*\}\s*(```\s*)?(<br\s*\/?>|\s)*/i', '', $output);
                $this->emitChatTitle($chat, $title);
                if ($responsedText === '' && $output === '') {
                    $responsedText = $cleanText;
                    $output = $cleanText;
                }

                // When the streaming title regex (line 152) never matched (e.g. title JSON
                // split across chunks), titleBuffer holds the entire response. The title was
                // only recovered here at end-of-stream — so the answer body was never emitted
                // as a data event and the UI bubble is empty until reload. Flush the cleaned
                // remainder now. Strip meta/suggestions tails first so we don't leak JSON.
                if ($bufferedContent !== '' && ! $this->entityBlockSuppressed) {
                    $remainder = (string) preg_replace('/^\s*(```[\w]*\s*(<br\s*\/?>|\s)*)?\{"title"\s*:\s*"[^"]*"[^}]*\}\s*(```\s*)?(<br\s*\/?>|\s)*/i', '', $bufferedContent);
                    $remainder = $this->stripSuggestionsJson($remainder);
                    $remainder = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*:::\s*(meta|entity[- ]?highlights?)[\s\S]*$/i', '', $remainder);
                    $remainder = rtrim($remainder);
                    if ($remainder !== '') {
                        echo PHP_EOL;
                        echo "event: data\n";
                        echo 'data: ' . $remainder;
                        echo "\n\n";
                        $this->safeFlush();
                    }
                }

            } elseif ($bufferedContent !== '') {
                // No title found — flush the buffered content as data so it appears in the bubble
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . $bufferedContent;
                echo "\n\n";
                $this->safeFlush();
            }

            $this->isFirstMessage = false;
        }

        if (trim(strip_tags((string) $responsedText)) === ''
            && trim(strip_tags((string) $output)) === ''
            && trim((string) ($main_message->input ?? '')) !== ''
            && ! connection_aborted()) {
            Log::warning('AI stream ended without a user-visible response', [
                'message_id' => $main_message->id,
                'model' => $driver?->enum()->value ?? null,
            ]);

            $fallbackMessage = 'Waan ka xumahay—AI-gu ma soo celin jawaab ama ma dhammeystirin codsiga. Fadlan mar kale isku day; haddii ay soo noqoto, dooro model kale.';
            $responsedText = $fallbackMessage;
            $output = $fallbackMessage;
            $total_used_tokens = countWords($fallbackMessage);

            echo PHP_EOL;
            echo "event: data\n";
            echo 'data: ' . $fallbackMessage;
            echo "\n\n";
            $this->safeFlush();
        }

        // If still in entity block suppression, discard the buffer entirely (it's all entity/suggestions data)
        if ($this->entityBlockSuppressed) {
            $this->suggestionsBuffer = '';
            $this->entityBlockSuppressed = false;
        }

        // Flush any remaining suggestions buffer content (strip headings/JSON/entity blocks, emit clean text)
        if ($this->suggestionsBuffer !== '') {
            $buffered = (string) preg_replace('/\s*(<br\s*\/?>)*\s*(?:\*{3,}|-{3,}|_{3,})?\s*(<br\s*\/?>)*\s*,*\s*(?:\bmeta\b\s*(<br\s*\/?>|\n)*\s*)?(```[\w]*\s*(<br\s*\/?>|\s)*)?\[?\s*\{[\s\S]*\}\s*\]?\s*,*\s*(```\s*)?(<br\s*\/?>|\s)*$/si', '', $this->suggestionsBuffer);
            // Strip trailing markdown horizontal rule with nothing after it.
            $buffered = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*(?:\*{3,}|-{3,}|_{3,})\s*(<br\s*\/?>|\n)*\s*$/', '', $buffered);
            // Strip :::meta / :::entity-highlights blocks or bare label text
            $buffered = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*:::\s*(meta|entity[- ]?highlights?)[\s\S]*$/i', '', $buffered);
            $buffered = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*:::?\s*(<br\s*\/?>|\n)*\s*(entity[- ]?highlights?\s*(<br\s*\/?>|\n)*\s*)?(\[?\{[\s\S]*"text"\s*:[\s\S]*)?$/i', '', $buffered);
            $buffered = (string) preg_replace('/\s*(<br\s*\/?>|\n)*\s*entity[- ]?highlights?\s*$/i', '', $buffered);
            $buffered = rtrim($buffered);
            if ($buffered !== '') {
                echo PHP_EOL;
                echo "event: data\n";
                echo 'data: ' . $buffered;
                echo "\n\n";
                $this->safeFlush();
            }
            $this->suggestionsBuffer = '';
        }

        if ($this->shouldGenerateSuggestions) {
            $suggestionsPayload = null;

            // When entity highlight is active, suggestions come from inside the :::meta block.
            if ($this->entityHighlightsEnabled) {
                $metaSuggestions = EntityHighlightService::parseSuggestions($output);
                if (is_array($metaSuggestions) && ! empty($metaSuggestions)) {
                    $suggestionsPayload = ['suggestions' => $metaSuggestions];
                }
            }

            // Fallback: standalone {"suggestions":[...]} JSON at the end of the response.
            if ($suggestionsPayload === null) {
                [$parsed, $cleanText] = $this->parseSuggestionsFromBuffer($responsedText);
                if ($parsed !== null) {
                    $responsedText = $cleanText;
                    $output = (string) preg_replace('/\s*(<br\s*\/?>)*\s*(```[\w]*\s*(<br\s*\/?>|\s)*)?\{"suggestions"\s*:\s*\[.*\]\s*\}\s*(```\s*)?(<br\s*\/?>|\s)*$/si', '', $output);
                    $suggestionsPayload = $parsed;
                }
            }

            if ($suggestionsPayload !== null) {
                $this->emitSuggestions($suggestionsPayload, $main_message);
            }

            $this->shouldGenerateSuggestions = false;
        }

        // Emit used skills event (for manual skills that didn't go through function calling)
        $this->emitUsedSkills();

        // Persist and emit entity highlights BEFORE [DONE] so frontend receives them
        if (! $this->tempChatActive && ! $this->isCouncilSubRequest && MarketplaceHelper::isRegistered('ai-chat-pro-entity-highlight')) {
            $output = $this->persistEntityHighlights($output, (int) $main_message->id, (int) $main_message->user_id);
            $responsedText = EntityHighlightService::stripAnnotationBlock($responsedText);
            $this->entityHighlightsEnabled = false;
        }

        echo "event: stop\n";
        echo 'data: [DONE]';
        echo "\n\n";
        $this->safeFlush();

        if ($this->tempChatActive) {
            if ($chat) {
                $this->addToTempHistory($chat->id, [
                    'role'    => 'assistant',
                    'content' => $responsedText,
                ]);

                $chat->messages()->delete();
            }

            return;
        }

        // Strip suggestions JSON from output before saving
        $output = $this->stripSuggestionsJson($output);
        $responsedText = $this->stripSuggestionsJson($responsedText);

        // Strip leaked function-call text (e.g. search_images({"query":"..."})) so it doesn't pollute chat history
        $output = preg_replace('/search_images\s*\(\s*\{[^}]*\}\s*\)/', '', $output);
        $responsedText = preg_replace('/search_images\s*\(\s*\{[^}]*\}\s*\)/', '', $responsedText);

        // Persist smart images to database if present in output
        $this->persistSmartImages($main_message, $output);

        $main_message->response = $responsedText;
        $main_message->output = $output;
        $main_message->credits = $total_used_tokens;
        $main_message->words = $total_used_tokens;

        if (! empty($this->usedSkills)) {
            $main_message->used_skills = $this->usedSkills;
        }

        if (! empty($this->usedConnectors)) {
            $main_message->used_connectors = $this->usedConnectors;
        }

        $main_message->save();

        if ($chat) {
            $chat->total_credits += $total_used_tokens;
            $chat->save();
        }

        $driver?->input($responsedText)->calculateCredit()->decreaseCredit();
        Usage::getSingle()->updateWordCounts($driver?->calculate());
    }

    /**
     * Extract and persist smart images from the stream output to the database.
     */
    private function persistSmartImages($main_message, string $output): void
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-smart-image')) {
            return;
        }

        if (preg_match('/:::smart-images\s*\n(.*?)\n\s*:::/s', $output, $matches)) {
            try {
                $images = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
                if (! empty($images) && is_array($images)) {
                    SmartImageService::saveForMessage(
                        $main_message->id,
                        $main_message->user_id,
                        $images[0]['title'] ?? 'image search',
                        $images
                    );
                }
            } catch (Throwable $e) {
                // Silently fail — images will still display from the output
            }
        }
    }

    private function saveOtherStreamResponse($entry, $title, $responsedText, $output, $total_used_tokens, $driver): void
    {
        $entry->title = $title ?: null;
        $entry->credits = $total_used_tokens;
        $entry->words = $total_used_tokens;
        $entry->response = $responsedText;
        $entry->output = $output;
        $entry->save();

        $driver->input($responsedText)->calculateCredit()->decreaseCredit();
        Usage::getSingle()->updateWordCounts($driver->calculate());
    }

    /**
     * Get temporary chat history from session
     */
    private function getTempChatHistory(string $chatId): array
    {
        if (! $this->tempChatActive) {
            return [];
        }

        if (! auth()->check()) {
            return [];
        }

        $sessionKey = auth()->user()->id . '_' . $this->tempChatSessionKey . $chatId;

        return Session::get($sessionKey, []);
    }

    /**
     * Store temporary chat history in session
     */
    private function storeTempChatHistory(string $chatId, array $history): void
    {
        if (! $this->tempChatActive) {

            return;
        }

        if (! auth()->check()) {
            return;
        }

        // Limit history to last 20 messages to prevent session bloat
        $limitedHistory = array_slice($history, -20);

        $sessionKey = auth()->user()->id . '_' . $this->tempChatSessionKey . $chatId;

        Session::put($sessionKey, $limitedHistory);
    }

    /**
     * Add message to temporary chat history
     */
    private function addToTempHistory(string $chatId, array $message): void
    {
        if (! $this->tempChatActive) {
            return;
        }

        if (! auth()->check()) {
            return;
        }

        $history = $this->getTempChatHistory($chatId);
        $history[] = $message;

        $this->storeTempChatHistory($chatId, $history);
    }

    /**
     * Remove duplicate messages from history
     */
    private function removeDuplicateMessages(array $history): array
    {
        $seen = [];
        $filtered = [];

        foreach ($history as $message) {
            $key = $message['role'] . '|' . (is_array($message['content']) ? json_encode($message['content']) : $message['content']);

            if (! in_array($key, $seen)) {
                $seen[] = $key;
                $filtered[] = $message;
            }
        }

        return $filtered;
    }

    /**
     * Clear temporary chat history
     */
    public function clearTempChatHistory(bool $deleteConversations = true): void
    {
        if (! auth()->check()) {
            return;
        }

        $userId = auth()->user()->id;
        $sessionKeyPrefix = $userId . '_' . $this->tempChatSessionKey;

        // Get all session keys
        $allSessionKeys = array_keys(Session::all());

        // Filter keys that match our temp chat pattern
        $tempChatKeys = array_filter($allSessionKeys, static function ($key) use ($sessionKeyPrefix) {
            return str_starts_with($key, $sessionKeyPrefix);
        });

        $deletedConversations = [];
        $errors = [];

        // Remove each temp chat session and optionally delete conversations
        foreach ($tempChatKeys as $key) {
            try {
                // Extract chatId from the session key
                $chatId = $this->extractChatIdFromSessionKey($key, $sessionKeyPrefix);

                if ($chatId && $deleteConversations) {
                    // Delete the conversation from database
                    $deleted = $this->deleteConversation($chatId);
                    if ($deleted) {
                        $deletedConversations[] = $chatId;
                    }
                }

                // Remove from session
                Session::forget($key);
            } catch (Exception $e) {
                $errors[] = "Error with session {$key}: " . $e->getMessage();
            }
        }

        if (! empty($errors)) {
            Log::warning('Errors during temp chat cleanup: ' . json_encode($errors));
        }
    }

    /**
     * Extract chatId from session key
     */
    private function extractChatIdFromSessionKey(string $sessionKey, string $prefix): ?string
    {
        // Remove the prefix to get just the chatId
        if (str_starts_with($sessionKey, $prefix)) {
            $chatId = substr($sessionKey, strlen($prefix));

            // Validate that it's a valid chat ID (numeric or UUID format)
            if (is_numeric($chatId)) {
                return $chatId;
            }
        }

        return null;
    }

    /**
     * Delete conversation from database
     */
    private function deleteConversation(string $chatId): bool
    {
        try {
            $chat = UserOpenaiChat::find($chatId);
            if (! $chat) {
                return false;
            }

            // Delete the chat itself
            $chat?->delete();

            return true;
        } catch (Exception $e) {
            Log::error("Error deleting conversation {$chatId}: " . $e->getMessage());

            return false;
        }
    }
}
