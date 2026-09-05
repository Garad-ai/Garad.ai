@php
    use App\Helpers\Classes\MarketplaceHelper;

    $veAiVideoProEnabled = MarketplaceHelper::isRegistered('ai-video-pro');
    $veAiMusicProEnabled = MarketplaceHelper::isRegistered('ai-music-pro');
    $veTtsEnabled = ($settings_two->feature_tts_openai ?? false)
        || ($settings_two->feature_tts_google ?? false)
        || ($settings_two->feature_tts_elevenlabs ?? false)
        || ($settings_two->feature_tts_azure ?? false)
        || ($settings_two->feature_tts_speechify ?? false);
@endphp

{{-- Left Panel: Library / AI / Size / Layers --}}
<div
	class="lqd-ve-left-panel flex w-80 shrink-0 flex-col border-e border-foreground/5 bg-background/90 backdrop-blur-xl backdrop-saturate-[120%]"
	x-show="leftPanelOpen"
	x-transition:enter="transition ease-out duration-200"
	x-transition:enter-start="opacity-0 -translate-x-4"
	x-transition:enter-end="opacity-100 translate-x-0"
>
	{{-- Panel Title --}}
	<div class="flex items-center justify-between border-b border-foreground/5 px-4 py-3">
		<h3
			class="text-xs font-semibold text-heading-foreground"
			x-text="{ library: '{{ __('Library') }}', ai: '{{ __('AI Generate') }}', voice: '{{ __('AI Voiceover') }}', music: '{{ __('AI Music') }}', size: '{{ __('Project Size') }}', layers: '{{ __('Layers') }}' }[leftPanelTab] || ''"
		></h3>
		<button
			class="flex size-6 items-center justify-center rounded-md text-foreground/40 transition-colors hover:bg-foreground/5 hover:text-foreground"
			@click.prevent="leftPanelOpen = false"
			title="{{ __('Close Panel') }}"
		>
			<x-tabler-x class="size-3.5" />
		</button>
	</div>

	{{-- Library Tab --}}
	<div
		class="flex flex-1 flex-col overflow-hidden"
		x-show="leftPanelTab === 'library'"
	>
		{{-- Upload + Filter --}}
		<div class="flex flex-col gap-3 border-b border-foreground/5 p-3">
			<div class="flex items-center gap-2">
				<x-button
					class="flex-1"
					variant="outline"
					size="sm"
					@click.prevent="openContentManager()"
				>
					<x-tabler-upload class="size-4" />
					{{ __('Upload') }}
				</x-button>
			</div>

			<input
				x-ref="mediaUploadInput"
				type="file"
				class="hidden"
				accept="video/*,audio/*,image/*"
				multiple
				data-exclude-media-manager
				@change="handleMediaUpload($event)"
			/>

			{{-- Type Filter --}}
			<div class="flex gap-1">
				<template x-for="filterType in ['all', 'video', 'image', 'audio']" :key="filterType">
					<button
						class="rounded-lg px-2.5 py-1 text-3xs font-medium capitalize transition-colors hover:bg-foreground/5 [&.active]:bg-primary/10 [&.active]:text-primary"
						:class="{ 'active': mediaFilter === filterType }"
						@click.prevent="mediaFilter = filterType; loadMedia()"
						x-text="filterType"
					></button>
				</template>
			</div>
		</div>

		{{-- Media Grid --}}
		<div class="flex-1 overflow-y-auto p-3">
			{{-- Upload Progress --}}
			<template x-if="uploading">
				<div class="mb-3 rounded-lg border border-foreground/5 bg-foreground/[3%] p-3">
					<div class="mb-1.5 flex items-center justify-between text-3xs">
						<span class="font-medium">{{ __('Uploading...') }}</span>
						<span x-text="uploadProgress + '%'"></span>
					</div>
					<div class="h-1 overflow-hidden rounded-full bg-foreground/10">
						<div
							class="h-full rounded-full bg-primary transition-all"
							:style="'width: ' + uploadProgress + '%'"
						></div>
					</div>
				</div>
			</template>

			{{-- Media Items --}}
			<div class="grid grid-cols-2 gap-2">
				<template x-for="media in mediaItems" :key="media.id">
					<div
						class="group relative cursor-grab overflow-hidden rounded-lg border border-foreground/5 bg-foreground/[3%] transition-all hover:border-primary/30 hover:shadow-md hover:shadow-primary/5"
						draggable="true"
						@dragstart="handleMediaDragStart($event, media)"
						@dragend="handleMediaDragEnd($event)"
						@dblclick.prevent="addMediaToTimeline(media)"
					>
						{{-- Thumbnail --}}
						<div class="relative aspect-video overflow-hidden bg-foreground/5">
							<template x-if="media.thumbnail_url">
								<img
									:src="media.thumbnail_url"
									:alt="media.original_filename"
									class="h-full w-full object-cover"
								/>
							</template>
							<template x-if="!media.thumbnail_url">
								<div class="flex h-full w-full items-center justify-center">
									<template x-if="media.type === 'video'">
										<x-tabler-video class="size-6 text-foreground/30" />
									</template>
									<template x-if="media.type === 'audio'">
										<x-tabler-music class="size-6 text-foreground/30" />
									</template>
									<template x-if="media.type === 'image'">
										<x-tabler-photo class="size-6 text-foreground/30" />
									</template>
								</div>
							</template>

							{{-- Duration Badge --}}
							<template x-if="media.duration_ms">
								<span
									class="absolute bottom-1 end-1 rounded bg-black/70 px-1 py-0.5 text-4xs text-white"
									x-text="formatDuration(media.duration_ms)"
								></span>
							</template>

							{{-- Type Icon Overlay --}}
							<span class="absolute start-1 top-1 rounded bg-black/50 p-0.5">
								<template x-if="media.type === 'video'">
									<x-tabler-video class="size-3 text-white" />
								</template>
								<template x-if="media.type === 'audio'">
									<x-tabler-music class="size-3 text-white" />
								</template>
								<template x-if="media.type === 'image'">
									<x-tabler-photo class="size-3 text-white" />
								</template>
							</span>
						</div>

						{{-- Filename --}}
						<div class="px-2 py-1.5">
							<p
								class="truncate text-3xs font-medium"
								x-text="media.original_filename"
							></p>
						</div>

						{{-- Delete Overlay --}}
						<button
							class="absolute end-1 top-1 z-10 hidden size-5 items-center justify-center rounded-full bg-red-500 text-white transition-all hover:bg-red-600 group-hover:flex"
							@click.stop.prevent="deleteMedia(media.id)"
							title="{{ __('Delete') }}"
						>
							<x-tabler-x class="size-3" />
						</button>
					</div>
				</template>
			</div>

			{{-- Empty State --}}
			<template x-if="!mediaItems.length && !uploading">
				<div class="flex flex-col items-center justify-center py-10 text-center">
					<x-tabler-upload class="mb-2 size-8 text-foreground/20" />
					<p class="text-xs font-medium text-foreground/50">{{ __('No media yet') }}</p>
					<p class="mt-1 text-3xs text-foreground/40">{{ __('Upload or import media to get started') }}</p>
				</div>
			</template>

			{{-- Load More --}}
			<template x-if="mediaHasMore">
				<div class="mt-3 text-center">
					<x-button
						variant="ghost"
						size="sm"
						class="text-3xs"
						@click.prevent="loadMoreMedia()"
					>
						{{ __('Load More') }}
					</x-button>
				</div>
			</template>
		</div>
	</div>

	@if ($veAiVideoProEnabled)
	{{-- AI Generate Tab --}}
	{{--
		Sourced live from AiVideoPro\ModelConfigurationService via /ai/models.
		The nested model / sub-model / feature dropdown mirrors AiVideoPro's
		input-section so any future model additions there appear here too.
	--}}
	<div
		class="flex flex-1 flex-col overflow-y-auto"
		x-show="leftPanelTab === 'ai'"
		x-cloak
	>
		<div class="flex flex-col gap-3 p-3">
			{{-- Multi-level Model Dropdown --}}
			<div class="lqd-input-container relative">
				<label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label text-heading-foreground">
					<span class="lqd-input-label-txt">{{ __('AI Model') }}</span>
				</label>
				<div
					class="relative"
					@click.outside="aiModelDropdownOpen = false"
				>
					<button
						class="flex h-10 w-full items-center justify-between rounded-input border border-input-border bg-input-background px-3 py-2 text-start text-2xs font-medium transition"
						type="button"
						@click.prevent="aiModelDropdownOpen = !aiModelDropdownOpen"
					>
						<span class="w-full truncate" x-text="aiGetSelectedModelLabel()"></span>
						<x-tabler-chevron-down class="size-4 shrink-0 opacity-50" />
					</button>

					<div
						class="absolute inset-x-0 top-full z-50 mt-1 max-h-96 origin-top space-y-1 overflow-y-auto rounded-dropdown bg-dropdown-background/95 px-2 py-3 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
						x-show="aiModelDropdownOpen"
						x-cloak
						x-transition
					>
						<p class="m-0 px-3 pb-1 text-3xs font-medium opacity-50">{{ __('Select AI Model') }}</p>

						<template x-for="(model, modelKey) in aiConfig" :key="modelKey">
							<template x-if="model.isActive !== false">
								<div
									class="group/model"
									x-data="{ expanded: false }"
								>
									<button
										class="group/trigger flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
										:class="{ 'bg-foreground/5': aiSelectedAction === modelKey }"
										type="button"
										@click.prevent="aiSelectModelFromDropdown(modelKey); expanded = !expanded"
									>
										<x-tabler-check
											class="size-4 shrink-0 transition"
											::class="aiSelectedAction === modelKey ? 'opacity-100 text-primary' : 'opacity-0'"
										/>
										<span class="block min-w-0 flex-1">
											<span class="block truncate font-medium" x-text="model.label"></span>
											<span class="block truncate text-3xs opacity-60" x-text="model.description || ''"></span>
										</span>
										<template x-if="Object.keys(model.subModels || {}).length > 1">
											<x-tabler-chevron-right
												class="size-3.5 shrink-0 opacity-40 transition"
												::class="expanded ? 'rotate-90' : ''"
											/>
										</template>
									</button>

									{{-- Sub-models list (inline expansion to fit narrow panel) --}}
									<template x-if="Object.keys(model.subModels || {}).length > 1">
										<div
											class="ms-6 space-y-0.5 py-1"
											x-show="expanded || aiSelectedAction === modelKey"
											x-collapse
										>
											<template x-for="(subModel, subKey) in model.subModels" :key="subKey">
												<template x-if="subModel.isActive !== false">
													<div>
														<p class="px-3 py-1 text-3xs font-semibold uppercase tracking-wide opacity-40" x-text="subKey"></p>
														<template x-for="(feature, featureSlug) in subModel.features" :key="featureSlug">
															<button
																class="flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-start text-3xs text-heading-foreground transition hover:bg-foreground/5"
																:class="{ 'bg-primary/10 text-primary': aiSelectedAction === modelKey && aiSelectedSubModel === subKey && aiSelectedFeature === featureSlug }"
																type="button"
																@click.prevent="aiSelectSubModelFromDropdown(modelKey, subKey); aiSelectedFeature = featureSlug; aiInitializeFormValues(); checkAiCredits()"
															>
																<x-tabler-check
																	class="size-3.5 shrink-0"
																	::class="aiSelectedAction === modelKey && aiSelectedSubModel === subKey && aiSelectedFeature === featureSlug ? 'opacity-100' : 'opacity-0'"
																/>
																<span class="block truncate" x-text="feature.label || featureSlug"></span>
															</button>
														</template>
													</div>
												</template>
											</template>
										</div>
									</template>
								</div>
							</template>
						</template>
					</div>
				</div>
			</div>

			{{-- Feature picker (only when multiple features under the active sub-model) --}}
			<div
				class="lqd-input-container relative"
				x-show="aiShouldShowFeatures"
				x-cloak
			>
				<label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label text-heading-foreground">
					<span class="lqd-input-label-txt">{{ __('Feature') }}</span>
				</label>
				<div
					class="relative"
					@click.outside="aiFeatureDropdownOpen = false"
				>
					<button
						class="flex h-10 w-full items-center justify-between rounded-input border border-input-border bg-input-background px-3 py-2 text-start text-2xs font-medium transition"
						type="button"
						@click.prevent="aiFeatureDropdownOpen = !aiFeatureDropdownOpen"
					>
						<span class="w-full truncate" x-text="aiGetSelectedFeatureLabel()"></span>
						<x-tabler-chevron-down class="size-4 shrink-0 opacity-50" />
					</button>

					<div
						class="absolute inset-x-0 top-full z-50 mt-1 max-h-72 origin-top space-y-1 overflow-y-auto rounded-dropdown bg-dropdown-background/95 px-2 py-3 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
						x-show="aiFeatureDropdownOpen"
						x-cloak
						x-transition
					>
						<template x-for="feature in aiFeatures" :key="feature.value">
							<button
								class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
								:class="{ 'bg-primary/10 text-primary': aiSelectedFeature === feature.value }"
								type="button"
								@click.prevent="aiSelectedFeature = feature.value; aiInitializeFormValues(); checkAiCredits(); aiFeatureDropdownOpen = false"
							>
								<x-tabler-check
									class="size-4 shrink-0"
									::class="aiSelectedFeature === feature.value ? 'opacity-100' : 'opacity-0'"
								/>
								<span class="block min-w-0">
									<span class="block truncate font-medium" x-text="feature.label"></span>
									<span class="block truncate text-3xs opacity-60" x-text="feature.description || ''"></span>
								</span>
							</button>
						</template>
					</div>
				</div>
			</div>

			{{-- Primary Inputs (prompt, required textareas, file uploads) --}}
			<template x-for="input in aiPrimaryInputs" :key="'primary-' + input.name">
				<div
					class="lqd-input-container relative"
					x-show="aiShouldShowInput(input)"
				>
					<label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-heading-foreground" x-text="input.label" x-show="input.type !== 'checkbox'"></label>

					<template x-if="input.type === 'textarea'">
						<textarea
							class="lqd-input lqd-input-size-none block w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors placeholder:text-foreground/50 focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
							:rows="input.rows || 3"
							:placeholder="input.placeholder || ''"
							x-model="aiFormValues[input.name]"
						></textarea>
					</template>

					<template x-if="input.type === 'text'">
						<input
							type="text"
							class="lqd-input lqd-input-lg block h-11 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors placeholder:text-foreground/50 focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
							:placeholder="input.placeholder || ''"
							x-model="aiFormValues[input.name]"
						/>
					</template>

					<template x-if="input.type === 'file'">
						<div
							class="cursor-pointer rounded-input border border-dashed border-input-border bg-input-background/50 px-4 py-4 text-center transition hover:border-primary/50 hover:bg-primary/[3%]"
							data-exclude-media-manager="true"
							@click="$el.querySelector('input[type=file]').click()"
						>
							<x-tabler-upload class="mx-auto mb-1.5 size-5 text-foreground/40 pointer-events-none" />
							<p class="mb-0.5 text-3xs font-medium text-heading-foreground pointer-events-none">
								<span x-show="!aiFileLabel(input.name)">{{ __('Upload file') }}</span>
								<span class="text-primary" x-show="aiFileLabel(input.name)" x-text="aiFileLabel(input.name)"></span>
							</p>
							<p class="m-0 text-4xs text-foreground/40 pointer-events-none" x-show="!aiFileLabel(input.name)">
								<span x-text="input.accept || ''"></span>
							</p>
							<input
								type="file"
								class="hidden"
								:accept="input.accept || ''"
								:multiple="!!input.multiple"
								data-exclude-media-manager="true"
								@change="aiHandleFileSelect(input.name, $event)"
								@click.stop
							/>
						</div>
					</template>
				</div>
			</template>

			{{-- Advanced options as pill-style dropdowns (mirrors AiVideoPro) --}}
			<template x-if="aiAdvancedInputs.length > 0">
				<div>
					<label class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-label text-heading-foreground">
						{{ __('Options') }}
					</label>
					<div class="grid grid-cols-2 gap-2">
						<template x-for="input in aiAdvancedInputs" :key="'adv-' + input.name">
							<div
								class="relative"
								x-show="aiShouldShowInput(input)"
								@click.outside="aiOpenPill === input.name ? aiOpenPill = null : null"
							>
								<button
									class="flex h-9 w-full items-center gap-1.5 rounded-input border border-input-border bg-input-background px-2.5 text-3xs font-medium transition hover:bg-foreground/5"
									type="button"
									@click.prevent="aiOpenPill = aiOpenPill === input.name ? null : input.name"
								>
									<span x-show="aiGetPillIcon(input) === 'clock'"><x-tabler-clock class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'volume'"><x-tabler-volume class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'ratio'"><x-tabler-aspect-ratio class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'resolution'"><x-tabler-device-desktop class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'sparkle'"><x-tabler-sparkles class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'wand'"><x-tabler-wand class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'dice'"><x-tabler-dice class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'ban'"><x-tabler-ban class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'text'"><x-tabler-align-left class="size-3.5 shrink-0 opacity-60" /></span>
									<span x-show="aiGetPillIcon(input) === 'settings'"><x-tabler-settings class="size-3.5 shrink-0 opacity-60" /></span>
									<span class="truncate" x-text="aiGetPillDisplayValue(input)"></span>
								</button>

								<div
									class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 origin-top overflow-y-auto rounded-dropdown bg-dropdown-background/95 p-2 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
									x-show="aiOpenPill === input.name"
									x-cloak
									x-transition
								>
									<p class="mb-1 px-2 text-3xs font-medium opacity-50" x-text="input.label"></p>

									<template x-if="input.type === 'select'">
										<div class="space-y-0.5">
											<template x-for="option in input.options" :key="option.value">
												<button
													class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-3xs transition hover:bg-foreground/5"
													:class="{ 'bg-primary/10 text-primary': String(aiFormValues[input.name]) === String(option.value) }"
													type="button"
													@click.prevent="aiFormValues[input.name] = option.value; aiOpenPill = null"
												>
													<x-tabler-check
														class="size-3.5 shrink-0"
														::class="String(aiFormValues[input.name]) === String(option.value) ? 'opacity-100' : 'opacity-0'"
													/>
													<span x-text="option.label"></span>
												</button>
											</template>
										</div>
									</template>

									<template x-if="input.type === 'checkbox'">
										<div class="space-y-0.5">
											<button
												class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-3xs transition hover:bg-foreground/5"
												:class="{ 'bg-primary/10 text-primary': aiFormValues[input.name] === true }"
												type="button"
												@click.prevent="aiFormValues[input.name] = true; aiOpenPill = null"
											>
												<x-tabler-check class="size-3.5 shrink-0" ::class="aiFormValues[input.name] === true ? 'opacity-100' : 'opacity-0'" />
												{{ __('On') }}
											</button>
											<button
												class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-3xs transition hover:bg-foreground/5"
												:class="{ 'bg-primary/10 text-primary': aiFormValues[input.name] === false }"
												type="button"
												@click.prevent="aiFormValues[input.name] = false; aiOpenPill = null"
											>
												<x-tabler-check class="size-3.5 shrink-0" ::class="aiFormValues[input.name] === false ? 'opacity-100' : 'opacity-0'" />
												{{ __('Off') }}
											</button>
										</div>
									</template>

									<template x-if="input.type === 'number'">
										<input
											type="number"
											class="lqd-input block h-9 w-full border border-input-border bg-input-background px-2 text-3xs text-heading-foreground transition focus:border-secondary focus:outline-0"
											:placeholder="input.placeholder || ''"
											:min="input.min"
											:max="input.max"
											:step="input.step || 1"
											x-model="aiFormValues[input.name]"
										/>
									</template>

									<template x-if="input.type === 'textarea'">
										<textarea
											class="lqd-input lqd-input-size-none block w-full border border-input-border bg-input-background px-2 py-1.5 text-3xs text-heading-foreground transition focus:border-secondary focus:outline-0"
											rows="3"
											:placeholder="input.placeholder || ''"
											x-model="aiFormValues[input.name]"
										></textarea>
									</template>

									<template x-if="input.type === 'text'">
										<input
											type="text"
											class="lqd-input block h-9 w-full border border-input-border bg-input-background px-2 text-3xs text-heading-foreground transition focus:border-secondary focus:outline-0"
											:placeholder="input.placeholder || ''"
											x-model="aiFormValues[input.name]"
										/>
									</template>
								</div>
							</div>
						</template>
					</div>
				</div>
			</template>

			{{-- Credits Info --}}
			<div
				class="rounded-lg bg-foreground/[3%] p-3 text-3xs"
				x-show="aiCredits !== null"
			>
				<div class="flex items-center justify-between">
					<span class="text-foreground/60">{{ __('Credits Available') }}</span>
					<span
						class="font-semibold"
						:class="aiCredits?.has_credits ? 'text-emerald-500' : 'text-red-500'"
						x-text="aiCredits?.has_credits ? '{{ __('Yes') }}' : '{{ __('No') }}'"
					></span>
				</div>
			</div>

			{{-- No Credits Warning --}}
			<template x-if="aiCredits && !aiCredits.has_credits">
				<div class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 text-3xs text-amber-700 dark:text-amber-400">
					<p class="font-medium">{{ __('Insufficient credits') }}</p>
					<a
						href="{{ route('dashboard.user.payment.subscription') }}"
						class="mt-1 inline-block text-primary underline"
					>
						{{ __('Upgrade your plan') }}
					</a>
				</div>
			</template>

			{{-- Generate Button --}}
			<x-button
				class="w-full"
				variant="primary"
				size="md"
				@click.prevent="generateAiVideo()"
				x-bind:disabled="aiGenerating || aiHasPending() || !aiHasPrompt() || !aiSelectedFeature || (aiCredits && !aiCredits.has_credits)"
			>
				<template x-if="!aiGenerating && !aiHasPending()">
					<span class="flex items-center gap-1.5">
						<x-tabler-sparkles class="size-4" />
						{{ __('Generate Video') }}
					</span>
				</template>
				<template x-if="aiGenerating || aiHasPending()">
					<span class="flex items-center gap-1.5">
						<x-tabler-loader-2 class="size-4 animate-spin" />
						<span x-show="aiGenerating">{{ __('Submitting...') }}</span>
						<span x-show="!aiGenerating && aiHasPending()">{{ __('Generating...') }}</span>
					</span>
				</template>
			</x-button>

			{{-- Active Generations --}}
			<template x-for="gen in aiGenerations" :key="gen.id">
				<div class="rounded-lg border border-foreground/5 bg-foreground/[3%] p-3">
					<div class="mb-2 flex items-center justify-between text-3xs">
						<span class="font-medium" x-text="gen.prompt?.substring(0, 40) + '...'"></span>
						<span
							class="rounded-full px-2 py-0.5 text-4xs font-medium capitalize"
							:class="{
								'bg-amber-500/10 text-amber-600': gen.status === 'IN_QUEUE' || gen.status === 'queued' || gen.status === 'processing',
								'bg-emerald-500/10 text-emerald-600': gen.status === 'complete',
								'bg-red-500/10 text-red-600': gen.status === 'error'
							}"
							x-text="gen.status === 'IN_QUEUE' ? '{{ __('In Queue') }}' : gen.status"
						></span>
					</div>
					<template x-if="gen.status === 'complete' && gen.video_url">
						<x-button
							variant="outline"
							size="sm"
							class="w-full text-3xs"
							@click.prevent="importAiVideoToEditor(gen.id)"
						>
							<x-tabler-file-import class="size-3.5" />
							{{ __('Import to Library') }}
						</x-button>
					</template>
				</div>
			</template>
		</div>
	</div>

	@endif

	@if ($veTtsEnabled)
	{{-- Voice Tab --}}
	<div
		class="flex flex-1 flex-col overflow-hidden"
		x-show="leftPanelTab === 'voice'"
	>
		<div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
			@if (($ttsProvider ?? \App\Extensions\VideoEditor\System\Http\Controllers\VideoEditorAiController::resolveTtsProvider($settings_two)) === 'openai')
				{{-- OpenAI TTS: Voice + Model --}}
				<x-forms.input
					class="bg-input-background text-heading-foreground"
					class:label="text-heading-foreground"
					id="ve_voice_id"
					name="voice_id"
					type="select"
					label="{{ __('Voice') }}"
					size="lg"
					x-model="voiceId"
				>
					<option value="alloy">{{ __('Alloy') }}</option>
					<option value="echo">{{ __('Echo') }}</option>
					<option value="fable">{{ __('Fable') }}</option>
					<option value="onyx">{{ __('Onyx') }}</option>
					<option value="nova">{{ __('Nova') }}</option>
					<option value="shimmer">{{ __('Shimmer') }}</option>
				</x-forms.input>

				<x-forms.input
					class="bg-input-background text-heading-foreground"
					class:label="text-heading-foreground"
					id="ve_voice_model"
					name="voice_model"
					type="select"
					label="{{ __('Model') }}"
					size="lg"
					x-model="voiceModel"
				>
					<option value="{{ \App\Domains\Entity\Enums\EntityEnum::TTS_1->value }}">
						{{ \App\Domains\Entity\Enums\EntityEnum::TTS_1->label() }}
					</option>
					<option value="{{ \App\Domains\Entity\Enums\EntityEnum::TTS_1_HD->value }}">
						{{ \App\Domains\Entity\Enums\EntityEnum::TTS_1_HD->label() }}
					</option>
				</x-forms.input>
			@else
				{{-- Google / ElevenLabs / Azure / Speechify: Language + Voice + Pace --}}
				<x-forms.input
					class="bg-input-background text-heading-foreground"
					class:label="text-heading-foreground"
					id="ve_voice_lang"
					name="voice_lang"
					type="select"
					label="{{ __('Language') }}"
					size="lg"
					x-model="voiceLang"
					@change="onVoiceLanguageChange()"
				>
					<option language="Afrikaans" value="af-ZA">{{ __('Afrikaans (South Africa)') }}</option>
					<option language="Arabic" value="ar-XA">{{ __('Arabic') }}</option>
					<option language="Bulgarian" value="bg-BG">{{ __('Bulgarian (Bulgaria)') }}</option>
					<option language="Catalan" value="ca-ES">{{ __('Catalan (Spain)') }}</option>
					<option language="Chinese" value="yue-HK">{{ __('Chinese (Hong Kong)') }}</option>
					<option language="Chinese" value="zh-CN">{{ __('Chinese (Mandarin, Simplified)') }}</option>
					<option language="Czech" value="cs-CZ">{{ __('Czech (Czech Republic)') }}</option>
					<option language="Danish" value="da-DK">{{ __('Danish (Denmark)') }}</option>
					<option language="Dutch" value="nl-BE">{{ __('Dutch (Belgium)') }}</option>
					<option language="Dutch" value="nl-NL">{{ __('Dutch (Netherlands)') }}</option>
					<option language="English" value="en-AU">{{ __('English (Australia)') }}</option>
					<option language="English" value="en-IN">{{ __('English (India)') }}</option>
					<option language="English" value="en-GB">{{ __('English (UK)') }}</option>
					<option language="English" value="en-US" selected>{{ __('English (US)') }}</option>
					<option language="Filipino" value="fil-PH">{{ __('Filipino (Philippines)') }}</option>
					<option language="Finnish" value="fi-FI">{{ __('Finnish (Finland)') }}</option>
					<option language="French" value="fr-CA">{{ __('French (Canada)') }}</option>
					<option language="French" value="fr-FR">{{ __('French (France)') }}</option>
					<option language="German" value="de-DE">{{ __('German (Germany)') }}</option>
					<option language="Greek" value="el-GR">{{ __('Greek (Greece)') }}</option>
					<option language="Hebrew" value="he-IL">{{ __('Hebrew (Israel)') }}</option>
					<option language="Hindi" value="hi-IN">{{ __('Hindi (India)') }}</option>
					<option language="Hungarian" value="hu-HU">{{ __('Hungarian (Hungary)') }}</option>
					<option language="Icelandic" value="is-IS">{{ __('Icelandic (Iceland)') }}</option>
					<option language="Indonesian" value="id-ID">{{ __('Indonesian (Indonesia)') }}</option>
					<option language="Italian" value="it-IT">{{ __('Italian (Italy)') }}</option>
					<option language="Japanese" value="ja-JP">{{ __('Japanese (Japan)') }}</option>
					<option language="Kannada" value="kn-IN">{{ __('Kannada (India)') }}</option>
					<option language="Korean" value="ko-KR">{{ __('Korean (South Korea)') }}</option>
					<option language="Latvian" value="lv-LV">{{ __('Latvian (Latvia)') }}</option>
					<option language="Malay" value="ms-MY">{{ __('Malay (Malaysia)') }}</option>
					<option language="Malayalam" value="ml-IN">{{ __('Malayalam (India)') }}</option>
					<option language="Mandarin" value="cmn-CN">{{ __('Mandarin Chinese') }}</option>
					<option language="Norwegian" value="nb-NO">{{ __('Norwegian (Norway)') }}</option>
					<option language="Polish" value="pl-PL">{{ __('Polish (Poland)') }}</option>
					<option language="Portuguese" value="pt-BR">{{ __('Portuguese (Brazil)') }}</option>
					<option language="Portuguese" value="pt-PT">{{ __('Portuguese (Portugal)') }}</option>
					<option language="Romanian" value="ro-RO">{{ __('Romanian (Romania)') }}</option>
					<option language="Russian" value="ru-RU">{{ __('Russian (Russia)') }}</option>
					<option language="Serbian" value="sr-RS">{{ __('Serbian (Cyrillic)') }}</option>
					<option language="Slovak" value="sk-SK">{{ __('Slovak (Slovakia)') }}</option>
					<option language="Spanish" value="es-ES">{{ __('Spanish (Spain)') }}</option>
					<option language="Swedish" value="sv-SE">{{ __('Swedish (Sweden)') }}</option>
					<option language="Tamil" value="ta-IN">{{ __('Tamil (India)') }}</option>
					<option language="Thai" value="th-TH">{{ __('Thai (Thailand)') }}</option>
					<option language="Turkish" value="tr-TR">{{ __('Turkish (Turkey)') }}</option>
					<option language="Ukrainian" value="uk-UA">{{ __('Ukrainian (Ukraine)') }}</option>
					<option language="Vietnamese" value="vi-VN">{{ __('Vietnamese (Vietnam)') }}</option>
				</x-forms.input>

				<div class="lqd-input-container relative">
					<label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-heading-foreground" for="ve_voice_select">
						<span class="lqd-input-label-txt">{{ __('Voice') }}</span>
					</label>
					<select
						class="lqd-input lqd-input-lg block h-11 w-full min-w-0 cursor-pointer border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
						id="ve_voice_select"
						x-model="voiceId"
					>
						<template x-for="v in voiceOptions" :key="v.value">
							<option :value="v.value" x-text="v.label" :data-platform="v.platform"></option>
						</template>
					</select>
				</div>

				<x-forms.input
					class="bg-input-background text-heading-foreground"
					class:label="text-heading-foreground"
					id="ve_voice_pace"
					name="voice_pace"
					type="select"
					label="{{ __('Pace') }}"
					size="lg"
					x-model="voicePace"
				>
					<option value="x-slow">{{ __('Very Slow') }}</option>
					<option value="slow">{{ __('Slow') }}</option>
					<option value="medium" selected>{{ __('Medium') }}</option>
					<option value="fast">{{ __('Fast') }}</option>
					<option value="x-fast">{{ __('Ultra Fast') }}</option>
				</x-forms.input>
			@endif

			{{-- Text --}}
			<x-forms.input
				class="bg-input-background text-heading-foreground placeholder:text-foreground/50"
				class:label="text-heading-foreground"
				id="ve_voice_text"
				name="voice_text"
				type="textarea"
				label="{{ __('Text') }}"
				size="none"
				placeholder="{{ __('Enter the text you want to convert to speech...') }}"
				x-model="voiceText"
				rows="5"
				maxlength="5000"
			/>
			<div class="-mt-2 text-right text-3xs text-foreground/40">
				<span x-text="(voiceText || '').length"></span>/5000
			</div>
		</div>

		{{-- Generate Button --}}
		<div class="border-t border-foreground/5 p-3">
			<x-button
				class="w-full"
				variant="primary"
				size="sm"
				@click.prevent="generateVoice()"
				x-bind:disabled="voiceGenerating || !voiceText?.trim()"
			>
				<template x-if="!voiceGenerating">
					<span class="flex items-center gap-1.5">
						<x-tabler-microphone class="size-4" />
						{{ __('Generate Voiceover') }}
					</span>
				</template>
				<template x-if="voiceGenerating">
					<span class="flex items-center gap-1.5">
						<x-tabler-loader-2 class="size-4 animate-spin" />
						{{ __('Generating...') }}
					</span>
				</template>
			</x-button>
		</div>
	</div>

	@endif

	@if ($veAiMusicProEnabled)
	{{-- Music Tab --}}
	{{-- UI patterns match the refactored AiMusicPro page (random prompt helper + collapsible advanced). --}}
	<div
		class="flex flex-1 flex-col overflow-hidden"
		x-show="leftPanelTab === 'music'"
	>
		<div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
			{{-- Prompt with random-example helper --}}
			<div>
				<div class="mb-2 flex items-center justify-between gap-2">
					<label class="text-2xs font-medium text-heading-foreground" for="ve_music_prompt">{{ __('Describe your song') }}</label>
					<button
						type="button"
						class="text-3xs text-foreground/70 underline transition-colors hover:text-foreground"
						@click.prevent="musicRandomPrompt()"
					>
						{{ __('Random example') }}
					</button>
				</div>
				<textarea
					id="ve_music_prompt"
					name="music_prompt"
					rows="4"
					maxlength="2000"
					placeholder="{{ __('Describe the music you want to generate...') }}"
					class="lqd-input lqd-input-size-none block w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors placeholder:text-foreground/50 focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
					x-model="musicPrompt"
				></textarea>
				<div class="mt-1 text-right text-3xs text-foreground/40">
					<span x-text="(musicPrompt || '').length"></span>/2000
				</div>
			</div>

			{{-- Advanced Settings Toggle --}}
			<button
				class="flex w-full items-center justify-between rounded-lg bg-foreground/[3%] px-3 py-2 text-3xs font-medium text-foreground/60 transition-colors hover:bg-foreground/5"
				@click.prevent="musicAdvancedOpen = !musicAdvancedOpen"
				type="button"
			>
				{{ __('Advanced Settings') }}
				<x-tabler-chevron-down class="size-3.5 transition-transform" x-bind:class="{ 'rotate-180': musicAdvancedOpen }" />
			</button>

			<div
				class="flex flex-col gap-3"
				x-show="musicAdvancedOpen"
				x-collapse
			>
				{{-- Provider info banner (Lyria only) --}}
				<template x-if="musicProvider === 'lyria3clip'">
					<div class="rounded-md border border-info/20 bg-info/5 px-3 py-2 text-3xs leading-relaxed text-info">
						{{ __('Lyria 3 Clip generates ~30 second music clips.') }}
					</div>
				</template>
				<template x-if="musicProvider === 'lyria3pro'">
					<div class="rounded-md border border-info/20 bg-info/5 px-3 py-2 text-3xs leading-relaxed text-info">
						{{ __('Lyria 3 Pro generates full songs (~2 minutes). Use timestamps in the lyrics to control duration.') }}
					</div>
				</template>

				{{-- Style is supported by all providers --}}
				<x-forms.input
					class="bg-input-background text-heading-foreground"
					class:label="text-heading-foreground"
					id="ve_music_style"
					name="music_style"
					type="select"
					label="{{ __('Music Style') }}"
					size="lg"
					x-model="musicStyle"
				>
					<option value="">{{ __('None') }}</option>
					<option value="techno">{{ __('Techno') }}</option>
					<option value="lofi">{{ __('Lo-Fi') }}</option>
					<option value="pop">{{ __('Pop') }}</option>
					<option value="jazz">{{ __('Jazz') }}</option>
					<option value="rock">{{ __('Rock') }}</option>
					<option value="classical">{{ __('Classical') }}</option>
					<option value="hiphop">{{ __('Hip-Hop/Trap') }}</option>
					<option value="edm">{{ __('EDM/House') }}</option>
					<option value="reggae">{{ __('Reggae') }}</option>
					<option value="ambient">{{ __('Ambient') }}</option>
					<option value="cozy">{{ __('Cozy') }}</option>
					<option value="relaxed">{{ __('Relaxed') }}</option>
				</x-forms.input>

				{{-- ElevenLabs only: explicit duration picker --}}
				<div x-show="musicProvider === 'elevenlabs'" x-cloak>
					<x-forms.input
						class="bg-input-background text-heading-foreground"
						class:label="text-heading-foreground"
						id="ve_music_duration"
						name="music_duration"
						type="select"
						label="{{ __('Music Duration') }}"
						size="lg"
						x-model.number="musicDuration"
					>
						<option value="">{{ __('None') }}</option>
						<option value="30">30 {{ __('seconds') }}</option>
						<option value="60">1 {{ __('minute') }}</option>
						<option value="120">2 {{ __('minutes') }}</option>
						<option value="180">3 {{ __('minutes') }}</option>
					</x-forms.input>
				</div>

				{{-- Lyria providers: lyrics + inspiration images --}}
				<div x-show="musicProvider !== 'elevenlabs'" x-cloak class="flex flex-col gap-3">
					<x-forms.input
						class="bg-input-background font-mono text-heading-foreground placeholder:text-foreground/50"
						class:label="text-heading-foreground"
						id="ve_music_lyrics"
						name="music_lyrics"
						type="textarea"
						label="{{ __('Custom Lyrics') }}"
						size="none"
						rows="5"
						placeholder="Verse 1&#10;Walking down the road at dawn&#10;Feeling like the world moves on&#10;&#10;Chorus&#10;But I keep holding on..."
						x-model="musicLyrics"
					/>

					<div>
						<label class="lqd-input-label mb-2 flex items-center gap-2 text-2xs font-medium leading-none text-heading-foreground">
							{{ __('Inspiration Images') }}
						</label>
						<div class="flex flex-wrap items-center gap-2">
							<template x-for="(preview, index) in musicImagePreviews" :key="'mimg-' + index">
								<div class="relative size-14 overflow-hidden rounded-lg border border-input-border">
									<img class="size-full object-cover" :src="preview" />
									<button
										class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity hover:opacity-100"
										type="button"
										@click.prevent="musicRemoveImage(index)"
									>
										<x-tabler-x class="size-4 text-white" />
									</button>
								</div>
							</template>
							<label
								class="flex size-14 cursor-pointer items-center justify-center rounded-lg border border-dashed border-input-border bg-input-background transition-colors hover:border-primary"
								x-show="musicImages.length < 10"
								data-exclude-media-manager="true"
							>
								<x-tabler-plus class="size-5 text-foreground/50 pointer-events-none" />
								<input
									class="hidden"
									type="file"
									accept="image/jpeg,image/png,image/webp"
									multiple
									data-exclude-media-manager="true"
									@change="musicAddImages($event)"
								/>
							</label>
						</div>
						<p class="mt-1 text-3xs text-foreground/50" x-show="musicImages.length > 0">
							<span x-text="musicImages.length"></span>/10 {{ __('images') }}
						</p>
					</div>

					{{-- Lyria 3 Pro only: output format --}}
					<div x-show="musicProvider === 'lyria3pro'" x-cloak>
						<x-forms.input
							class="bg-input-background text-heading-foreground"
							class:label="text-heading-foreground"
							id="ve_music_output_format"
							name="music_output_format"
							type="select"
							label="{{ __('Output Format') }}"
							size="lg"
							x-model="musicOutputFormat"
						>
							<option value="mp3">MP3</option>
							<option value="wav">WAV ({{ __('Higher quality') }})</option>
						</x-forms.input>
					</div>
				</div>
			</div>
		</div>

		{{-- Generate Button --}}
		<div class="border-t border-foreground/5 p-3">
			<x-button
				class="w-full"
				variant="primary"
				size="sm"
				@click.prevent="generateMusic()"
				x-bind:disabled="musicGenerating || !musicPrompt?.trim()"
			>
				<template x-if="!musicGenerating">
					<span class="flex items-center gap-1.5">
						<x-tabler-music class="size-4" />
						{{ __('Generate Music') }}
					</span>
				</template>
				<template x-if="musicGenerating">
					<span class="flex items-center gap-1.5">
						<x-tabler-loader-2 class="size-4 animate-spin" />
						{{ __('Generating...') }}
					</span>
				</template>
			</x-button>
		</div>
	</div>

	@endif

	{{-- Size Tab --}}
	<div
		class="flex flex-1 flex-col overflow-hidden"
		x-show="leftPanelTab === 'size'"
	>
		<div class="flex flex-col gap-4 p-4">
			{{-- Aspect Ratio Presets --}}
			<div>
				<label class="mb-2 block text-2xs font-medium text-foreground/60">{{ __('Aspect Ratio') }}</label>
				<div class="grid grid-cols-3 gap-2">
					<template x-for="preset in [
						{ label: '16:9', w: 1920, h: 1080 },
						{ label: '9:16', w: 1080, h: 1920 },
						{ label: '1:1', w: 1080, h: 1080 },
						{ label: '4:5', w: 1080, h: 1350 },
						{ label: '4:3', w: 1440, h: 1080 },
						{ label: '21:9', w: 2560, h: 1080 },
					]" :key="preset.label">
						<button
							class="rounded-lg border px-3 py-2.5 text-center text-xs font-medium transition-colors"
							:class="projectAspectRatio === preset.label
								? 'border-primary bg-primary/10 text-primary'
								: 'border-foreground/10 text-foreground/70 hover:border-foreground/20 hover:text-foreground'"
							@click.prevent="projectAspectRatio = preset.label; projectWidth = preset.w; projectHeight = preset.h; zoomCanvasFit()"
							x-text="preset.label"
						></button>
					</template>
				</div>
			</div>

			{{-- Custom Dimensions --}}
			<div>
				<label class="mb-2 block text-2xs font-medium text-foreground/60">{{ __('Dimensions') }}</label>
				<div class="flex items-center gap-2">
					<div class="flex-1">
						<label class="mb-1 block text-3xs text-foreground/40">{{ __('Width') }}</label>
						<input
							type="number"
							class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
							x-model.number="projectWidth"
							min="320"
							max="7680"
							@change="zoomCanvasFit()"
						/>
					</div>
					<x-tabler-x class="mt-4 size-3 shrink-0 text-foreground/30" />
					<div class="flex-1">
						<label class="mb-1 block text-3xs text-foreground/40">{{ __('Height') }}</label>
						<input
							type="number"
							class="lqd-input lqd-input-sm block h-9 w-full border border-input-border bg-input-background px-4 py-2 text-base text-heading-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary sm:text-2xs"
							x-model.number="projectHeight"
							min="320"
							max="7680"
							@change="zoomCanvasFit()"
						/>
					</div>
				</div>
			</div>

			{{-- FPS --}}
			<div>
				<label class="mb-2 block text-2xs font-medium text-foreground/60">{{ __('Frame Rate') }}</label>
				<div class="flex gap-2">
					<template x-for="fps in [24, 25, 30, 60]" :key="fps">
						<button
							class="flex-1 rounded-lg border px-3 py-2 text-center text-xs font-medium transition-colors"
							:class="projectFps === fps
								? 'border-primary bg-primary/10 text-primary'
								: 'border-foreground/10 text-foreground/70 hover:border-foreground/20 hover:text-foreground'"
							@click.prevent="projectFps = fps"
							x-text="fps + ' fps'"
						></button>
					</template>
				</div>
			</div>
		</div>
	</div>

	{{-- Layers Tab --}}
	<div
		class="flex flex-1 flex-col overflow-hidden"
		x-show="leftPanelTab === 'layers'"
	>
		<div class="flex-1 overflow-y-auto">
			<template x-if="!tracks.length">
				<div class="flex flex-col items-center justify-center py-10 text-center">
					<x-tabler-stack-2 class="mb-2 size-8 text-foreground/20" />
					<p class="text-xs font-medium text-foreground/50">{{ __('No layers yet') }}</p>
					<p class="mt-1 text-3xs text-foreground/40">{{ __('Add media or text to create layers') }}</p>
				</div>
			</template>

			<template x-if="tracks.length">
				<div class="flex flex-col">
					<template x-for="(track, trackIndex) in [...tracks].reverse()" :key="track.id">
						<div
							class="group flex items-center gap-2 border-b border-foreground/5 px-3 py-2 transition-colors hover:bg-foreground/[2%]"
							:class="selectedTrackId === track.id ? 'bg-primary/5' : ''"
						>
							{{-- Track Type Icon --}}
							<div class="flex size-7 shrink-0 items-center justify-center rounded text-foreground/40">
								<template x-if="track.type === 'video'">
									<x-tabler-movie class="size-4" />
								</template>
								<template x-if="track.type === 'audio'">
									<x-tabler-music class="size-4" />
								</template>
								<template x-if="track.type === 'text'">
									<x-tabler-typography class="size-4" />
								</template>
							</div>

							{{-- Track Label --}}
							<span
								class="flex-1 truncate text-xs"
								:class="selectedTrackId === track.id ? 'text-primary font-medium' : 'text-foreground/70'"
								x-text="track.label"
							></span>

							{{-- Clip Count --}}
							<span
								class="rounded bg-foreground/5 px-1.5 py-0.5 text-3xs text-foreground/40"
								x-text="track.clips.length"
							></span>

							{{-- Lock / Mute --}}
							<button
								class="flex size-6 items-center justify-center rounded text-foreground/30 transition-colors hover:text-foreground"
								@click.prevent="track.muted = !track.muted; markChanged()"
								:title="track.muted ? '{{ __('Unmute') }}' : '{{ __('Mute') }}'"
							>
								<template x-if="!track.muted">
									<x-tabler-eye class="size-3.5" />
								</template>
								<template x-if="track.muted">
									<x-tabler-eye-off class="size-3.5" />
								</template>
							</button>

							<button
								class="flex size-6 items-center justify-center rounded text-foreground/30 transition-colors hover:text-foreground"
								@click.prevent="track.locked = !track.locked; markChanged()"
								:title="track.locked ? '{{ __('Unlock') }}' : '{{ __('Lock') }}'"
							>
								<template x-if="!track.locked">
									<x-tabler-lock-open class="size-3.5" />
								</template>
								<template x-if="track.locked">
									<x-tabler-lock class="size-3.5" />
								</template>
							</button>
						</div>
					</template>
				</div>
			</template>
		</div>

		{{-- Add Track Buttons --}}
		<div class="flex gap-2 border-t border-foreground/5 p-3">
			<x-button
				class="flex-1 text-3xs"
				variant="outline"
				size="sm"
				@click.prevent="addTrack('video')"
			>
				<x-tabler-plus class="size-3" />
				{{ __('Video') }}
			</x-button>
			<x-button
				class="flex-1 text-3xs"
				variant="outline"
				size="sm"
				@click.prevent="addTrack('audio')"
			>
				<x-tabler-plus class="size-3" />
				{{ __('Audio') }}
			</x-button>
			<x-button
				class="flex-1 text-3xs"
				variant="outline"
				size="sm"
				@click.prevent="addTextClip()"
			>
				<x-tabler-plus class="size-3" />
				{{ __('Text') }}
			</x-button>
		</div>
	</div>
</div>
