<x-card
    class="text-center"
    size="lg"
    x-data="{
        status: '{{ $workflow->status->value }}',
        toggling: false,
        async toggleStatus() {
            if (this.toggling) return;
            this.toggling = true;
            try {
                const res = await fetch('{{ route('dashboard.user.ai-agent.workflows.toggle-status', $workflow) }}', {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (res.ok && json.data?.status) {
                    this.status = json.data.status;
                    toastr.success(this.status === 'active' ? '{{ __('Agent activated.') }}' : '{{ __('Agent paused.') }}');
                } else {
                    toastr.error(json.message || '{{ __('Failed to update status.') }}');
                }
            } catch {
                toastr.error('{{ __('Failed to update status.') }}');
            } finally {
                this.toggling = false;
            }
        },
    }"
>
    <x-dropdown.dropdown
        class="absolute end-3 top-3"
        anchor="end"
    >
        <x-slot:trigger
            class="size-8"
        >
            <x-tabler-dots class="size-5" />
            <span class="sr-only">@lang('Agent Options')</span>
        </x-slot:trigger>
        <x-slot:dropdown
            class="min-w-[170px]"
        >
            <ul class="py-1 text-xs font-medium">
                <li>
                    <a
                        class="flex px-5 py-2 text-heading-foreground transition-colors hover:bg-heading-foreground/[3%]"
                        href="{{ route('dashboard.user.ai-agent.workflows.edit', $workflow) }}"
                    >
                        @lang('Edit')
                    </a>
                </li>
                <li :class="{ 'opacity-50 pointer-events-none': toggling }">
                    <button
                        class="flex w-full items-center justify-between px-5 py-2 text-start text-heading-foreground transition-colors hover:bg-heading-foreground/[3%]"
                        type="button"
                        @click="toggleStatus()"
                    >
                        <span x-text="status === 'active' ? '{{ __('Pause') }}' : '{{ __('Activate') }}'"></span>
                        <x-tabler-player-pause
                            class="size-3.5 shrink-0"
                            x-show="status === 'active'"
                        />
                        <x-tabler-player-play
                            class="size-3.5 shrink-0"
                            x-show="status !== 'active'"
                        />
                    </button>
                </li>
                <li>
                    <button
                        class="flex w-full px-5 py-2 text-start text-heading-foreground transition-colors hover:bg-heading-foreground/[3%]"
                        type="button"
                        @click="toggle(false); $dispatch('open-logs', { id: {{ $workflow->id }}, name: '{{ addslashes($workflow->name) }}', url: '{{ route('dashboard.user.ai-agent.workflows.runs', $workflow) }}' })"
                    >
                        @lang('Show Logs')
                    </button>
                </li>
                <li>
                    <form
                        action="{{ route('dashboard.user.ai-agent.workflows.destroy', $workflow) }}"
                        method="POST"
                        @submit.prevent="if(confirm('{{ __('Delete this agent?') }}')) $el.submit()"
                    >
                        @csrf
                        @method('DELETE')
                        <x-button
                            class="w-full justify-between rounded-none px-5 py-2 text-start text-xs font-medium text-heading-foreground hover:translate-y-0"
                            variant="ghost"
                            hover-variant="danger"
                            type="submit"
                        >
                            @lang('Delete')
                            <x-tabler-trash
                                class="size-4"
                                aria-hidden="true"
                            />
                        </x-button>
                    </form>
                </li>
            </ul>
        </x-slot:dropdown>
    </x-dropdown.dropdown>

    @if (isset($workflow->avatar_url) && filled($workflow->avatar_url))
        <img
            class="mx-auto mb-4 max-h-[50px] w-[50px] rounded-lg object-contain object-center"
            src="{{ $workflow->avatar_url }}"
            alt="{{ $workflow->name }}"
        />
    @endif

    <h4 class="mb-4 text-base font-medium">
        {{ $workflow->name }}
    </h4>

    @if (isset($workflow->description) && filled($workflow->description))
        <p class="mb-2.5 text-2xs text-foreground/60">{{ $workflow->description }}</p>
    @endif

    <p class="mb-2.5 text-3xs font-medium text-foreground/50">
        {{ __('Created') }}
        {{ $workflow->created_at->diffForHumans() }}
    </p>

    <p class="mb-4 text-3xs font-medium text-foreground/50">
        <span
            class="inline-flex size-1.5 rounded-full me-1.5 align-middle"
            :class="status === 'active' ? 'bg-green-500' : 'bg-yellow-500'"
        ></span>
        <span x-text="status === 'active' ? '{{ __('Active') }}' : '{{ __('Paused') }}'"></span>:
        <u class="decoration-dashed">
            {{ $workflow->last_run_at ? __('Last Run : :time', ['time' => $workflow->last_run_at->diffForHumans()]) : __('Never Ran') }}
        </u>
    </p>

    <x-button
        class="px-4"
        href="{{ route('dashboard.user.ai-agent.messages.index', ['workflow_id' => $workflow->id]) }}"
        variant="ghost-shadow"
    >
        {{ __('Start Conversation') }}
    </x-button>

    @if (isset($workflow->channel) && filled($workflow->channel))
        <div class="mt-4 flex items-center justify-center gap-2">
            <img
                class="size-4 object-contain"
                src="{{ custom_theme_url('/vendor/ai-agent/images/platforms/') }}{{ $workflow->channel->type }}.png"
                alt="{{ $workflow->channel->type }}"
                aria-hidden="true"
            >
            <span class="text-2xs">{{ $workflow->channel->name ?: $workflow->channel->type }}</span>
        </div>
    @endif
</x-card>
