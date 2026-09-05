@php
    $hasServerVideos = $todayVideos->isNotEmpty() || $previousVideos->isNotEmpty();
@endphp

<div class="lqd-ai-captions-videos-wrap flex grow flex-col">
    {{-- Empty state --}}
    <div
        class="m-auto"
        x-show="!pendingVideos.length && !{{ $hasServerVideos ? 'true' : 'false' }}"
        @if ($hasServerVideos) style="display:none" @endif
        x-cloak
    >
        <x-empty-state
            icon="tabler-quote-off"
            :title="__('No captioned videos yet')"
            :description="__('Upload a video and pick a template to get started.')"
        />
    </div>

    <div
        id="captions-videos-container"
        x-show="pendingVideos.length > 0 || {{ $hasServerVideos ? 'true' : 'false' }}"
        @if (! $hasServerVideos) x-cloak @endif
    >
        {{-- Today --}}
        <div
            class="mb-8"
            x-show="pendingVideos.length > 0 || {{ $todayVideos->isNotEmpty() ? 'true' : 'false' }}"
            @if ($todayVideos->isEmpty()) x-cloak @endif
        >
            <div class="mb-5 flex items-center justify-between border-b py-2.5 transition">
                <p class="mb-0 text-[12px] font-semibold">
                    {{ __('Today') }}
                </p>
                @if ($todayVideos->isNotEmpty())
                    <x-button
                        class="text-[12px] font-semibold text-primary hover:underline"
                        variant="link"
                        size="none"
                        type="button"
                        @click="
                            const todayIds = {{ $todayVideos->pluck('id')->toJson() }};
                            if (selectedToday.length === todayIds.length) {
                                selectedToday = [];
                            } else {
                                selectedToday = [...todayIds];
                            }
                        "
                    >
                        <span x-text="selectedToday.length === {{ $todayVideos->count() }} ? '{{ __('Deselect All') }}' : '{{ __('Select All') }}'"></span>
                    </x-button>
                @endif
            </div>

            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
                id="today-captions-grid"
            >
                <template
                    x-for="videoId in pendingVideos"
                    :key="'pending-' + videoId"
                >
                    <div class="lqd-caption-video-item group/item relative">
                        <div class="flex aspect-square flex-col items-center justify-center rounded-md bg-foreground/[3%] p-5 text-center">
                            <x-shimmer-text class="text-xs">
                                @lang('Generating captions...')
                            </x-shimmer-text>
                        </div>
                    </div>
                </template>

                @foreach ($todayVideos as $video)
                    @include('ai-captions::partials.video-item', [
                        'entry' => $video,
                        'selectionGroup' => 'selectedToday',
                    ])
                @endforeach
            </div>
        </div>

        {{-- Previous --}}
        @if ($previousVideos->isNotEmpty())
            <div>
                <div class="mb-5 flex items-center justify-between border-b py-2.5">
                    <p class="mb-0 text-[12px] font-semibold">
                        {{ __('Created Previously') }}
                    </p>
                    <x-button
                        class="text-[12px] font-semibold text-primary hover:underline"
                        variant="link"
                        size="none"
                        type="button"
                        @click="
                            const prevIds = {{ $previousVideos->pluck('id')->toJson() }};
                            if (selectedPrevious.length === prevIds.length) {
                                selectedPrevious = [];
                            } else {
                                selectedPrevious = [...prevIds];
                            }
                        "
                    >
                        <span x-text="selectedPrevious.length === {{ $previousVideos->count() }} ? '{{ __('Deselect All') }}' : '{{ __('Select All') }}'"></span>
                    </x-button>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($previousVideos as $video)
                        @include('ai-captions::partials.video-item', [
                            'entry' => $video,
                            'selectionGroup' => 'selectedPrevious',
                        ])
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Bulk action bar --}}
    <div
        class="pointer-events-none fixed bottom-8 end-0 start-0 z-20 transition-all max-lg:bottom-[calc(var(--bottom-menu-height)+1rem)] lg:start-[--navbar-width]"
        x-show="totalSelected > 0"
        x-cloak
        x-transition.scale-95
    >
        <div class="container">
            <form
                class="pointer-events-auto flex flex-col items-center justify-between gap-1 rounded-full border border-foreground/5 bg-background px-6 py-4 shadow-xl shadow-black/5 md:flex-row md:py-1 md:pe-1"
                @submit.prevent="bulkDelete"
            >
                <span
                    class="text-2xs font-medium"
                    x-text="totalSelected + ' {{ __('selected') }}'"
                ></span>

                <div class="flex items-center gap-2">
                    <x-button type="submit">
                        {{ __('Move to Trash') }}
                    </x-button>

                    <x-button
                        variant="outline"
                        hover-variant="none"
                        type="button"
                        @click="cancelSelection"
                    >
                        {{ __('Cancel') }}
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>
