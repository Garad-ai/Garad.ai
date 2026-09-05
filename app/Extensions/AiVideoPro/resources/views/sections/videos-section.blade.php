@php
    $hasServerVideos = $todayVideos->isNotEmpty() || $previousVideos->isNotEmpty();
@endphp

<div
    class="lqd-ai-videos-wrap flex grow flex-col"
    id="lqd-ai-videos-wrap"
>
    <svg
        width="0"
        height="0"
    >
        <defs>
            <linearGradient
                id="loader-spinner-gradient"
                x1="0.667969"
                y1="6.10667"
                x2="23.0413"
                y2="25.84"
                gradientUnits="userSpaceOnUse"
            >
                <stop stop-color="#82E2F4" />
                <stop
                    offset="0.502"
                    stop-color="#8A8AED"
                />
                <stop
                    offset="1"
                    stop-color="#6977DE"
                />
            </linearGradient>
        </defs>
    </svg>

    {{-- Empty state --}}
    <div
        class="m-auto"
        x-show="!pendingVideos.length && !{{ $hasServerVideos ? 'true' : 'false' }}"
        @if ($hasServerVideos) style="display:none" @endif
        x-cloak
    >
        <x-empty-state
            icon="tabler-video-off"
            :title="__('No videos yet')"
            :description="__('Start generating videos to see them here.')"
        />
    </div>

    <div
        id="videos-container"
        x-show="pendingVideos.length > 0 || {{ $hasServerVideos ? 'true' : 'false' }}"
        @if (!$hasServerVideos) x-cloak @endif>
        {{-- Today Section --}}
        <div
            class="mb-8"
            x-show="pendingVideos.length > 0 || {{ $todayVideos->isNotEmpty() ? 'true' : 'false' }}"
            @if ($todayVideos->isEmpty()) x-cloak @endif>
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
                id="today-videos-grid"
            >
                {{-- Pending shimmer items from AJAX submissions --}}
                {{-- No id attr here — polling targets server-rendered items, not these --}}
                <template
                    x-for="videoId in pendingVideos"
                    :key="'pending-' + videoId"
                >
                    <div class="lqd-video-pro-item group/item relative">
                        <div class="flex aspect-square flex-col items-center justify-center rounded-md bg-foreground/[3%] p-5 text-center">
                            <x-shimmer-text class="text-xs">
                                @lang('Generating Video...')
                            </x-shimmer-text>
                        </div>
                    </div>
                </template>

                {{-- Server-rendered today videos (complete, in-progress, error) --}}
                @foreach ($todayVideos as $video)
                    @include('ai-video-pro::partials.video-item', [
                        'entry' => $video,
                        'loop' => $loop,
                        'selectionGroup' => 'selectedToday',
                    ])
                @endforeach
            </div>
        </div>

        {{-- Created Previously Section --}}
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
                        @include('ai-video-pro::partials.video-item', [
                            'entry' => $video,
                            'loop' => $loop,
                            'selectionGroup' => 'selectedPrevious',
                        ])
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div
        class="pointer-events-none fixed bottom-8 end-0 start-0 z-20 transition-all max-lg:bottom-[calc(var(--bottom-menu-height)+1rem)] lg:start-[--navbar-width]"
        x-show="totalSelected > 0"
        x-cloak
        x-transition.scale-95
    >
        <div class="container">
            <form
                class="pointer-events-auto flex flex-col items-center justify-between gap-1 rounded-full border border-foreground/5 bg-background px-6 py-4 shadow-xl shadow-black/5 md:flex-row md:py-1 md:pe-1"
                x-data="{ selectedAction: 'delete' }"
                @submit.prevent="bulkDelete"
            >
                <span
                    class="text-2xs font-medium"
                    x-text="totalSelected + ' {{ __('selected') }}'"
                ></span>

                <div class="flex items-center gap-2">
                    <x-forms.input
                        class="w-full rounded-full pe-12 md:w-auto md:pe-12"
                        type="select"
                        size="md"
                        x-model="selectedAction"
                    >
                        <option value="delete">
                            {{ __('Move to Trash') }}
                        </option>
                    </x-forms.input>

                    <x-button type="submit">
                        {{ __('Apply') }}
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
