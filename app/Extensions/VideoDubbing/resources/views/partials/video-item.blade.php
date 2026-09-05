@php
    $entryId = is_array($entry) ? $entry['id'] : $entry->id;
    $status = is_array($entry) ? $entry['status'] : $entry->status;
    $outputUrl = is_array($entry) ? ($entry['output_url'] ?? '') : ($entry->output_url ?? '');
    $title = is_array($entry) ? ($entry['title'] ?? '') : ($entry->title ?? '');
    $targetLanguage = is_array($entry) ? ($entry['target_language'] ?? '') : ($entry->target_language ?? '');
    $provider = is_array($entry) ? ($entry['provider'] ?? 'heygen') : ($entry->provider ?? 'heygen');
    $errorMessage = is_array($entry) ? ($entry['error_message'] ?? '') : ($entry->error_message ?? '');
    $createdAt = is_array($entry) ? ($entry['created_at'] ?? '') : ($entry->created_at ?? '');
    $durationSeconds = is_array($entry) ? ($entry['duration_seconds'] ?? null) : ($entry->duration_seconds ?? null);
    $selectionGroup = $selectionGroup ?? 'selectedToday';

    $languages = config('videodubbing.' . $provider . '.languages', []);
    $targetLangName = $languages[$targetLanguage] ?? $targetLanguage;
    $displayTitle = $title ?: __('Dubbed to :lang', ['lang' => __($targetLangName)]);
@endphp

<div
    class="group/item relative"
    id="dubbing-{{ $entryId }}"
