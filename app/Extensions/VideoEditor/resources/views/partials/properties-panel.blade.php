{{-- Right Panel: Properties --}}
<div
	class="lqd-ve-properties-panel flex w-72 shrink-0 flex-col border-s border-foreground/5 bg-background/90 backdrop-blur-xl backdrop-saturate-[120%]"
	x-show="rightPanelOpen && selectedClipId"
	x-transition:enter="transition ease-out duration-200"
	x-transition:enter-start="opacity-0 translate-x-4"
	x-transition:enter-end="opacity-100 translate-x-0"
	x-cloak
>
	{{-- Panel Header --}}
	<div class="flex items-center justify-between border-b border-foreground/5 px-4 py-3">
		<h3 class="text-xs font-semibold text-heading-foreground">{{ __('Properties') }}</h3>
		<x-button
			class="size-6 hover:translate-y-0"
			variant="ghost"
			size="none"
			@click.prevent="rightPanelOpen = false"
		>
			<x-tabler-x class="size-3.5" />
		</x-button>
	</div>

	<div class="flex-1 overflow-y-auto">
		{{-- Video/Image Clip Properties --}}
		<template x-if="selectedClipType === 'video' || selectedClipType === 'image'" :key="'props-' + selectedClipId">
			<div class="flex flex-col gap-0">
				{{-- Transform Section --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Transform') }}</h4>
					<div class="grid grid-cols-2 gap-2">
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('X Position') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.x ?? 0"
								@change="updateClipProp('x', +$event.target.value)"
							/>
						</div>
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('Y Position') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.y ?? 0"
								@change="updateClipProp('y', +$event.target.value)"
							/>
						</div>
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('Width') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.width ?? projectWidth"
								@change="updateClipProp('width', +$event.target.value)"
							/>
						</div>
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('Height') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.height ?? projectHeight"
								@change="updateClipProp('height', +$event.target.value)"
							/>
						</div>
					</div>
				</div>

				{{-- Layer Order --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Layer Order') }}</h4>
					<div class="grid grid-cols-4 gap-1">
						<button
							type="button"
							class="rounded-md border border-foreground/10 px-1.5 py-1.5 text-center text-4xs font-medium transition-colors hover:bg-foreground/5 disabled:cursor-not-allowed disabled:opacity-40"
							:disabled="!canMoveClipLayer(selectedClipId, 'back')"
							@click.prevent="moveClipLayer(selectedClipId, 'back')"
							:title="'{{ __('Send to Back') }}'"
						>{{ __('To Back') }}</button>
						<button
							type="button"
							class="rounded-md border border-foreground/10 px-1.5 py-1.5 text-center text-4xs font-medium transition-colors hover:bg-foreground/5 disabled:cursor-not-allowed disabled:opacity-40"
							:disabled="!canMoveClipLayer(selectedClipId, 'backward')"
							@click.prevent="moveClipLayer(selectedClipId, 'backward')"
							:title="'{{ __('Send Backward') }}'"
						>{{ __('Backward') }}</button>
						<button
							type="button"
							class="rounded-md border border-foreground/10 px-1.5 py-1.5 text-center text-4xs font-medium transition-colors hover:bg-foreground/5 disabled:cursor-not-allowed disabled:opacity-40"
							:disabled="!canMoveClipLayer(selectedClipId, 'forward')"
							@click.prevent="moveClipLayer(selectedClipId, 'forward')"
							:title="'{{ __('Bring Forward') }}'"
						>{{ __('Forward') }}</button>
						<button
							type="button"
							class="rounded-md border border-foreground/10 px-1.5 py-1.5 text-center text-4xs font-medium transition-colors hover:bg-foreground/5 disabled:cursor-not-allowed disabled:opacity-40"
							:disabled="!canMoveClipLayer(selectedClipId, 'front')"
							@click.prevent="moveClipLayer(selectedClipId, 'front')"
							:title="'{{ __('Bring to Front') }}'"
						>{{ __('To Front') }}</button>
					</div>
				</div>

				{{-- Opacity --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Opacity') }}</h4>
					<div class="flex items-center gap-3">
						<input
							type="range"
							class="flex-1 cursor-pointer"
							min="0"
							max="1"
							step="0.05"
							:value="selectedClipProps.opacity ?? 1"
							@input="updateClipPropLive('opacity', +$event.target.value)"
						/>
						<span
							class="min-w-8 text-end text-3xs font-medium text-foreground/70"
							x-text="Math.round((selectedClipProps.opacity ?? 1) * 100) + '%'"
						></span>
					</div>
				</div>

				{{-- Volume (for video clips) --}}
				<template x-if="selectedClipType === 'video'">
					<div class="border-b border-foreground/5 p-4">
						<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Volume') }}</h4>
						<div class="flex items-center gap-3">
							<input
								type="range"
								class="flex-1 cursor-pointer"
								min="0"
								max="2"
								step="0.05"
								:value="selectedClipProps.volume ?? 1"
								@input="updateClipPropLive('volume', +$event.target.value)"
							/>
							<span
								class="min-w-8 text-end text-3xs font-medium text-foreground/70"
								x-text="Math.round((selectedClipProps.volume ?? 1) * 100) + '%'"
							></span>
						</div>
					</div>
				</template>

				{{-- Speed --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Speed') }}</h4>
					<div class="flex gap-1">
						<template x-for="speed in [0.25, 0.5, 1, 1.5, 2]" :key="speed">
							<button
								class="flex-1 rounded-md border px-1.5 py-1.5 text-center text-4xs font-medium transition-colors hover:bg-foreground/5"
								:class="(selectedClipProps.speed ?? 1) === speed ? 'border-primary bg-primary/5 text-primary' : 'border-foreground/10'"
								@click.prevent="updateClipProp('speed', speed)"
								x-text="speed + 'x'"
							></button>
						</template>
					</div>
				</div>

				{{-- Clip Timing --}}
				<div class="p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Timing') }}</h4>
					<div class="grid grid-cols-2 gap-2">
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('Start (ms)') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.startTime ?? 0"
								@change="updateClipProp('startTime', +$event.target.value)"
								step="100"
							/>
						</div>
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('End (ms)') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.endTime ?? 0"
								@change="updateClipProp('endTime', +$event.target.value)"
								step="100"
							/>
						</div>
					</div>
				</div>
			</div>
		</template>

		{{-- Text Clip Properties --}}
		<template x-if="selectedClipType === 'text'" :key="'text-' + selectedClipId">
			<div class="flex flex-col gap-0">
				{{-- Text Content --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Text') }}</h4>
					<textarea
						class="lqd-input lqd-input-size-none block w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors placeholder:text-foreground/50 focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
						rows="3"
						:value="selectedClipProps.text ?? ''"
						@input="updateClipPropLive('text', $event.target.value)"
					></textarea>
				</div>

				{{-- Typography --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Typography') }}</h4>
					<div class="flex flex-col gap-2">
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('Font Family') }}</label>
							<select
								class="lqd-input lqd-input-sm block h-9 w-full cursor-pointer border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.fontFamily ?? 'Inter'"
								@change="updateClipPropLive('fontFamily', $event.target.value)"
							>
								<option value="Inter">Inter</option>
								<option value="Arial">Arial</option>
								<option value="Helvetica">Helvetica</option>
								<option value="Georgia">Georgia</option>
								<option value="Times New Roman">Times New Roman</option>
								<option value="Courier New">Courier New</option>
								<option value="Verdana">Verdana</option>
								<option value="Impact">Impact</option>
							</select>
						</div>
						<div class="grid grid-cols-2 gap-2">
							<div>
								<label class="mb-1 block text-4xs text-foreground/50">{{ __('Size') }}</label>
								<input
									type="number"
									class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
									:value="selectedClipProps.fontSize ?? 48"
									@input="updateClipPropLive('fontSize', +$event.target.value)"
									min="8"
									max="500"
								/>
							</div>
							<div>
								<label class="mb-1 block text-4xs text-foreground/50">{{ __('Weight') }}</label>
								<select
									class="lqd-input lqd-input-sm block h-9 w-full cursor-pointer border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
									:value="selectedClipProps.fontWeight ?? '400'"
									@change="updateClipPropLive('fontWeight', $event.target.value)"
								>
									<option value="300">Light</option>
									<option value="400">Regular</option>
									<option value="500">Medium</option>
									<option value="600">Semibold</option>
									<option value="700">Bold</option>
									<option value="900">Black</option>
								</select>
							</div>
						</div>
					</div>
				</div>

				{{-- Color --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Color') }}</h4>
					<div class="flex items-center gap-2">
						<input
							type="color"
							class="size-9 cursor-pointer rounded-lg border border-input-border bg-input-background p-1"
							:value="selectedClipProps.color ?? '#ffffff'"
							@input="updateClipPropLive('color', $event.target.value)"
						/>
						<input
							type="text"
							class="lqd-input lqd-input-sm block h-9 flex-1 border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
							:value="selectedClipProps.color ?? '#ffffff'"
							@change="updateClipProp('color', $event.target.value)"
						/>
					</div>
				</div>

				{{-- Position --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Position') }}</h4>
					<div class="grid grid-cols-2 gap-2">
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">X</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.x ?? 0"
								@change="updateClipProp('x', +$event.target.value)"
							/>
						</div>
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">Y</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.y ?? 0"
								@change="updateClipProp('y', +$event.target.value)"
							/>
						</div>
					</div>
				</div>

				{{-- Timing --}}
				<div class="p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Timing') }}</h4>
					<div class="grid grid-cols-2 gap-2">
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('Start (ms)') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.startTime ?? 0"
								@change="updateClipProp('startTime', +$event.target.value)"
								step="100"
							/>
						</div>
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('End (ms)') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.endTime ?? 0"
								@change="updateClipProp('endTime', +$event.target.value)"
								step="100"
							/>
						</div>
					</div>
				</div>
			</div>
		</template>

		{{-- Audio Clip Properties --}}
		<template x-if="selectedClipType === 'audio'" :key="'audio-' + selectedClipId">
			<div class="flex flex-col gap-0">
				{{-- Volume --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Volume') }}</h4>
					<div class="flex items-center gap-3">
						<input
							type="range"
							class="flex-1 cursor-pointer"
							min="0"
							max="2"
							step="0.05"
							:value="selectedClipProps.volume ?? 1"
							@input="updateClipPropLive('volume', +$event.target.value)"
						/>
						<span
							class="min-w-8 text-end text-3xs font-medium text-foreground/70"
							x-text="Math.round((selectedClipProps.volume ?? 1) * 100) + '%'"
						></span>
					</div>
				</div>

				{{-- Speed --}}
				<div class="border-b border-foreground/5 p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Speed') }}</h4>
					<div class="flex gap-1">
						<template x-for="speed in [0.25, 0.5, 1, 1.5, 2]" :key="speed">
							<button
								class="flex-1 rounded-md border px-1.5 py-1.5 text-center text-4xs font-medium transition-colors hover:bg-foreground/5"
								:class="(selectedClipProps.speed ?? 1) === speed ? 'border-primary bg-primary/5 text-primary' : 'border-foreground/10'"
								@click.prevent="updateClipProp('speed', speed)"
								x-text="speed + 'x'"
							></button>
						</template>
					</div>
				</div>

				{{-- Timing --}}
				<div class="p-4">
					<h4 class="mb-3 text-3xs font-semibold uppercase tracking-wide text-foreground/50">{{ __('Timing') }}</h4>
					<div class="grid grid-cols-2 gap-2">
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('Start (ms)') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.startTime ?? 0"
								@change="updateClipProp('startTime', +$event.target.value)"
								step="100"
							/>
						</div>
						<div>
							<label class="mb-1 block text-4xs text-foreground/50">{{ __('End (ms)') }}</label>
							<input
								type="number"
								class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
								:value="selectedClipProps.endTime ?? 0"
								@change="updateClipProp('endTime', +$event.target.value)"
								step="100"
							/>
						</div>
					</div>
				</div>
			</div>
		</template>

		{{-- No Selection --}}
		<template x-if="!selectedClipId">
			<div class="flex h-full flex-col items-center justify-center p-6 text-center">
				<x-tabler-click class="mb-3 size-10 text-foreground/15" />
				<p class="text-xs font-medium text-foreground/40">{{ __('Select a clip') }}</p>
				<p class="mt-1 text-3xs text-foreground/30">{{ __('Click on a clip in the timeline to see its properties') }}</p>
			</div>
		</template>

		{{-- Clip Actions --}}
		<template x-if="selectedClipId">
			<div class="border-t border-foreground/5 p-4">
				<div class="flex gap-2">
					<x-button
						class="flex-1 text-3xs hover:translate-y-0"
						variant="ghost"
						size="sm"
						@click.prevent="duplicateClip(selectedClipId)"
					>
						<x-tabler-copy class="size-3.5" />
						{{ __('Duplicate') }}
					</x-button>
					<x-button
						class="flex-1 text-3xs text-red-500 hover:translate-y-0 hover:bg-red-500/10 hover:text-red-500"
						variant="ghost"
						size="sm"
						@click.prevent="deleteClip(selectedClipId)"
					>
						<x-tabler-trash class="size-3.5" />
						{{ __('Delete') }}
					</x-button>
				</div>
			</div>
		</template>
	</div>
</div>
