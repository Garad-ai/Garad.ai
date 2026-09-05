@php
    $aiTemplateEnabled = \App\Helpers\Classes\MarketplaceHelper::isRegistered('creative-suite-ai-template');
@endphp

<div class="lqd-adv-img-editor-form-wrap pb-6 pt-16">
    <h1 class="mb-11 text-center">
        @lang('Create exceptional <span class="opacity-50">on-brand assets</span>')
    </h1>

    @if (!$aiTemplateEnabled)
        @include('creative-suite::home.includes.ai-image-generator-form')
    @else
        <div x-data="{
            activeTab: 'ai-template'
        }">
            <div class="mb-11 flex items-center justify-center gap-1">
                <x-button
                    class="active px-4 py-2.5 text-2xs font-medium leading-tight [&.active]:bg-foreground/5 [&.active]:text-foreground"
                    variant="none"
                    ::class="{ 'active': activeTab === 'ai-template' }"
                    @click.prevent="activeTab = 'ai-template'"
                    type="button"
                >
                    {{ __('Start with AI Template') }}
                </x-button>
                <x-button
                    class="px-4 py-2.5 text-2xs font-medium leading-tight [&.active]:bg-foreground/5 [&.active]:text-foreground"
                    variant="none"
                    ::class="{ 'active': activeTab === 'ai-image' }"
                    @click.prevent="activeTab = 'ai-image'"
                    type="button"
                >
                    {{ __('Start with AI Image') }}
                </x-button>
            </div>

            <div
                class="mx-auto mb-10 grid h-[160px] w-[min(100%,740px)] place-items-start rounded-[22px] border border-transparent bg-background shadow-[0_4px_55px_hsl(0_0%_0%/6%)] dark:border-foreground/[2%]">
                <div
                    class="w-full min-w-0 max-w-full"
                    x-show="activeTab === 'ai-template'"
                    x-trap="activeTab === 'ai-template'"
                >
                    @includeIf('creative-suite-ai-template::partials.template-form', ['style' => 'expanded'])
                </div>
                <div
                    class="w-full min-w-0 max-w-full"
                    x-cloak
                    x-show="activeTab === 'ai-image'"
                    x-init="$watch('activeTab', value => { if (value === 'ai-image') { $focus.focus($el.querySelector('#description')); } })"
                >
                    @include('creative-suite::home.includes.ai-image-generator-form', ['style' => 'expanded'])
                </div>
            </div>

            <div class="flex items-center gap-7">
                <span class="h-px grow bg-current opacity-5"></span>
                <x-button
                    class="text-2xs font-medium leading-tight"
                    variant="link"
                    @click.prevent="switchView('editor')"
                >
                    <span x-text="!nodes.length ? '{{ __('or Start with a Blank Template') }}' : '{{ __('or continue editing') }}'">
                        {{ __('or Start with a Blank Template') }}
                    </span>
                    <x-tabler-chevron-right class="size-4 shrink-0" />
                </x-button>
                <span class="h-px grow bg-current opacity-5"></span>
            </div>
        </div>
    @endif
</div>
