@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Deals Pipeline'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Drag and drop deals between stages.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.deals.list') }}"
		>
			<x-tabler-list class="size-4" />
			{{ __('List View') }}
		</x-button>
		<x-button href="{{ route('dashboard.user.crm.deals.create') }}">
			<x-tabler-plus class="size-4" />
			{{ __('Add Deal') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div
		class="py-10"
		x-data="crmKanban()"
	>
		<div class="mb-6">
			@include('crm::partials.stats-bar', ['stats' => $stats])
		</div>

		<div class="flex gap-4 overflow-x-auto pb-4">
			@foreach ($stages as $stage)
				<div class="min-w-[200px] flex-1">
					{{-- Stage Header --}}
					<div
						class="mb-3 flex items-center justify-between rounded-lg px-3 py-2"
						style="background-color: {{ $stage->color }}20"
					>
						<div class="flex items-center gap-2">
							<span
								class="inline-block size-3 rounded-full"
								style="background-color: {{ $stage->color }}"
							></span>
							<h3 class="m-0 text-sm font-semibold leading-none">{{ $stage->name }}</h3>
						</div>
						<span class="rounded-full bg-foreground/10 px-2 py-0.5 text-xs font-medium">
							{{ $stage->deals->count() }}
						</span>
					</div>

					{{-- Drop Zone --}}
					<div
						class="flex min-h-[200px] flex-col gap-2 rounded-lg border-2 border-dashed border-transparent p-1 transition-colors"
						data-stage-id="{{ $stage->id }}"
						x-on:dragover.prevent="onDragOver($event)"
						x-on:dragleave="onDragLeave($event)"
						x-on:drop="onDrop($event, {{ $stage->id }})"
					>
						@foreach ($stage->deals as $deal)
							<div
								class="group cursor-grab rounded-lg border bg-background p-3 shadow-sm transition-shadow hover:shadow-md active:cursor-grabbing dark:border-white/10 dark:bg-white/[3%]"
								draggable="true"
								data-deal-id="{{ $deal->id }}"
								x-on:dragstart="onDragStart($event, {{ $deal->id }})"
								x-on:dragend="onDragEnd($event)"
							>
								<div class="mb-2 flex items-start justify-between">
									<a
										class="text-sm font-semibold hover:text-primary"
										href="{{ route('dashboard.user.crm.deals.edit', $deal->id) }}"
									>
										{{ $deal->title }}
									</a>
									<span class="shrink-0 text-sm font-bold text-primary">${{ number_format($deal->value, 0) }}</span>
								</div>
								@if ($deal->contact)
									<p class="text-xs text-foreground/60">
										<x-tabler-user class="inline size-3" />
										{{ $deal->contact->full_name }}
									</p>
								@endif
								@if ($deal->company)
									<p class="text-xs text-foreground/60">
										<x-tabler-building class="inline size-3" />
										{{ $deal->company->name }}
									</p>
								@endif
								@if ($deal->expected_close_date)
									<p class="mt-1 text-xs text-foreground/50">
										<x-tabler-calendar class="inline size-3" />
										{{ $deal->expected_close_date->format('M d, Y') }}
									</p>
								@endif
							</div>
						@endforeach
					</div>
				</div>
			@endforeach
		</div>
	</div>
@endsection

@push('script')
	<script>
		function crmKanban() {
			return {
				draggedDealId: null,
				draggedFromStageId: null,

				onDragStart(event, dealId) {
					this.draggedDealId = dealId;
					this.draggedFromStageId = event.target.closest('[data-stage-id]')?.dataset.stageId ?? null;
					event.target.style.opacity = '0.5';
					event.dataTransfer.effectAllowed = 'move';
				},

				onDragEnd(event) {
					event.target.style.opacity = '1';
					this.draggedDealId = null;
					document.querySelectorAll('[data-stage-id]').forEach(el => {
						el.classList.remove('border-primary', 'bg-primary/5');
						el.classList.add('border-transparent');
					});
				},

				onDragOver(event) {
					const zone = event.currentTarget;
					zone.classList.remove('border-transparent');
					zone.classList.add('border-primary', 'bg-primary/5');

					if (!this.draggedDealId) return;

					// Live preview: move the dragged card to the hovered position
					const card = document.querySelector(`[data-deal-id="${this.draggedDealId}"]`);
					if (!card) return;

					const afterCard = this.getDragAfterElement(zone, event.clientY);
					if (afterCard == null) {
						zone.appendChild(card);
					} else if (afterCard !== card) {
						zone.insertBefore(card, afterCard);
					}
				},

				onDragLeave(event) {
					const zone = event.currentTarget;
					zone.classList.add('border-transparent');
					zone.classList.remove('border-primary', 'bg-primary/5');
				},

				onDrop(event, stageId) {
					event.preventDefault();
					const zone = event.currentTarget;
					zone.classList.add('border-transparent');
					zone.classList.remove('border-primary', 'bg-primary/5');

					if (!this.draggedDealId) return;

					// Move the card visually to the drop position
					const card = document.querySelector(`[data-deal-id="${this.draggedDealId}"]`);
					if (!card) return;

					const afterCard = this.getDragAfterElement(zone, event.clientY);
					if (afterCard == null) {
						zone.appendChild(card);
					} else {
						zone.insertBefore(card, afterCard);
					}

					// Collect the new order of deals in the target stage
					const orderedIds = [...zone.querySelectorAll('[data-deal-id]')]
						.map(el => el.dataset.dealId);

					// Update via AJAX
					$.ajax({
						type: 'POST',
						url: '{{ route("dashboard.user.crm.deals.updateStage") }}',
						data: {
							_token: '{{ csrf_token() }}',
							stage_id: stageId,
							ordered_ids: orderedIds,
						},
						success: function(data) {
							toastr.success(data.message);
						},
						error: function() {
							toastr.error('{{ __("Failed to update deal stage.") }}');
							location.reload();
						}
					});
				},

				getDragAfterElement(zone, y) {
					const cards = [...zone.querySelectorAll('[data-deal-id]:not([style*="opacity: 0.5"])')];

					return cards.reduce((closest, child) => {
						const box = child.getBoundingClientRect();
						const offset = y - box.top - box.height / 2;
						if (offset < 0 && offset > closest.offset) {
							return { offset: offset, element: child };
						}
						return closest;
					}, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
				}
			};
		}
	</script>
@endpush
