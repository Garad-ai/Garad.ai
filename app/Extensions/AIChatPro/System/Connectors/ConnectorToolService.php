<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Connectors;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use Illuminate\Support\Collection;

/**
 * Aggregates tool definitions across active connectors for StreamService.
 *
 * Mirrors the shape of SkillToolService so the integration point in StreamService
 * is symmetric.
 */
class ConnectorToolService
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    /**
     * @param  Collection<int, AIChatProConnector>  $connectors
     */
    public function openAiTools(Collection $connectors): array
    {
        return $this->collect($connectors, 'open_ai');
    }

    /**
     * @param  Collection<int, AIChatProConnector>  $connectors
     */
    public function anthropicTools(Collection $connectors): array
    {
        return $this->collect($connectors, 'anthropic');
    }

    /**
     * @param  Collection<int, AIChatProConnector>  $connectors
     */
    public function geminiTools(Collection $connectors): array
    {
        $declarations = $this->collect($connectors, 'gemini_inner');

        if ($declarations === []) {
            return [];
        }

        return [
            ['functionDeclarations' => $declarations],
        ];
    }

    /**
     * @param  Collection<int, AIChatProConnector>  $connectors
     */
    public function handle(string $functionName, array $arguments, Collection $connectors): ?array
    {
        $definition = $this->registry->findByFunctionName($functionName);

        if (! $definition) {
            return null;
        }

        $connector = $connectors->first(fn (AIChatProConnector $c): bool => $c->type === $definition->key() && ! $c->is_paused);

        if (! $connector) {
            return null;
        }

        return $definition->handleToolCall($functionName, $arguments, $connector);
    }

    /**
     * @param  Collection<int, AIChatProConnector>  $connectors
     */
    public function getMeta(string $functionName, Collection $connectors): ?array
    {
        $definition = $this->registry->findByFunctionName($functionName);

        if (! $definition) {
            return null;
        }

        // Match handle() — a paused connector cannot actually be used, so we
        // must not surface its meta either (would otherwise show a "Used X"
        // badge for a call that returned null/error).
        $connector = $connectors->first(fn (AIChatProConnector $c): bool => $c->type === $definition->key() && ! $c->is_paused);

        return $connector ? $definition->meta($connector) : null;
    }

    /**
     * @param  Collection<int, AIChatProConnector>  $connectors
     */
    public function systemPromptSummary(Collection $connectors): ?string
    {
        $hints = $connectors
            ->reject(fn (AIChatProConnector $connector): bool => (bool) $connector->is_paused)
            ->map(function (AIChatProConnector $connector): ?string {
                $definition = $this->registry->get($connector->type);

                return $definition?->systemPromptHint($connector);
            })
            ->filter()
            ->values()
            ->all();

        if ($hints === []) {
            return null;
        }

        return 'The user has connected the following external services via AI Chat Pro Connectors. '
            . 'When a question would clearly benefit from up-to-date information from one of these services, '
            . 'call the matching connector_* tool. Do not call them speculatively or when the user has not asked about that data. '
            . "\n\n"
            . implode("\n", array_map(fn (string $hint): string => '- ' . $hint, $hints));
    }

    /**
     * @param  Collection<int, AIChatProConnector>  $connectors
     */
    private function collect(Collection $connectors, string $engine): array
    {
        $tools = [];

        foreach ($connectors as $connector) {
            if ($connector->is_paused) {
                continue;
            }

            $definition = $this->registry->get($connector->type);

            if (! $definition) {
                continue;
            }

            $engineKey = $engine === 'gemini_inner' ? 'gemini' : $engine;

            foreach ($definition->tools($connector, $engineKey) as $tool) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }
}
