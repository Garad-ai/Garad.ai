@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Invoices'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Create and manage invoices for your clients.'))

@section('titlebar_actions')
	<x-modal title="{{ __('Create Invoice') }}">
		<x-slot:trigger>
			<x-tabler-plus class="size-4" />
			{{ __('Create Invoice') }}
		</x-slot:trigger>

		<x-slot:modal>
			<form
				class="flex flex-col gap-5"
				id="createInvoiceForm"
				onsubmit="return salesCreateInvoice(event)"
			>
				@csrf
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input
						size="lg"
						type="date"
						label="{{ __('Issue Date') }}"
						name="issue_date"
						required
						value="{{ now()->format('Y-m-d') }}"
					/>
					<x-forms.input
						size="lg"
						type="date"
						label="{{ __('Due Date') }}"
						name="due_date"
						value="{{ now()->addDays(30)->format('Y-m-d') }}"
					/>
				</div>
				@php $crmDefaultCurrency = setting('crm_default_currency', 'USD'); @endphp
				<x-forms.input
					size="lg"
					type="select"
					label="{{ __('Currency') }}"
					name="currency"
				>
					<option value="USD" {{ $crmDefaultCurrency === 'USD' ? 'selected' : '' }}>USD</option>
					<option value="EUR" {{ $crmDefaultCurrency === 'EUR' ? 'selected' : '' }}>EUR</option>
					<option value="GBP" {{ $crmDefaultCurrency === 'GBP' ? 'selected' : '' }}>GBP</option>
					<option value="TRY" {{ $crmDefaultCurrency === 'TRY' ? 'selected' : '' }}>TRY</option>
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
				<x-forms.input
					size="lg"
					type="select"
					label="{{ __('Deal') }}"
					name="crm_deal_id"
				>
					<option value="">{{ __('Select a deal') }}</option>
					@foreach ($deals as $deal)
						<option value="{{ $deal->id }}">{{ $deal->title }}</option>
					@endforeach
				</x-forms.input>
				<x-forms.input
					size="lg"
					label="{{ __('Notes') }}"
					name="notes"
					type="textarea"
					rows="2"
					placeholder="{{ __('Additional notes') }}"
				></x-forms.input>
				<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
					<x-button
						@click.prevent="modalOpen = false"
						variant="outline"
						type="button"
					>{{ __('Cancel') }}</x-button>
					<x-button type="submit">{{ __('Create') }}</x-button>
				</div>
			</form>
		</x-slot:modal>
	</x-modal>
@endsection

