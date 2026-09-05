@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Estimates'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Create estimates and quotes for potential clients.'))

@section('titlebar_actions')
	<x-modal title="{{ __('Create Estimate') }}">
		<x-slot:trigger>
			<x-tabler-plus class="size-4" />
			{{ __('Create Estimate') }}
		</x-slot:trigger>

		<x-slot:modal>
			<form
				class="flex flex-col gap-5"
				onsubmit="return salesSubmitForm(event, '{{ route('dashboard.user.sales.estimates.store') }}')"
			>
				@csrf
				<x-forms.input size="lg" label="{{ __('Title') }}" name="title" required placeholder="{{ __('Estimate title') }}" />
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input size="lg" type="select" label="{{ __('Contact') }}" name="crm_contact_id">
						<option value="">{{ __('Select a contact') }}</option>
						@foreach ($contacts as $contact)
							<option value="{{ $contact->id }}">{{ $contact->full_name }}</option>
						@endforeach
					</x-forms.input>
					<x-forms.input size="lg" type="select" label="{{ __('Company') }}" name="crm_company_id">
						<option value="">{{ __('Select a company') }}</option>
						@foreach ($companies as $company)
							<option value="{{ $company->id }}">{{ $company->name }}</option>
						@endforeach
					</x-forms.input>
				</div>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input size="lg" type="date" label="{{ __('Issue Date') }}" name="issue_date" required value="{{ date('Y-m-d') }}" />
					<x-forms.input size="lg" type="date" label="{{ __('Valid Until') }}" name="valid_until" />
				</div>
				<x-forms.input size="lg" type="select" label="{{ __('Status') }}" name="status">
					<option value="draft">{{ __('Draft') }}</option>
					<option value="sent">{{ __('Sent') }}</option>
					<option value="accepted">{{ __('Accepted') }}</option>
					<option value="rejected">{{ __('Rejected') }}</option>
					<option value="expired">{{ __('Expired') }}</option>
				</x-forms.input>
				<x-forms.input size="lg" label="{{ __('Notes') }}" name="notes" type="textarea" rows="2" placeholder="{{ __('Additional notes') }}"></x-forms.input>
				<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
					<x-button @click.prevent="modalOpen = false" variant="outline" type="button">{{ __('Cancel') }}</x-button>
					<x-button type="submit">{{ __('Save') }}</x-button>
				</div>
			</form>
		</x-slot:modal>
	</x-modal>
@endsection

@php
	$sort_buttons = [
		['label' => __('Date'),        'sort' => 'created_at'],
		['label' => __('Title'),       'sort' => 'title'],
		['label' => __('Status'),      'sort' => 'status'],
		['label' => __('Valid Until'), 'sort' => 'valid_until'],
		['label' => __('Total'),       'sort' => 'total'],
	];

	$filter_buttons = [
		['label' => __('All'),      'filter' => 'all'],
		['label' => __('Draft'),    'filter' => 'draft'],
		['label' => __('Sent'),     'filter' => 'sent'],
		['label' => __('Accepted'), 'filter' => 'accepted'],
		['label' => __('Rejected'), 'filter' => 'rejected'],
		['label' => __('Expired'),  'filter' => 'expired'],
	];
@endphp

