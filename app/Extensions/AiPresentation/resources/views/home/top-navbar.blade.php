<nav
    class="lqd-adv-img-editor-nav fixed inset-x-0 top-0 z-10 flex min-h-[--header-h] gap-6 border-b border-heading-foreground/5 bg-background/10 px-6 py-2.5 backdrop-blur-xl backdrop-saturate-[120%]">
    <div class="flex grow items-center gap-6">
        <div class="inline-grid size-[36px] place-content-center overflow-hidden transition-all duration-300"
			 :class="{ 'w-0': currentView !== 'home', 'size-[36px]': currentView === 'home', '-me-6': currentView !== 'home' }"
		>
            <x-button
                class="size-[34px] hover:translate-y-0"
                variant="outline"
                hover-variant="primary"
                size="none"
                title="{{ __('Dashboard') }}"
                href="{{ route('dashboard.user.index') }}"
            >
                <x-tabler-chevron-left class="size-4" />
            </x-button>
        </div>

        <x-header-logo />

    </div>

    <div class="flex select-none items-center justify-end gap-6">
		<div class="items-center gap-6 text-2xs">
            <span>
                <x-credit-list
					showType="button"
					modal-trigger-pos="block"
					expanded-modal-trigger
					modal-trigger-variant="ghost-shadow"
				/>
            </span>
		</div>

		<x-button
			class="hidden size-[34px] shrink-0"
			variant="outline"
			hover-variant="primary"
			size="none"
			title="{{ __('Close') }}"
			::class="{ 'hidden': currentView === 'home', 'inline-flex': currentView !== 'home' }"
			@click.prevent="switchView('home')"
		>
			<x-tabler-x class="size-4" />
		</x-button>
    </div>
</nav>
