<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowCopilotMessage;
use App\Extensions\AIAgent\System\Services\CopilotService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CopilotController extends Controller
{
    public function __construct(
        private readonly CopilotService $copilotService,
    ) {}

    public function chat(Request $request, AIAgentWorkflow $workflow): StreamedResponse
    {
        $request->validate([
            'message'        => ['required', 'string', 'max:4000'],
            'steps'          => ['nullable', 'array'],
            'channels'       => ['nullable', 'array'],
            'connectors'     => ['nullable', 'array'],
            'trigger_type'   => ['nullable', 'string'],
            'trigger_config' => ['nullable', 'array'],
        ]);

        if ($workflow->user_id !== Auth::id()) {
            abort(403);
        }

        $userMessage = $request->string('message')->trim()->toString();
        $steps = $request->input('steps', []);
        $channels = $request->input('channels', []);
        $connectors = $request->input('connectors', []);
        $triggerType = $request->input('trigger_type');
        $triggerConfig = $request->input('trigger_config', []);

        // Persist user message
        $this->copilotService->saveMessage($workflow, 'user', $userMessage);

        // Load history (excluding the just-saved user message — it's in $messages as the last item)
        $history = $this->copilotService->loadHistory($workflow, turns: 20);
        // Remove the last item from history since we'll pass it as userMessage
        $history = array_slice($history, 0, count($history) - 1);

        return response()->stream(function () use ($workflow, $steps, $channels, $connectors, $triggerType, $triggerConfig, $history, $userMessage): void {
            set_time_limit(120);

            // Disable all output buffering so SSE chunks reach the browser immediately
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', '0');
            @ob_implicit_flush(true);
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $buffer = [
                'text'                    => '',
                'tool_calls'              => [],
                'thinking_content'        => '',
                'thinking_total_duration' => 0,
                'thinking_started'        => null,
                'thinking_has_data'       => false,
            ];

            $this->copilotService->streamChat(
                workflow: $workflow,
                steps: $steps,
                channels: $channels,
                connectors: $connectors,
                triggerType: $triggerType,
                triggerConfig: $triggerConfig,
                history: $history,
                userMessage: $userMessage,
                emit: function (array $event) use (&$buffer): void {
                    if ($event['type'] === 'text_delta') {
                        $buffer['text'] .= $event['payload']['text'] ?? $event['payload'];
                    } elseif ($event['type'] === 'tool_call_end') {
                        $buffer['tool_calls'][] = $event['payload'];
                    } elseif ($event['type'] === 'tool_call') {
                        $buffer['tool_calls'][] = $event['payload'];
                    } elseif ($event['type'] === 'thinking_start') {
                        $buffer['thinking_started'] = microtime(true);
                        $buffer['thinking_has_data'] = true;
                        $event['payload']['started_at'] = (int) round($buffer['thinking_started'] * 1000);
                    } elseif ($event['type'] === 'thinking_delta') {
                        $buffer['thinking_content'] .= $event['payload']['text'] ?? '';
                    } elseif ($event['type'] === 'thinking_end') {
                        if ($buffer['thinking_started'] !== null) {
                            $elapsed = (int) round((microtime(true) - $buffer['thinking_started']) * 1000);
                            $buffer['thinking_total_duration'] += $elapsed;
                            $buffer['thinking_started'] = null;
                            $event['payload']['duration_ms'] = $elapsed;
                        }
                    }

                    echo 'data: ' . json_encode($event) . "\n\n";

                    @ob_flush();
                    @flush();
                },
            );

            $metadata = null;
            if ($buffer['thinking_has_data']) {
                $metadata = [
                    'thinking' => [
                        'content'     => $buffer['thinking_content'],
                        'duration_ms' => $buffer['thinking_total_duration'],
                    ],
                ];
            }

            // Persist assistant response
            $this->copilotService->saveMessage(
                $workflow,
                'assistant',
                $buffer['text'],
                empty($buffer['tool_calls']) ? null : $buffer['tool_calls'],
                $metadata,
            );
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function history(AIAgentWorkflow $workflow): JsonResponse
    {
        if ($workflow->user_id !== Auth::id()) {
            abort(403);
        }

        $messages = AIAgentWorkflowCopilotMessage::query()
            ->where('workflow_id', $workflow->id)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'tool_calls', 'metadata'])
            ->map(fn (AIAgentWorkflowCopilotMessage $m) => [
                'id'        => $m->id,
                'role'      => $m->role,
                'content'   => $m->content ?? '',
                'toolCalls' => $m->tool_calls ?? [],
                'metadata'  => $m->metadata ?? null,
            ]);

        return response()->json(['data' => $messages]);
    }
}
