@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $item->proposal_number)
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', $item->title)

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.sales.proposals.index') }}"
		>
			<x-tabler-arrow-left class="size-4" />
			{{ __('Back to Proposals') }}
		</x-button>
		<x-button
			variant="ghost-shadow"
			hover-variant="danger"
			id="crm_delete_proposal_btn"
		>
			<x-tabler-trash class="size-4" />
			{{ __('Delete') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div
		class="py-10"
		x-data="proposalItems()"
	>
		<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
			<x-card class:body="flex flex-col gap-2 p-5">
				<div class="flex items-center justify-between">
					<h3 class="text-lg font-bold">{{ $item->proposal_number }}</h3>
					@switch($item->status)
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
				</div>
				<p class="text-sm text-foreground/60">{{ __('Issue Date') }}: {{ $item->issue_date->format('M d, Y') }}</p>
				@if ($item->valid_until)
					<p class="text-sm text-foreground/60">{{ __('Valid Until') }}: {{ $item->valid_until->format('M d, Y') }}</p>
				@endif
				@if ($item->deal)
					<p class="text-sm text-foreground/60">{{ __('Deal') }}: {{ $item->deal->title }}</p>
				@endif
			</x-card>

			<x-card class:body="flex flex-col gap-2 p-5">
				<h4 class="text-sm font-semibold text-foreground/70">{{ __('Client') }}</h4>
				@if ($item->contact)
					<p class="font-medium">{{ $item->contact->full_name }}</p>
				@endif
				@if ($item->company)
					<p class="text-sm text-foreground/60">{{ $item->company->name }}</p>
				@endif
				@if (!$item->contact && !$item->company)
					<p class="text-sm text-foreground/50">{{ __('No client assigned') }}</p>
				@endif
			</x-card>

			<x-card class:body="flex flex-col gap-2 p-5">
				<h4 class="text-sm font-semibold text-foreground/70">{{ __('Totals') }}</h4>
				<p class="text-sm">{{ __('Subtotal') }}: <span class="font-medium">${{ number_format($item->subtotal, 2) }}</span></p>
				<p class="text-sm">{{ __('Tax') }} ({{ $item->tax_rate }}%): <span class="font-medium">${{ number_format($item->tax_amount, 2) }}</span></p>
				<p class="text-lg font-bold text-primary">${{ number_format($item->total, 2) }}</p>
			</x-card>
		</div>

		<x-card class:body="p-0">
			<div class="flex items-center justify-between border-b px-5 py-4 dark:border-white/5">
				<h3 class="font-semibold">{{ __('Line Items') }}</h3>
			</div>
			<div class="p-5">
				<table class="w-full text-sm">
					<thead>
						<tr class="border-b text-left dark:border-white/5">
							<th class="pb-2 font-medium">{{ __('Description') }}</th>
							<th class="w-24 pb-2 text-center font-medium">{{ __('Qty') }}</th>
							<th class="w-32 pb-2 text-end font-medium">{{ __('Unit Price') }}</th>
							<th class="w-32 pb-2 text-end font-medium">{{ __('Total') }}</th>
							<th class="w-12 pb-2"></th>
						</tr>
					</thead>
					<tbody>
						<template x-for="(item, index) in items" :key="index">
							<tr class="border-b dark:border-white/5">
								<td class="py-2 pe-2">
									<input type="text" class="w-full rounded-lg border bg-transparent px-3 py-2 text-sm dark:border-white/10" x-model="item.description" placeholder="{{ __('Item description') }}" />
								</td>
								<td class="py-2 px-1">
									<input type="number" class="w-full rounded-lg border bg-transparent px-3 py-2 text-center text-sm dark:border-white/10" x-model.number="item.quantity" step="0.01" min="0" @input="calcLineTotal(index)" />
								</td>
								<td class="py-2 px-1">
									<input type="number" class="w-full rounded-lg border bg-transparent px-3 py-2 text-end text-sm dark:border-white/10" x-model.number="item.unit_price" step="0.01" min="0" @input="calcLineTotal(index)" />
								</td>
								<td class="py-2 px-1 text-end font-medium" x-text="'$' + item.total.toFixed(2)"></td>
								<td class="py-2 ps-1">
									<button type="button" class="text-red-500 hover:text-red-700" @click="removeItem(index)"><x-tabler-x class="size-4" /></button>
								</td>
							</tr>
						</template>
					</tbody>
				</table>

				<button type="button" class="mt-3 flex items-center gap-1 text-sm text-primary hover:underline" @click="addItem()">
					<x-tabler-plus class="size-4" /> {{ __('Add Item') }}
				</button>

				<div class="mt-6 flex flex-col items-end gap-2 border-t pt-4 dark:border-white/5">
					<div class="flex items-center gap-3 text-sm">
						<span class="text-foreground/70">{{ __('Subtotal') }}:</span>
						<span class="w-28 text-end font-medium" x-text="'$' + subtotal.toFixed(2)"></span>
					</div>
					<div class="flex items-center gap-3 text-sm">
						<span class="text-foreground/70">{{ __('Tax Rate') }} (%):</span>
						<input type="number" class="w-20 rounded-lg border bg-transparent px-2 py-1 text-end text-sm dark:border-white/10" x-model.number="taxRate" step="0.01" min="0" max="100" @input="calcTotals()" />
					</div>
					<div class="flex items-center gap-3 text-sm">
						<span class="text-foreground/70">{{ __('Tax Amount') }}:</span>
						<span class="w-28 text-end font-medium" x-text="'$' + taxAmount.toFixed(2)"></span>
					</div>
					<div class="flex items-center gap-3 text-lg font-bold">
						<span>{{ __('Total') }}:</span>
						<span class="w-28 text-end text-primary" x-text="'$' + total.toFixed(2)"></span>
					</div>
				</div>

				<div class="mt-4 flex justify-end">
					<x-button type="button" @click="saveItems()" x-bind:disabled="saving">
						<span x-show="!saving">{{ __('Save Items') }}</span>
						<span x-show="saving">{{ __('Saving...') }}</span>
					</x-button>
				</div>
			</div>
		</x-card>

		@if ($item->content)
			<x-card class="mt-6" class:body="p-5">
				<h4 class="mb-2 font-semibold">{{ __('Proposal Content') }}</h4>
				<div class="prose max-w-none text-sm text-foreground/70 dark:prose-invert">{!! nl2br(e($item->content)) !!}</div>
			</x-card>
		@endif

		@if ($item->notes)
			<x-card class="mt-6" class:body="p-5">
				<h4 class="mb-2 font-semibold">{{ __('Notes') }}</h4>
				<p class="text-sm text-foreground/70">{{ $item->notes }}</p>
			</x-card>
		@endif
	</div>
@endsection

@push('script')
	<script>
		document.getElementById('crm_delete_proposal_btn')?.addEventListener('click', function() {
			if (!confirm('{{ __("Are you sure you want to delete this proposal?") }}')) return;

			const btn = this;
			btn.disabled = true;
			btn.innerHTML = '<svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

			$.ajax({
				type: 'GET',
				url: '{{ route("dashboard.user.sales.proposals.delete", $item->id) }}',
				success: function(data) {
					toastr.success(data.message || '{{ __("Proposal deleted successfully.") }}');
					if (data.redirect) {
						setTimeout(function() { window.location.href = data.redirect; }, 300);
					} else {
						setTimeout(function() { window.location.href = '{{ route("dashboard.user.sales.proposals.index") }}'; }, 300);
					}
				},
				error: function(data) {
					toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}');
					btn.disabled = false;
					btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> {{ __("Delete") }}';
				}
			});
		});

		@php
			$proposalItems = $item->items->map(fn($i) => [
				'description' => $i->description,
				'quantity' => (float) $i->quantity,
				'unit_price' => (float) $i->unit_price,
				'total' => (float) $i->total,
			]);
		@endphp
		function proposalItems() {
			return {
				items: {!! $proposalItems->toJson() !!},
				taxRate: {{ (float) $item->tax_rate }},
				subtotal: {{ (float) $item->subtotal }},
				taxAmount: {{ (float) $item->tax_amount }},
				total: {{ (float) $item->total }},
				saving: false,

				addItem() { this.items.push({ description: '', quantity: 1, unit_price: 0, total: 0 }); },
				removeItem(index) { this.items.splice(index, 1); this.calcTotals(); },
				calcLineTotal(index) {
					this.items[index].total = Math.round((this.items[index].quantity * this.items[index].unit_price) * 100) / 100;
					this.calcTotals();
				},
				calcTotals() {
					this.subtotal = this.items.reduce((sum, item) => sum + (item.total || 0), 0);
					this.taxAmount = Math.round((this.subtotal * (this.taxRate / 100)) * 100) / 100;
					this.total = this.subtotal + this.taxAmount;
				},
				saveItems() {
					this.saving = true;
					$.ajax({
						type: 'POST',
						url: '{{ route("dashboard.user.sales.proposals.saveItems", $item->id) }}',
						data: JSON.stringify({ items: this.items, tax_rate: this.taxRate, _token: '{{ csrf_token() }}' }),
						contentType: 'application/json',
						success: (data) => { toastr.success(data.message); this.saving = false; },
						error: (data) => { toastr.error(data.responseJSON?.message || '{{ __("Failed to save items.") }}'); this.saving = false; }
					});
				}
			};
		}
	</script>
@endpush
