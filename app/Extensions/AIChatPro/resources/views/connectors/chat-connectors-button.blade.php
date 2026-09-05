@php
    use App\Extensions\AIChatPro\System\Connectors\ConnectorPlanGate;
    use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
    use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;

    $connectorsButtonEnabled = (bool) setting('ai_chat_pro_connectors_enabled', '1');

    $enabledDefinitions = [];
    if ($connectorsButtonEnabled && class_exists(ConnectorRegistry::class)) {
        try {
            $connectorPlan = ConnectorPlanGate::resolvePlan(auth()->user());

            $enabledDefinitions = array_filter(
                app(ConnectorRegistry::class)->enabled(),
                fn ($definition, $key) => ConnectorPlanGate::allowsForPlan($connectorPlan, $key),
                ARRAY_FILTER_USE_BOTH
            );
        } catch (\Throwable) {
            $enabledDefinitions = [];
        }
    }
@endphp

@if ($connectorsButtonEnabled && count($enabledDefinitions) > 0)
    @php
        $userConnectors = auth()->check()
            ? AIChatProConnector::query()
                ->where('user_id', auth()->id())
                ->get()
                ->keyBy('type')
            : collect();

        $hasInactiveConnector = false;
        $initialConnectors = [];
        foreach ($enabledDefinitions as $key => $definition) {
            $row = $userConnectors->get($key);
            $isActive = $row?->is_active && ($row->expires_at === null || $row->expires_at->isFuture());

            if ($row && ! $isActive) {
                $hasInactiveConnector = true;
            }

            $initialConnectors[] = [
                'key'              => $key,
                'label'            => $definition->label(),
                'description'      => $definition->description(),
                'details'          => $definition->details(),
                'permissions'      => $definition->permissions(),
                'redirect_url'     => $definition->redirectRoute(),
                'pre_consent_url'  => route('dashboard.user.ai-chat-pro.connectors.pre-consent', ['key' => $key]),
                'is_connected'     => (bool) $row,
                'is_active'        => (bool) $isActive,
                'is_paused'        => (bool) ($row?->is_paused),
                'connector_id'     => $row?->id,
                'connection_uuid'  => $row?->uuid,
                'connected_email'  => $row?->getCredential('email'),
                'connected_name'   => $row?->getCredential('name'),
                'picture'          => $row?->getCredential('picture'),
                'connected_at'     => $row?->connected_at?->toDateTimeString(),
                'disconnect_url'   => $row
                    ? route('dashboard.user.ai-chat-pro.connectors.destroy', $row)
                    : null,
                'access_url'       => $row
                    ? route('dashboard.user.ai-chat-pro.connectors.access.show', $row)
                    : null,
                'pause_url'        => $row
                    ? route('dashboard.user.ai-chat-pro.connectors.toggle-pause', $row)
                    : null,
            ];
        }
    @endphp

    <button
        class="lqd-generator-connectors-trigger group relative"
        type="button"
        title="{{ __('Connectors') }}"
        @click.prevent="window.dispatchEvent(new CustomEvent('open-connectors-modal')); toggle(false)"
    >
        <x-tabler-plug class="size-4.5 shrink-0 opacity-75" />

        <span class="truncate">
            {{ __('Connectors') }}
        </span>

        @if ($hasInactiveConnector)
            <span
                class="absolute -end-1 -top-1 inline-block size-2 rounded-full bg-red-500 ring-2 ring-card-background"
                title="{{ __('A connector needs attention.') }}"
            ></span>
        @endif
    </button>

    @include('ai-chat-pro::connectors.connectors-modal', [
        'enabledDefinitions' => $enabledDefinitions,
        'initialConnectors'  => $initialConnectors,
    ])
@endif
