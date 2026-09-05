<div
    @class([
        'lqd-chatbot-edit-window',
        'fixed bottom-0 end-0 start-0 top-0 z-20 overflow-y-auto bg-background lg:start-[--navbar-width]' => $overlay,
    ])
    x-data="createAiClipsData"
    @if ($overlay) x-cloak @endif
    x-show="{{ $overlay ? 'openAiClipsWindow' : 'true' }}"
    x-init="$nextTick(() => { $watch('aiClipsWindowKey', () => initialize()) })"
>
    @if ($overlay)
        {{-- Edit Window Header --}}
        <div class="lqd-chatbot-edit-window-header border-b py-6">
            <div class="container">
                <div class="flex flex-wrap items-center justify-between gap-y-4">
                    <div class="flex flex-col items-start gap-3">
                        <x-button
                            class="m-0 text-xs text-foreground/70"
                            variant="link"
                            @click.prevent="Alpine.store('aiViralClipsData').toggleAiClipsWindow(false);"
                        >
                            <x-tabler-chevron-left class="size-4" />
                            {{ __('Back') }}
                        </x-button>
                        <h1 class="m-0">
                            {{ __('AI Clips') }}
                        </h1>
                        <p class="m-0 text-2xs font-medium text-foreground">
                            {{ __('Generate viral clips from long video content.') }}
                        </p>
                    </div>

                    {{-- <x-button
                        href="#"
                        @click.prevent="aiClipsWindowKey++"
                    >
                        <x-tabler-plus class="size-4" />
                        @lang('Create Video')
                    </x-button> --}}
                </div>
            </div>
        </div>
    @endif

    <div class="lqd-chatbot-edit-window-content mt-3 py-8 max-lg:px-3">
        <div class="mx-auto flex max-w-[786px] flex-col flex-wrap justify-center gap-y-7 lg:w-[430px]">
            @if (setting('default_ai_clip_tool', 'vizard') == 'klap')
                @include('ai-viral-clips::create-clips.steps.generate-clips')
                @include('ai-viral-clips::create-clips.steps.preview-clips')
            @else
                @include('ai-viral-clips::create-clips.steps.vizard.generate-clips')
            @endif

            <x-cost-preview class="w-full justify-end" />
        </div>
    </div>
</div>

@if (setting('default_ai_clip_tool', 'vizard') == 'klap')
    @include('ai-viral-clips::scripts.klap')
@else
    @include('ai-viral-clips::scripts.vizard')
@endif
