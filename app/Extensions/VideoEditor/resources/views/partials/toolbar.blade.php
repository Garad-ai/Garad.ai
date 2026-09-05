{{-- Top Toolbar --}}
<nav class="lqd-ve-toolbar relative z-20 flex h-14 shrink-0 items-center gap-3 border-b border-foreground/5 bg-background/90 px-4 shadow-sm shadow-black/5 backdrop-blur-xl backdrop-saturate-[120%]">
	{{-- Left: Back + Project Name --}}
	<div class="flex items-center gap-3">
		<a
			class="lqd-btn inline-flex size-8 items-center justify-center rounded-full bg-transparent text-foreground transition-all hover:bg-foreground/10"
			x-bind:href="routes.index"
			title="{{ __('Back to Projects') }}"
		>
			<x-tabler-chevron-left class="size-4" />
		</a>

		<div class="flex items-center gap-2">
			<x-tabler-movie class="size-5 text-primary" />
			<template x-if="!editingName">
				<button
					class="max-w-52 truncate text-sm font-semibold text-heading-foreground transition-colors hover:text-primary"
					@click.prevent="editingName = true; $nextTick(() => $refs.nameInput.focus())"
					x-text="projectName"
				></button>
			</template>
			<template x-if="editingName">
				<input
					x-ref="nameInput"
					type="text"
					class="w-52 rounded-lg border border-foreground/10 bg-foreground/5 px-2 py-1 text-sm font-semibold text-heading-foreground outline-none focus:border-primary"
					x-model="projectName"
					@keydown.enter="editingName = false"
					@keydown.escape="editingName = false"
					@blur="editingName = false"
				/>
			</template>
		</div>
	</div>

	{{-- Center: Undo / Redo / Zoom --}}
	<div class="flex flex-1 items-center justify-center gap-1">
		<x-button
			class="size-8 hover:translate-y-0"
			variant="ghost"
			size="none"
			title="{{ __('Undo') }} (Ctrl+Z)"
			@click.prevent="undo()"
			x-bind:disabled="!canUndo"
		>
			<x-tabler-arrow-back-up class="size-4" />
		</x-button>

		<x-button
			class="size-8 hover:translate-y-0"
			variant="ghost"
			size="none"
			title="{{ __('Redo') }} (Ctrl+Shift+Z)"
			@click.prevent="redo()"
			x-bind:disabled="!canRedo"
		>
			<x-tabler-arrow-forward-up class="size-4" />
		</x-button>

		<div class="mx-2 h-5 w-px bg-foreground/10"></div>

		<x-button
			class="size-8 hover:translate-y-0"
			variant="ghost"
			size="none"
			title="{{ __('Zoom Out') }}"
			@click.prevent="zoomCanvas(-0.1)"
		>
			<x-tabler-zoom-out class="size-4" />
		</x-button>

		<span
			class="min-w-10 text-center text-2xs font-medium text-foreground/70"
			x-text="Math.round(canvasZoom * 100) + '%'"
		></span>

		<x-button
			class="size-8 hover:translate-y-0"
			variant="ghost"
			size="none"
			title="{{ __('Zoom In') }}"
			@click.prevent="zoomCanvas(0.1)"
		>
			<x-tabler-zoom-in class="size-4" />
		</x-button>

		<x-button
			class="size-8 hover:translate-y-0"
			variant="ghost"
			size="none"
			title="{{ __('Fit to Screen') }}"
			@click.prevent="zoomCanvasFit()"
		>
			<x-tabler-arrows-maximize class="size-4" />
		</x-button>
	</div>

	{{-- Right: Save + Export --}}
	<div class="flex items-center gap-2">
		<span
			class="text-3xs text-foreground/50"
			x-show="lastSavedText"
			x-text="lastSavedText"
		></span>

		<x-button
			class="hover:translate-y-0"
			variant="ghost"
			size="sm"
			@click.prevent="saveProject()"
			x-bind:disabled="saving"
		>
			<x-tabler-device-floppy class="size-4" />
			<span x-text="saving ? '{{ __('Saving...') }}' : '{{ __('Save') }}'"></span>
		</x-button>

		<x-button
			variant="primary"
			size="sm"
			@click.prevent="openExportModal()"
		>
			<x-tabler-download class="size-4" />
			{{ __('Export') }}
		</x-button>
	</div>
</nav>