@section('content')
	<div class="py-10">
		<div class="mb-6">
			@include('crm::partials.stats-bar', ['stats' => $stats])
		</div>

		@php
			$sort_buttons = [
				['label' => __('Date'),     'sort' => 'created_at'],
				['label' => __('Due Date'), 'sort' => 'due_date'],
				['label' => __('Total'),    'sort' => 'total'],
				['label' => __('Status'),   'sort' => 'status'],
			];

			$filter_buttons = [
				['label' => __('All'),       'filter' => 'all'],
				['label' => __('Draft'),     'filter' => 'draft'],
				['label' => __('Sent'),      'filter' => 'sent'],
				['label' => __('Paid'),      'filter' => 'paid'],
				['label' => __('Partial'),   'filter' => 'partial'],
				['label' => __('Overdue'),   'filter' => 'overdue'],
				['label' => __('Cancelled'), 'filter' => 'cancelled'],
			];
		@endphp
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
								href="{{ route('dashboard.user.sales.invoices.index', ['filter' => $filter, 'sort' => $button['sort'], 'sort_dir' => ($sort === $button['sort'] && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
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
						href="{{ route('dashboard.user.sales.invoices.index', ['filter' => $button['filter'], 'sort' => $sort, 'sort_dir' => $sortDir]) }}"
						variant="ghost"
					>
						{{ $button['label'] }}
					</x-button>
				@endforeach
			</div>
		</div>

		{{-- Table --}}
		<x-table>
			<x-slot:head>
				<tr>
					<th>{{ __('Invoice #') }}</th>
					<th>{{ __('Client') }}</th>
					<th>{{ __('Status') }}</th>
					<th>{{ __('Issue Date') }}</th>
					<th>{{ __('Due Date') }}</th>
					<th class="text-end">{{ __('Total') }}</th>
					<th class="text-end">{{ __('Balance') }}</th>
					<th class="text-end">{{ __('Actions') }}</th>
				</tr>
			</x-slot:head>

			<x-slot:body>
				@forelse ($list as $entry)
					<tr class="{{ in_array($entry->status, ['paid', 'cancelled']) ? 'opacity-50' : '' }}">
						<td>
							<a
								class="font-medium text-primary hover:underline"
								href="{{ route('dashboard.user.sales.invoices.show', $entry->id) }}"
							>{{ $entry->invoice_number }}</a>
						</td>
						<td>
							{{ $entry->contact?->full_name ?? $entry->company?->name ?? '-' }}
						</td>
						<td>
							@switch($entry->status)
								@case('paid')
									<x-badge class="bg-green-500/10 text-green-500">{{ __('Paid') }}</x-badge>
									@break
								@case('sent')
									<x-badge class="bg-blue-500/10 text-blue-500">{{ __('Sent') }}</x-badge>
									@break
								@case('overdue')
									<x-badge class="bg-red-500/10 text-red-500">{{ __('Overdue') }}</x-badge>
									@break
								@case('partial')
									<x-badge class="bg-orange-500/10 text-orange-500">{{ __('Partial') }}</x-badge>
									@break
								@case('cancelled')
									<x-badge class="bg-foreground/5 text-foreground/50">{{ __('Cancelled') }}</x-badge>
									@break
								@default
									<x-badge class="bg-yellow-500/10 text-yellow-600">{{ __('Draft') }}</x-badge>
							@endswitch
						</td>
						<td>{{ $entry->issue_date->format('M d, Y') }}</td>
						<td>
							@if ($entry->due_date)
								<span class="{{ $entry->is_overdue ? 'font-semibold text-red-500' : '' }}">
									{{ $entry->due_date->format('M d, Y') }}
								</span>
							@else
								-
							@endif
						</td>
						<td class="text-end font-medium">{{ $entry->currency }} {{ number_format($entry->total, 2) }}</td>
						<td class="text-end">
							@if ($entry->balance_due > 0)
								<span class="font-medium text-red-500">{{ $entry->currency }} {{ number_format($entry->balance_due, 2) }}</span>
							@else
								<span class="text-green-500">{{ __('Paid') }}</span>
							@endif
						</td>
						<td class="whitespace-nowrap text-end">
							<x-button
								class="size-9"
								variant="ghost-shadow"
								size="none"
								href="{{ route('dashboard.user.sales.invoices.show', $entry->id) }}"
								title="{{ __('View') }}"
							>
								<x-tabler-eye class="size-4" />
							</x-button>
							<x-button
								class="size-9"
								variant="ghost-shadow"
								size="none"
								href="{{ route('dashboard.user.sales.invoices.pdf', $entry->id) }}"
								title="{{ __('Download PDF') }}"
							>
								<x-tabler-download class="size-4" />
							</x-button>
							<x-button
								class="size-9"
								variant="ghost-shadow"
								hover-variant="danger"
								size="none"
								onclick="return confirm('{{ __('Are you sure you want to delete this invoice?') }}')"
								href="{{ route('dashboard.user.sales.invoices.delete', $entry->id) }}"
								title="{{ __('Delete') }}"
							>
								<x-tabler-x class="size-4" />
							</x-button>
						</td>
					</tr>
				@empty
					<tr>
						<td
							class="text-center text-foreground/60"
							colspan="8"
						>
							{{ __('No invoices yet. Create your first one!') }}
						</td>
					</tr>
				@endforelse
			</x-slot:body>
		</x-table>
	</div>
@endsection

@push('script')
	<script>
		function salesCreateInvoice(event) {
			event.preventDefault();
			const form = event.target;
			const btn = form.querySelector('button[type="submit"]');
			btn.disabled = true;
			btn.innerHTML = magicai_localize?.please_wait || 'Please wait...';

			$.ajax({
				type: 'POST',
				url: '{{ route("dashboard.user.sales.invoices.store") }}',
				data: new FormData(form),
				contentType: false,
				processData: false,
				success: function(data) {
					toastr.success(data.message || '{{ __("Invoice created.") }}');
					if (data.invoice_id) {
						window.location.href = '{{ route("dashboard.user.sales.invoices.index") }}/' + data.invoice_id;
					} else {
						location.reload();
					}
				},
				error: function(data) {
					var err = data.responseJSON?.errors;
					if (err) {
						$.each(err, function(index, value) { toastr.error(value); });
					} else {
						toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}');
					}
					btn.disabled = false;
					btn.innerHTML = '{{ __("Create") }}';
				}
			});
			return false;
		}
	</script>
@endpush