@section('content')
	<div class="py-10">
		<div class="mb-6 flex flex-wrap items-center gap-3">
			<x-dropdown.dropdown class="pe-3" offsetY="1rem">
				<x-slot:trigger class="whitespace-nowrap py-1.5" variant="link" size="xs">
					{{ __('Sort by:') }}
					<x-tabler-arrows-sort class="size-4" />
				</x-slot:trigger>

				<x-slot:dropdown class="overflow-hidden text-2xs font-medium">
					<div class="flex flex-col">
						@foreach ($sort_buttons as $button)
							<a
								@class([
									'flex w-full items-center gap-1 px-3 py-2 hover:bg-foreground/5',
									'bg-foreground/5' => $sort === $button['sort'],
								])
								href="{{ route('dashboard.user.sales.estimates.index', ['filter' => $filter, 'sort' => $button['sort'], 'sort_dir' => ($sort === $button['sort'] && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
							>
								{{ $button['label'] }}
								@if ($sort === $button['sort'])
									<x-tabler-caret-down-filled @class([
										'size-3 opacity-80 transition-all',
										'rotate-180' => $sortDir === 'asc',
									]) />
								@endif
							</a>
						@endforeach
					</div>
				</x-slot:dropdown>
			</x-dropdown.dropdown>

			<div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-heading-foreground max-sm:gap-3">
				@foreach ($filter_buttons as $button)
					<x-button
						@class([
							'inline-flex px-2.5 py-0.5 transition-colors hover:bg-foreground/5 hover:translate-y-0 text-2xs leading-tight',
							'bg-foreground/5' => $filter === $button['filter'],
						])
						tag="a"
						href="{{ route('dashboard.user.sales.estimates.index', ['filter' => $button['filter'], 'sort' => $sort, 'sort_dir' => $sortDir]) }}"
						variant="ghost"
					>
						{{ $button['label'] }}
					</x-button>
				@endforeach
			</div>
		</div>

		<x-table>
			<x-slot:head>
				<tr>
					<th>{{ __('Estimate #') }}</th>
					<th>{{ __('Title') }}</th>
					<th>{{ __('Contact / Company') }}</th>
					<th>{{ __('Status') }}</th>
					<th>{{ __('Valid Until') }}</th>
					<th class="text-end">{{ __('Total') }}</th>
					<th class="text-end">{{ __('Actions') }}</th>
				</tr>
			</x-slot:head>

			<x-slot:body>
				@forelse ($list as $entry)
					<tr>
						<td>
							<a class="font-medium text-primary hover:underline" href="{{ route('dashboard.user.sales.estimates.show', $entry->id) }}">{{ $entry->estimate_number }}</a>
						</td>
						<td class="font-medium">{{ $entry->title }}</td>
						<td>{{ $entry->contact?->full_name ?? $entry->company?->name ?? '-' }}</td>
						<td>
							@switch($entry->status)
								@case('accepted')
									<x-badge class="bg-green-500/10 text-green-500">{{ __('Accepted') }}</x-badge>
									@break
								@case('sent')
									<x-badge class="bg-blue-500/10 text-blue-500">{{ __('Sent') }}</x-badge>
									@break
								@case('rejected')
									<x-badge class="bg-red-500/10 text-red-500">{{ __('Rejected') }}</x-badge>
									@break
								@case('expired')
									<x-badge class="bg-foreground/5 text-foreground/50">{{ __('Expired') }}</x-badge>
									@break
								@default
									<x-badge class="bg-yellow-500/10 text-yellow-600">{{ __('Draft') }}</x-badge>
							@endswitch
						</td>
						<td>{{ $entry->valid_until?->format('M d, Y') ?? '-' }}</td>
						<td class="text-end font-medium">${{ number_format($entry->total, 2) }}</td>
						<td class="whitespace-nowrap text-end">
							<x-modal class="inline-flex" title="{{ __('Edit Estimate') }} - {{ $entry->estimate_number }}">
								<x-slot:trigger class="size-9" size="none" variant="ghost-shadow" title="{{ __('Edit') }}">
									<x-tabler-pencil class="size-4" />
								</x-slot:trigger>

								<x-slot:modal>
									<form class="flex flex-col gap-5" onsubmit="return salesSubmitForm(event, '{{ route('dashboard.user.sales.estimates.update', $entry->id) }}')">
										@csrf
										@method('PUT')
										<x-forms.input size="lg" label="{{ __('Title') }}" name="title" required value="{{ $entry->title }}" />
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input size="lg" type="select" label="{{ __('Contact') }}" name="crm_contact_id">
												<option value="">{{ __('Select a contact') }}</option>
												@foreach ($contacts as $contact)
													<option value="{{ $contact->id }}" @selected($entry->crm_contact_id == $contact->id)>{{ $contact->full_name }}</option>
												@endforeach
											</x-forms.input>
											<x-forms.input size="lg" type="select" label="{{ __('Company') }}" name="crm_company_id">
												<option value="">{{ __('Select a company') }}</option>
												@foreach ($companies as $company)
													<option value="{{ $company->id }}" @selected($entry->crm_company_id == $company->id)>{{ $company->name }}</option>
												@endforeach
											</x-forms.input>
										</div>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input size="lg" type="date" label="{{ __('Issue Date') }}" name="issue_date" required value="{{ $entry->issue_date->format('Y-m-d') }}" />
											<x-forms.input size="lg" type="date" label="{{ __('Valid Until') }}" name="valid_until" value="{{ $entry->valid_until?->format('Y-m-d') }}" />
										</div>
										<x-forms.input size="lg" type="select" label="{{ __('Status') }}" name="status">
											<option value="draft" @selected($entry->status === 'draft')>{{ __('Draft') }}</option>
											<option value="sent" @selected($entry->status === 'sent')>{{ __('Sent') }}</option>
											<option value="accepted" @selected($entry->status === 'accepted')>{{ __('Accepted') }}</option>
											<option value="rejected" @selected($entry->status === 'rejected')>{{ __('Rejected') }}</option>
											<option value="expired" @selected($entry->status === 'expired')>{{ __('Expired') }}</option>
										</x-forms.input>
										<x-forms.input size="lg" label="{{ __('Notes') }}" name="notes" type="textarea" rows="2">{{ $entry->notes }}</x-forms.input>
										<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
											<x-button @click.prevent="modalOpen = false" variant="outline" type="button">{{ __('Cancel') }}</x-button>
											<x-button type="submit">{{ __('Save') }}</x-button>
										</div>
									</form>
								</x-slot:modal>
							</x-modal>
							<x-button class="size-9" variant="ghost-shadow" hover-variant="danger" size="none" onclick="return confirm('{{ __('Are you sure you want to delete this estimate?') }}')" href="{{ route('dashboard.user.sales.estimates.delete', $entry->id) }}" title="{{ __('Delete') }}">
								<x-tabler-x class="size-4" />
							</x-button>
						</td>
					</tr>
				@empty
					<tr>
						<td class="text-center text-foreground/60" colspan="7">
							{{ __('No estimates yet. Create your first one!') }}
						</td>
					</tr>
				@endforelse
			</x-slot:body>
		</x-table>
	</div>
@endsection

@push('script')
	<script>
		function salesSubmitForm(event, url) {
			event.preventDefault();
			const form = event.target;
			const btn = form.querySelector('button[type="submit"]');
			btn.disabled = true;
			btn.innerHTML = magicai_localize?.please_wait || 'Please wait...';

			$.ajax({
				type: 'POST',
				url: url,
				data: new FormData(form),
				contentType: false,
				processData: false,
				success: function(data) {
					toastr.success(data.message || '{{ __('Saved successfully.') }}');
					location.reload();
				},
				error: function(data) {
					var err = data.responseJSON?.errors;
					if (err) {
						$.each(err, function(index, value) { toastr.error(value); });
					} else {
						toastr.error(data.responseJSON?.message || '{{ __('An error occurred.') }}');
					}
					btn.disabled = false;
					btn.innerHTML = '{{ __('Save') }}';
				}
			});
			return false;
		}
	</script>
@endpush
