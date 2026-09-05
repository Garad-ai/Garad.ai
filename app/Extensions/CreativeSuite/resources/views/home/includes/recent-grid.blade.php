<div class="lqd-cs-recent-projects py-9">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h2 class="mb-0">
            @lang('Recent Projects')
        </h2>

        <x-button
            class="text-2xs font-medium opacity-80 hover:opacity-100"
            variant="link"
            href="#"
            @click.prevent="switchView('gallery')"
        >
            @lang('View All')
            <x-tabler-chevron-right class="size-4" />
        </x-button>
    </div>

    <div
        class="lqd-cs-recent-projects-grid grid grid-cols-1 place-items-start gap-5 sm:grid-cols-2 md:grid-cols-3 md:gap-x-6 lg:grid-cols-5 lg:gap-x-11 [&_.lqd-cs-doc-item:nth-child(n+7)]:hidden">
        @include('creative-suite::includes.documents-grid')
    </div>
</div>
