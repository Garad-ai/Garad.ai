@php
    /** @var \App\Extensions\AiCaptions\System\Models\AiCaptionVideo $entry */
    $entryId = $entry->id;
    $status = $entry->status;
    $videoUrl = $entry->output_url ?? '';
    $title = $entry->title ?: ($entry->template_name ?: '');
    $errorMessage = $entry->error_message ?? '';
    $durationSeconds = $entry->duration_seconds;
    $selectionGroup = $selectionGroup ?? 'selectedToday';
@endphp

<div
    class="lqd-caption-video-item group/item relative"
    id="caption-video-{{ $entryId }}"
    :class="{ 'selected': {{ $selectionGroup }}.includes({{ $entryId }}) }"
>
    @if ($entry->isComplete())
        <div
            class="relative aspect-square overflow-hidden rounded-md bg-foreground/[3%]"
            x-data="{
                hovering: false,
                _playPromise: null,
                play() {
                    const v = this.$refs.video;
                    if (!v) return;
                    this._playPromise = Promise.resolve(v.play()).catch(() => {});
                },
                async pause() {
                    const v = this.$refs.video;
                    if (!v) return;
                    if (this._playPromise) {
                        try { await this._playPromise; } catch (e) {}
                    }
                    if (v.isConnected) v.pause();
                },
            }"
            @mouseenter="hovering = true; play()"
            @mouseleave="hovering = false; pause()"
        >
            <video
                class="size-full object-cover"
                x-ref="video"
                src="{{ $videoUrl }}{{ $videoUrl ? '#t=0.1' : '' }}"
                preload="metadata"
                muted
                loop
                playsinline
                loading="lazy"
            ></video>

            <div
                class="pointer-events-none absolute inset-x-0 bottom-0 top-2/3 z-1 overflow-hidden bg-gradient-to-t from-black/60 to-transparent">
            </div>

            <div class="absolute inset-0 z-2 flex flex-col justify-between gap-1 p-3">
                <div class="flex items-center justify-between gap-2">
                    <label class="relative z-3 inline-grid size-6 cursor-pointer place-items-center rounded bg-white/90 opacity-0 backdrop-blur-sm transition group-hover/item:opacity-100 group-[&.selected]/item:bg-primary group-[&.selected]/item:text-primary-foreground group-[&.selected]/item:opacity-100">
                        <input
                            class="hidden"
                            type="checkbox"
                            :checked="{{ $selectionGroup }}.includes({{ $entryId }})"
                            x-on:change="{{ $selectionGroup }}.includes({{ $entryId }}) ? {{ $selectionGroup }} = {{ $selectionGroup }}.filter(id => id !== {{ $entryId }}) : {{ $selectionGroup }}.push({{ $entryId }})"
                        />
                        <x-tabler-check class="size-3.5 opacity-0 group-[&.selected]/item:opacity-100" />
                    </label>

                    @if ($durationSeconds)
                        <p class="m-0 text-4xs font-medium text-white opacity-0 mix-blend-difference transition group-hover/item:opacity-100">
                            {{ sprintf('%02d:%02d', intdiv((int) $durationSeconds, 60), (int) $durationSeconds % 60) }}
                        </p>
                    @endif
                </div>

                <div class="pointer-events-none relative z-3 flex items-center justify-between gap-2 text-white">
                    <p class="m-0 truncate text-2xs font-medium">
                        {{ \Illuminate\Support\Str::limit($title, 60, '...') }}
                    </p>

                    <a
                        class="pointer-events-auto inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-white/20 text-white opacity-0 backdrop-blur-sm transition-colors hover:scale-110 hover:bg-white hover:text-black group-hover/item:opacity-100"
                        href="{{ $videoUrl }}"
                        download="captions-{{ $entryId }}.mp4"
                        title="{{ __('Download') }}"
                    >
                        <x-tabler-download class="size-4" />
                    </a>
                </div>
            </div>

            <x-button
                class="absolute inset-0 z-3 !min-h-0 cursor-pointer !rounded-card hover:!bg-transparent focus:!bg-transparent"
                variant="none"
                hover-variant="none"
                size="none"
                type="button"
                @click="$dispatch('open-caption-detail', { id: {{ $entryId }} })"
            ></x-button>
        </div>
    @elseif ($entry->isFailed())
        <div class="relative flex aspect-square flex-col items-center justify-center overflow-hidden rounded-card border border-card-border bg-card-background p-5 text-center shadow-sm">
            <div class="mx-auto mb-3 inline-grid size-9 place-items-center rounded-full bg-red-100 text-red-600">
                <x-tabler-alert-circle class="size-5" />
            </div>
            <span class="inline-block text-sm font-semibold text-red-600">
                @lang('Failed')
            </span>
            <p class="mt-2 text-2xs font-medium text-heading-foreground/70">
                {{ \Illuminate\Support\Str::limit($errorMessage ?: __('Caption generation failed.'), 100) }}
            </p>
        </div>
    @else
        <div class="flex aspect-square flex-col items-center justify-center rounded-md bg-foreground/[3%] p-5 text-center">
            <x-shimmer-text class="text-xs">
                @if ($entry->status === 'rendering')
                    @lang('Rendering...')
                @else
                    @lang('Generating captions...')
                @endif
            </x-shimmer-text>
        </div>
    @endif
</div>
