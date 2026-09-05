<x-card
    class="flex flex-col"
    class:body="flex flex-col grow p-0"
>
    <x-slot:head
        class="flex items-center justify-between px-5 py-4"
    >
        <h4 class="m-0 text-xs font-medium">
            {{ __('Recent Activity') }}
        </h4>
    </x-slot:head>

    <div>
        @forelse ($activityLog as $activity)
            <div class="group/activity flex items-center gap-3 px-5 py-4">
                <div
                    class="relative inline-grid size-9 shrink-0 place-items-center rounded-full bg-heading-foreground text-header-background transition after:absolute after:-bottom-8 after:top-full after:w-0.5 after:bg-foreground/10 group-last/activity:after:content-none group-hover/activity:scale-110">
                    <x-dynamic-component
                        class="size-5.5"
                        :component="$activity['icon']"
                    />
                </div>

                <div class="min-w-0 grow">
                    <p class="m-0 flex items-center overflow-hidden text-xs text-heading-foreground">
                        <span class="m-0 me-1 truncate font-medium">
                            {{ $activity['desc'] }}
                        </span>
                        <span class="shrink-0 truncate opacity-75">
                            {{ $activity['title'] }}
                        </span>
                    </p>
                </div>

                <span class="shrink-0 text-[12px] opacity-65">
                    {{ $activity['time']->diffForHumans(null, true, true) }}
                </span>
            </div>
        @empty
            <div class="p-5">
                <x-empty-state
                    icon="tabler-clipboard-off"
                    title="{{ __('No activity yet.') }}"
                />
            </div>
        @endforelse
    </div>
</x-card>
