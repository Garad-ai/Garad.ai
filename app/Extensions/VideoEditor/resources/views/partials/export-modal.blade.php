{{-- Export Modal --}}
<div
	class="fixed inset-0 z-50 flex items-center justify-center"
	x-show="exportModalOpen"
	x-cloak
	x-transition:enter="transition ease-out duration-200"
	x-transition:enter-start="opacity-0"
	x-transition:enter-end="opacity-100"
	x-transition:leave="transition ease-in duration-150"
	x-transition:leave-start="opacity-100"
	x-transition:leave-end="opacity-0"
>
	{{-- Backdrop --}}
	<div
		class="absolute inset-0 bg-black/5 backdrop-blur-sm"
		@click="!exporting && (exportModalOpen = false)"
	></div>

	{{-- Modal Content --}}
	<div
		class="relative z-10 w-full max-w-md overflow-hidden rounded-xl bg-background shadow-2xl shadow-black/10"
		x-transition:enter="transition ease-out duration-200"
		x-transition:enter-start="scale-95 opacity-0"
		x-transition:enter-end="scale-100 opacity-100"
		@click.outside="!exporting && (exportModalOpen = false)"
	>
		{{-- Header --}}
		<div class="flex items-center justify-between border-b border-foreground/5 px-6 py-4">
			<h3 class="text-base font-semibold text-heading-foreground">
				<x-tabler-download class="me-1.5 inline size-5 align-text-bottom" />
				{{ __('Export Video') }}
			</h3>
			<x-button
				class="size-7 hover:translate-y-0"
				variant="ghost"
				size="none"
				@click.prevent="!exporting && (exportModalOpen = false)"
				x-bind:disabled="exporting"
			>
				<x-tabler-x class="size-4" />
			</x-button>
		</div>

		{{-- Body --}}
		<div class="p-6">
			{{-- Not Exporting: Summary --}}
			<template x-if="!exporting && !exportComplete">
				<div class="flex flex-col gap-4">
					<p class="text-xs text-foreground/60">{{ __('Your video will be exported using the resolution defined in your composition settings.') }}</p>
					<div class="rounded-lg bg-foreground/[3%] p-4">
						<div class="flex items-center justify-between text-xs">
							<span class="text-foreground/60">{{ __('Estimated Duration') }}</span>
							<span
								class="font-medium"
								x-text="formatTimecode(totalDuration)"
							></span>
						</div>
					</div>
				</div>
			</template>

			{{-- Exporting: Progress --}}
			<template x-if="exporting && !exportComplete">
				<div class="flex flex-col items-center py-4">
					<div class="relative mb-4 size-20">
						{{-- Progress Circle --}}
						<svg class="size-20 -rotate-90" viewBox="0 0 80 80">
							<circle
								cx="40"
								cy="40"
								r="36"
								fill="none"
								stroke="currentColor"
								stroke-width="4"
								class="text-foreground/10"
							/>
							<circle
								cx="40"
								cy="40"
								r="36"
								fill="none"
								stroke="currentColor"
								stroke-width="4"
								stroke-linecap="round"
								class="text-primary transition-all duration-500"
								:stroke-dasharray="226.2"
								:stroke-dashoffset="226.2 - (226.2 * exportProgress / 100)"
							/>
						</svg>
						<span
							class="absolute inset-0 flex items-center justify-center text-sm font-bold"
							x-text="exportProgress + '%'"
						></span>
					</div>

					<h4 class="text-sm font-semibold text-heading-foreground">{{ __('Exporting...') }}</h4>
					<p class="mt-1 text-3xs text-foreground/50">{{ __('This may take a while depending on your video length and resolution.') }}</p>
					<p class="mt-0.5 text-3xs text-foreground/40">{{ __('Please do not close this window.') }}</p>
				</div>
			</template>

			{{-- Export Complete --}}
			<template x-if="exportComplete">
				<div class="flex flex-col items-center py-4">
					<div class="mb-4 flex size-16 items-center justify-center rounded-full bg-emerald-500/10">
						<x-tabler-check class="size-8 text-emerald-500" />
					</div>

					<h4 class="text-sm font-semibold text-heading-foreground">{{ __('Export Complete!') }}</h4>
					<p class="mt-1 text-3xs text-foreground/50">{{ __('Your video is ready to download.') }}</p>

					<div class="mt-4 flex gap-2">
						<x-button
							variant="primary"
							size="md"
							@click.prevent="downloadExport()"
						>
							<x-tabler-download class="size-4" />
							{{ __('Download') }}
						</x-button>
						<x-button
							variant="ghost"
							size="md"
							@click.prevent="resetExport()"
						>
							{{ __('Close') }}
						</x-button>
					</div>
				</div>
			</template>

			{{-- Export Error --}}
			<template x-if="exportError">
				<div class="mt-4 rounded-lg border border-red-500/20 bg-red-500/5 p-3 text-xs text-red-600 dark:text-red-400">
					<p class="font-medium">{{ __('Export failed') }}</p>
					<p
						class="mt-1 text-3xs"
						x-text="exportError"
					></p>
				</div>
			</template>
		</div>

		{{-- Footer --}}
		<template x-if="!exporting && !exportComplete">
			<div class="flex items-center justify-end gap-2 border-t border-foreground/5 px-6 py-4">
				<x-button
					variant="ghost"
					size="md"
					@click.prevent="exportModalOpen = false"
				>
					{{ __('Cancel') }}
				</x-button>
				<x-button
					variant="primary"
					size="md"
					@click.prevent="startExport()"
					x-bind:disabled="!tracks.length"
				>
					<x-tabler-player-play-filled class="size-3.5" />
					{{ __('Start Export') }}
				</x-button>
			</div>
		</template>
	</div>
</div>
