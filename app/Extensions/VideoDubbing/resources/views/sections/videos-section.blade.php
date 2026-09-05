<div class="lqd-dubbing-videos-wrap" id="lqd-dubbing-videos-wrap">
    <svg width="0" height="0">
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
                <stop offset="0.502" stop-color="#8A8AED" />
                <stop offset="1" stop-color="#6977DE" />
            </linearGradient>
        </defs>
    </svg>

    @if ($todayDubbings->isEmpty() && $previousDubbings->isEmpty())
        <x-empty-state
            icon="tabler-language"
            :title="__('No dubbed videos yet')"
            :description="__('Upload a video or paste a URL to get started with dubbing.')"
        />
    @else
        {{-- Bulk Action Bar --}}
        <div
            class="mb-4 flex items-center gap-3 rounded-card border border-card-border bg-card-background px-4 py-3"
            x-show="totalSelected > 0"
            x-cloak
            x-transition
        >
            <span class="text-sm font-medium" x-text="totalSelected + ' {{ __('selected') }}'"></span>
            <div class="ms-auto flex items-center gap-2">
                <x-button
                    size="sm"
                    variant="ghost"
                    hover-variant="none"
                    type="button"
                    @click="cancelSelection()"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    class="text-red-600 hover:bg-red-50 hover:text-red-700"
                    size="sm"
                    variant="ghost"
                    hover-variant="none"
                    type="button"
                    @click="bulkDelete()"
                >
                    <x-tabler-trash class="size-4" />
                    {{ __('Delete') }}
                </x-button>
            </div>
        </div>

        <div id="videos-container">
            {{-- Today Section --}}
            @if ($todayDubbings->isNotEmpty())
                <div class="mb-8">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold">{{ __('Today') }}</h3>
                        <x-button
                            class="text-xs font-medium text-primary hover:underline"
                            variant="link"
                            size="none"
                            type="button"
                            @click="
                                const todayIds = {{ $todayDubbings->pluck('id')->toJson() }};
                                if (selectedToday.length === todayIds.length) {
                                    selectedToday = [];
                                } else {
                                    selectedToday = [...todayIds];
                                }
                            "
                        >
                            <span x-text="selectedToday.length === {{ $todayDubbings->count() }} ? '{{ __('Deselect All') }}' : '{{ __('Select All') }}'"></span>
                        </x-button>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($todayDubbings as $entry)
                            @include('video-dubbing::partials.video-item', [
                                'entry' => $entry,
                                'loop' => $loop,
                                'selectionGroup' => 'selectedToday',
                            ])
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Created Previously Section --}}
            @if ($previousDubbings->isNotEmpty())
                <div>
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold">{{ __('Created Previously') }}</h3>
                        <x-button
                            class="text-xs font-medium text-primary hover:underline"
                            variant="link"
                            size="none"
                            type="button"
                            @click="
                                const prevIds = {{ $previousDubbings->pluck('id')->toJson() }};
                                if (selectedPrevious.length === prevIds.length) {
                                    selectedPrevious = [];
                                } else {
                                    selectedPrevious = [...prevIds];
                                }
                            "
                        >
                            <span x-text="selectedPrevious.length === {{ $previousDubbings->count() }} ? '{{ __('Deselect All') }}' : '{{ __('Select All') }}'"></span>
                        </x-button>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($previousDubbings as $entry)
                            @include('video-dubbing::partials.video-item', [
                                'entry' => $entry,
                                'loop' => $loop,
                                'selectionGroup' => 'selectedPrevious',
                            ])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    @include('video-dubbing::partials.video-detail-modal')
</div>
