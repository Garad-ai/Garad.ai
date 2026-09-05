{{-- Bottom: Timeline Panel --}}
<div class="lqd-ve-timeline relative flex shrink-0 flex-col border-t border-foreground/5 bg-background/95 backdrop-blur-xl"
	 :style="{ height: timelineHeight + 'px', minHeight: '180px' }"
>
	{{-- Timeline Resize Handle --}}
	<div
		class="absolute inset-x-0 -top-1 z-10 h-2 cursor-ns-resize hover:bg-primary/20"
		@mousedown.prevent="startTimelineResize($event)"
	></div>

	{{-- Timeline Toolbar --}}
	<div class="flex shrink-0 items-center gap-2 border-b border-foreground/5 px-3 py-1.5">
		{{-- Track Actions --}}
		<div class="flex items-center gap-1">
			<div class="relative" x-data="{ addTrackOpen: false }">
				<x-button
					class="text-3xs hover:translate-y-0"
					variant="ghost"
					size="sm"
					@click.prevent="addTrackOpen = !addTrackOpen"
				>
					<x-tabler-plus class="size-3.5" />
					{{ __('Add Track') }}
				</x-button>
				<div
					class="absolute bottom-full start-0 z-20 mb-1 min-w-36 rounded-lg bg-background p-1 shadow-lg shadow-black/15"
					x-show="addTrackOpen"
					x-cloak
					x-transition
					@click.outside="addTrackOpen = false"
				>
					<button
						class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-xs transition-colors hover:bg-foreground/5"
						@click.prevent="addTrack('video'); addTrackOpen = false"
					>
						<x-tabler-video class="size-4 text-blue-500" />
						{{ __('Video Track') }}
					</button>
					<button
						class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-xs transition-colors hover:bg-foreground/5"
						@click.prevent="addTrack('audio'); addTrackOpen = false"
					>
						<x-tabler-music class="size-4 text-green-500" />
						{{ __('Audio Track') }}
					</button>
					<button
						class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-xs transition-colors hover:bg-foreground/5"
						@click.prevent="addTrack('text'); addTrackOpen = false"
					>
						<x-tabler-letter-t class="size-4 text-amber-500" />
						{{ __('Text Track') }}
					</button>
				</div>
			</div>

			<div class="h-4 w-px bg-foreground/10"></div>

			{{-- Clip Operations --}}
			<x-button
				class="text-3xs hover:translate-y-0"
				variant="ghost"
				size="sm"
				title="{{ __('Split at Playhead') }} (S)"
				@click.prevent="splitClipAtPlayhead()"
				x-bind:disabled="!selectedClipId"
			>
				<x-tabler-cut class="size-3.5" />
				{{ __('Split') }}
			</x-button>

			<x-button
				class="text-3xs hover:translate-y-0"
				variant="ghost"
				size="sm"
				title="{{ __('Delete Selected') }} (Del)"
				@click.prevent="deleteClip(selectedClipId)"
				x-bind:disabled="!selectedClipId"
			>
				<x-tabler-trash class="size-3.5" />
				{{ __('Delete') }}
			</x-button>
		</div>

		<div class="flex-1"></div>

		{{-- Snap Toggle --}}
		<button
			class="lqd-btn inline-flex items-center justify-center gap-1.5 rounded-full py-1 px-2.5 text-3xs font-medium transition-all"
			:class="snapEnabled ? 'outline outline-button-border -outline-offset-1 text-foreground' : 'bg-transparent text-foreground hover:bg-foreground/10'"
			@click.prevent="snapEnabled = !snapEnabled"
			:title="snapEnabled ? '{{ __('Snapping On') }}' : '{{ __('Snapping Off') }}'"
		>
			<x-tabler-magnet class="size-3.5" />
		</button>

		{{-- Timeline Zoom --}}
		<div class="flex items-center gap-1.5">
			<x-tabler-zoom-out class="size-3.5 text-foreground/40" />
			<input
				type="range"
				class="w-20 cursor-pointer"
				min="0.1"
				max="5"
				step="0.1"
				x-model.number="timelineZoom"
			/>
			<x-tabler-zoom-in class="size-3.5 text-foreground/40" />
		</div>
	</div>

	{{-- Timeline Content --}}
	<div class="flex flex-1 overflow-hidden">
		{{-- Track Labels --}}
		<div class="flex w-40 shrink-0 flex-col overflow-hidden border-e border-foreground/5 bg-background/50">
			{{-- Ruler spacer for track labels — stays pinned at the top (mirrors the
			     sticky ruler in the tracks area on the right). --}}
			<div class="h-7 shrink-0 border-b border-foreground/5"></div>

			{{-- Scrollable label list. No scrollbar of its own — it's translated in
			     lockstep with the tracks area's vertical scroll, so the two columns
			     stay row-aligned when there are more tracks than fit. --}}
			<div
				class="flex flex-col"
				:style="{ transform: 'translateY(' + (-timelineScrollY) + 'px)' }"
			>
			<template x-for="(track, trackIndex) in tracks" :key="track.id">
				<div
					class="group flex items-center gap-1.5 border-b border-foreground/5 px-2"
					:style="{ height: getTrackHeight(track) + 'px' }"
				>
					{{-- Track Type Icon --}}
					<span
						class="flex size-5 shrink-0 items-center justify-center rounded"
						:class="{
							'bg-blue-500/10 text-blue-500': track.type === 'video' || track.type === 'image',
							'bg-green-500/10 text-green-500': track.type === 'audio',
							'bg-amber-500/10 text-amber-500': track.type === 'text'
						}"
					>
						<template x-if="track.type === 'video' || track.type === 'image'">
							<x-tabler-video class="size-3" />
						</template>
						<template x-if="track.type === 'audio'">
							<x-tabler-music class="size-3" />
						</template>
						<template x-if="track.type === 'text'">
							<x-tabler-letter-t class="size-3" />
						</template>
					</span>

					{{-- Track Name --}}
					<span
						class="flex-1 truncate text-3xs font-medium"
						x-text="track.label"
					></span>

					{{-- Track Controls --}}
					<div class="hidden items-center gap-0.5 group-hover:flex">
						<button
							class="flex size-5 items-center justify-center rounded transition-colors hover:bg-foreground/10"
							:class="{ 'text-amber-500': track.locked }"
							@click.prevent="toggleTrackLock(track.id)"
							:title="track.locked ? '{{ __('Unlock') }}' : '{{ __('Lock') }}'"
						>
							<template x-if="!track.locked">
								<x-tabler-lock-open class="size-3" />
							</template>
							<template x-if="track.locked">
								<x-tabler-lock class="size-3" />
							</template>
						</button>
						<button
							class="flex size-5 items-center justify-center rounded transition-colors hover:bg-foreground/10"
							:class="{ 'text-red-500': track.muted }"
							@click.prevent="toggleTrackMute(track.id)"
							:title="track.muted ? '{{ __('Unmute') }}' : '{{ __('Mute') }}'"
						>
							<template x-if="!track.muted">
								<x-tabler-eye class="size-3" />
							</template>
							<template x-if="track.muted">
								<x-tabler-eye-off class="size-3" />
							</template>
						</button>
						<button
							class="flex size-5 items-center justify-center rounded text-red-500 transition-colors hover:bg-red-500/10"
							@click.prevent="removeTrack(track.id)"
							title="{{ __('Remove Track') }}"
						>
							<x-tabler-x class="size-3" />
						</button>
					</div>
				</div>
			</template>
			</div>
		</div>

		{{-- Timeline Tracks Area --}}
		<div
			class="relative flex-1 overflow-x-auto overflow-y-auto"
			x-ref="timelineScroll"
			@scroll="syncTimelineScroll()"
		>
			{{-- Time Ruler (HTML-based) --}}
			<div
				class="sticky top-0 z-10 flex h-7 shrink-0 cursor-pointer items-end border-b border-foreground/5 bg-background/95"
				:style="{ width: timelineContentWidth + 'px' }"
				@mousedown.prevent="startRulerScrub($event)"
			>
				<template x-for="tick in getRulerTicks()" :key="tick.ms">
					<div
						class="absolute bottom-0 flex flex-col items-center"
						:style="{ left: tick.px + 'px' }"
					>
						{{-- Tick label (only for major ticks) --}}
						<span
							x-show="tick.major"
							class="mb-0.5 select-none text-[10px] leading-none text-foreground/40"
							x-text="tick.label"
						></span>
						{{-- Tick line --}}
						<div
							class="bg-foreground/15"
							:style="{ width: '1px', height: tick.major ? '8px' : '4px' }"
						></div>
					</div>
				</template>
			</div>

			{{-- Tracks Container --}}
			<div
				class="relative"
				x-ref="tracksContainer"
				:style="{ width: timelineContentWidth + 'px' }"
				@dragover.prevent="handleTimelineDragOver($event)"
				@drop.prevent="handleTimelineDrop($event)"
			>
				{{-- Tracks --}}
				<template x-for="(track, trackIndex) in tracks" :key="track.id">
					<div
						class="relative border-b border-foreground/5"
						:style="{ height: getTrackHeight(track) + 'px' }"
						:class="{
							'opacity-50': track.locked,
							'bg-foreground/[2%]': trackIndex % 2 === 0
						}"
						@click="deselectClip()"
					>
						{{-- Clips --}}
						<template x-for="clip in track.clips" :key="clip.id">
							<div
								class="absolute cursor-pointer select-none overflow-hidden rounded-md border transition-colors"
								:class="{
									'border-blue-500/60 bg-blue-950/40 hover:bg-blue-900/40': (track.type === 'video' || track.type === 'image') && selectedClipId !== clip.id,
									'border-green-500/60 bg-green-950 hover:bg-green-900': track.type === 'audio' && selectedClipId !== clip.id,
									'border-amber-500/60 bg-amber-950 hover:bg-amber-900': track.type === 'text' && selectedClipId !== clip.id,
									'border-primary bg-primary/40 ring-1 ring-primary shadow-md': selectedClipId === clip.id,
									'pointer-events-none': track.locked
								}"
								:style="{
									left: msToPixels(clip.startTime) + 'px',
									top: ((clip.lane || 0) * trackItemHeight + 1) + 'px',
									width: Math.max(msToPixels(clip.endTime - clip.startTime), 20) + 'px',
									height: (trackItemHeight - 2) + 'px'
								}"
								@click.stop="selectClip(clip.id, track.id)"
								@mousedown.prevent="!track.locked && startClipDrag($event, clip, track)"
							>
								{{-- Left Trim Handle --}}
								<div
									class="absolute -start-px bottom-0 top-0 z-10 w-2.5 cursor-col-resize rounded-s-md hover:bg-white/30"
									@mousedown.stop.prevent="startClipTrim($event, clip, track, 'left')"
								></div>

								{{-- Audio Waveform --}}
								<template x-if="track.type === 'audio'">
									<svg
										class="pointer-events-none absolute inset-0 h-full w-full text-green-300/70"
										preserveAspectRatio="none"
										:viewBox="'0 0 ' + Math.max(msToPixels(clip.endTime - clip.startTime), 20) + ' ' + (trackItemHeight - 10)"
									>
										<path
											fill="currentColor"
											:d="buildWaveformPath(getAudioPeaks(clip.mediaId), Math.max(msToPixels(clip.endTime - clip.startTime), 20), trackItemHeight - 10)"
										></path>
									</svg>
								</template>

								{{-- Clip Content --}}
								<div class="relative z-[1] flex h-full items-center overflow-hidden px-3">
									{{-- Thumbnail for video/image --}}
									<template x-if="(track.type === 'video' || track.type === 'image') && _mediaMap[clip.mediaId]?.thumbnail_url">
										<img
											:src="_mediaMap[clip.mediaId].thumbnail_url"
											class="me-1.5 h-full max-h-8 w-auto shrink-0 rounded-sm object-cover"
										/>
									</template>

									<span
										class="truncate text-4xs font-medium"
										x-text="clip.text || _mediaMap[clip.mediaId]?.original_filename || '{{ __('Clip') }}'"
									></span>
								</div>

								{{-- Right Trim Handle --}}
								<div
									class="absolute -end-px bottom-0 top-0 z-10 w-2.5 cursor-col-resize rounded-e-md hover:bg-white/30"
									@mousedown.stop.prevent="startClipTrim($event, clip, track, 'right')"
								></div>
							</div>
						</template>
					</div>
				</template>

				{{-- Playhead Line --}}
				<div
					class="absolute bottom-0 top-0 z-20 w-px bg-red-500"
					style="pointer-events: none"
					:style="{ left: msToPixels(currentTime) + 'px' }"
				>
					{{-- Draggable playhead handle --}}
					<div
						class="absolute -top-7 start-1/2 -translate-x-1/2 cursor-grab active:cursor-grabbing"
						style="pointer-events: auto; padding: 0 6px"
						@mousedown.prevent.stop="startRulerScrub($event)"
					>
						<div class="size-0 border-x-[5px] border-t-[6px] border-x-transparent border-t-red-500"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
