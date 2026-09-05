<div
	class="lqd-adv-presentation-gallery pointer-events-none invisible fixed inset-0 z-2 flex min-h-screen overflow-y-auto bg-background opacity-0 transition-all"
	:class="{
        'opacity-0': currentView !== 'gallery',
        'invisible': currentView !== 'gallery',
        'pointer-events-none': currentView !== 'gallery'
    }"
	x-data="{
        cols: 1,
        presentations: [],
        loading: false,
        currentPage: 1,
        lastPage: 1,
        hasLoaded: false,
        init() {
            this.cols = this.getCols();
        },
        async loadPresentations(page = 1) {
            this.loading = true;
            try {
                const response = await fetch(`{{ route('dashboard.user.ai-presentation.gallery') }}?page=${page}`);
                const data = await response.json();

                if (page === 1) {
                    this.presentations = data.data;
                } else {
                    this.presentations = [...this.presentations, ...data.data];
                }

                this.currentPage = data.current_page;
                this.lastPage = data.last_page;
                this.hasLoaded = true;
            } catch (error) {
                console.error('Error loading presentations:', error);
            } finally {
                this.loading = false;
            }
        },
        loadMore() {
            if (this.currentPage < this.lastPage) {
                this.loadPresentations(this.currentPage + 1);
            }
        },
        increaseCols() {
            this.cols = Math.min(6, this.cols + 1);
        },
        decreaseCols() {
            this.cols = Math.max(1, this.cols - 1);
        },
        getCols() {
            const imageGrid = this.$refs.galleryPresentationGrid;
            if (!imageGrid) return 1;
            const gridStyles = window.getComputedStyle(imageGrid);
            return parseInt(gridStyles.getPropertyValue('--cols')) || 1;
        },
        async deletePresentation(id) {
            if (!confirm('{{ __('Are you sure you want to delete this presentation?') }}')) {
                return;
            }

            try {
                const response = await fetch(`/dashboard/user/ai-presentation/${id}`, {
                    method: 'DELETE',
                    headers: {
						'Accept': 'application/json'
					}
				});

				if (response.ok) {
					this.presentations = this.presentations.filter(p => p.id !== id);
				}
			} catch (error) {
				console.error('Error deleting presentation:', error);
			}
		}
	}"
	x-effect="if (currentView === 'gallery' && !hasLoaded) { loadPresentations(1); }"
