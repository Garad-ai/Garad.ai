<div class="lqd-cs-templates py-9">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h2 class="mb-0 flex items-center gap-1">
            @lang('Find a template')

            <span x-show="!templatesList.length && !loadingTemplatesFailed">
                <x-tabler-refresh class="size-5 animate-spin" />
            </span>
        </h2>

        <x-button
            class="text-2xs font-medium opacity-80 hover:opacity-100"
            variant="link"
            href="#"
            @click.prevent="switchView('editor'); $nextTick(() => {activeTool = 'templates';})"
        >
            @lang('View All')
            <x-tabler-chevron-right class="size-4" />
        </x-button>
    </div>

    <div
        class="lqd-cs-templates-grid grid grid-cols-1 place-items-start gap-5 sm:grid-cols-2 md:grid-cols-3 md:gap-x-6 lg:grid-cols-5 lg:gap-x-11 [&_.lqd-cs-template:nth-child(n+9)]:hidden"
        x-intersect.once="loadTemplatesList('{{ $templates_list_url }}')"
    >
        <div
            class="col-span-full w-full"
            x-cloak
            x-show="loadingTemplatesFailed"
        >
            <x-button @click.prevent="loadTemplatesList('{{ $templates_list_url }}')">
                <x-tabler-download size="4" />
                {{ __('Fetch Templates') }}
            </x-button>
        </div>

        <div
            class="col-span-full w-full"
            x-cloak
            x-show="!loadingTemplates && !loadingTemplatesFailed && !templatesList.length"
        >
            <h4 class="m-0">
                {{ __('No templates being added yet.') }}
            </h4>
        </div>

        <template x-for="template in templatesList">
            <a
                class="lqd-cs-template group relative w-full overflow-hidden rounded-md shadow-lg shadow-black/5 transition-transform hover/item:-translate-y-1"
                href="#"
                @click.prevent="loadTemplate(template.id)"
            >
                <img
                    class="group-hover/item:scale-105"
                    src="data:image/svg+xml;charset=utf-8,%3Csvg xmlns%3D'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg' viewBox%3D'0 0 100 100'%2F%3E"
                    alt="{{ __('Creative Suite Preview') }}"
                    x-intersect.once="$el.src = $el.getAttribute('data-src')"
                    :data-src="template.preview"
                >
                <span class="absolute inset-0 inline-grid place-items-center bg-black/15 text-white opacity-0 transition-opacity group-hover:opacity-100">
                    <x-tabler-plus class="size-5" />
                </span>
            </a>
        </template>
    </div>
</div>
