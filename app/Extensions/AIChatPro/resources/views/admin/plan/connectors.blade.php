@php
    use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;

    $connectorDefinitions = class_exists(ConnectorRegistry::class)
        ? app(ConnectorRegistry::class)->enabled()
        : [];
@endphp

@if (! empty($connectorDefinitions))
    <div class="mt-10 rounded-2xl border border-border bg-card p-6">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                {{ __('AI Chat Pro Connectors') }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ __('Choose which external service connectors users on this plan can use. Unchecked connectors are hidden and cannot be used.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($connectorDefinitions as $key => $definition)
                <div wire:key="ai-chat-pro-connector-{{ $key }}">
                    <x-form.group
                        no-group-label
                        :error="'plan.ai_chat_pro_connectors.' . $key"
                    >
                        <x-form.checkbox
                            class="border-input rounded-input border !px-2.5 !py-3"
                            wire:model="plan.ai_chat_pro_connectors.{{ $key }}"
                            label="{{ $definition->label() }}"
                            tooltip="{{ $definition->description() }}"
                        />
                    </x-form.group>
                </div>
            @endforeach
        </div>
    </div>
@endif