>
<div class="container">
	<div class="py-28">
		<div class="mb-10 flex flex-wrap items-center justify-between gap-x-2 gap-y-4">
			<h2 class="m-0">
				@lang('My Presentations')
			</h2>

			<div class="flex flex-wrap items-center gap-3 text-label lg:flex-nowrap">
				<label
					class="text-2xs font-medium text-heading-foreground/80"
					for="gallery_columns"
				>
					@lang('Columns')
				</label>
				<div class="flex w-full max-w-60 items-center gap-3">
					<button
						class="inline-grid size-4 place-content-center"
						type="button"
						@click.prevent="decreaseCols"
					>
						<x-tabler-minus class="size-4" />
					</button>
					<input
						class="h-0.5 w-full appearance-none rounded-full bg-neutral-50 focus:outline-black dark:bg-neutral-900 dark:focus:outline-white [&::-moz-range-thumb]:size-2.5 [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border [&::-moz-range-thumb]:border-none [&::-moz-range-thumb]:border-background [&::-moz-range-thumb]:bg-black active:[&::-moz-range-thumb]:scale-110 [&::-moz-range-thumb]:dark:bg-white [&::-webkit-slider-thumb]:size-2.5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border [&::-webkit-slider-thumb]:border-none [&::-webkit-slider-thumb]:border-background [&::-webkit-slider-thumb]:bg-black active:[&::-webkit-slider-thumb]:scale-110 [&::-webkit-slider-thumb]:dark:bg-white"
						id="gallery_columns"
						type="range"
						value="1"
						min="1"
						max="6"
						step="1"
						x-model="cols"
					/>
					<button
						class="inline-grid size-4 place-content-center"
						type="button"
						@click.prevent="increaseCols"
					>
						<x-tabler-plus class="size-4" />
					</button>
				</div>
			</div>
		</div>

		<div id="lqd-presentation-recent-grid-wrap">
			<!-- Loading State -->
			<div x-show="loading && presentations.length === 0" class="text-center py-10">
				<div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 dark:border-white"></div>
				<p class="mt-2 text-sm text-gray-600 dark:text-gray-400">@lang('Loading presentations...')</p>
			</div>

			<!-- Presentations Grid -->
			<div
				x-show="presentations.length > 0"
				class="lqd-presentation-recent-grid grid grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-5 transition-all [--cols:1] sm:[--cols:2] md:gap-x-6 md:[--cols:3] lg:gap-x-11 lg:[--cols:5] [&_.presentation-result:nth-child(n+17)]:hidden"
				x-ref="galleryPresentationGrid"
				:style="{ '--cols': cols }"
			>
				<template x-for="item in presentations" :key="item.id">
					<div
						class="presentation-result group/item"
						:data-id="item.id"
						data-id-prefix="gallery-"
						:data-generator="item.format"
						:data-payload="JSON.stringify(item)"
						:data-generation-id="item.generation_id"
					>
						<div class="relative overflow-hidden rounded-2xl bg-white shadow-sm transition-all duration-300 hover:shadow-xl dark:bg-zinc-800">
							<!-- Presentation Preview Card -->
							<div class="relative aspect-[4/3] w-full overflow-hidden bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-zinc-700 dark:to-zinc-600">
								<!-- Loading Spinner (shown when processing/pending) -->
								<template x-if="item.status === 'processing' || item.status === 'pending'">
									<div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/80 backdrop-blur-sm dark:bg-zinc-800/80">
										<div class="size-12 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600"></div>
										<span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">@lang('Generating')...</span>
									</div>
								</template>

								<!-- Error State (shown when failed) -->
								<template x-if="item.status === 'failed'">
									<div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-red-50/80 backdrop-blur-sm dark:bg-red-900/20">
										<x-tabler-alert-circle class="size-12 text-red-500" />
										<span class="text-sm font-medium text-red-600 dark:text-red-400">@lang('Generation Failed')</span>
									</div>
								</template>

								<!-- Presentation Thumbnail/Icon -->
								<div class="flex size-full items-center justify-center p-6">
									<x-tabler-presentation class="size-16 text-indigo-300 dark:text-indigo-400" />
								</div>
								<!-- Hover Overlay with Actions (only for completed) -->
								<template x-if="item.status === 'completed' && (item.pdf_url || item.pptx_url)">
									<div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/40 opacity-0 backdrop-blur-sm transition-opacity duration-300 group-hover/item:opacity-100">
										<!-- View Button - Opens Modal (PDF only) -->
										<template x-if="item.pdf_url">
											<button
												@click.prevent="$dispatch('open-pdf', {
													url: item.pdf_url,
													title: item.input_text,
													pages: item.total_pages || 1
												})"
												class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-emerald-400 hover:text-white"
												title="View PDF"
											>
												<x-tabler-eye class="size-5" />
											</button>
										</template>
										<!-- Download Button -->
										<a
											:href="item.pptx_url || item.pdf_url"
											:download="`presentation-${item.id}.${item.pptx_url ? 'pptx' : 'pdf'}`"
											class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-blue-400 hover:text-white"
											title="Download"
										>
											<x-tabler-download class="size-5" />
										</a>
										<!-- Delete Button -->
										<button
											@click.prevent="deletePresentation(item.id)"
											class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-red-400 hover:text-white"
											title="Delete"
										>
											<x-tabler-trash class="size-5" />
										</button>
									</div>
								</template>
							</div>

							<!-- Card Footer -->
							<div class="p-4">
								<h5 class="mb-2 line-clamp-2 text-sm font-semibold text-zinc-900 dark:text-white" x-text="(item.input_text || '@lang('Untitled Presentation')').substring(0, 60) + ((item.input_text || '').length > 60 ? '...' : '')">
								</h5>
								<div class="flex items-center justify-between">
									<time class="text-xs text-zinc-500 dark:text-zinc-400" x-text="new Date(item.created_at).toLocaleDateString()">
									</time>
								</div>
							</div>
						</div>
					</div>
				</template>
			</div>

			<!-- Empty State -->
			<div x-show="!loading && presentations.length === 0 && hasLoaded" class="text-center py-16">
				<p class="text-gray-500 dark:text-gray-400">@lang('No presentations found.')</p>
			</div>

			<!-- Load More Button -->
			<div class="mt-8 text-center" x-show="currentPage < lastPage">
				<button
					@click="loadMore"
					:disabled="loading"
					class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
				>
					<span x-show="!loading">@lang('Load More')</span>
					<span x-show="loading">@lang('Loading...')</span>
				</button>
			</div>
		</div>
	</div>
</div>
</div>
