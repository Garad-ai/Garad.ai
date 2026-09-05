<div class="mt-10 rounded-2xl border border-border bg-card p-6">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                {{ __('AI Agent Limits') }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ __('Define how many agents, channels, messages, and memory entries are included in this plan. Set -1 for unlimited access and 0 to disable the feature entirely.') }}
            </p>
        </div>
    </div>

    <div class="row gap-y-5">
        <div class="col-12 col-md-3">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Agent Limit') }}"
                    tooltip="{{ __('Maximum number of agents a customer can create') }}"
                    error="plan.ai_agent_workflow_limit"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.ai_agent_workflow_limit"
                        placeholder="{{ __('e.g. 5 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Channel Limit') }}"
                    tooltip="{{ __('Maximum number of channels a customer can connect') }}"
                    error="plan.ai_agent_channel_limit"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.ai_agent_channel_limit"
                        placeholder="{{ __('e.g. 3 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Message Limit') }}"
                    tooltip="{{ __('Maximum number of messages a customer can send') }}"
                    error="plan.ai_agent_message_limit"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.ai_agent_message_limit"
                        placeholder="{{ __('e.g. 500 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Memory Limit') }}"
                    tooltip="{{ __('Maximum number of memory entries the AI Agent can store for a customer') }}"
                    error="plan.ai_agent_memory_limit"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.ai_agent_memory_limit"
                        placeholder="{{ __('e.g. 120 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>
    </div>
</div>
