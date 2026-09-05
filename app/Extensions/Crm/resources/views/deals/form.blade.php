@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $item->id ? __('Edit Deal') : __('New Deal'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Manage deal details.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		@if ($item->id)
			<x-button
				variant="ghost-shadow"
				id="crm_generate_presentation_btn"
			>
				<x-tabler-presentation class="size-4" />
				{{ __('Generate Presentation') }}
			</x-button>
			<x-button
				variant="ghost-shadow"
				hover-variant="danger"
				id="crm_delete_deal_btn"
			>
				<x-tabler-trash class="size-4" />
				{{ __('Delete') }}
			</x-button>
		@endif
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.deals.index') }}"
		>
			{{ __('Back to Pipeline') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div class="py-10">
		@if ($item->id)
			{{-- Edit mode: 2-column layout with form + timeline --}}
			<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
				<div>
					<x-card class:body="flex flex-col gap-5 p-6">
						<form
							class="flex flex-col gap-5"
							id="crm_deal_form"
						>
							@csrf
							@method($method)

							<x-forms.input
								id="title"
								size="lg"
								label="{{ __('Deal Title') }}"
								name="title"
								required
								value="{{ $item->title }}"
								placeholder="{{ __('Enter deal title') }}"
							/>

							<x-forms.input
								id="crm_deal_stage_id"
								size="lg"
								type="select"
								label="{{ __('Stage') }}"
								name="crm_deal_stage_id"
								required
							>
								@foreach ($stages as $stage)
									<option
										value="{{ $stage->id }}"
										@selected($item->crm_deal_stage_id == $stage->id)
									>
										{{ $stage->name }}
									</option>
								@endforeach
							</x-forms.input>

							<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
								<x-forms.input
									id="value"
									size="lg"
									type="number"
									label="{{ __('Value') }}"
									name="value"
									value="{{ $item->value ?? 0 }}"
									placeholder="0.00"
								/>

								<x-forms.input
									id="currency"
									size="lg"
									label="{{ __('Currency') }}"
									name="currency"
									value="{{ $item->currency ?? 'USD' }}"
									placeholder="USD"
								/>
							</div>

							<x-forms.input
								id="expected_close_date"
								size="lg"
								type="date"
								label="{{ __('Expected Close Date') }}"
								name="expected_close_date"
								value="{{ $item->expected_close_date?->format('Y-m-d') }}"
							/>

							<x-forms.input
								id="crm_contact_id"
								size="lg"
								type="select"
								label="{{ __('Contact') }}"
								name="crm_contact_id"
							>
								<option value="">{{ __('Select a contact') }}</option>
								@foreach ($contacts as $contact)
									<option
										value="{{ $contact->id }}"
										@selected($item->crm_contact_id == $contact->id)
									>
										{{ $contact->full_name }}
									</option>
								@endforeach
							</x-forms.input>

							<x-forms.input
								id="crm_company_id"
								size="lg"
								type="select"
								label="{{ __('Company') }}"
								name="crm_company_id"
							>
								<option value="">{{ __('Select a company') }}</option>
								@foreach ($companies as $company)
									<option
										value="{{ $company->id }}"
										@selected($item->crm_company_id == $company->id)
									>
										{{ $company->name }}
									</option>
								@endforeach
							</x-forms.input>

							<x-forms.input
								id="description"
								size="lg"
								label="{{ __('Description') }}"
								name="description"
								type="textarea"
								rows="3"
								placeholder="{{ __('Deal details and notes') }}"
							>{{ $item->description }}</x-forms.input>

							<x-button
								id="crm_deal_button"
								type="button"
							>
								{{ __('Save') }}
							</x-button>
						</form>
					</x-card>
				</div>

				{{-- Activity Timeline --}}
				<div class="lg:col-span-2">
					@include('crm::partials.activity-timeline', [
						'notes'       => $notes,
						'events'      => $events,
						'notableType' => 'deal',
						'notableId'   => $item->id,
					])
				</div>
			</div>
		@else
			{{-- Create mode: centered single-column form --}}
			<div class="mx-auto w-full lg:w-5/12">
				<x-card class:body="flex flex-col gap-5 p-6">
					<form
						class="flex flex-col gap-5"
						id="crm_deal_form"
					>
						@csrf
						@method($method)

						<x-forms.input
							id="title"
							size="lg"
							label="{{ __('Deal Title') }}"
							name="title"
							required
							value="{{ $item->title }}"
							placeholder="{{ __('Enter deal title') }}"
						/>

						<x-forms.input
							id="crm_deal_stage_id"
							size="lg"
							type="select"
							label="{{ __('Stage') }}"
							name="crm_deal_stage_id"
							required
						>
							@foreach ($stages as $stage)
								<option
									value="{{ $stage->id }}"
									@selected($item->crm_deal_stage_id == $stage->id)
								>
									{{ $stage->name }}
								</option>
							@endforeach
						</x-forms.input>

						<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
							<x-forms.input
								id="value"
								size="lg"
								type="number"
								label="{{ __('Value') }}"
								name="value"
								value="{{ $item->value ?? 0 }}"
								placeholder="0.00"
							/>

							<x-forms.input
								id="currency"
								size="lg"
								label="{{ __('Currency') }}"
								name="currency"
								value="{{ $item->currency ?? 'USD' }}"
								placeholder="USD"
							/>
						</div>

						<x-forms.input
							id="expected_close_date"
							size="lg"
							type="date"
							label="{{ __('Expected Close Date') }}"
							name="expected_close_date"
							value="{{ $item->expected_close_date?->format('Y-m-d') }}"
						/>

						<x-forms.input
							id="crm_contact_id"
							size="lg"
							type="select"
							label="{{ __('Contact') }}"
							name="crm_contact_id"
						>
							<option value="">{{ __('Select a contact') }}</option>
							@foreach ($contacts as $contact)
								<option
									value="{{ $contact->id }}"
									@selected($item->crm_contact_id == $contact->id)
								>
									{{ $contact->full_name }}
								</option>
							@endforeach
						</x-forms.input>

						<x-forms.input
							id="crm_company_id"
							size="lg"
							type="select"
							label="{{ __('Company') }}"
							name="crm_company_id"
						>
							<option value="">{{ __('Select a company') }}</option>
							@foreach ($companies as $company)
								<option
									value="{{ $company->id }}"
									@selected($item->crm_company_id == $company->id)
								>
									{{ $company->name }}
								</option>
							@endforeach
						</x-forms.input>

						<x-forms.input
							id="description"
							size="lg"
							label="{{ __('Description') }}"
							name="description"
							type="textarea"
							rows="3"
							placeholder="{{ __('Deal details and notes') }}"
						>{{ $item->description }}</x-forms.input>

						<x-button
							id="crm_deal_button"
							type="button"
						>
							{{ __('Save') }}
						</x-button>
					</form>
				</x-card>
			</div>
		@endif
	</div>
@endsection

@push('script')
	<script>
		@if ($item->id)
		document.getElementById('crm_delete_deal_btn')?.addEventListener('click', function() {
			if (!confirm('{{ __("Are you sure you want to delete this deal?") }}')) return;

			const btn = this;
			btn.disabled = true;
			btn.innerHTML = '<svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

			$.ajax({
				type: 'GET',
				url: '{{ route("dashboard.user.crm.deals.delete", $item->id) }}',
				success: function(data) {
					toastr.success(data.message || '{{ __("Deal deleted successfully.") }}');
					if (data.redirect) {
						setTimeout(function() { window.location.href = data.redirect; }, 300);
					} else {
						setTimeout(function() { window.location.href = '{{ route("dashboard.user.crm.deals.index") }}'; }, 300);
					}
				},
				error: function(data) {
					toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}');
					btn.disabled = false;
					btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> {{ __("Delete") }}';
				}
			});
		});

		document.getElementById('crm_generate_presentation_btn')?.addEventListener('click', function() {
			const btn = this;
			btn.disabled = true;
			btn.innerHTML = '<svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> {{ __("Generating...") }}';

			$.ajax({
				type: 'POST',
				url: '{{ route("dashboard.user.crm.presentations.store") }}',
				data: {
					_token: '{{ csrf_token() }}',
					crm_deal_id: {{ $item->id }},
					style: 'corporate'
				},
				success: function(data) {
					toastr.success(data.message || '{{ __("Presentation generation started!") }}');
					if (data.redirect) {
						setTimeout(function() { window.location.href = data.redirect; }, 300);
					}
				},
				error: function(data) {
					toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}');
					btn.disabled = false;
					btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-presentation size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4l18 0"/><path d="M4 4v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-10"/><path d="M12 16l0 4"/><path d="M9 20l6 0"/><path d="M8 12l3-3l2 2l3-3"/></svg> {{ __("Generate Presentation") }}';
				}
			});
		});
		@endif

		document.getElementById('crm_deal_button').addEventListener('click', function() {
			const btn = this;
			btn.disabled = true;
			btn.innerHTML = magicai_localize?.please_wait || 'Please wait...';

			const form = document.getElementById('crm_deal_form');
			const formData = new FormData(form);

			$.ajax({
				type: 'POST',
				url: '{{ $action }}',
				data: formData,
				contentType: false,
				processData: false,
				success: function(data) {
					toastr.success(data.message || '{{ __("Saved successfully.") }}');
					btn.disabled = false;
					btn.innerHTML = '{{ __("Save") }}';
					setTimeout(function() {
						window.location.href = '{{ route("dashboard.user.crm.deals.index") }}';
					}, 300);
				},
				error: function(data) {
					var err = data.responseJSON?.errors;
					if (err) {
						$.each(err, function(index, value) {
							toastr.error(value);
						});
					} else {
						toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}');
					}
					btn.disabled = false;
					btn.innerHTML = '{{ __("Save") }}';
				}
			});
		});
	</script>
@endpush
