<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Engine\ActionDispatcher;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;

class PathAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly ActionDispatcher $dispatcher,
    ) {}

    /**
     * Config keys:
     *   - paths (array): Array of path branches, each with:
     *     - id (string)          : Unique branch identifier
     *     - name (string)        : Display name (e.g. "Path A")
     *     - ruleGroups (array)   : OR-combined groups of AND-combined rules
     *                              Empty array = fallback (always executes if no prior path matched)
     *     - steps (array)        : Sub-steps to execute when this branch matches
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $paths = $config['paths'] ?? [];
        $anyMatched = false;

        foreach ($paths as $path) {
            $isFallback = empty($path['ruleGroups']);

            if ($isFallback && $anyMatched) {
                continue;
            }

            $matches = $isFallback || $this->evaluatePath($path, $context);

            if (! $matches) {
                continue;
            }

            $anyMatched = true;
            $context = $this->executeSubSteps($path['steps'] ?? [], $context, $workflow, $run);
        }

        return $context;
    }

    private function evaluatePath(array $path, array $context): bool
    {
        foreach ($path['ruleGroups'] as $group) {
            if ($this->evaluateGroup($group, $context)) {
                return true;
            }
        }

        return false;
    }

    private function evaluateGroup(array $group, array $context): bool
    {
        foreach ($group['rules'] ?? [] as $rule) {
            if (! $this->evaluateRule($rule, $context)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateRule(array $rule, array $context): bool
    {
        $field = $this->resolveRuleField($rule['field'] ?? '', $context);
        $operator = $rule['operator'] ?? 'equals';
        $value = (string) ($rule['value'] ?? '');
        $left = $this->toNumber($field);
        $right = $this->toNumber($value);

        return match ($operator) {
            'contains'      => str_contains($field, $value),
            'not_contains'  => ! str_contains($field, $value),
            'equals'        => $field === $value,
            'not_equals'    => $field !== $value,
            'is_empty'      => trim($field) === '',
            'is_not_empty'  => trim($field) !== '',
            'starts_with'   => str_starts_with($field, $value),
            'ends_with'     => str_ends_with($field, $value),
            'greater_than'  => $left !== null && $right !== null && $left > $right,
            'less_than'     => $left !== null && $right !== null && $left < $right,
            default         => false,
        };
    }

    /**
     * Amounts reaching a rule usually come from an AI step, which returns "$50,000" as readily as
     * "50000". A plain is_numeric check rejects the formatted form and silently drops the branch.
     */
    private function toNumber(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $stripped = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $value)) ?? '';

        return is_numeric($stripped) ? (float) $stripped : null;
    }

    /**
     * A rule reads a value rather than rendering text, so a variable the run never produced
     * has to compare as absent. Leaving the literal "{title}" in place made is_empty false
     * and sent every message down the matched branch.
     */
    private function resolveRuleField(string $field, array $context): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($context): string {
            $value = $context[$matches[1]] ?? null;

            if ($value === null) {
                return '';
            }

            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }

            return (string) $value;
        }, $field) ?? '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function executeSubSteps(array $steps, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        foreach ($steps as $stepDef) {
            $type = $stepDef['type'] ?? $stepDef['action'] ?? '';
            // The engine interpolated this config against the context as it stood before the
            // path ran, so anything an earlier sibling produced is still a literal token here.
            $config = $this->interpolateConfig($stepDef['config'] ?? [], $context);
            $action = $this->dispatcher->resolve($type);
            $context = $action->execute($config, $context, $workflow, $run);
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $config
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

    public function getCategory(): string
    {
        return 'flow_controls';
    }

    public function getLabel(): string
    {
        return 'Path';
    }

    public function getDescription(): string
    {
        return 'Split execution into multiple branches based on conditions.';
    }

    public function getIcon(): string
    {
        return 'tabler-git-fork';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'paths' => [
                    'type'        => 'array',
                    'description' => 'Array of conditional path branches.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'id'         => ['type' => 'string'],
                            'name'       => ['type' => 'string'],
                            'ruleGroups' => ['type' => 'array'],
                            'steps'      => ['type' => 'array'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
