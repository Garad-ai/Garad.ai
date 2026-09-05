@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Presentations'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Generate AI-powered pitch decks for your deals.'))

@section('titlebar_actions')
	<x-modal title="{{ __('Generate Presentation') }}">
		<x-slot:trigger>
			<x-tabler-plus class="size-4" />
			{{ __('New Presentation') }}
		</x-slot:trigger>

		<x-slot:modal>
			<form
				class="flex flex-col gap-5"
				id="crm_presentation_form"
			>
				@csrf
				<x-forms.input
					size="lg"
					type="select"
					label="{{ __('Deal') }}"
					name="crm_deal_id"
					required
				>
					<option value="">{{ __('Select a deal') }}</option>
					@foreach ($deals as $deal)
						<option value="{{ $deal->id }}">
							{{ $deal->title }}
							@if ($deal->company)
								- {{ $deal->company->name }}
							@endif
						</option>
					@endforeach
				</x-forms.input>

				<x-forms.input
					size="lg"
					type="select"
					label="{{ __('Style') }}"
					name="style"
				>
					<option value="corporate">{{ __('Corporate') }}</option>
					<option value="modern">{{ __('Modern') }}</option>
					<option value="minimal">{{ __('Minimal') }}</option>
					<option value="creative">{{ __('Creative') }}</option>
				</x-forms.input>

				<p class="text-xs text-foreground/60">
					{{ __('This will generate 5-8 slide images using AI. Text generation and image credits will be deducted.') }}
				</p>

				<x-button
					id="crm_presentation_submit"
					type="button"
				>
					<x-tabler-sparkles class="size-4" />
					{{ __('Generate') }}
				</x-button>
			</form>
		</x-slot:modal>
	</x-modal>
@endsection

@section('content')
	<div class="py-10">
		@if ($presentations->isEmpty())
			<div class="flex flex-col items-center justify-center py-20 text-center">
				<div class="mb-4 flex size-16 items-center justify-center rounded-full bg-foreground/5">
					<x-tabler-presentation class="size-8 text-foreground/40" />
				</div>
				<h3 class="mb-2 text-lg font-semibold">{{ __('No Presentations Yet') }}</h3>
				<p class="mb-6 text-foreground/60">{{ __('Generate your first AI-powered pitch deck for a deal.') }}</p>
			</div>
		@else
			<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
				@foreach ($presentations as $pres)
					<x-card
						class="group/pres"
						class:body="p-0 overflow-hidden"
					>
						{{-- Thumbnail --}}
						<a
							class="relative block aspect-video w-full bg-foreground/5"
							href="{{ route('dashboard.user.crm.presentations.show', $pres->id) }}"
							title="{{ $pres->title }}"
						>
							@php
								$firstSlide = $pres->slides->first();
							@endphp
							@if ($firstSlide && $firstSlide->image_url)
								<img
									src="{{ $firstSlide->image_url }}"
									alt="{{ $pres->title }}"
									class="h-full w-full object-cover"
								/>
							@else
								<div class="flex h-full items-center justify-center">
									<x-tabler-presentation class="size-12 text-foreground/20" />
								</div>
							@endif

							{{-- Status badge --}}
							<div class="absolute right-2 top-2">
								@if ($pres->status === 'completed')
									<x-badge variant="success">{{ __('Completed') }}</x-badge>
								@elseif ($pres->status === 'generating')
									<x-badge variant="info">{{ __('Generating...') }}</x-badge>
								@elseif ($pres->status === 'failed')
									<x-badge variant="danger">{{ __('Failed') }}</x-badge>
								@else
									<x-badge variant="secondary">{{ __('Pending') }}</x-badge>
								@endif
							</div>
						</a>

						{{-- Info --}}
						<div class="flex flex-col gap-2 p-4">
							<h4 class="text-sm font-semibold leading-tight">{{ $pres->title }}</h4>
							<div class="flex items-center gap-2 text-xs text-foreground/60">
								@if ($pres->deal)
									<span>{{ $pres->deal->title }}</span>
									<span>&middot;</span>
								@endif
								<span>{{ $pres->created_at->diffForHumans() }}</span>
							</div>

							@if ($pres->status === 'generating')
								<div class="mt-1">
									<div class="flex items-center justify-between text-xs text-foreground/60">
										<span>{{ __(':done of :total slides', ['done' => $pres->completed_slides, 'total' => $pres->total_slides]) }}</span>
										<span>{{ $pres->progress }}%</span>
									</div>
									<div class="mt-1 h-1.5 overflow-hidden rounded-full bg-foreground/10">
										<div
											class="h-full rounded-full bg-primary transition-all"
											style="width: {{ $pres->progress }}%"
										></div>
									</div>
								</div>
							@endif

							<div class="mt-2 flex items-center gap-2">
								<x-button
									size="sm"
									variant="ghost-shadow"
									href="{{ route('dashboard.user.crm.presentations.show', $pres->id) }}"
								>
									{{ __('View') }}
								</x-button>
								<x-button
									class="invisible opacity-0 transition-all focus-visible:visible focus-visible:opacity-100 group-hover/pres:visible group-hover/pres:opacity-100"
									size="sm"
									variant="danger"
									href="{{ route('dashboard.user.crm.presentations.delete', $pres->id) }}"
									onclick="return confirm('{{ __('Are you sure?') }}')"
								>
									<x-tabler-trash class="size-3" />
								</x-button>
							</div>
						</div>
					</x-card>
				@endforeach
			</div>
		@endif
	</div>
@endsection

@push('script')
	<script>
		document.addEventListener('click', function(e) {
			const btn = e.target.closest('#crm_presentation_submit');
			if (!btn) {
				return;
			}

			const form = document.getElementById('crm_presentation_form');
			const formData = new FormData(form);
			const dealId = formData.get('crm_deal_id');

			if (!dealId) {
				toastr.error('{{ __('Please select a deal.') }}');
				return;
			}

			btn.disabled = true;
			btn.innerHTML = '<svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> {{ __('Generating...') }}';

			$.ajax({
				type: 'POST',
				url: '{{ route('dashboard.user.crm.presentations.store') }}',
				data: formData,
				contentType: false,
				processData: false,
				success: function(data) {
					toastr.success(data.message || '{{ __('Presentation generation started!') }}');
					if (data.redirect) {
						setTimeout(function() {
							window.location.href = data.redirect;
						}, 300);
					}
				},
				error: function(data) {
					var err = data.responseJSON?.errors;
					if (err) {
						$.each(err, function(index, value) {
							toastr.error(value);
						});
					} else {
						toastr.error(data.responseJSON?.message || '{{ __('An error occurred.') }}');
					}
					btn.disabled = false;
					btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.912 5.813a2 2 0 001.272 1.272L21 12l-5.816 1.912a2 2 0 00-1.272 1.272L12 21l-1.912-5.816a2 2 0 00-1.272-1.272L3 12l5.816-1.912a2 2 0 001.272-1.272z"/></svg> {{ __('Generate') }}';
				}
			});
		});
	</script>
@endpush
