@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Payments'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Track payments received from clients.'))

@section('titlebar_actions')
	<x-modal title="{{ __('Record Payment') }}">
		<x-slot:trigger>
			<x-tabler-plus class="size-4" />
			{{ __('Record Payment') }}
		</x-slot:trigger>

		<x-slot:modal>
			<form
				class="flex flex-col gap-5"
				onsubmit="return salesSubmitForm(event, '{{ route('dashboard.user.sales.payments.store') }}')"
			>
				@csrf
				<x-forms.input
					size="lg"
					type="select"
					label="{{ __('Invoice') }}"
					name="sales_invoice_id"
				>
					<option value="">{{ __('No linked invoice') }}</option>
					@foreach ($invoices as $invoice)
						<option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} (${{ number_format($invoice->total, 2) }})</option>
					@endforeach
				</x-forms.input>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Contact') }}"
						name="crm_contact_id"
					>
						<option value="">{{ __('Select a contact') }}</option>
						@foreach ($contacts as $contact)
							<option value="{{ $contact->id }}">{{ $contact->full_name }}</option>
						@endforeach
					</x-forms.input>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Company') }}"
						name="crm_company_id"
					>
						<option value="">{{ __('Select a company') }}</option>
						@foreach ($companies as $company)
							<option value="{{ $company->id }}">{{ $company->name }}</option>
						@endforeach
					</x-forms.input>
				</div>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input
						size="lg"
						type="number"
						label="{{ __('Amount') }}"
						name="amount"
						required
						step="0.01"
						min="0"
						placeholder="0.00"
					/>
					<x-forms.input
						size="lg"
						type="date"
						label="{{ __('Payment Date') }}"
						name="payment_date"
						required
						value="{{ date('Y-m-d') }}"
					/>
				</div>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Method') }}"
						name="payment_method"
					>
						<option value="bank_transfer">{{ __('Bank Transfer') }}</option>
						<option value="credit_card">{{ __('Credit Card') }}</option>
						<option value="cash">{{ __('Cash') }}</option>
						<option value="check">{{ __('Check') }}</option>
						<option value="other">{{ __('Other') }}</option>
					</x-forms.input>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Status') }}"
						name="status"
					>
						<option value="completed">{{ __('Completed') }}</option>
						<option value="pending">{{ __('Pending') }}</option>
						<option value="refunded">{{ __('Refunded') }}</option>
					</x-forms.input>
				</div>
				<x-forms.input
					size="lg"
					label="{{ __('Reference') }}"
					name="reference"
					placeholder="{{ __('Transaction reference or ID') }}"
				/>
				<x-forms.input
					size="lg"
					label="{{ __('Notes') }}"
					name="notes"
					type="textarea"
					rows="2"
					placeholder="{{ __('Additional notes') }}"
				></x-forms.input>
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
		['label' => __('Date'),   'sort' => 'payment_date'],
		['label' => __('Amount'), 'sort' => 'amount'],
		['label' => __('Status'), 'sort' => 'status'],
	];

	$filter_buttons = [
		['label' => __('All'),       'filter' => 'all'],
		['label' => __('Completed'), 'filter' => 'completed'],
		['label' => __('Pending'),   'filter' => 'pending'],
		['label' => __('Refunded'),  'filter' => 'refunded'],
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
								href="{{ route('dashboard.user.sales.payments.index', ['filter' => $filter, 'sort' => $button['sort'], 'sort_dir' => ($sort === $button['sort'] && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
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
						href="{{ route('dashboard.user.sales.payments.index', ['filter' => $button['filter'], 'sort' => $sort, 'sort_dir' => $sortDir]) }}"
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
					<th>{{ __('Invoice') }}</th>
					<th>{{ __('Contact / Company') }}</th>
					<th class="text-end">{{ __('Amount') }}</th>
					<th>{{ __('Date') }}</th>
					<th>{{ __('Method') }}</th>
					<th>{{ __('Status') }}</th>
					<th class="text-end">{{ __('Actions') }}</th>
				</tr>
			</x-slot:head>

			<x-slot:body>
				@forelse ($list as $entry)
					<tr>
						<td>
							@if ($entry->invoice)
								<a class="text-primary hover:underline" href="{{ route('dashboard.user.sales.invoices.show', $entry->invoice->id) }}">{{ $entry->invoice->invoice_number }}</a>
							@else
								-
							@endif
						</td>
						<td>{{ $entry->contact?->full_name ?? $entry->company?->name ?? '-' }}</td>
						<td class="text-end font-medium">${{ number_format($entry->amount, 2) }}</td>
						<td>{{ $entry->payment_date->format('M d, Y') }}</td>
						<td>
							<x-badge class="bg-foreground/5 text-foreground/70 capitalize">
								{{ str_replace('_', ' ', $entry->payment_method) }}
							</x-badge>
						</td>
						<td>
							@switch($entry->status)
								@case('completed')
									<x-badge class="bg-green-500/10 text-green-500">{{ __('Completed') }}</x-badge>
									@break
								@case('pending')
									<x-badge class="bg-yellow-500/10 text-yellow-600">{{ __('Pending') }}</x-badge>
									@break
								@case('refunded')
									<x-badge class="bg-red-500/10 text-red-500">{{ __('Refunded') }}</x-badge>
									@break
							@endswitch
						</td>
						<td class="whitespace-nowrap text-end">
							<x-modal class="inline-flex" title="{{ __('Edit Payment') }}">
								<x-slot:trigger class="size-9" size="none" variant="ghost-shadow" title="{{ __('Edit') }}">
									<x-tabler-pencil class="size-4" />
								</x-slot:trigger>

								<x-slot:modal>
									<form
										class="flex flex-col gap-5"
										onsubmit="return salesSubmitForm(event, '{{ route('dashboard.user.sales.payments.update', $entry->id) }}')"
									>
										@csrf
										@method('PUT')
										<x-forms.input size="lg" type="select" label="{{ __('Invoice') }}" name="sales_invoice_id">
											<option value="">{{ __('No linked invoice') }}</option>
											@foreach ($invoices as $invoice)
												<option value="{{ $invoice->id }}" @selected($entry->sales_invoice_id == $invoice->id)>{{ $invoice->invoice_number }} (${{ number_format($invoice->total, 2) }})</option>
											@endforeach
										</x-forms.input>
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
											<x-forms.input size="lg" type="number" label="{{ __('Amount') }}" name="amount" required step="0.01" min="0" value="{{ $entry->amount }}" />
											<x-forms.input size="lg" type="date" label="{{ __('Payment Date') }}" name="payment_date" required value="{{ $entry->payment_date->format('Y-m-d') }}" />
										</div>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input size="lg" type="select" label="{{ __('Method') }}" name="payment_method">
												<option value="bank_transfer" @selected($entry->payment_method === 'bank_transfer')>{{ __('Bank Transfer') }}</option>
												<option value="credit_card" @selected($entry->payment_method === 'credit_card')>{{ __('Credit Card') }}</option>
												<option value="cash" @selected($entry->payment_method === 'cash')>{{ __('Cash') }}</option>
												<option value="check" @selected($entry->payment_method === 'check')>{{ __('Check') }}</option>
												<option value="other" @selected($entry->payment_method === 'other')>{{ __('Other') }}</option>
											</x-forms.input>
											<x-forms.input size="lg" type="select" label="{{ __('Status') }}" name="status">
												<option value="completed" @selected($entry->status === 'completed')>{{ __('Completed') }}</option>
												<option value="pending" @selected($entry->status === 'pending')>{{ __('Pending') }}</option>
												<option value="refunded" @selected($entry->status === 'refunded')>{{ __('Refunded') }}</option>
											</x-forms.input>
										</div>
										<x-forms.input size="lg" label="{{ __('Reference') }}" name="reference" value="{{ $entry->reference }}" />
										<x-forms.input size="lg" label="{{ __('Notes') }}" name="notes" type="textarea" rows="2">{{ $entry->notes }}</x-forms.input>
										<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
											<x-button @click.prevent="modalOpen = false" variant="outline" type="button">{{ __('Cancel') }}</x-button>
											<x-button type="submit">{{ __('Save') }}</x-button>
										</div>
									</form>
								</x-slot:modal>
							</x-modal>
							<x-button
								class="size-9"
								variant="ghost-shadow"
								hover-variant="danger"
								size="none"
								onclick="return confirm('{{ __('Are you sure you want to delete this payment?') }}')"
								href="{{ route('dashboard.user.sales.payments.delete', $entry->id) }}"
								title="{{ __('Delete') }}"
							>
								<x-tabler-x class="size-4" />
							</x-button>
						</td>
					</tr>
				@empty
					<tr>
						<td class="text-center text-foreground/60" colspan="7">
							{{ __('No payments yet. Record your first one!') }}
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
