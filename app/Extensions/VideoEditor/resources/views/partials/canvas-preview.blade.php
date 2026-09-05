{{-- Center: Canvas Preview --}}
<div
	class="lqd-ve-canvas-area relative flex flex-1 flex-col overflow-hidden"
	@dragover.prevent="handleCanvasDragOver($event)"
	@drop.prevent="handleCanvasDrop($event)"
>
	{{-- Canvas Container --}}
	<div class="relative flex flex-1 items-center justify-center overflow-hidden bg-foreground/[3%]"
		 style="background-image: radial-gradient(hsl(var(--foreground) / 8%) 0.75px, transparent 0px); background-size: 16px 16px; background-position: center;">

		{{-- Canvas Wrapper (with zoom) --}}
		<div
			class="relative shadow-2xl shadow-black/20"
			:style="{
				width: projectWidth * canvasZoom + 'px',
				height: projectHeight * canvasZoom + 'px',
				transition: 'width 0.2s, height 0.2s'
			}"
		>
			{{-- Main Canvas --}}
			<canvas
				x-ref="previewCanvas"
				class="h-full w-full bg-black"
				:width="projectWidth"
				:height="projectHeight"
				@mousedown.prevent="startCanvasClipDrag($event)"
			></canvas>

			{{-- Selection Overlay for Video/Image Clips --}}
			<div
				x-show="selectedClipId && (selectedClipType === 'video' || selectedClipType === 'image') && selectedClipProps.width"
				class="pointer-events-none absolute border-2 border-blue-500"
				style="overflow: visible;"
				:style="{
					left: ((selectedClipProps.x || 0) * canvasZoom) + 'px',
					top: ((selectedClipProps.y || 0) * canvasZoom) + 'px',
					width: ((selectedClipProps.width || 0) * canvasZoom) + 'px',
					height: ((selectedClipProps.height || 0) * canvasZoom) + 'px',
				}"
			>
				{{-- Corner Resize Handles --}}
				<div class="pointer-events-auto absolute size-3 cursor-nw-resize rounded-full border-2 border-blue-500 bg-white"
					style="top: 0; left: 0; transform: translate(-50%, -50%);"
					@mousedown.prevent.stop="startCanvasClipResize($event, 'tl')"></div>
				<div class="pointer-events-auto absolute size-3 cursor-ne-resize rounded-full border-2 border-blue-500 bg-white"
					style="top: 0; right: 0; transform: translate(50%, -50%);"
					@mousedown.prevent.stop="startCanvasClipResize($event, 'tr')"></div>
				<div class="pointer-events-auto absolute size-3 cursor-sw-resize rounded-full border-2 border-blue-500 bg-white"
					style="bottom: 0; left: 0; transform: translate(-50%, 50%);"
					@mousedown.prevent.stop="startCanvasClipResize($event, 'bl')"></div>
				<div class="pointer-events-auto absolute size-3 cursor-se-resize rounded-full border-2 border-blue-500 bg-white"
					style="bottom: 0; right: 0; transform: translate(50%, 50%);"
					@mousedown.prevent.stop="startCanvasClipResize($event, 'se')"></div>
			</div>

			{{-- Text Overlays (rendered on top of canvas for editing) --}}
			<template x-for="textClip in getVisibleTextClips()" :key="textClip.id">
				<div
					class="absolute z-10 cursor-move select-none"
					style="white-space: pre-line; line-height: 1.2;"
					:data-text-clip-id="textClip.id"
					:style="{
						left: (textClip.x * canvasZoom) + 'px',
						top: (textClip.y * canvasZoom) + 'px',
						fontSize: (textClip.fontSize * canvasZoom) + 'px',
						color: textClip.color || '#ffffff',
						fontFamily: textClip.fontFamily || 'Inter, sans-serif',
						fontWeight: textClip.fontWeight || '400',
						textShadow: '0 1px 4px rgba(0,0,0,0.5)',
					}"
					@mousedown.prevent.stop="startTextDrag($event, textClip)"
					@click.prevent="selectClip(textClip.id, textClip.trackId)"
					x-effect="
						// Track reactive deps that affect the rendered text box so
						// the selection chrome re-measures when any of them change.
						textClip.text; textClip.fontSize; textClip.fontFamily; textClip.fontWeight; textClip.x; textClip.y; canvasZoom;
						if (selectedClipId === textClip.id) $nextTick(() => measureSelectedTextBox());
					"
					x-text="textClip.text"
				></div>
			</template>

			{{-- Selection chrome for the currently-selected text clip. Rendered
				 as a sibling (not a child) of the text wrapper so its sizing is
				 independent of inline-flow / line-box quirks — corner dots stay
				 at the bounding-box corners even when text wraps. --}}
			<template x-if="selectedClipId && selectedClipType === 'text' && selectedTextBox">
				<div
					class="pointer-events-none absolute border-2 border-primary"
					:style="{
						left: (selectedTextBox.x * canvasZoom - 2) + 'px',
						top: (selectedTextBox.y * canvasZoom - 2) + 'px',
						width: (selectedTextBox.w * canvasZoom + 4) + 'px',
						height: (selectedTextBox.h * canvasZoom + 4) + 'px',
					}"
				>
					<div class="pointer-events-auto absolute -start-1.5 -top-1.5 size-2.5 cursor-nw-resize rounded-full border-2 border-primary bg-white"
						@mousedown.prevent.stop="startTextResize($event, 'tl')"></div>
					<div class="pointer-events-auto absolute -end-1.5 -top-1.5 size-2.5 cursor-ne-resize rounded-full border-2 border-primary bg-white"
						@mousedown.prevent.stop="startTextResize($event, 'tr')"></div>
					<div class="pointer-events-auto absolute -bottom-1.5 -start-1.5 size-2.5 cursor-sw-resize rounded-full border-2 border-primary bg-white"
						@mousedown.prevent.stop="startTextResize($event, 'bl')"></div>
					<div class="pointer-events-auto absolute -bottom-1.5 -end-1.5 size-2.5 cursor-se-resize rounded-full border-2 border-primary bg-white"
						@mousedown.prevent.stop="startTextResize($event, 'br')"></div>
				</div>
			</template>

			{{-- Playhead Indicator --}}
			<div class="pointer-events-none absolute inset-x-0 bottom-0 h-1 bg-gradient-to-t from-black/20 to-transparent"></div>
		</div>

	</div>

	{{-- Playback Controls --}}
	<div class="flex items-center justify-between border-t border-foreground/5 bg-background/90 px-4 py-2 backdrop-blur-xl">
		{{-- Time Display --}}
		<span
			class="min-w-20 font-mono text-2xs text-foreground/70"
			x-text="formatTimecode(currentTime) + ' / ' + formatTimecode(totalDuration)"
		></span>

		{{-- Transport Controls --}}
		<div class="flex items-center gap-1">
			<x-button
				class="size-7 hover:translate-y-0"
				variant="ghost"
				size="none"
				title="{{ __('Go to Start') }}"
				@click.prevent="seekTo(0)"
			>
				<x-tabler-player-skip-back-filled class="size-3.5" />
			</x-button>

			<x-button
				class="size-7 hover:translate-y-0"
				variant="ghost"
				size="none"
				title="{{ __('Previous Frame') }}"
				@click.prevent="stepFrame(-1)"
			>
				<x-tabler-player-track-prev-filled class="size-3.5" />
			</x-button>

			<button
				class="lqd-btn inline-flex size-9 items-center justify-center rounded-full transition-all"
				:class="playing ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/10' : 'bg-background text-foreground shadow-xs hover:bg-primary hover:text-primary-foreground'"
				title="{{ __('Play/Pause') }} (Space)"
				@click.prevent="togglePlayback()"
			>
				<template x-if="!playing">
					<x-tabler-player-play-filled class="size-4" />
				</template>
				<template x-if="playing">
					<x-tabler-player-pause-filled class="size-4" />
				</template>
			</button>

			<x-button
				class="size-7 hover:translate-y-0"
				variant="ghost"
				size="none"
				title="{{ __('Next Frame') }}"
				@click.prevent="stepFrame(1)"
			>
				<x-tabler-player-track-next-filled class="size-3.5" />
			</x-button>

			<x-button
				class="size-7 hover:translate-y-0"
				variant="ghost"
				size="none"
				title="{{ __('Go to End') }}"
				@click.prevent="seekTo(totalDuration)"
			>
				<x-tabler-player-skip-forward-filled class="size-3.5" />
			</x-button>
		</div>

		{{-- Volume --}}
		<div class="flex items-center gap-1.5">
			<x-button
				class="size-7 hover:translate-y-0"
				variant="ghost"
				size="none"
				@click.prevent="masterMuted = !masterMuted"
			>
				<template x-if="!masterMuted && masterVolume > 0">
					<x-tabler-volume class="size-4" />
				</template>
				<template x-if="masterMuted || masterVolume === 0">
					<x-tabler-volume-off class="size-4" />
				</template>
			</x-button>
			<input
				type="range"
				class="w-16 cursor-pointer"
				min="0"
				max="1"
				step="0.05"
				x-model.number="masterVolume"
				@input="updateMasterVolume()"
			/>
		</div>

		{{-- Resolution & FPS --}}
		<div class="flex items-center gap-2">
			<span class="rounded bg-foreground/5 px-1.5 py-0.5 text-4xs font-medium text-foreground/50">
				<span x-text="projectAspectRatio"></span>
				<span class="mx-0.5 opacity-40">|</span>
				<span x-text="projectWidth + '×' + projectHeight"></span>
			</span>
			<span class="rounded bg-foreground/5 px-1.5 py-0.5 text-4xs font-medium text-foreground/50">
				<span x-text="projectFps"></span> fps
			</span>
		</div>
	</div>
</div>
