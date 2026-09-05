{{--
    CRM Bulk Action bar + checkbox helpers.
    Required variables:
    - $bulkType  : string (contact, company, deal, task, project)
--}}

{{-- Floating action bar --}}
<div
	class="pointer-events-none fixed bottom-8 end-0 start-0 z-10 transition-all lg:start-[--navbar-width]"
	x-cloak
	x-show="$store.crmBulk.selected.length > 0"
	x-transition.scale.95
>
	<div class="container">
		<form
			class="pointer-events-auto flex flex-col items-center justify-between gap-1 rounded-full border bg-background px-5 py-3 shadow-lg dark:border-white/10 sm:flex-row sm:gap-4"
			x-data="{ selectedAction: 'delete' }"
			@submit.prevent="$store.crmBulk.execute(selectedAction)"
		>
			<p class="m-0 text-2xs font-medium">
				<span x-text="$store.crmBulk.selected.length"></span>
				<span x-show="$store.crmBulk.selected.length === 1">{{ __('Item') }}</span>
				<span x-show="$store.crmBulk.selected.length !== 1">{{ __('Items') }}</span>
				{{ __('selected') }}
			</p>
			<div class="flex items-center gap-3">
				<x-forms.input
					class="w-full rounded-full md:w-auto md:pe-12"
					type="select"
					size="md"
					x-model="selectedAction"
				>
					<option value="delete">{{ __('Move to Trash') }}</option>
				</x-forms.input>

				<x-button
					type="submit"
					size="md"
				>
					{{ __('Apply') }}
				</x-button>
			</div>
		</form>
	</div>
</div>

@push('script')
<script>
	document.addEventListener('alpine:init', () => {
		if (Alpine.store('crmBulk')) return;
		Alpine.store('crmBulk', {
			selected: [],
			type: '{{ $bulkType }}',

			toggle(id) {
				const idx = this.selected.indexOf(id);
				if (idx === -1) { this.selected.push(id); }
				else { this.selected.splice(idx, 1); }
				this.updateSelectAllState();
			},

			toggleAll() {
				const boxes = document.querySelectorAll('.crm-bulk-cb');
				const selectAllCb = document.querySelector('.crm-bulk-cb-all');
				const allChecked = this.selected.length === boxes.length && boxes.length > 0;

				if (allChecked) {
					// Deselect all
					this.selected = [];
					boxes.forEach(cb => cb.checked = false);
					if (selectAllCb) { selectAllCb.checked = false; selectAllCb.classList.remove('partial'); }
				} else {
					// Select all
					this.selected = [];
					boxes.forEach(cb => {
						const id = parseInt(cb.dataset.id);
						if (!this.selected.includes(id)) this.selected.push(id);
						cb.checked = true;
					});
					if (selectAllCb) { selectAllCb.checked = true; selectAllCb.classList.remove('partial'); }
				}
			},

			updateSelectAllState() {
				const selectAllCb = document.querySelector('.crm-bulk-cb-all');
				if (!selectAllCb) return;
				const boxes = document.querySelectorAll('.crm-bulk-cb');
				const checkedCount = this.selected.length;

				if (checkedCount === 0) {
					selectAllCb.checked = false;
					selectAllCb.classList.remove('partial');
				} else if (checkedCount === boxes.length) {
					selectAllCb.checked = true;
					selectAllCb.classList.remove('partial');
				} else {
					selectAllCb.checked = false;
					selectAllCb.classList.add('partial');
				}
			},

			isSelected(id) {
				return this.selected.includes(id);
			},

			async execute(action) {
				if (this.selected.length === 0) return;
				if (action === 'delete') {
					if (!confirm('{{ __("Are you sure you want to delete the selected items?") }}')) return;
					try {
						const res = await fetch('{{ route("dashboard.user.crm.bulkDelete") }}', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
							},
							body: JSON.stringify({ type: this.type, ids: this.selected })
						});
						const data = await res.json();
						if (data.success) {
							if (typeof toastr !== 'undefined') toastr.success(data.message);
							this.selected = [];
							location.reload();
						} else {
							if (typeof toastr !== 'undefined') toastr.error(data.message);
						}
					} catch (e) {
						console.error(e);
						if (typeof toastr !== 'undefined') toastr.error('{{ __("An error occurred.") }}');
					}
				}
			}
		});
	});
</script>
@endpush
