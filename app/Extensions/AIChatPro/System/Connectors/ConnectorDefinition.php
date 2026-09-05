<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Connectors;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;

/**
 * Contract every provider extension implements and registers with ConnectorRegistry.
 *
 * Provider extensions live in their own folders (e.g. AIChatProGmail) and never
 * import each other. The host (AIChatPro) only ever talks to definitions through
 * this interface.
 */
interface ConnectorDefinition
{
    /**
     * Stable key used in routes, settings, and the connector row's `type` column.
     */
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * A Blade view name rendered as the connector card icon, or a tabler-* icon name.
     */
    public function icon(): string;

    /**
     * The route to start the OAuth flow.
     */
    public function redirectRoute(): string;

    /**
     * Whether this connector is globally enabled by the admin (per-provider toggle).
     */
    public function isEnabled(): bool;

    /**
     * Build function-calling tool definitions for the given engine.
     *
     * @param  string  $engine  one of EngineEnum values (open_ai|anthropic|gemini)
     */
    public function tools(AIChatProConnector $connector, string $engine): array;

    /**
     * Dispatch a tool call. Return any structured result (will be JSON-encoded into
     * the conversation as tool output).
     */
    public function handleToolCall(string $functionName, array $arguments, AIChatProConnector $connector): array;

    /**
     * Short hint appended to the system prompt so the model knows the connector is available.
     */
    public function systemPromptHint(AIChatProConnector $connector): ?string;

    /**
     * Display metadata for the source badge shown under chat responses when a tool was used.
     *
     * @return array{name: string, key: string, icon: string}
     */
    public function meta(AIChatProConnector $connector): array;

    /**
     * Static, public-facing metadata shown in the "Connector Details" modal.
     *
     * @return array{type: string, author: string, uuid: string, website: string}
     */
    public function details(): array;

    /**
     * Human-readable permission labels shown in the pre-consent screen and the
     * connected card. Exactly what the provider will let us read.
     *
     * @return array<int, string>
     */
    public function permissions(): array;

    /**
     * Provider-fetched options the user can scope access to (labels, folders,
     * calendars, pages...). Returns:
     *   [
     *     'type'    => 'labels'|'folders'|'calendars'|'pages',
     *     'label'   => human label for the chooser,
     *     'options' => [['id' => ..., 'name' => ..., 'meta' => ...], ...],
     *   ]
     *
     * Implementations may return an empty array if the provider has no per-scope
     * options to display.
     */
    public function accessOptions(AIChatProConnector $connector): array;

    /**
     * Revoke the connector's token at the provider. Implementations should swallow
     * provider-side failures (log them) so local deletion always proceeds.
     */
    public function revokeToken(AIChatProConnector $connector): void;
}
