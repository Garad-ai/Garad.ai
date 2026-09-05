<div class="py-12">
	<div class="mx-auto max-w-7xl px-6">
		<!-- Header -->
		<div class="mb-8 flex items-center justify-between">
			<h2 class="text-2xl font-bold text-zinc-900 dark:text-white">
				@lang('My Presentations')
			</h2>

			<x-button
				class="group inline-flex items-center gap-1.5 text-sm font-medium text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"
				variant="link"
				href="#"
				@click.prevent="switchView('gallery')"
			>
				@lang('View All')
				<x-tabler-chevron-right class="size-4 transition-transform group-hover:translate-x-0.5" />
			</x-button>
		</div>

		<!-- Grid -->
		<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
			@foreach ($presentations as $item)
				<div
					class="presentation-result group/item"
					data-id="{{ $item->id }}"
					data-id-prefix="{{ $id_prefix ?? 'modal-' }}"
					data-generator="{{ $item->format }}"
					data-payload="{{ json_encode($item) }}"
					data-generation-id="{{ $item->generation_id }}"
				>
					<div class="relative overflow-hidden rounded-2xl bg-white shadow-sm transition-all duration-300 hover:shadow-xl dark:bg-zinc-800">
						<!-- Presentation Preview Card -->
						<div class="relative aspect-[4/3] w-full overflow-hidden bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-zinc-700 dark:to-zinc-600">
							<!-- Loading Spinner (shown when processing/pending) -->
							@if($item->status === 'processing' || $item->status === 'pending')
								<div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/80 backdrop-blur-sm dark:bg-zinc-800/80">
									<div class="size-12 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600"></div>
									<span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{__('Generating')}}...</span>
								</div>
							@endif

							<!-- Error State (shown when failed) -->
							@if($item->status === 'failed')
								<div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-red-50/80 backdrop-blur-sm dark:bg-red-900/20">
									<x-tabler-alert-circle class="size-12 text-red-500" />
									<span class="text-sm font-medium text-red-600 dark:text-red-400">{{__('Generation Failed')}}</span>
								</div>
							@endif

							<!-- Presentation Thumbnail/Icon -->
							<div class="flex size-full items-center justify-center p-6">
								<x-tabler-presentation class="size-16 text-indigo-300 dark:text-indigo-400" />
							</div>

							<!-- Hover Overlay with Actions (only for completed) -->
							
							@if($item->status === 'completed' && ($item->pdf_url || $item->pptx_url))
								@php
									$downloadUrl = $item->pptx_url ?: $item->pdf_url;
									$downloadExt = $item->pptx_url ? 'pptx' : 'pdf';
								@endphp
								<div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/40 opacity-0 backdrop-blur-sm transition-opacity duration-300 group-hover/item:opacity-100">
									<!-- View Button - Opens Modal (PDF only) -->
									@if($item->pdf_url)
										<button
											@click.prevent="$dispatch('open-pdf', {
							url: '{{ $item->pdf_url }}',
							title: '{{ addslashes(pathinfo($item->pdf_url, PATHINFO_FILENAME)) }}',
							pages: {{ $item->total_pages ?? 1 }}
						})"
											class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-emerald-400 hover:text-white"
											title="View PDF"
										>
											<x-tabler-eye class="size-5" />
										</button>
									@endif
									<!-- Download Button -->
									<a
										href="{{ $downloadUrl }}"
										download="presentation-{{ $item->id }}.{{ $downloadExt }}"
										class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-blue-400 hover:text-white"
										title="Download"
									>
										<x-tabler-download class="size-5" />
									</a>
									<!-- Delete Button -->
									<button
										@click.prevent="deletePresentation({{ $item->id }})"
										class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-red-400 hover:text-white"
										title="Delete"
									>
										<x-tabler-trash class="size-5" />
									</button>
								</div>
							@endif
						</div>

						<!-- Card Footer -->
						<div class="p-4">
							<h5 class="mb-2 line-clamp-2 text-sm font-semibold text-zinc-900 dark:text-white">
								{{ \Illuminate\Support\Str::limit($item->input_text, 20) ?: __('Untitled Presentation') }}
							</h5>
							<div class="flex items-center justify-between">
								<time class="text-xs text-zinc-500 dark:text-zinc-400">
									{{ $item->created_at->diffForHumans() }}
								</time>
							</div>
						</div>
					</div>
				</div>
			@endforeach
		</div>
	</div>
</div>


<div id="presentation-action-template" class="hidden">
	<div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/40 opacity-0 backdrop-blur-sm transition-opacity duration-300 group-hover/item:opacity-100">
		<!-- View Button -->
		<button
			class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-emerald-400 hover:text-white"
			title="View PDF"
		>
			<x-tabler-eye class="size-5" />
		</button>

		<!-- Download Button -->
		<a
			href="#"
			download=""
			class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-blue-400 hover:text-white"
			title="Download"
		>
			<x-tabler-download class="size-5" />
		</a>

		<!-- Delete Button -->
		<button
			class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-900 shadow-lg transition-all hover:scale-110 hover:bg-red-400 hover:text-white"
			title="Delete"
		>
			<x-tabler-trash class="size-5" />
		</button>
	</div>
</div>
