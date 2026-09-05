<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Triggers;

use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Triggers\Contracts\TriggerInterface;

class ChannelMessageTrigger implements TriggerInterface
{
    private ?IncomingMessage $incomingMessage = null;

    private ?AIAgentChannel $channel = null;

    public function setContext(AIAgentChannel $channel, IncomingMessage $message): static
    {
        $this->channel = $channel;
        $this->incomingMessage = $message;

        return $this;
    }

    public function shouldFire(AIAgentWorkflow $workflow): bool
    {
        if ($this->incomingMessage === null || $this->channel === null) {
            return false;
        }

        $config = $workflow->trigger_config ?? [];
        $channelId = $config['channel_id'] ?? null;

        if ($channelId !== null && (int) $channelId !== $this->channel->id) {
            return false;
        }

        $filter = $config['keyword_filter'] ?? null;

        if (! empty($filter['enabled']) && ! empty($filter['ruleGroups'])) {
            return $this->evaluateRuleGroups($filter['ruleGroups']);
        }

        return true;
    }

    /**
     * Evaluate OR groups — any group matching = true.
     *
     * @param  array<int, array{rules: array<int, array{field: string, operator: string, value: string}>}>  $groups
     */
    private function evaluateRuleGroups(array $groups): bool
    {
        foreach ($groups as $group) {
            if ($this->evaluateRuleGroup($group['rules'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate AND rules within a group — all must match.
     *
     * @param  array<int, array{field: string, operator: string, value: string}>  $rules
     */
    private function evaluateRuleGroup(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (! $this->evaluateRule($rule)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{field: string, operator: string, value?: string}  $rule
     */
    private function evaluateRule(array $rule): bool
    {
        $fieldValue = $this->resolveField($rule['field'] ?? '');
        $operator = $rule['operator'] ?? 'contains';
        $ruleValue = strtolower((string) ($rule['value'] ?? ''));
        $subject = strtolower((string) $fieldValue);

        return match ($operator) {
            'contains'      => str_contains($subject, $ruleValue),
            'not_contains'  => ! str_contains($subject, $ruleValue),
            'equals'        => $subject === $ruleValue,
            'not_equals'    => $subject !== $ruleValue,
            'is_empty'      => $subject === '',
            'is_not_empty'  => $subject !== '',
            'starts_with'   => str_starts_with($subject, $ruleValue),
            'ends_with'     => str_ends_with($subject, $ruleValue),
            default         => false,
        };
    }

    private function resolveField(string $field): string
    {
        return match ($field) {
            '{message_text}' => $this->incomingMessage?->text ?? '',
            '{sender_id}'    => (string) ($this->incomingMessage?->senderId ?? ''),
            default          => '',
        };
    }

    public function buildTriggerData(AIAgentWorkflow $workflow): array
    {
        return [
            'trigger_type' => 'channel_message',
            'fired_at'     => now()->toIso8601String(),
            'channel_id'   => $this->channel?->id,
            'sender_id'    => $this->incomingMessage?->senderId,
            'message_text' => $this->incomingMessage?->text,
        ];
    }
}
