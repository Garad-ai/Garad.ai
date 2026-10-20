<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Engine;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowRunStatusEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentConversation;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowApproval;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRunStep;
use App\Extensions\AIAgent\System\Triggers\ChannelMessageTrigger;
use App\Extensions\AIAgent\System\Triggers\ScheduleTrigger;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

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
    public function processScheduledWorkflows(
        ?Command $command = null,
        ?callable $scope = null,
        array $triggerData = [],
    ): int {
        $query = AIAgentWorkflow::query()
            ->where('status', WorkflowStatusEnum::Active)
            ->where('trigger_type', TriggerTypeEnum::Schedule);

        if ($scope !== null) {
            $scope($query);
        }

        $candidates = $query->get();

        $command?->line("[AIAgent] Found {$candidates->count()} active scheduled workflow(s).");

        $fired = 0;

        foreach ($candidates as $workflow) {
            $lock = Cache::lock('garad-scheduled-workflow:' . $workflow->id, 1800);
            if (! $lock->get()) {
                $command?->line("[AIAgent] Workflow #{$workflow->id} is already being processed.");

                continue;
            }

            try {
                $workflow->refresh();
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

                $runData = array_merge(
                    $this->scheduleTrigger->buildTriggerData($workflow),
                    $triggerData,
                );
                $run = $this->run($workflow, $runData);
                $this->updateNextRunAt($workflow);
                $fired++;

                $status = $run->status->value;
                $message = "[AIAgent] Workflow #{$workflow->id} run #{$run->id} — {$status}.";
                if ($status === WorkflowRunStatusEnum::PendingApproval->value) {
                    $command?->warn($message);
                } elseif ($status === WorkflowRunStatusEnum::Completed->value) {
                    $command?->info($message);
                } else {
                    $command?->error($message);
                }

                if ($run->error_message) {
                    $command?->error("[AIAgent] Error: {$run->error_message}");
                }
            } finally {
                $lock->release();
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
        ?string $idempotencyKey = null,
    ): AIAgentWorkflowRun {
        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        if ($idempotencyKey !== null) {
            $existingRun = AIAgentWorkflowRun::query()
                ->where('workflow_id', $workflow->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingRun) {
                return $existingRun;
            }
        }

        /** @var AIAgentWorkflowRun $run */
        try {
            $run = AIAgentWorkflowRun::query()->create([
                'workflow_id'        => $workflow->id,
                'idempotency_key'    => $idempotencyKey,
                'status'             => WorkflowRunStatusEnum::Running,
                'trigger_data'       => $triggerData,
                'run_context'        => array_merge($initialContext, $triggerData),
                'steps_output'       => [],
                'current_step_order' => 0,
                'started_at'         => now(),
            ]);
        } catch (QueryException $exception) {
            if ($idempotencyKey === null) {
                throw $exception;
            }

            $existingRun = AIAgentWorkflowRun::query()
                ->where('workflow_id', $workflow->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existingRun) {
                throw $exception;
            }

            return $existingRun;
        }

        return $this->executeFromStep(
            $workflow,
            $run,
            array_merge($initialContext, $triggerData),
            [],
            0,
        );
    }

    public function resumeAfterApproval(AIAgentWorkflowRun $run): AIAgentWorkflowRun
    {
        $run->loadMissing('workflow');
        if ($run->status !== WorkflowRunStatusEnum::PendingApproval) {
            throw new Exception('This workflow run is not waiting for approval.');
        }

        $approval = AIAgentWorkflowApproval::query()
            ->where('workflow_run_id', $run->id)
            ->where('status', 'approved')
            ->latest('id')
            ->first();
        if (! $approval) {
            throw new Exception('The workflow action has not been approved.');
        }

        return $this->executeFromStep(
            $run->workflow,
            $run,
            $run->run_context ?? [],
            $run->steps_output ?? [],
            (int) $run->current_step_order,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, mixed>  $stepsOutput
     */
    private function executeFromStep(
        AIAgentWorkflow $workflow,
        AIAgentWorkflowRun $run,
        array $context,
        array $stepsOutput,
        int $startAt,
    ): AIAgentWorkflowRun {
        $currentStep = null;

        try {
            $validationErrors = $this->validateWorkflow(
                $workflow,
                $context,
            );
            if ($validationErrors !== []) {
                throw new Exception('Workflow validation failed: ' . implode(' ', $validationErrors));
            }

            $steps = $this->sortSteps($workflow->resolved_steps);

            foreach ($steps as $index => $stepDef) {
                $latestStatus = $run->fresh()->status;
                if (in_array($latestStatus, [
                    WorkflowRunStatusEnum::Cancelling,
                    WorkflowRunStatusEnum::Cancelled,
                ], true)) {
                    $run->update([
                        'status'       => WorkflowRunStatusEnum::Cancelled,
                        'completed_at' => now(),
                    ]);

                    return $run->fresh();
                }

                if ($index < $startAt) {
                    continue;
                }

                $currentStep = $index;
                $type = (string) ($stepDef['type'] ?? $stepDef['action'] ?? '');
                $config = $this->interpolateConfig($stepDef['config'] ?? [], $context);
                $this->assertNoUnresolvedVariables($config, $type, $index);

                $step = AIAgentWorkflowRunStep::query()->firstOrNew([
                    'workflow_run_id' => $run->id,
                    'step_index'      => $index,
                ]);
                $step->fill([
                    'step_id'       => $stepDef['id'] ?? null,
                    'type'          => $type,
                    'input'         => $config,
                    'status'        => 'running',
                    'started_at'    => $step->started_at ?? now(),
                    'completed_at'  => null,
                    'error_message' => null,
                ]);
                $step->save();

                if ($this->requiresApproval($type, $stepDef)) {
                    $approval = AIAgentWorkflowApproval::query()
                        ->where('workflow_run_step_id', $step->id)
                        ->first();
                    if (! $approval || $approval->status !== 'approved') {
                        AIAgentWorkflowApproval::query()->firstOrCreate(
                            ['workflow_run_step_id' => $step->id],
                            [
                                'workflow_id'     => $workflow->id,
                                'workflow_run_id' => $run->id,
                                'requested_by'    => $workflow->user_id,
                                'status'          => 'pending',
                                'payload'         => ['type' => $type, 'config' => $config],
                            ],
                        );
                        $step->update(['status' => 'waiting_for_approval']);
                        $run->update([
                            'status'             => WorkflowRunStatusEnum::PendingApproval,
                            'run_context'        => $context,
                            'steps_output'       => $stepsOutput,
                            'current_step_order' => $index,
                            'completed_at'       => null,
                        ]);

                        return $run->fresh();
                    }
                }

                $action = $this->dispatcher->resolve($type);
                $context = $action->execute($config, $context, $workflow, $run);

                $step->update([
                    'status'       => 'completed',
                    'output'       => $context,
                    'completed_at' => now(),
                ]);
                $stepsOutput[$index] = [
                    'step_id' => $stepDef['id'] ?? null,
                    'type'    => $type,
                    'status'  => 'completed',
                    'output'  => $context,
                ];
                $run->update([
                    'run_context'        => $context,
                    'steps_output'       => $stepsOutput,
                    'current_step_order' => $index + 1,
                ]);
            }

            if (in_array($run->fresh()->status, [
                WorkflowRunStatusEnum::Cancelling,
                WorkflowRunStatusEnum::Cancelled,
            ], true)) {
                $run->update([
                    'status'       => WorkflowRunStatusEnum::Cancelled,
                    'completed_at' => now(),
                ]);

                return $run->fresh();
            }

            $run->update([
                'status'       => WorkflowRunStatusEnum::Completed,
                'steps_output' => $stepsOutput,
                'run_context'  => $context,
                'completed_at' => now(),
            ]);

            $workflow->update(['last_run_at' => now()]);
        } catch (Throwable $e) {
            if (in_array($run->fresh()->status, [
                WorkflowRunStatusEnum::Cancelling,
                WorkflowRunStatusEnum::Cancelled,
            ], true)) {
                if ($currentStep !== null) {
                    AIAgentWorkflowRunStep::query()
                        ->where('workflow_run_id', $run->id)
                        ->where('step_index', $currentStep)
                        ->whereNotIn('status', ['completed', 'waiting_for_approval'])
                        ->update([
                            'status'        => 'cancelled',
                            'error_message' => 'Cancelled after the current action stopped.',
                            'completed_at'  => now(),
                            'updated_at'    => now(),
                        ]);
                }
                $run->update([
                    'status'       => WorkflowRunStatusEnum::Cancelled,
                    'completed_at' => now(),
                ]);

                return $run->fresh();
            }

            Log::error('AIAgent WorkflowEngine error', [
                'workflow_id' => $workflow->id,
                'run_id'      => $run->id,
                'error'       => $e->getMessage(),
            ]);

            if ($currentStep !== null) {
                AIAgentWorkflowRunStep::query()
                    ->where('workflow_run_id', $run->id)
                    ->where('step_index', $currentStep)
                    ->whereNotIn('status', ['completed', 'waiting_for_approval'])
                    ->update([
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at'  => now(),
                        'updated_at'    => now(),
                    ]);
            }

            $run->update([
                'status'        => WorkflowRunStatusEnum::Failed,
                'steps_output'  => $stepsOutput,
                'run_context'   => $context,
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }

        return $run;
    }

    private function requiresApproval(string $type, array $step): bool
    {
        return $type === 'send_message'
            || ($step['config']['requires_approval'] ?? false) === true;
    }

    /**
     * Validate every action before a workflow starts, including all nested path branches.
     *
     * @param  array<string, mixed>  $context
     *
     * @return array<int, string>
     */
    public function validateWorkflow(AIAgentWorkflow $workflow, array $context = []): array
    {
        $steps = $workflow->resolved_steps;
        if (! is_array($steps) || $steps === []) {
            $triggerConfig = $workflow->trigger_config ?? [];
            if (
                ($triggerConfig['garad_task'] ?? false) === true
                && ($triggerConfig['garad_task_type'] ?? null) === 'reminder'
                && ($triggerConfig['notification_enabled'] ?? true) === false
            ) {
                return [];
            }

            return ['The agent has no runnable workflow steps.'];
        }

        $errors = [];
        $executableActions = 0;
        $availableVariables = array_fill_keys(array_keys($context), true);
        $this->validateSteps($steps, 'workflow', $errors, $executableActions, $availableVariables);

        if ($executableActions === 0) {
            $errors[] = 'The agent has no executable actions.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<int, mixed>  $steps
     * @param  array<int, string>  $errors
     * @param  array<string, bool>  $availableVariables
     */
    private function validateSteps(
        array $steps,
        string $prefix,
        array &$errors,
        int &$executableActions,
        array $availableVariables,
    ): array {
        usort($steps, static function (mixed $left, mixed $right): int {
            if (! is_array($left) || ! is_array($right)) {
                return 0;
            }

            return ($left['order'] ?? PHP_INT_MAX) <=> ($right['order'] ?? PHP_INT_MAX);
        });

        foreach ($steps as $index => $step) {
            $stepPath = $prefix . ' step ' . ((int) $index + 1);
            if (! is_array($step)) {
                $errors[] = ucfirst($stepPath) . ' is malformed.';

                continue;
            }

            $type = trim((string) ($step['type'] ?? $step['action'] ?? ''));
            if ($type === '') {
                $errors[] = ucfirst($stepPath) . ' has no action type.';

                continue;
            }

            $config = $step['config'] ?? [];
            if (! is_array($config)) {
                $errors[] = ucfirst($stepPath) . " ({$type}) has malformed configuration.";

                continue;
            }

            try {
                $action = $this->dispatcher->resolve($type);
            } catch (Exception) {
                $errors[] = ucfirst($stepPath) . " uses unavailable action [{$type}].";

                continue;
            }

            if ($type !== 'path') {
                $executableActions++;
            }

            if ($action instanceof AIAgentActionInterface) {
                $schema = $action->getConfigSchema();
                foreach ($schema['required'] ?? [] as $requiredKey) {
                    if (! is_string($requiredKey)
                        || ! array_key_exists($requiredKey, $config)
                        || (is_string($config[$requiredKey]) && trim($config[$requiredKey]) === '')
                        || $config[$requiredKey] === null
                        || $config[$requiredKey] === []) {
                        $errors[] = ucfirst($stepPath) . " ({$type}) requires [{$requiredKey}] configuration.";
                    }
                }
            }

            if ($type === 'path') {
                $paths = $config['paths'] ?? null;
                if (! is_array($paths)) {
                    continue;
                }

                $branchOutputs = [];
                foreach ($paths as $pathIndex => $path) {
                    if (! is_array($path)) {
                        $errors[] = ucfirst($stepPath) . ' has a malformed path branch.';

                        continue;
                    }

                    foreach ($path['ruleGroups'] ?? [] as $group) {
                        foreach (($group['rules'] ?? []) as $rule) {
                            $field = is_array($rule) ? (string) ($rule['field'] ?? '') : '';
                            $this->validateVariableReferences(
                                ['field' => $field],
                                $availableVariables,
                                $stepPath . ' path rule',
                                $errors,
                            );
                        }
                    }

                    $branchSteps = $path['steps'] ?? [];
                    if (! is_array($branchSteps)) {
                        $errors[] = ucfirst($stepPath) . ' has malformed nested steps.';

                        continue;
                    }

                    $branchName = trim((string) ($path['name'] ?? ''));
                    $branchLabel = $branchName !== '' ? $branchName : 'branch ' . ((int) $pathIndex + 1);
                    $branchOutputs[] = $this->validateSteps(
                        $branchSteps,
                        $stepPath . ' / ' . $branchLabel,
                        $errors,
                        $executableActions,
                        $availableVariables,
                    );
                }

                if ($branchOutputs !== []) {
                    $commonOutputs = array_shift($branchOutputs);
                    foreach ($branchOutputs as $branchOutput) {
                        $commonOutputs = array_intersect_key($commonOutputs, $branchOutput);
                    }
                    $availableVariables = $commonOutputs;
                }

                continue;
            }

            $this->validateVariableReferences($config, $availableVariables, $stepPath, $errors);
            $this->addActionOutputs($type, $config, $availableVariables);
        }

        return $availableVariables;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, bool>  $availableVariables
     * @param  array<int, string>  $errors
     */
    private function validateVariableReferences(
        array $values,
        array $availableVariables,
        string $stepPath,
        array &$errors,
    ): void {
        foreach ($values as $key => $value) {
            if ($key === 'system_prompt') {
                continue;
            }

            if (is_array($value)) {
                $this->validateVariableReferences($value, $availableVariables, $stepPath, $errors);

                continue;
            }

            if (! is_string($value) || preg_match_all('/\{(\w+)\}/', $value, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $variable) {
                if (! isset($availableVariables[$variable])) {
                    $errors[] = ucfirst($stepPath) . " references variable [{$variable}], but no earlier step or chat input provides it.";
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, bool>  $availableVariables
     */
    private function addActionOutputs(string $type, array $config, array &$availableVariables): void
    {
        if ($type === 'ai_call') {
            $outputKey = trim((string) ($config['store_output_as'] ?? 'ai_response'));
            if ($outputKey !== '') {
                $availableVariables[$outputKey] = true;
            }

            if (($config['response_format'] ?? null) === 'json' && is_array($config['output_keys'] ?? null)) {
                foreach ($config['output_keys'] as $key) {
                    if (is_string($key) && trim($key) !== '') {
                        $availableVariables[$key] = true;
                    }
                }
            }

            return;
        }

        $outputs = match ($type) {
            'send_message'    => ['last_sent_message'],
            'generate_report' => ['report_output', 'tool_result'],
            'memory', 'workflow' => ['tool_result'],
            default => [],
        };

        foreach ($outputs as $output) {
            $availableVariables[$output] = true;
        }
    }

    private function updateNextRunAt(AIAgentWorkflow $workflow): void
    {
        $config = $workflow->trigger_config ?? [];
        if (! empty($config['run_once_at'])) {
            $workflow->update([
                'last_run_at' => $workflow->last_run_at ?? now(),
                'next_run_at' => null,
            ]);

            return;
        }

        $cron = $config['cron'] ?? null;

        if (empty($cron)) {
            return;
        }

        try {
            $nextRun = $this->scheduleTrigger->nextRunAt(
                $cron,
                (string) ($config['timezone'] ?? config('app.timezone', 'UTC')),
            );
            $workflow->update(['next_run_at' => $nextRun]);
        } catch (Exception $e) {
            Log::warning('[AIAgent] Could not calculate the next scheduled run.', [
                'workflow_id' => $workflow->id,
                'error'       => $e->getMessage(),
            ]);
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
