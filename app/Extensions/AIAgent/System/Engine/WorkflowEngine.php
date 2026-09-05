<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Engine;

use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowRunStatusEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentConversation;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Triggers\ChannelMessageTrigger;
use App\Extensions\AIAgent\System\Triggers\ScheduleTrigger;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WorkflowEngine
{
    public function __construct(
        private readonly ActionDispatcher $dispatcher,
        private readonly ScheduleTrigger $scheduleTrigger,
        private readonly ChannelMessageTrigger $channelMessageTrigger,
    ) {}

    /**
     * Poll all active schedule-triggered workflows and fire any that are due.
     */
    public function processScheduledWorkflows(?Command $command = null): int
    {
        $candidates = AIAgentWorkflow::query()
            ->where('status', WorkflowStatusEnum::Active)
            ->where('trigger_type', TriggerTypeEnum::Schedule)
            ->get();

        $command?->line("[AIAgent] Found {$candidates->count()} active scheduled workflow(s).");

        $fired = 0;

        foreach ($candidates as $workflow) {
            $due = $this->scheduleTrigger->shouldFire($workflow);

            $command?->line("[AIAgent] Workflow #{$workflow->id} \"{$workflow->name}\" — " . ($due ? 'due, firing...' : 'not due, skipping.'));
            Log::info('[AIAgent] Schedule check', [
                'workflow_id'   => $workflow->id,
                'workflow_name' => $workflow->name,
                'due'           => $due,
                'next_run_at'   => $workflow->next_run_at?->toIso8601String(),
            ]);

            if (! $due) {
                continue;
            }

            $triggerData = $this->scheduleTrigger->buildTriggerData($workflow);
            $run = $this->run($workflow, $triggerData);
            $this->updateNextRunAt($workflow);
            $fired++;

            $status = $run->status->value;
            $command?->{$status === 'info' || $status === 'completed' ? 'info' : 'error'}(
                "[AIAgent] Workflow #{$workflow->id} run #{$run->id} — {$status}."
            );

            if ($run->error_message) {
                $command?->error("[AIAgent] Error: {$run->error_message}");
            }
        }

        return $fired;
    }

    /**
     * Fire all active channel_message workflows that match the incoming message.
     */
    public function fireChannelMessageTrigger(AIAgentChannel $channel, IncomingMessage $message): void
    {
        $this->channelMessageTrigger->setContext($channel, $message);

        AIAgentWorkflow::query()
            ->where('user_id', $channel->user_id)
            ->where('status', WorkflowStatusEnum::Active)
            ->where('trigger_type', TriggerTypeEnum::ChannelMessage)
            ->get()
            ->filter(fn (AIAgentWorkflow $w) => $this->channelMessageTrigger->shouldFire($w))
            ->each(function (AIAgentWorkflow $workflow) use ($message): void {
                if ($message->conversationId) {
                    AIAgentConversation::query()
                        ->where('id', $message->conversationId)
                        ->whereNull('workflow_id')
                        ->update(['workflow_id' => $workflow->id]);
                }

                $triggerData = $this->channelMessageTrigger->buildTriggerData($workflow);

                $initialContext = [
                    'message_text'        => $message->text,
                    'sender_id'           => $message->senderId,
                    'conversation_id'     => $message->conversationId,
                    'message_attachments' => $message->attachments,
                ];

                $this->run($workflow, $triggerData, $initialContext);
            });
    }

    /**
     * Run all matching channel-message workflows from a web reply — connector sends suppressed.
     *
     * @return Collection<int, AIAgentWorkflowRun>
     */
    public function fireChannelMessageTriggerForWeb(AIAgentChannel $channel, IncomingMessage $message): Collection
    {
        $this->channelMessageTrigger->setContext($channel, $message);

        return AIAgentWorkflow::query()
            ->where('user_id', $channel->user_id)
            ->where('status', WorkflowStatusEnum::Active)
            ->where('trigger_type', TriggerTypeEnum::ChannelMessage)
            ->get()
            ->filter(fn (AIAgentWorkflow $w) => $this->channelMessageTrigger->shouldFire($w))
            ->map(function (AIAgentWorkflow $workflow) use ($message): AIAgentWorkflowRun {
                if ($message->conversationId) {
                    AIAgentConversation::query()
                        ->where('id', $message->conversationId)
                        ->whereNull('workflow_id')
                        ->update(['workflow_id' => $workflow->id]);
                }

                $triggerData = $this->channelMessageTrigger->buildTriggerData($workflow);

                $initialContext = [
                    'message_text'        => $message->text,
                    'sender_id'           => $message->senderId,
                    'conversation_id'     => $message->conversationId,
                    'message_attachments' => $message->attachments,
                    'web_reply_mode'      => true,
                    'web_channel_id'      => $channel->id,
                ];

                return $this->run($workflow, $triggerData, $initialContext);
            })
            ->values();
    }

    /**
     * Fire a webhook-triggered workflow directly (called from the webhook controller).
     */
    public function fireWebhookTrigger(AIAgentWorkflow $workflow, array $payload = []): void
    {
        $triggerData = [
            'trigger_type' => 'webhook',
            'fired_at'     => now()->toIso8601String(),
            'payload'      => $payload,
        ];

        $this->run($workflow, $triggerData, $payload);
    }

    /**
     * Execute a workflow: create a run record, execute each step in sequence.
     */
    public function run(
        AIAgentWorkflow $workflow,
        array $triggerData = [],
        array $initialContext = [],
    ): AIAgentWorkflowRun {
        /** @var AIAgentWorkflowRun $run */
        $run = AIAgentWorkflowRun::query()->create([
            'workflow_id'  => $workflow->id,
            'status'       => WorkflowRunStatusEnum::Running,
            'trigger_data' => $triggerData,
            'started_at'   => now(),
        ]);

        $context = array_merge($initialContext, $triggerData);
        $stepsOutput = [];

        try {
            $steps = $this->sortSteps($workflow->resolved_steps);

            foreach ($steps as $index => $stepDef) {
                $type = $stepDef['type'] ?? $stepDef['action'] ?? '';
                $config = $this->interpolateConfig($stepDef['config'] ?? [], $context);
                $this->assertNoUnresolvedVariables($config, $type, $index);
                $action = $this->dispatcher->resolve($type);
                $context = $action->execute($config, $context, $workflow, $run);

                $stepsOutput[$index] = [
                    'type'   => $type,
                    'status' => 'completed',
                    'output' => $context,
                ];
            }

            $run->update([
                'status'       => WorkflowRunStatusEnum::Completed,
                'steps_output' => $stepsOutput,
                'completed_at' => now(),
            ]);

            $workflow->update(['last_run_at' => now()]);
        } catch (Exception $e) {
            Log::error('AIAgent WorkflowEngine error', [
                'workflow_id' => $workflow->id,
                'run_id'      => $run->id,
                'error'       => $e->getMessage(),
            ]);

            $run->update([
                'status'        => WorkflowRunStatusEnum::Failed,
                'steps_output'  => $stepsOutput,
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }

        return $run;
    }

    private function updateNextRunAt(AIAgentWorkflow $workflow): void
    {
        $cron = $workflow->trigger_config['cron'] ?? null;

        if (empty($cron)) {
            return;
        }

        try {
            $nextRun = $this->scheduleTrigger->nextRunAt($cron);
            $workflow->update(['next_run_at' => $nextRun]);
        } catch (Exception) {
            // Invalid cron — skip silently; was already logged in execute.
        }
    }

    /**
     * Config keys whose value is free-form prose written for a model. A `{word}` in there is
     * part of the instructions, not a variable reference, so it is never treated as unresolved.
     */
    private const UNCHECKED_CONFIG_KEYS = ['system_prompt'];

    /**
     * Execute steps in their declared `order`, falling back to array position when absent.
     * Guarantees a step that produces a variable runs before the step that consumes it.
     *
     * @param  array<int, array<string, mixed>>  $steps
     *
     * @return array<int, array<string, mixed>>
     */
    private function sortSteps(array $steps): array
    {
        $ordered = array_values($steps);

        usort($ordered, static fn (array $a, array $b): int => ($a['order'] ?? PHP_INT_MAX) <=> ($b['order'] ?? PHP_INT_MAX));

        return $ordered;
    }

    /**
     * Fail the run when a step config still contains a `{variable}` that nothing produced.
     * Without this the placeholder is written verbatim into the record (e.g. a CRM deal titled
     * "{deal_title}"), which looks like success but silently corrupts the user's data.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws Exception
     */
    private function assertNoUnresolvedVariables(array $config, string $type, int $index): void
    {
        // Path steps hold rule fields and nested steps that PathAction resolves against its own
        // context, so they are not checkable here.
        if ($type === 'path') {
            return;
        }

        $unresolved = [];

        array_walk_recursive($config, function (mixed $value, int|string $key) use (&$unresolved): void {
            if (! is_string($value) || in_array($key, self::UNCHECKED_CONFIG_KEYS, true)) {
                return;
            }

            if (preg_match_all('/\{(\w+)\}/', $value, $matches)) {
                $unresolved = array_merge($unresolved, $matches[0]);
            }
        });

        if ($unresolved === []) {
            return;
        }

        $unique = implode(', ', array_unique($unresolved));

        throw new Exception(
            sprintf(
                'Step %d (%s): unresolved variable %s — no earlier step produces it. Check the "Store output as" name on the step that should provide it.',
                $index + 1,
                $type,
                $unique,
            ),
        );
    }

    /**
     * Recursively substitute {placeholder} tokens in a step's config from the run context,
     * so every action receives resolved values without each having to interpolate itself.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     *
     * @return array<string, mixed>
     */
    private function interpolateConfig(array $config, array $context): array
    {
        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $config[$key] = $this->interpolate($value, $context);
            } elseif (is_array($value)) {
                $config[$key] = $this->interpolateConfig($value, $context);
            }
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($context): string {
            $value = $context[$matches[1]] ?? $matches[0];

            if (is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: $matches[0];
            }

            return (string) $value;
        }, $template);
    }
}
