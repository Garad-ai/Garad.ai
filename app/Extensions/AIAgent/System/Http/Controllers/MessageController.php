<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentConversation;
use App\Extensions\AIAgent\System\Models\AIAgentMessage;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Services\AIAgentConversationExportService;
use App\Extensions\AIAgent\System\Services\MagicAiChannelService;
use App\Extensions\AIAgent\System\Services\UnreadMessagesService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MessageController extends Controller
{
    public function __construct(
        private readonly MagicAiChannelService $magicAiChannelService,
        private readonly WorkflowEngine $workflowEngine,
        private readonly UnreadMessagesService $unreadMessages,
        private readonly AIAgentConversationExportService $exportService,
    ) {}

    public function index(): View
    {
        $this->magicAiChannelService->ensureExists(Auth::id());
        $this->unreadMessages->markSeen(Auth::id());

        $channels = AIAgentChannel::query()
            ->where('user_id', Auth::id())
            ->where('type', '!=', ChannelEnum::MagicAi->value)
            ->orderBy('name')
            ->get();

        $workflows = AIAgentWorkflow::query()
            ->where('user_id', Auth::id())
            ->where('status', WorkflowStatusEnum::Active)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('ai-agent::messages.index', [
            'title'     => __('Channel Messages'),
            'channels'  => $channels,
            'workflows' => $workflows,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'workflow_id' => ['required', 'integer'],
            'message'     => ['required', 'string', 'max:10000'],
        ]);

        $workflow = AIAgentWorkflow::query()
            ->where('user_id', Auth::id())
            ->findOrFail($request->integer('workflow_id'));

        $channel = $this->magicAiChannelService->ensureExists(Auth::id());
        $senderId = 'wf_' . $workflow->id;
        $messageText = $request->string('message')->toString();

        $conversation = AIAgentConversation::query()->firstOrCreate(
            ['channel_id' => $channel->id, 'sender_id' => $senderId],
        );

        AIAgentMessage::query()->create([
            'channel_id'      => $channel->id,
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'sender_id'       => $senderId,
            'content'         => $messageText,
            'status'          => 'received',
        ]);

        $conversation->update(['last_message_at' => now()]);
        $channel->update(['last_message_at' => now()]);

        $triggerData = [
            'trigger_type'    => 'web',
            'fired_at'        => now()->toIso8601String(),
            'channel_id'      => $channel->id,
            'conversation_id' => $conversation->id,
            'sender_id'       => $senderId,
            'message_text'    => $messageText,
        ];

        $run = $this->workflowEngine->run($workflow, $triggerData, [
            'message_text'    => $messageText,
            'sender_id'       => $senderId,
            'conversation_id' => $conversation->id,
            'web_reply_mode'  => true,
            'web_channel_id'  => $channel->id,
        ]);

        $webOutboundExists = AIAgentMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('workflow_run_id', $run->id)
            ->where('direction', 'outbound')
            ->exists();

        if (! $webOutboundExists) {
            $responseText = $this->extractResponseFromRun($run->steps_output ?? []);

            if ($responseText) {
                AIAgentMessage::query()->create([
                    'channel_id'      => $channel->id,
                    'conversation_id' => $conversation->id,
                    'workflow_run_id' => $run->id,
                    'direction'       => 'outbound',
                    'sender_id'       => $senderId,
                    'content'         => $responseText,
                    'status'          => 'sent',
                ]);
            }
        }

        return response()->json(['status' => 'ok', 'conversation_id' => $conversation->id]);
    }

    public function reply(Request $request, AIAgentConversation $conversation): JsonResponse
    {
        $this->assertConversationOwnership($conversation);

        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $messageText = $request->string('message')->toString();
        $channel = $conversation->channel;

        AIAgentMessage::query()->create([
            'channel_id'      => $channel->id,
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'sender_id'       => $conversation->sender_id,
            'content'         => $messageText,
            'status'          => 'received',
        ]);

        $conversation->update(['last_message_at' => now()]);

        $incomingMessage = new IncomingMessage(
            channel: $channel->type,
            senderId: $conversation->sender_id,
            text: $messageText,
            conversationId: $conversation->id,
        );

        $runs = $this->workflowEngine->fireChannelMessageTriggerForWeb($channel, $incomingMessage);

        foreach ($runs as $run) {
            $outboundExists = AIAgentMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('workflow_run_id', $run->id)
                ->where('direction', 'outbound')
                ->exists();

            if (! $outboundExists) {
                $responseText = $this->extractResponseFromRun($run->steps_output ?? []);

                if ($responseText) {
                    AIAgentMessage::query()->create([
                        'channel_id'      => $channel->id,
                        'conversation_id' => $conversation->id,
                        'workflow_run_id' => $run->id,
                        'direction'       => 'outbound',
                        'sender_id'       => $conversation->sender_id,
                        'content'         => $responseText,
                        'status'          => 'sent',
                    ]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function conversations(Request $request): JsonResponse
    {
        $channelIds = AIAgentChannel::query()
            ->where('user_id', Auth::id())
            ->pluck('id');

        $conversations = AIAgentConversation::query()
            ->whereIn('channel_id', $channelIds)
            ->when($request->input('channel_id'), fn ($q, $id) => $q->where('channel_id', $id))
            ->with(['channel:id,name,type', 'workflow:id,name,description,avatar,status,last_run_at,created_at'])
            ->orderByDesc('pinned')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        $conversations->getCollection()->transform(function (AIAgentConversation $conv) {
            $last = AIAgentMessage::query()
                ->where('conversation_id', $conv->id)
                ->latest()
                ->value('content');

            $conv->last_message_preview = $last ? mb_strimwidth($last, 0, 80, '…') : '';

            return $conv;
        });

        // Handle legacy wf_-prefixed sender_id conversations (copilot flows)
        $wfPrefixIds = $conversations->getCollection()
            ->filter(fn ($conv) => str_starts_with($conv->sender_id ?? '', 'wf_'))
            ->map(fn ($conv) => (int) substr($conv->sender_id, 3))
            ->unique()
            ->values();

        $legacyWorkflows = $wfPrefixIds->isNotEmpty()
            ? AIAgentWorkflow::query()
                ->whereIn('id', $wfPrefixIds)
                ->get(['id', 'name', 'description', 'avatar', 'status', 'last_run_at', 'created_at'])
                ->keyBy('id')
            : collect();

        $conversations->getCollection()->transform(function (AIAgentConversation $conv) use ($legacyWorkflows) {
            $workflow = null;

            if (str_starts_with($conv->sender_id ?? '', 'wf_')) {
                $workflow = $legacyWorkflows->get((int) substr($conv->sender_id, 3));
            } elseif ($conv->workflow_id && $conv->relationLoaded('workflow')) {
                $workflow = $conv->workflow;
            }

            if ($workflow) {
                $conv->workflow_name = $workflow->name;
                $conv->agent = [
                    'id'          => $workflow->id,
                    'name'        => $workflow->name,
                    'description' => $workflow->description,
                    'avatar'      => $workflow->avatar_url,
                    'is_active'   => $workflow->isActive(),
                    'created_at'  => $workflow->created_at?->format('Y-m-d'),
                    'last_run_at' => $workflow->last_run_at?->diffForHumans(),
                ];
            }

            return $conv;
        });

        return response()->json($conversations);
    }

    public function thread(AIAgentConversation $conversation): JsonResponse
    {
        $this->assertConversationOwnership($conversation);

        $messages = AIAgentMessage::query()
            ->where('conversation_id', $conversation->id)
            ->oldest()
            ->get(['id', 'direction', 'content', 'raw_payload', 'created_at']);

        return response()->json($messages);
    }

    public function pin(AIAgentConversation $conversation): JsonResponse
    {
        $this->assertConversationOwnership($conversation);

        if ((int) $conversation->pinned === 0) {
            $userChannelIds = AIAgentChannel::query()
                ->where('user_id', Auth::id())
                ->pluck('id');

            $maxPinned = (int) AIAgentConversation::query()
                ->whereIn('channel_id', $userChannelIds)
                ->max('pinned');

            $conversation->update(['pinned' => $maxPinned + 1]);
        } else {
            $conversation->update(['pinned' => 0]);
        }

        return response()->json([
            'status' => 'ok',
            'pinned' => $conversation->pinned,
        ]);
    }

    public function close(AIAgentConversation $conversation): JsonResponse
    {
        $this->assertConversationOwnership($conversation);

        $conversation->update([
            'closed_at' => $conversation->closed_at ? null : now(),
        ]);

        return response()->json([
            'status'    => 'ok',
            'closed_at' => $conversation->closed_at?->toIso8601String(),
        ]);
    }

    public function destroy(AIAgentConversation $conversation): JsonResponse
    {
        $this->assertConversationOwnership($conversation);

        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json(['status' => 'ok']);
    }

    public function export(Request $request, AIAgentConversation $conversation): SymfonyResponse
    {
        $this->assertConversationOwnership($conversation);

        $format = $request->string('format', 'txt')->toString();

        return $this->exportService->export($conversation, $format);
    }

    public function relatedHistory(AIAgentConversation $conversation): JsonResponse
    {
        $this->assertConversationOwnership($conversation);

        $userChannelIds = AIAgentChannel::query()
            ->where('user_id', Auth::id())
            ->pluck('id');

        $related = AIAgentConversation::query()
            ->whereIn('channel_id', $userChannelIds)
            ->where('sender_id', $conversation->sender_id)
            ->where('id', '!=', $conversation->id)
            ->with(['channel:id,name,type'])
            ->orderByDesc('last_message_at')
            ->get();

        $lastSeenAt = $this->unreadMessages->lastSeenAt(Auth::id());

        $related->transform(function (AIAgentConversation $conv) use ($lastSeenAt) {
            $last = AIAgentMessage::query()
                ->where('conversation_id', $conv->id)
                ->latest()
                ->value('content');

            $unreadCount = AIAgentMessage::query()
                ->where('conversation_id', $conv->id)
                ->where('direction', 'inbound')
                ->where('created_at', '>', $lastSeenAt)
                ->count();

            $conv->last_message_preview = $last ? mb_strimwidth($last, 0, 80, '…') : '';
            $conv->unread_count = $unreadCount;

            return $conv;
        });

        return response()->json($related);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['count' => $this->unreadMessages->unreadCount(Auth::id())]);
    }

    public function markSeen(): JsonResponse
    {
        $this->unreadMessages->markSeen(Auth::id());

        return response()->json(['status' => 'success']);
    }

    private function assertConversationOwnership(AIAgentConversation $conversation): void
    {
        abort_unless(
            AIAgentChannel::query()
                ->where('id', $conversation->channel_id)
                ->where('user_id', Auth::id())
                ->exists(),
            403
        );
    }

    /**
     * Walk steps_output in reverse to find the first available AI/sent response text.
     */
    private function extractResponseFromRun(array $stepsOutput): ?string
    {
        foreach (array_reverse($stepsOutput) as $step) {
            $output = $step['output'] ?? [];

            if (! empty($output['last_sent_message'])) {
                return $output['last_sent_message'];
            }

            if (! empty($output['ai_response'])) {
                return $output['ai_response'];
            }
        }

        return null;
    }
}
