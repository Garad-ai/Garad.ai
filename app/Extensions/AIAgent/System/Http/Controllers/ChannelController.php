<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Extensions\AIAgent\System\Connectors\ConnectorRegistry;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Exceptions\PlanLimitExceededException;
use App\Extensions\AIAgent\System\Http\Requests\StoreChannelRequest;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Services\AIAgentPlanService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ChannelController extends Controller
{
    public function __construct(
        private readonly AIAgentPlanService $planService,
        private readonly ConnectorRegistry $connectorRegistry,
    ) {}

    public function index(): View
    {
        $channels = AIAgentChannel::query()
            ->where('user_id', Auth::id())
            ->where('type', '!=', ChannelEnum::MagicAi->value)
            ->latest()
            ->get();

        return view('ai-agent::channels.index', [
            'title'    => __('Connected Channels'),
            'channels' => $channels,
        ]);
    }

    public function create(): View
    {
        $channelTypes = array_values(array_filter(
            ChannelEnum::cases(),
            fn (ChannelEnum $c) => $c !== ChannelEnum::MagicAi,
        ));

        return view('ai-agent::channels.create', [
            'title'        => __('Connect a Channel'),
            'channelTypes' => $channelTypes,
        ]);
    }

    public function store(StoreChannelRequest $request): JsonResponse|RedirectResponse
    {
        if (Helper::appIsDemo()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
            }

            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        try {
            $this->planService->checkChannelLimit(Auth::user());
        } catch (PlanLimitExceededException $e) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }

            return back()->with(['type' => 'error', 'message' => $e->getMessage()]);
        }

        $data = $request->validated();
        $data['user_id'] = Auth::id();

        Log::info('[AIAgent] Creating channel', [
            'user_id' => Auth::id(),
            'type'    => $data['type'] ?? null,
            'name'    => $data['name'] ?? null,
        ]);

        /** @var AIAgentChannel $channel */
        $channel = AIAgentChannel::query()->create($data);

        Log::info('[AIAgent] Channel created', ['channel_id' => $channel->id]);

        // Register webhook if the connector supports it
        if ($this->connectorRegistry->has($channel->type)) {
            try {
                $this->connectorRegistry->resolve($channel->type, $channel)->registerWebhook();
                Log::info('[AIAgent] Webhook registered successfully', ['channel_id' => $channel->id]);
            } catch (Exception $e) {
                Log::channel('daily')->error('[AIAgent] Webhook registration failed', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
                $channel->delete();

                if ($request->expectsJson()) {
                    return response()->json(['status' => 'error', 'message' => __('Webhook registration failed: ') . $e->getMessage()], 422);
                }

                return back()->with(['type' => 'error', 'message' => __('Webhook registration failed: ') . $e->getMessage()]);
            }
        }

        if ($request->expectsJson()) {
            $webhookUrl = null;
            $refreshWebhookUrl = null;

            try {
                if ($channel->type === ChannelEnum::Telegram) {
                    $refreshWebhookUrl = route('dashboard.user.ai-agent.channels.refresh-webhook', $channel);
                } elseif ($channel->type === ChannelEnum::Whatsapp) {
                    $webhookUrl = route('api.ai-agent.whatsapp.webhook', ['channel' => $channel->id]);
                } elseif ($channel->type === ChannelEnum::Slack) {
                    $webhookUrl = route('api.ai-agent.slack.webhook', ['channel' => $channel->id]);
                }
            } catch (Throwable) {
            }

            return response()->json([
                'status'  => 'success',
                'message' => __('Channel connected successfully.'),
                'channel' => [
                    'id'                  => $channel->id,
                    'name'                => $channel->name,
                    'type'                => $channel->type->value,
                    'type_label'          => $channel->type->label(),
                    'webhook_url'         => $webhookUrl,
                    'refresh_webhook_url' => $refreshWebhookUrl,
                    'delete_url'          => route('dashboard.user.ai-agent.channels.destroy', $channel),
                ],
            ]);
        }

        return redirect()
            ->route('dashboard.user.ai-agent.channels.index')
            ->with(['type' => 'success', 'message' => __('Channel connected successfully.')]);
    }

    public function destroy(AIAgentChannel $channel, Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $channel);

        if (Helper::appIsDemo()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
            }

            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $channel->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => __('Channel removed.')]);
        }

        return redirect()
            ->route('dashboard.user.ai-agent.channels.index')
            ->with(['type' => 'success', 'message' => __('Channel removed.')]);
    }

    public function refreshWebhook(AIAgentChannel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        if (! $this->connectorRegistry->has($channel->type)) {
            return response()->json(['message' => 'Not supported for this channel type.'], 422);
        }

        try {
            $this->connectorRegistry->resolve($channel->type, $channel)->registerWebhook();

            return response()->json(['message' => __('Webhook refreshed successfully.')]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function checkStatus(AIAgentChannel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        try {
            if ($channel->type === ChannelEnum::Telegram) {
                $token = $channel->getCredential('telegram_token');

                if (empty($token)) {
                    return response()->json(['connected' => false, 'message' => __('Telegram API Error: Token not found')]);
                }

                $response = Http::timeout(5)->get("https://api.telegram.org/bot{$token}/getMe");

                if ($response->failed() || ! $response->json('ok')) {
                    $description = $response->json('description', __('Invalid token'));

                    return response()->json(['connected' => false, 'message' => __('Telegram API Error: :error', ['error' => $description])]);
                }

                return response()->json(['connected' => true, 'message' => null]);
            }

            if ($channel->type === ChannelEnum::Whatsapp) {
                $phoneNumberId = $channel->getCredential('whatsapp_phone_number_id');
                $accessToken = $channel->getCredential('whatsapp_access_token');

                if (empty($phoneNumberId) || empty($accessToken)) {
                    return response()->json(['connected' => false, 'message' => __('WhatsApp API Error: Key not found')]);
                }

                $response = Http::timeout(5)
                    ->withToken($accessToken)
                    ->get("https://graph.facebook.com/v20.0/{$phoneNumberId}");

                if ($response->failed()) {
                    $error = $response->json('error.message', __('Invalid credentials'));

                    return response()->json(['connected' => false, 'message' => __('WhatsApp API Error: :error', ['error' => $error])]);
                }

                return response()->json(['connected' => true, 'message' => null]);
            }

            return response()->json(['connected' => true, 'message' => null]);
        } catch (Exception $e) {
            Log::warning('[AIAgent] Channel status check failed', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);

            return response()->json(['connected' => false, 'message' => $e->getMessage()]);
        }
    }
}
