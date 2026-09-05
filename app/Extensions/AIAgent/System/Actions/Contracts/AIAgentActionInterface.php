<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions\Contracts;

interface AIAgentActionInterface extends ActionInterface
{
    /**
     * The category this action belongs to (used in the tool category modal sidebar).
     * Expected values: 'ai' | 'agents' | 'channels' | 'flow-controls' | 'utilities' | 'custom'
     */
    public function getCategory(): string;

    /**
     * Human-readable display name for this action.
     */
    public function getLabel(): string;

    /**
     * Short description shown in the tool category modal.
     */
    public function getDescription(): string;

    /**
     * Tabler icon identifier (e.g. 'tabler-brain', 'tabler-send').
     */
    public function getIcon(): string;

    /**
     * JSON Schema object describing the config fields for this action's settings panel.
     *
     * @return array<string, mixed>
     */
    public function getConfigSchema(): array;
}