>
    @if ($status === 'complete')
        <div
            class="relative aspect-square overflow-hidden rounded-card border border-card-border bg-card-background shadow-sm"
            x-data="{ hovering: false }"
            @mouseenter="hovering = true; $refs.vid{{ $entryId }}?.play().catch(() => {})"
            @mouseleave="hovering = false; if ($refs.vid{{ $entryId }}) { $refs.vid{{ $entryId }}.pause(); $refs.vid{{ $entryId }}.currentTime = 0; }"
        >
            {{-- Video --}}
            <video
                class="size-full object-cover"
                x-ref="vid{{ $entryId }}"
                src="{{ $outputUrl }}"
                preload="metadata"
                muted
                loop
                playsinline
            ></video>

            {{-- Play icon overlay (visible until hover) --}}
            <div
                class="pointer-events-none absolute inset-0 z-[2] flex items-center justify-center transition-opacity"
                :class="hovering ? 'opacity-0' : 'opacity-100'"
            >
                <div class="flex size-10 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-sm">
                    <x-tabler-player-play-filled class="size-4 ms-0.5" />
                </div>
            </div>

            {{-- Selection checkbox --}}
            <div
                class="absolute start-2 top-2 z-10 opacity-0 transition-opacity group-hover/item:opacity-100"
                :class="{ '!opacity-100': {{ $selectionGroup }}.includes({{ $entryId }}) }"
            >
                <label
                    class="flex size-6 cursor-pointer items-center justify-center rounded-md border-2 border-white/80 bg-black/30 backdrop-blur-sm transition-colors"
                    :class="{ 'bg-primary border-primary': {{ $selectionGroup }}.includes({{ $entryId }}) }"
                >
                    <input
                        class="hidden"
                        type="checkbox"
                        :checked="{{ $selectionGroup }}.includes({{ $entryId }})"
                        x-on:change="{{ $selectionGroup }}.includes({{ $entryId }}) ? {{ $selectionGroup }} = {{ $selectionGroup }}.filter(id => id !== {{ $entryId }}) : {{ $selectionGroup }}.push({{ $entryId }})"
                    />
                    <x-tabler-check class="size-3.5 text-white" x-show="{{ $selectionGroup }}.includes({{ $entryId }})" x-cloak />
                </label>
            </div>

            {{-- Duration badge --}}
            @if ($durationSeconds)
                <div class="absolute end-2 top-2 z-10 rounded-md bg-black/60 px-1.5 py-0.5 text-2xs font-medium text-white backdrop-blur-sm">
                    {{ sprintf('%02d:%02d', intdiv($durationSeconds, 60), $durationSeconds % 60) }}
                </div>
            @endif

            {{-- Bottom gradient overlay --}}
            <div class="absolute inset-x-0 bottom-0 z-10 flex items-center gap-2 bg-gradient-to-t from-black/70 to-transparent px-3 pb-3 pt-8 opacity-0 transition-opacity group-hover/item:opacity-100">
                <p class="min-w-0 flex-1 line-clamp-2 text-xs text-white/90">
                    {{ Str::limit($displayTitle, 60, '...') }}
                </p>
                <a
                    class="shrink-0 inline-flex size-7 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-sm transition-colors hover:bg-white/30"
                    href="{{ $outputUrl }}"
                    download="{{ basename($outputUrl) }}"
                    title="{{ __('Download') }}"
                    @click.stop
                >
                    <x-tabler-download class="size-3.5" />
                </a>
            </div>

            {{-- Clickable area for modal --}}
            <x-button
                class="absolute inset-0 z-[5] cursor-pointer !min-h-0 !rounded-card hover:!bg-transparent focus:!bg-transparent"
                variant="none"
                hover-variant="none"
                size="none"
                type="button"
                @click="$dispatch('open-video-detail', { id: {{ $entryId }} })"
            ></x-button>
        </div>
    @elseif ($status === 'error')
        <div class="relative flex aspect-square flex-col items-center justify-center overflow-hidden rounded-card border border-card-border bg-card-background p-5 text-center shadow-sm">
            {{-- Selection checkbox --}}
            <div
                class="absolute start-2 top-2 z-10 opacity-0 transition-opacity group-hover/item:opacity-100"
                :class="{ '!opacity-100': {{ $selectionGroup }}.includes({{ $entryId }}) }"
            >
                <label
                    class="flex size-6 cursor-pointer items-center justify-center rounded-md border-2 border-foreground/30 bg-foreground/10 backdrop-blur-sm transition-colors"
                    :class="{ 'bg-primary border-primary': {{ $selectionGroup }}.includes({{ $entryId }}) }"
                >
                    <input
                        class="hidden"
                        type="checkbox"
                        :checked="{{ $selectionGroup }}.includes({{ $entryId }})"
                        x-on:change="{{ $selectionGroup }}.includes({{ $entryId }}) ? {{ $selectionGroup }} = {{ $selectionGroup }}.filter(id => id !== {{ $entryId }}) : {{ $selectionGroup }}.push({{ $entryId }})"
                    />
                    <x-tabler-check class="size-3.5 text-white" x-show="{{ $selectionGroup }}.includes({{ $entryId }})" x-cloak />
                </label>
            </div>

            <div class="mx-auto mb-3 inline-grid size-9 place-items-center rounded-full bg-red-100 text-red-600">
                <x-tabler-alert-circle class="size-5" />
            </div>
            <span class="inline-block text-sm font-semibold text-red-600">
                @lang('Failed')
            </span>
            <p class="mt-2 text-2xs font-medium text-heading-foreground/70">
                {{ \Illuminate\Support\Str::limit($errorMessage ?: __('Something went wrong. Please try again.'), 100) }}
            </p>

            {{-- Clickable area for modal --}}
            <x-button
                class="absolute inset-0 z-[5] cursor-pointer !min-h-0 !rounded-card hover:!bg-transparent focus:!bg-transparent"
                variant="none"
                hover-variant="none"
                size="none"
                type="button"
                @click="$dispatch('open-video-detail', { id: {{ $entryId }} })"
            ></x-button>
        </div>
    @else
        <div class="flex aspect-square flex-col items-center justify-center rounded-card border border-card-border bg-foreground/[0.03] p-5 text-center shadow-sm">
            <x-shimmer-text class="text-sm font-semibold">
                @lang('Generating Video...')
            </x-shimmer-text>
            <p class="mt-1 text-2xs text-foreground/50">
                {{ __('Dubbing to :lang', ['lang' => __($targetLangName)]) }}
            </p>
        </div>
    @endif
</div>
