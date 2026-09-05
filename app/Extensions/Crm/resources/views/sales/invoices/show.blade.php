@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $item->invoice_number)
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Invoice details, line items and payments.'))

@section('titlebar_actions')
	<div class="flex flex-wrap gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.sales.invoices.index') }}"
		>
			<x-tabler-arrow-left class="size-4" />
			{{ __('Back') }}
		</x-button>

		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.sales.invoices.pdf', $item->id) }}"
		>
			<x-tabler-download class="size-4" />
			{{ __('Download PDF') }}
		</x-button>

		{{-- Status Dropdown --}}
		<div
			class="relative"
			x-data="{ open: false }"
		>
			<x-button
				variant="ghost-shadow"
				@click="open = !open"
			>
				<x-tabler-toggle-left class="size-4" />
				{{ __('Status') }}
				<x-tabler-chevron-down class="size-3" />
			</x-button>
			<div
				class="absolute right-0 z-10 mt-1 w-40 rounded-lg border bg-background p-1 shadow-lg dark:border-white/10"
				x-show="open"
				x-cloak
				@click.outside="open = false"
			>
				@foreach (['draft', 'sent', 'paid', 'partial', 'overdue', 'cancelled'] as $st)
					<button
						class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-foreground/5 {{ $item->status === $st ? 'font-semibold text-primary' : '' }}"
						type="button"
						onclick="updateInvoiceStatus('{{ $st }}')"
					>
						@switch($st)
							@case('draft')
								<span class="size-2 rounded-full bg-gray-400"></span>
								@break
							@case('sent')
								<span class="size-2 rounded-full bg-blue-500"></span>
								@break
							@case('paid')
								<span class="size-2 rounded-full bg-green-500"></span>
								@break
							@case('partial')
								<span class="size-2 rounded-full bg-orange-500"></span>
								@break
							@case('overdue')
								<span class="size-2 rounded-full bg-red-500"></span>
								@break
							@case('cancelled')
								<span class="size-2 rounded-full bg-yellow-500"></span>
								@break
						@endswitch
						{{ __(ucfirst($st)) }}
					</button>
				@endforeach
			</div>
		</div>

		{{-- Edit Modal --}}
		<x-modal title="{{ __('Edit Invoice') }}">
			<x-slot:trigger variant="primary">
				<x-tabler-pencil class="size-4" />
				{{ __('Edit') }}
			</x-slot:trigger>

			<x-slot:modal>
				<form
					class="flex flex-col gap-5"
					onsubmit="return salesSubmitForm(event, '{{ route('dashboard.user.sales.invoices.update', $item->id) }}')"
				>
					@csrf
					@method('PUT')
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="date"
							label="{{ __('Issue Date') }}"
							name="issue_date"
							required
							value="{{ $item->issue_date->format('Y-m-d') }}"
						/>
						<x-forms.input
							size="lg"
							type="date"
							label="{{ __('Due Date') }}"
							name="due_date"
							value="{{ $item->due_date?->format('Y-m-d') }}"
						/>
					</div>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Currency') }}"
						name="currency"
					>
						<option value="USD" @selected($item->currency === 'USD')>USD</option>
						<option value="EUR" @selected($item->currency === 'EUR')>EUR</option>
						<option value="GBP" @selected($item->currency === 'GBP')>GBP</option>
						<option value="TRY" @selected($item->currency === 'TRY')>TRY</option>
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
								<option value="{{ $contact->id }}" @selected($item->crm_contact_id == $contact->id)>{{ $contact->full_name }}</option>
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
								<option value="{{ $company->id }}" @selected($item->crm_company_id == $company->id)>{{ $company->name }}</option>
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
							<option value="{{ $deal->id }}" @selected($item->crm_deal_id == $deal->id)>{{ $deal->title }}</option>
						@endforeach
					</x-forms.input>
					<hr class="dark:border-white/5">
					<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('From (Your Details)') }}</p>
					<x-forms.input size="lg" label="{{ __('Business Name') }}" name="from_name" value="{{ $item->from_name }}" />
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input size="lg" type="email" label="{{ __('Email') }}" name="from_email" value="{{ $item->from_email }}" />
						<x-forms.input size="lg" label="{{ __('Phone') }}" name="from_phone" value="{{ $item->from_phone }}" />
					</div>
					<x-forms.input size="lg" label="{{ __('Address') }}" name="from_address" type="textarea" rows="2">{{ $item->from_address }}</x-forms.input>
					<hr class="dark:border-white/5">
					<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Discount & Tax') }}</p>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input size="lg" type="select" label="{{ __('Discount Type') }}" name="discount_type">
							<option value="fixed" @selected($item->discount_type === 'fixed')>{{ __('Fixed Amount') }}</option>
							<option value="percentage" @selected($item->discount_type === 'percentage')>{{ __('Percentage') }}</option>
						</x-forms.input>
						<x-forms.input size="lg" type="number" label="{{ __('Discount Value') }}" name="discount_value" step="0.01" min="0" value="{{ $item->discount_value }}" />
					</div>
					<x-forms.input size="lg" type="number" label="{{ __('Tax Rate (%)') }}" name="tax_rate" step="0.01" min="0" max="100" value="{{ $item->tax_rate }}" />
					<x-forms.input size="lg" label="{{ __('Notes') }}" name="notes" type="textarea" rows="2">{{ $item->notes }}</x-forms.input>
					<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
						<x-button @click.prevent="modalOpen = false" variant="outline" type="button">{{ __('Cancel') }}</x-button>
						<x-button type="submit">{{ __('Save') }}</x-button>
					</div>
				</form>
			</x-slot:modal>
		</x-modal>
		<x-button
			variant="ghost-shadow"
			hover-variant="danger"
			id="crm_delete_invoice_btn"
		>
			<x-tabler-trash class="size-4" />
			{{ __('Delete') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div
		class="py-10"
		x-data="invoiceManager()"
	>
		{{-- Summary Cards --}}
		<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Status') }}</p>
				<div>
					@switch($item->status)
						@case('draft')
							<x-badge class="bg-yellow-500/10 text-yellow-600">{{ __('Draft') }}</x-badge>
							@break
						@case('sent')
							<x-badge class="bg-blue-500/10 text-blue-500">{{ __('Sent') }}</x-badge>
							@break
						@case('paid')
							<x-badge class="bg-green-500/10 text-green-500">{{ __('Paid') }}</x-badge>
							@break
						@case('overdue')
							<x-badge class="bg-red-500/10 text-red-500">{{ __('Overdue') }}</x-badge>
							@break
						@case('cancelled')
							<x-badge class="bg-foreground/5 text-foreground/50">{{ __('Cancelled') }}</x-badge>
							@break
						@case('partial')
							<x-badge class="bg-orange-500/10 text-orange-500">{{ __('Partial') }}</x-badge>
							@break
						@default
							<x-badge class="bg-gray-500/10 text-gray-500">{{ ucfirst($item->status) }}</x-badge>
					@endswitch
				</div>
			</x-card>

			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Due Date') }}</p>
				@if ($item->due_date)
					<p class="text-lg font-semibold {{ $item->is_overdue ? 'text-red-500' : '' }}">{{ $item->due_date->format('M d, Y') }}</p>
					@if ($item->is_overdue)
						<p class="text-xs font-semibold text-red-500">{{ abs((int) now()->diffInDays($item->due_date, false)) }} {{ __('days overdue') }}</p>
					@endif
				@else
					<p class="text-lg font-semibold text-foreground/30">{{ __('Not set') }}</p>
				@endif
			</x-card>

			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Total') }}</p>
				<p class="text-lg font-semibold" x-text="'{{ $item->currency }} ' + inv.total.toFixed(2)">{{ $item->currency }} {{ number_format($item->total, 2) }}</p>
			</x-card>

			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Balance Due') }}</p>
				<p
					class="text-lg font-semibold"
					:class="inv.balance_due > 0 ? 'text-red-500' : 'text-green-500'"
					x-text="inv.balance_due > 0 ? '{{ $item->currency }} ' + inv.balance_due.toFixed(2) : '{{ __('Paid') }}'"
				></p>
			</x-card>
		</div>

		<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
			{{-- LEFT: Invoice Document --}}
			<div class="lg:col-span-2">
				<x-card class:body="p-0">
					{{-- Header --}}
					<div class="border-b p-6 dark:border-white/5">
						<div class="flex flex-col justify-between gap-4 sm:flex-row">
							<div>
								@if ($item->from_name)
									<p class="text-lg font-bold">{{ $item->from_name }}</p>
								@endif
								@if ($item->from_email)
									<p class="text-sm text-foreground/60">{{ $item->from_email }}</p>
								@endif
								@if ($item->from_phone)
									<p class="text-sm text-foreground/60">{{ $item->from_phone }}</p>
								@endif
								@if ($item->from_address)
									<p class="mt-1 whitespace-pre-line text-sm text-foreground/60">{{ $item->from_address }}</p>
								@endif
							</div>
							<div class="text-right">
								<p class="text-2xl font-bold text-primary">{{ $item->invoice_number }}</p>
								<p class="text-sm text-foreground/60">{{ __('Issue Date') }}: {{ $item->issue_date->format('M d, Y') }}</p>
								@if ($item->due_date)
									<p class="text-sm text-foreground/60">{{ __('Due Date') }}: {{ $item->due_date->format('M d, Y') }}</p>
								@endif
							</div>
						</div>
					</div>

					{{-- Bill To --}}
					@if ($item->company || $item->contact)
						<div class="border-b p-6 dark:border-white/5">
							<p class="mb-1 text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Bill To') }}</p>
							@if ($item->company)
								<p class="font-semibold">{{ $item->company->name }}</p>
								@if ($item->company->email)
									<p class="text-sm text-foreground/60">{{ $item->company->email }}</p>
								@endif
							@endif
							@if ($item->contact)
								<p class="{{ $item->company ? 'mt-2' : '' }} font-semibold">{{ $item->contact->full_name }}</p>
								@if ($item->contact->email)
									<p class="text-sm text-foreground/60">{{ $item->contact->email }}</p>
								@endif
							@endif
						</div>
					@endif

					{{-- Line Items --}}
					<div class="p-6">
						<div class="mb-4 flex items-center justify-between">
							<h3 class="font-semibold">{{ __('Line Items') }}</h3>
							<button
								class="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
								type="button"
								@click="showAddItem = !showAddItem"
							>
								<x-tabler-plus class="size-3" />
								{{ __('Add Item') }}
							</button>
						</div>

						<div class="overflow-x-auto">
							<table class="w-full text-sm">
								<thead>
									<tr class="border-b text-left text-xs font-medium uppercase tracking-wider text-foreground/50 dark:border-white/5">
										<th class="pb-3 pe-4">{{ __('Item') }}</th>
										<th class="pb-3 pe-4 text-right">{{ __('Qty') }}</th>
										<th class="pb-3 pe-4">{{ __('Unit') }}</th>
										<th class="pb-3 pe-4 text-right">{{ __('Rate') }}</th>
										<th class="pb-3 pe-4 text-right">{{ __('Total') }}</th>
										<th class="pb-3 text-right">{{ __('Actions') }}</th>
									</tr>
								</thead>
								<tbody>
									<template x-for="(itm, idx) in inv.items" :key="itm.id">
										<tr class="border-b dark:border-white/5">
											<template x-if="editingItem !== itm.id">
												<td class="py-3 pe-4" colspan="6">
													<div class="flex items-center justify-between">
														<div class="flex flex-1 items-center gap-6">
															<span class="min-w-[120px] flex-1" x-text="itm.description"></span>
															<span class="w-16 text-right text-foreground/60" x-text="itm.quantity"></span>
															<span class="w-16 text-foreground/60" x-text="itm.unit || '-'"></span>
															<span class="w-24 text-right text-foreground/60" x-text="itm.unit_price.toFixed(2)"></span>
															<span class="w-24 text-right font-medium" x-text="itm.total.toFixed(2)"></span>
														</div>
														<div class="ms-4 flex shrink-0 gap-1">
															<button class="rounded p-1 text-foreground/40 hover:bg-foreground/5 hover:text-foreground" type="button" @click="startEditItem(itm)" title="{{ __('Edit') }}">
																<x-tabler-pencil class="size-3.5" />
															</button>
															<button class="rounded p-1 text-foreground/40 hover:bg-red-500/10 hover:text-red-500" type="button" @click="deleteItem(itm.id)" title="{{ __('Delete') }}">
																<x-tabler-trash class="size-3.5" />
															</button>
														</div>
													</div>
												</td>
											</template>
											<template x-if="editingItem === itm.id">
												<td class="py-3 pe-4" colspan="6">
													<form class="flex items-end gap-2" @submit.prevent="saveEditItem(itm.id)">
														<input class="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm dark:border-white/10 dark:bg-white/[3%]" type="text" x-model="editForm.description" required>
														<input class="w-20 rounded-lg border px-3 py-2 text-right text-sm dark:border-white/10 dark:bg-white/[3%]" type="number" x-model="editForm.quantity" step="0.01" min="0.01" required>
														<input class="w-20 rounded-lg border px-3 py-2 text-sm dark:border-white/10 dark:bg-white/[3%]" type="text" x-model="editForm.unit">
														<input class="w-24 rounded-lg border px-3 py-2 text-right text-sm dark:border-white/10 dark:bg-white/[3%]" type="number" x-model="editForm.unit_price" step="0.01" min="0" required>
														<button class="rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" type="submit"><x-tabler-check class="size-4" /></button>
														<button class="rounded-lg border px-3 py-2 text-sm dark:border-white/10" type="button" @click="editingItem = null"><x-tabler-x class="size-4" /></button>
													</form>
												</td>
											</template>
										</tr>
									</template>

									<tr x-show="showAddItem" x-cloak>
										<td class="py-3 pe-4" colspan="6">
											<form class="flex items-end gap-2" @submit.prevent="addItem()">
												<input class="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm dark:border-white/10 dark:bg-white/[3%]" type="text" x-model="newItem.description" placeholder="{{ __('Description') }}" required>
												<input class="w-20 rounded-lg border px-3 py-2 text-right text-sm dark:border-white/10 dark:bg-white/[3%]" type="number" x-model="newItem.quantity" step="0.01" min="0.01" placeholder="1" required>
												<input class="w-20 rounded-lg border px-3 py-2 text-sm dark:border-white/10 dark:bg-white/[3%]" type="text" x-model="newItem.unit" placeholder="{{ __('Unit') }}">
												<input class="w-24 rounded-lg border px-3 py-2 text-right text-sm dark:border-white/10 dark:bg-white/[3%]" type="number" x-model="newItem.unit_price" step="0.01" min="0" placeholder="0.00" required>
												<button class="rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" type="submit"><x-tabler-plus class="size-4" /></button>
												<button class="rounded-lg border px-3 py-2 text-sm dark:border-white/10" type="button" @click="showAddItem = false"><x-tabler-x class="size-4" /></button>
											</form>
										</td>
									</tr>

									<tr x-show="inv.items.length === 0 && !showAddItem">
										<td class="py-6 text-center text-foreground/60" colspan="6">{{ __('No items yet. Click "Add Item" to begin.') }}</td>
									</tr>
								</tbody>
							</table>
						</div>

						{{-- Totals --}}
						<div class="mt-6 flex justify-end">
							<div class="w-full max-w-xs space-y-2 text-sm">
								<div class="flex justify-between">
									<span class="text-foreground/60">{{ __('Subtotal') }}</span>
									<span x-text="'{{ $item->currency }} ' + inv.subtotal.toFixed(2)"></span>
								</div>
								<div class="flex justify-between" x-show="inv.discount_value > 0">
									<span class="text-foreground/60">
										{{ __('Discount') }}
										<span x-show="inv.discount_type === 'percentage'" x-text="'(' + inv.discount_value + '%)'"></span>
									</span>
									<span class="text-red-500" x-text="'- {{ $item->currency }} ' + discountAmount().toFixed(2)"></span>
								</div>
								<div class="flex justify-between" x-show="inv.tax_rate > 0">
									<span class="text-foreground/60">{{ __('Tax') }} (<span x-text="inv.tax_rate + '%'"></span>)</span>
									<span x-text="'{{ $item->currency }} ' + inv.tax_amount.toFixed(2)"></span>
								</div>
								<div class="flex justify-between border-t pt-2 text-base font-bold dark:border-white/5">
									<span>{{ __('Total') }}</span>
									<span x-text="'{{ $item->currency }} ' + inv.total.toFixed(2)"></span>
								</div>
								<div class="flex justify-between text-green-600" x-show="inv.amount_paid > 0">
									<span>{{ __('Paid') }}</span>
									<span x-text="'- {{ $item->currency }} ' + inv.amount_paid.toFixed(2)"></span>
								</div>
								<div class="flex justify-between border-t pt-2 font-bold dark:border-white/5" x-show="inv.amount_paid > 0" :class="inv.balance_due > 0 ? 'text-red-500' : 'text-green-500'">
									<span>{{ __('Balance Due') }}</span>
									<span x-text="'{{ $item->currency }} ' + inv.balance_due.toFixed(2)"></span>
								</div>
							</div>
						</div>
					</div>

					@if ($item->notes)
						<div class="border-t p-6 dark:border-white/5">
							<p class="mb-1 text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Notes') }}</p>
							<p class="whitespace-pre-line text-sm text-foreground/70">{{ $item->notes }}</p>
						</div>
					@endif
				</x-card>
			</div>

			{{-- RIGHT: Sidebar --}}
			<div class="flex flex-col gap-6">
				{{-- Invoice Info --}}
				<x-card class:body="flex flex-col gap-3 p-5">
					<h3 class="mb-2 text-lg font-semibold">{{ __('Invoice Info') }}</h3>
					<div>
						<p class="text-xs text-foreground/60">{{ __('Invoice Number') }}</p>
						<p class="font-medium">{{ $item->invoice_number }}</p>
					</div>
					<div>
						<p class="text-xs text-foreground/60">{{ __('Issue Date') }}</p>
						<p class="font-medium">{{ $item->issue_date->format('M d, Y') }}</p>
					</div>
					@if ($item->due_date)
						<div>
							<p class="text-xs text-foreground/60">{{ __('Due Date') }}</p>
							<p class="font-medium {{ $item->is_overdue ? 'text-red-500' : '' }}">{{ $item->due_date->format('M d, Y') }}</p>
						</div>
					@endif
					<div>
						<p class="text-xs text-foreground/60">{{ __('Currency') }}</p>
						<p class="font-medium">{{ $item->currency }}</p>
					</div>
					@if ($item->company)
						<div>
							<p class="text-xs text-foreground/60">{{ __('Company') }}</p>
							<p class="font-medium">{{ $item->company->name }}</p>
						</div>
					@endif
					@if ($item->contact)
						<div>
							<p class="text-xs text-foreground/60">{{ __('Contact') }}</p>
							<p class="font-medium">{{ $item->contact->full_name }}</p>
						</div>
					@endif
					@if ($item->deal)
						<div>
							<p class="text-xs text-foreground/60">{{ __('Linked Deal') }}</p>
							<p class="font-medium">
								{{ $item->deal->title }}
								@if ($item->deal->value > 0)
									<span class="text-foreground/50">({{ $item->deal->currency }} {{ number_format($item->deal->value, 0) }})</span>
								@endif
							</p>
						</div>
					@endif
				</x-card>

				{{-- Payments --}}
				<x-card class:body="p-0">
					<div class="flex items-center justify-between border-b px-5 py-4 dark:border-white/5">
						<h3 class="font-semibold">
							{{ __('Payments') }}
							(<span x-text="inv.payments.length">{{ $item->payments->count() }}</span>)
						</h3>
						<x-modal title="{{ __('Record Payment') }}">
							<x-slot:trigger size="sm" variant="ghost-shadow">
								<x-tabler-plus class="size-3" />
								{{ __('Add') }}
							</x-slot:trigger>

							<x-slot:modal>
								<form
									class="flex flex-col gap-5"
									onsubmit="return salesSubmitForm(event, '{{ route('dashboard.user.sales.invoices.storePayment', $item->id) }}')"
								>
									@csrf
									<x-forms.input size="lg" type="number" label="{{ __('Amount') }}" name="amount" step="0.01" min="0.01" required placeholder="0.00" />
									<x-forms.input size="lg" type="date" label="{{ __('Payment Date') }}" name="payment_date" required value="{{ now()->format('Y-m-d') }}" />
									<x-forms.input size="lg" type="select" label="{{ __('Payment Method') }}" name="payment_method">
										<option value="">{{ __('Select method') }}</option>
										<option value="bank_transfer">{{ __('Bank Transfer') }}</option>
										<option value="credit_card">{{ __('Credit Card') }}</option>
										<option value="cash">{{ __('Cash') }}</option>
										<option value="check">{{ __('Check') }}</option>
										<option value="other">{{ __('Other') }}</option>
									</x-forms.input>
									<x-forms.input size="lg" label="{{ __('Reference') }}" name="reference" placeholder="{{ __('Transaction ID or reference') }}" />
									<x-forms.input size="lg" label="{{ __('Notes') }}" name="notes" type="textarea" rows="2"></x-forms.input>
									<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
										<x-button @click.prevent="modalOpen = false" variant="outline" type="button">{{ __('Cancel') }}</x-button>
										<x-button type="submit">{{ __('Save') }}</x-button>
									</div>
								</form>
							</x-slot:modal>
						</x-modal>
					</div>

					<div class="divide-y dark:divide-white/5">
						<template x-for="pmt in inv.payments" :key="pmt.id">
							<div class="flex items-center justify-between px-5 py-3">
								<div>
									<p class="font-medium text-green-600" x-text="'{{ $item->currency }} ' + pmt.amount.toFixed(2)"></p>
									<p class="text-xs text-foreground/50">
										<span x-text="pmt.payment_date"></span>
										<template x-if="pmt.payment_method">
											<span x-text="' &middot; ' + pmt.payment_method.replace('_', ' ')"></span>
										</template>
									</p>
									<template x-if="pmt.reference">
										<p class="text-xs text-foreground/40" x-text="pmt.reference"></p>
									</template>
								</div>
								<button
									class="rounded p-1 text-foreground/40 hover:bg-red-500/10 hover:text-red-500"
									type="button"
									@click="deletePayment(pmt.id)"
									title="{{ __('Delete') }}"
								>
									<x-tabler-trash class="size-4" />
								</button>
							</div>
						</template>
						<div x-show="inv.payments.length === 0">
							<p class="px-5 py-6 text-center text-sm text-foreground/60">{{ __('No payments recorded yet.') }}</p>
						</div>
					</div>
				</x-card>
			</div>
		</div>
	</div>
@endsection

@push('script')
	<script>
		document.getElementById('crm_delete_invoice_btn')?.addEventListener('click', function() {
			if (!confirm('{{ __("Are you sure you want to delete this invoice?") }}')) return;

			const btn = this;
			btn.disabled = true;
			btn.innerHTML = '<svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

			$.ajax({
				type: 'GET',
				url: '{{ route("dashboard.user.sales.invoices.delete", $item->id) }}',
				success: function(data) {
					toastr.success(data.message || '{{ __("Invoice deleted successfully.") }}');
					if (data.redirect) {
						setTimeout(function() { window.location.href = data.redirect; }, 300);
					} else {
						setTimeout(function() { window.location.href = '{{ route("dashboard.user.sales.invoices.index") }}'; }, 300);
					}
				},
				error: function(data) {
					toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}');
					btn.disabled = false;
					btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> {{ __("Delete") }}';
				}
			});
		});

		function invoiceManager() {
			return {
				inv: {
					id: {{ $item->id }},
					subtotal: {{ (float) $item->subtotal }},
					discount_type: '{{ $item->discount_type }}',
					discount_value: {{ (float) $item->discount_value }},
					tax_rate: {{ (float) $item->tax_rate }},
					tax_amount: {{ (float) $item->tax_amount }},
					total: {{ (float) $item->total }},
					amount_paid: {{ (float) $item->amount_paid }},
					balance_due: {{ (float) $item->balance_due }},
					status: '{{ $item->status }}',
					items: {!! json_encode($item->items->map(fn ($i) => [
						'id'          => $i->id,
						'description' => $i->description,
						'quantity'    => (float) $i->quantity,
						'unit'        => $i->unit,
						'unit_price'  => (float) $i->unit_price,
						'total'       => (float) $i->total,
						'sort_order'  => $i->sort_order,
					])->values()) !!},
					payments: {!! json_encode($item->payments->map(fn ($p) => [
						'id'             => $p->id,
						'amount'         => (float) $p->amount,
						'payment_date'   => $p->payment_date->format('Y-m-d'),
						'payment_method' => $p->payment_method,
						'reference'      => $p->reference,
						'notes'          => $p->notes,
					])->values()) !!}
				},

				showAddItem: false,
				editingItem: null,
				newItem: { description: '', quantity: 1, unit: '', unit_price: '' },
				editForm: { description: '', quantity: 1, unit: '', unit_price: '' },

				discountAmount() {
					if (this.inv.discount_type === 'percentage') {
						return this.inv.subtotal * (this.inv.discount_value / 100);
					}
					return this.inv.discount_value;
				},

				syncFromResponse(data) {
					if (data.invoice) {
						Object.assign(this.inv, data.invoice);
					}
				},

				addItem() {
					const self = this;
					$.ajax({
						type: 'POST',
						url: '/dashboard/user/sales/invoices/' + this.inv.id + '/items',
						data: JSON.stringify({
							items: [...this.inv.items.map(i => ({
								description: i.description,
								quantity: i.quantity,
								unit: i.unit,
								unit_price: i.unit_price
							})), {
								description: this.newItem.description,
								quantity: this.newItem.quantity,
								unit: this.newItem.unit,
								unit_price: this.newItem.unit_price
							}],
							tax_rate: this.inv.tax_rate,
							_token: '{{ csrf_token() }}'
						}),
						contentType: 'application/json',
						success(data) {
							self.syncFromResponse(data);
							self.newItem = { description: '', quantity: 1, unit: '', unit_price: '' };
							self.showAddItem = false;
							toastr.success(data.message);
						},
						error(xhr) {
							var err = xhr.responseJSON?.errors;
							if (err) { $.each(err, function(i, v) { toastr.error(v); }); }
							else { toastr.error(xhr.responseJSON?.message || '{{ __("An error occurred.") }}'); }
						}
					});
				},

				startEditItem(itm) {
					this.editingItem = itm.id;
					this.editForm = {
						description: itm.description,
						quantity: itm.quantity,
						unit: itm.unit || '',
						unit_price: itm.unit_price
					};
				},

				saveEditItem(itemId) {
					const self = this;
					const updatedItems = this.inv.items.map(i => {
						if (i.id === itemId) {
							return {
								description: this.editForm.description,
								quantity: this.editForm.quantity,
								unit: this.editForm.unit,
								unit_price: this.editForm.unit_price
							};
						}
						return { description: i.description, quantity: i.quantity, unit: i.unit, unit_price: i.unit_price };
					});

					$.ajax({
						type: 'POST',
						url: '/dashboard/user/sales/invoices/' + this.inv.id + '/items',
						data: JSON.stringify({ items: updatedItems, tax_rate: this.inv.tax_rate, _token: '{{ csrf_token() }}' }),
						contentType: 'application/json',
						success(data) {
							self.syncFromResponse(data);
							self.editingItem = null;
							toastr.success(data.message);
						},
						error(xhr) {
							var err = xhr.responseJSON?.errors;
							if (err) { $.each(err, function(i, v) { toastr.error(v); }); }
							else { toastr.error(xhr.responseJSON?.message || '{{ __("An error occurred.") }}'); }
						}
					});
				},

				deleteItem(itemId) {
					if (!confirm('{{ __("Delete this item?") }}')) return;
					const self = this;
					const remaining = this.inv.items.filter(i => i.id !== itemId).map(i => ({
						description: i.description,
						quantity: i.quantity,
						unit: i.unit,
						unit_price: i.unit_price
					}));

					$.ajax({
						type: 'POST',
						url: '/dashboard/user/sales/invoices/' + this.inv.id + '/items',
						data: JSON.stringify({ items: remaining, tax_rate: this.inv.tax_rate, _token: '{{ csrf_token() }}' }),
						contentType: 'application/json',
						success(data) {
							self.syncFromResponse(data);
							toastr.success(data.message);
						},
						error() { toastr.error('{{ __("Failed to delete item.") }}'); }
					});
				},

				deletePayment(paymentId) {
					if (!confirm('{{ __("Delete this payment?") }}')) return;
					const self = this;
					$.ajax({
						type: 'DELETE',
						url: '/dashboard/user/sales/invoices/' + this.inv.id + '/payments/' + paymentId,
						data: { _token: '{{ csrf_token() }}' },
						success(data) {
							self.syncFromResponse(data);
							toastr.success(data.message);
						},
						error() { toastr.error('{{ __("Failed to delete payment.") }}'); }
					});
				}
			};
		}

		function updateInvoiceStatus(status) {
			$.ajax({
				type: 'POST',
				url: '{{ route("dashboard.user.sales.invoices.updateStatus") }}',
				data: {
					_token: '{{ csrf_token() }}',
					invoice_id: {{ $item->id }},
					status: status
				},
				success: function(data) {
					toastr.success(data.message);
					location.reload();
				},
				error: function() {
					toastr.error('{{ __("Failed to update status.") }}');
				}
			});
		}

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
					toastr.success(data.message || '{{ __("Saved successfully.") }}');
					location.reload();
				},
				error: function(data) {
					var err = data.responseJSON?.errors;
					if (err) { $.each(err, function(index, value) { toastr.error(value); }); }
					else { toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}'); }
					btn.disabled = false;
					btn.innerHTML = '{{ __("Save") }}';
				}
			});
			return false;
		}
	</script>
@endpush
