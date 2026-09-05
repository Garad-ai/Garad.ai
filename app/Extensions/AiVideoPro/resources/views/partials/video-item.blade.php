@php
    $entryId = is_array($entry) ? $entry['id'] : $entry->id;
    $status = is_array($entry) ? $entry['status'] : $entry->status;
    $videoUrl = is_array($entry) ? $entry['video_url'] ?? '' : $entry->video_url ?? '';
    $prompt = is_array($entry) ? $entry['prompt'] ?? '' : $entry->prompt ?? '';
    $errorMessage = is_array($entry) ? $entry['error_message'] ?? '' : $entry->error_message ?? '';
    $createdAt = is_array($entry) ? $entry['created_at'] ?? '' : $entry->created_at ?? '';
    $durationSeconds = is_array($entry) ? $entry['duration_seconds'] ?? null : $entry->duration_seconds ?? null;
    $selectionGroup = $selectionGroup ?? 'selectedToday';
@endphp

<div
    class="lqd-video-pro-item group/item relative"
    id="video-{{ $entryId }}"
    :class="{ 'selected': {{ $selectionGroup }}.includes({{ $entryId }}) }"
>
    @if ($status === 'complete')
        <div
            class="relative aspect-square overflow-hidden rounded-md bg-foreground/[3%]"
            x-data="{ hovering: false }"
            @mouseenter="hovering = true; $refs.video?.play().catch(() => {})"
            @mouseleave="hovering = false; if ($refs.video) { $refs.video.pause() }"
        >
            {{-- Video --}}
            <video
                class="size-full object-cover"
                x-ref="video"
                src="{{ $videoUrl }}"
                preload="metadata"
                muted
                loop
                playsinline
                loading="lazy"
            ></video>

            <div
                class="pointer-events-none absolute inset-0 z-0 translate-y-5 bg-gradient-to-t from-black/90 from-10% to-transparent to-40% opacity-0 transition group-hover/item:translate-y-0 group-hover/item:opacity-100">
            </div>

            <div class="absolute inset-0 flex flex-col justify-between gap-1 p-3">
                <div class="flex items-center justify-between gap-2">
                    <label
                        class="relative z-3 inline-grid size-6 cursor-pointer place-items-center rounded bg-white/90 opacity-0 backdrop-blur-sm transition group-hover/item:opacity-100 group-[&.selected]/item:bg-primary group-[&.selected]/item:text-primary-foreground group-[&.selected]/item:opacity-100"
                    >
                        <input
                            class="hidden"
                            type="checkbox"
                            :checked="{{ $selectionGroup }}.includes({{ $entryId }})"
                            x-on:change="{{ $selectionGroup }}.includes({{ $entryId }}) ? {{ $selectionGroup }} = {{ $selectionGroup }}.filter(id => id !== {{ $entryId }}) : {{ $selectionGroup }}.push({{ $entryId }})"
                        />
                        <x-tabler-check class="size-3.5 opacity-0 group-[&.selected]/item:opacity-100" />
                    </label>

                    {{-- Duration badge --}}
                    @if ($durationSeconds)
                        <p class="m-0 text-4xs font-medium text-white opacity-0 mix-blend-difference transition group-hover/item:opacity-100">
                            {{ sprintf('%02d:%02d', intdiv($durationSeconds, 60), $durationSeconds % 60) }}
                        </p>
                    @endif
                </div>

                <div class="pointer-events-none relative z-3 flex items-center justify-between gap-2 text-white opacity-0 transition group-hover/item:opacity-100">
                    <p class="m-0 truncate text-2xs font-medium">
                        {{ Str::limit($prompt, 60, '...') }}
                    </p>

                    <a
                        class="pointer-events-auto inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-sm transition-colors hover:scale-110 hover:bg-white hover:text-black"
                        href="{{ $videoUrl }}"
                        download="{{ basename($videoUrl) }}"
                        title="{{ __('Download') }}"
                    >
                        <x-tabler-download class="size-4" />
                    </a>
                </div>
            </div>

            {{-- Clickable area for modal --}}
            <x-button
                class="absolute inset-0 z-2 !min-h-0 cursor-pointer !rounded-card hover:!bg-transparent focus:!bg-transparent"
                variant="none"
                hover-variant="none"
                size="none"
                type="button"
                @click="$dispatch('open-video-detail', { id: {{ $entryId }} })"
            ></x-button>
        </div>
    @elseif ($status === 'error')
        <div
            class="relative flex aspect-square flex-col items-center justify-center overflow-hidden rounded-card border border-card-border bg-card-background p-5 text-center shadow-sm">
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
                    <x-tabler-check
                        class="size-3.5 text-white"
                        x-show="{{ $selectionGroup }}.includes({{ $entryId }})"
                        x-cloak
                    />
                </label>
            </div>

            <div class="mx-auto mb-3 inline-grid size-9 place-items-center rounded-full bg-red-100 text-red-600">
                <x-tabler-alert-circle class="size-5" />
            </div>
            <span class="inline-block text-sm font-semibold text-red-600">
                @lang('Failed')
            </span>
            <p class="mt-2 text-2xs font-medium text-heading-foreground/70">
                {{ \Illuminate\Support\Str::limit($errorMessage ?: __('Video generation failed.'), 100) }}
            </p>

            {{-- Clickable area for modal --}}
            <x-button
                class="absolute inset-0 z-[5] !min-h-0 cursor-pointer !rounded-card hover:!bg-transparent focus:!bg-transparent"
                variant="none"
                hover-variant="none"
                size="none"
                type="button"
                @click="$dispatch('open-video-detail', { id: {{ $entryId }} })"
            ></x-button>
        </div>
    @else
        <div class="flex aspect-square flex-col items-center justify-center rounded-md bg-foreground/[3%] p-5 text-center">
            <x-shimmer-text class="text-xs">
                @lang('Generating Video...')
            </x-shimmer-text>
        </div>
    @endif
</div>
