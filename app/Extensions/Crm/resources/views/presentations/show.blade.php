@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $presentation->title)
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', $presentation->deal ? $presentation->deal->title : '')

@section('titlebar_actions')
	<div class="flex gap-2">
		@if (! $presentation->isGamma() && $presentation->isCompleted())
			<x-button
				href="{{ route('dashboard.user.crm.presentations.downloadAll', $presentation->id) }}"
			>
				<x-tabler-download class="size-4" />
				{{ __('Download All') }}
			</x-button>
		@endif
		@if ($presentation->isCompleted())
			<x-button
				href="{{ route('dashboard.user.crm.presentations.downloadPdf', $presentation->id) }}"
			>
				<x-tabler-file-type-pdf class="size-4" />
				{{ __('Download as PDF') }}
			</x-button>
		@endif
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.presentations.index') }}"
		>
			{{ __('Back') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div
		class="py-10"
		x-data="presentationViewer()"
	>
		{{-- Progress bar (Fal AI engine) --}}
		<template x-if="engine !== 'gamma' && status !== 'completed' && status !== 'failed'">
			<div class="mb-8">
				<x-card class:body="p-5">
					<div class="flex items-center gap-4">
						<div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
							<svg
								class="size-5 animate-spin text-primary"
								xmlns="http://www.w3.org/2000/svg"
								fill="none"
								viewBox="0 0 24 24"
							>
								<circle
									class="opacity-25"
									cx="12"
									cy="12"
									r="10"
									stroke="currentColor"
									stroke-width="4"
								></circle>
								<path
									class="opacity-75"
									fill="currentColor"
									d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
								></path>
							</svg>
						</div>
						<div class="flex-1">
							<div class="mb-1 flex items-center justify-between text-sm">
								<span class="font-medium">{{ __('Generating slides...') }}</span>
								<span
									class="text-foreground/60"
									x-text="completedSlides + ' / ' + totalSlides + ' {{ __('slides') }}'"
								></span>
							</div>
							<div class="h-2 overflow-hidden rounded-full bg-foreground/10">
								<div
									class="h-full rounded-full bg-primary transition-all duration-500"
									:style="'width:' + progress + '%'"
								></div>
							</div>
						</div>
					</div>
				</x-card>
			</div>
		</template>

		{{-- Presentation info --}}
		<div class="mb-6 flex flex-wrap items-center gap-3">
			<template x-if="status === 'completed'">
				<x-badge variant="success">{{ __('Completed') }}</x-badge>
			</template>
			<template x-if="status === 'generating'">
				<x-badge variant="info">{{ __('Generating') }}</x-badge>
			</template>
			<template x-if="status === 'failed'">
				<x-badge variant="danger">{{ __('Failed') }}</x-badge>
			</template>
			@if ($presentation->style)
				<x-badge variant="secondary">{{ ucfirst($presentation->style) }}</x-badge>
			@endif
			<span class="text-sm text-foreground/60">
				{{ $presentation->created_at->format('M d, Y H:i') }}
				@if ($presentation->deal)
					&middot; {{ $presentation->deal->title }}
				@endif
			</span>
		</div>

		{{-- Slide grid (Fal AI engine) --}}
		<template x-if="engine !== 'gamma'">
		<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
			<template
				x-for="slide in slides"
				:key="slide.id"
			>
				<div class="group overflow-hidden rounded-xl border bg-background shadow-sm transition-shadow hover:shadow-md">
					{{-- Slide image area --}}
					<div class="relative aspect-video w-full bg-foreground/5">
						<template x-if="slide.status === 'completed' && slide.image_url">
							<div class="relative h-full w-full cursor-pointer" @click="openLightbox(slide)">
								<img
									:src="slide.image_url"
									:alt="slide.title"
									class="h-full w-full object-cover transition-transform group-hover:scale-[1.02]"
								/>
								@include('crm::presentations.partials.slide-overlay', ['slideExpr' => 'slide', 'variant' => 'grid'])
							</div>
						</template>
						<template x-if="slide.status === 'generating'">
							<div class="flex h-full flex-col items-center justify-center gap-2">
								<svg
									class="size-8 animate-spin text-primary"
									xmlns="http://www.w3.org/2000/svg"
									fill="none"
									viewBox="0 0 24 24"
								>
									<circle
										class="opacity-25"
										cx="12"
										cy="12"
										r="10"
										stroke="currentColor"
										stroke-width="4"
									></circle>
									<path
										class="opacity-75"
										fill="currentColor"
										d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
									></path>
								</svg>
								<span class="text-xs text-foreground/40">{{ __('Generating...') }}</span>
							</div>
						</template>
						<template x-if="slide.status === 'failed'">
							<div class="flex h-full flex-col items-center justify-center gap-2 text-red-400">
								<x-tabler-alert-triangle class="size-8" />
								<span class="text-xs">{{ __('Failed') }}</span>
							</div>
						</template>
						<template x-if="slide.status === 'pending'">
							<div class="flex h-full flex-col items-center justify-center gap-2 text-foreground/30">
								<x-tabler-clock class="size-8" />
								<span class="text-xs">{{ __('Queued') }}</span>
							</div>
						</template>

						{{-- Slide number --}}
						<div class="absolute left-2 top-2 flex size-6 items-center justify-center rounded-full bg-black/50 text-xs font-bold text-white">
							<span x-text="slide.sort_order + 1"></span>
						</div>
					</div>

					{{-- Slide footer --}}
					<div class="flex items-center gap-2 p-3">
						<span
							class="inline-block rounded bg-foreground/5 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-foreground/50"
							x-text="slide.slide_type.replace('_', ' ')"
						></span>
						<template x-if="slide.status === 'completed' && slide.image_url">
							<a
								:href="'{{ route('dashboard.user.crm.presentations.downloadSlide', '') }}/' + slide.id + '/download'"
								class="ml-auto text-foreground/40 transition-colors hover:text-primary"
								title="{{ __('Download') }}"
							>
								<x-tabler-download class="size-4" />
							</a>
						</template>
					</div>
				</div>
			</template>
		</div>
		</template>

		{{-- Gamma deck (Gamma engine) --}}
		<template x-if="engine === 'gamma'">
			<div class="mx-auto max-w-xl">
				<x-card class:body="p-8 text-center">
					<template x-if="status === 'completed'">
						<div class="flex flex-col items-center gap-5">
							<div class="flex size-16 items-center justify-center rounded-full bg-primary/10">
								<x-tabler-file-text class="size-8 text-primary" />
							</div>
							<div>
								<h3 class="text-lg font-semibold">{{ __('Your deck is ready') }}</h3>
								<p class="mt-1 text-sm text-foreground/60">
									{{ __('Generated with Gamma. View it online or download the PDF.') }}
								</p>
							</div>
							<div class="flex flex-wrap items-center justify-center gap-3">
								<template x-if="pdfUrl">
									<x-button
										href="#"
										::href="pdfUrl"
										download
									>
										<x-tabler-download class="size-4" />
										{{ __('Download PDF') }}
									</x-button>
								</template>
								<template x-if="gammaUrl">
									<x-button
										variant="ghost-shadow"
										href="#"
										::href="gammaUrl"
										target="_blank"
									>
										<x-tabler-external-link class="size-4" />
										{{ __('View on Gamma') }}
									</x-button>
								</template>
							</div>
						</div>
					</template>

					<template x-if="status !== 'completed' && status !== 'failed'">
						<div class="flex flex-col items-center gap-4 py-6">
							<svg
								class="size-10 animate-spin text-primary"
								xmlns="http://www.w3.org/2000/svg"
								fill="none"
								viewBox="0 0 24 24"
							>
								<circle
									class="opacity-25"
									cx="12"
									cy="12"
									r="10"
									stroke="currentColor"
									stroke-width="4"
								></circle>
								<path
									class="opacity-75"
									fill="currentColor"
									d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
								></path>
							</svg>
							<div>
								<p class="font-medium">{{ __('Generating your presentation...') }}</p>
								<p class="mt-1 text-sm text-foreground/60">
									{{ __('Gamma is building your deck. This can take a minute.') }}
								</p>
							</div>
						</div>
					</template>

					<template x-if="status === 'failed'">
						<div class="flex flex-col items-center gap-3 py-6 text-red-400">
							<x-tabler-alert-triangle class="size-10" />
							<p class="text-sm">{{ __('Generation failed. Please try again.') }}</p>
						</div>
					</template>
				</x-card>
			</div>
		</template>

		{{-- Lightbox modal --}}
		<template x-teleport="body">
			<template x-if="lightboxSlide">
				<div
					class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4"
					@click.self="closeLightbox()"
					@keydown.escape.window="closeLightbox()"
					@keydown.left.window="prevSlide()"
					@keydown.right.window="nextSlide()"
				>
				{{-- Close button --}}
				<button
					class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
					@click="closeLightbox()"
				>
					<x-tabler-x class="size-5" />
				</button>

				{{-- Navigation arrows --}}
				<button
					class="absolute left-4 top-1/2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
					@click="prevSlide()"
				>
					<x-tabler-chevron-left class="size-6" />
				</button>
				<button
					class="absolute right-4 top-1/2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
					@click="nextSlide()"
				>
					<x-tabler-chevron-right class="size-6" />
				</button>

				{{-- Image --}}
				<div class="relative inline-block max-h-[85vh] max-w-[90vw] overflow-hidden rounded-lg shadow-2xl">
					<img
						:src="lightboxSlide.image_url"
						:alt="lightboxSlide.title"
						class="max-h-[85vh] max-w-full object-contain"
					/>
					@include('crm::presentations.partials.slide-overlay', ['slideExpr' => 'lightboxSlide', 'variant' => 'lightbox'])
				</div>

				{{-- Bottom info bar --}}
				<div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-4 rounded-full bg-black/60 px-6 py-3 text-white backdrop-blur-sm">
					<span
						class="text-sm font-medium"
						x-text="(lightboxSlide.sort_order + 1) + ' / ' + slides.length + ' — ' + lightboxSlide.title"
					></span>
					<a
						:href="'{{ route('dashboard.user.crm.presentations.downloadSlide', '') }}/' + lightboxSlide.id + '/download'"
						class="flex items-center gap-1 rounded bg-white/20 px-3 py-1 text-xs transition-colors hover:bg-white/30"
					>
						<x-tabler-download class="size-3.5" />
						{{ __('Download') }}
					</a>
				</div>
			</div>
		</template>
		</template>
	</div>
@endsection

@push('script')
	<script>
		function presentationViewer() {
			return {
				presentationId: {{ $presentation->id }},
				status: '{{ $presentation->status }}',
				engine: '{{ $presentation->engine }}',
				gammaUrl: {!! json_encode($presentation->gamma_url) !!},
				pdfUrl: {!! json_encode($presentation->pdf_url) !!},
				progress: {{ $presentation->progress }},
				completedSlides: {{ $presentation->completed_slides }},
				totalSlides: {{ $presentation->total_slides }},
				slides: {!! json_encode($presentation->slides->map(function($s) {
				    return [
				        'id' => $s->id,
				        'sort_order' => $s->sort_order,
				        'title' => $s->title,
				        'slide_type' => $s->slide_type,
				        'status' => $s->status,
				        'image_url' => $s->image_url,
				        'content' => $s->content,
				    ];
				})) !!},
				lightboxSlide: null,
				pollInterval: null,

				init() {
					if (this.status !== 'completed' && this.status !== 'failed') {
						this.startPolling();
					}
				},

				startPolling() {
					this.pollInterval = setInterval(() => this.checkStatus(), 3000);
				},

				async checkStatus() {
					try {
						const resp = await fetch(`/dashboard/user/crm/presentations/${this.presentationId}/status`);
						const data = await resp.json();

						this.status = data.status;
						this.progress = data.progress;
						this.completedSlides = data.completed_slides;
						this.totalSlides = data.total_slides;
						this.slides = data.slides;

						if (data.engine === 'gamma') {
							this.gammaUrl = data.gamma_url;
							this.pdfUrl = data.pdf_url;
						}

						if (data.status === 'completed' || data.status === 'failed') {
							clearInterval(this.pollInterval);
							if (data.status === 'completed') {
								toastr.success(this.engine === 'gamma'
									? '{{ __('Your presentation is ready!') }}'
									: '{{ __('All slides are ready!') }}');
							}
						}
					} catch (e) {
						// Silently retry on next poll
					}
				},

				openLightbox(slide) {
					if (slide.status === 'completed' && slide.image_url) {
						this.lightboxSlide = slide;
					}
				},

				closeLightbox() {
					this.lightboxSlide = null;
				},

				prevSlide() {
					if (!this.lightboxSlide) return;
					const completedSlides = this.slides.filter(s => s.status === 'completed' && s.image_url);
					const idx = completedSlides.findIndex(s => s.id === this.lightboxSlide.id);
					if (idx > 0) {
						this.lightboxSlide = completedSlides[idx - 1];
					} else {
						this.lightboxSlide = completedSlides[completedSlides.length - 1];
					}
				},

				nextSlide() {
					if (!this.lightboxSlide) return;
					const completedSlides = this.slides.filter(s => s.status === 'completed' && s.image_url);
					const idx = completedSlides.findIndex(s => s.id === this.lightboxSlide.id);
					if (idx < completedSlides.length - 1) {
						this.lightboxSlide = completedSlides[idx + 1];
					} else {
						this.lightboxSlide = completedSlides[0];
					}
				},
			};
		}
	</script>
@endpush
