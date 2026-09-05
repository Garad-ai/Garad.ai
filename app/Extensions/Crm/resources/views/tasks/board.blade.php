@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Tasks Board'))
@section('titlebar_subtitle', __('Drag and drop tasks between statuses.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.tasks.list') }}"
		>
			<x-tabler-list class="size-4" />
			{{ __('List View') }}
		</x-button>
		<x-modal title="{{ __('Add Task') }}">
			<x-slot:trigger>
				<x-tabler-plus class="size-4" />
				{{ __('Add Task') }}
			</x-slot:trigger>

			<x-slot:modal>
				<form
					class="flex flex-col gap-5"
					onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.tasks.store') }}')"
				>
					@csrf
					<x-forms.input
						size="lg"
						label="{{ __('Task Title') }}"
						name="title"
						required
						placeholder="{{ __('Enter task title') }}"
					/>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="select"
							label="{{ __('Type') }}"
							name="type"
						>
							<option value="task">{{ __('Task') }}</option>
							<option value="call">{{ __('Call') }}</option>
							<option value="meeting">{{ __('Meeting') }}</option>
							<option value="email">{{ __('Email') }}</option>
							<option value="follow_up">{{ __('Follow Up') }}</option>
						</x-forms.input>
						<x-forms.input
							size="lg"
							type="select"
							label="{{ __('Priority') }}"
							name="priority"
						>
							<option value="low">{{ __('Low') }}</option>
							<option value="medium" selected>{{ __('Medium') }}</option>
							<option value="high">{{ __('High') }}</option>
						</x-forms.input>
					</div>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Status') }}"
						name="status"
					>
						<option value="pending">{{ __('Pending') }}</option>
						<option value="completed">{{ __('Completed') }}</option>
						<option value="cancelled">{{ __('Cancelled') }}</option>
					</x-forms.input>
					<x-forms.input
						size="lg"
						type="datetime-local"
						label="{{ __('Due Date') }}"
						name="due_date"
					/>
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
						label="{{ __('Description') }}"
						name="description"
						type="textarea"
						rows="2"
						placeholder="{{ __('Task details') }}"
					></x-forms.input>
					<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
						<x-button
							@click.prevent="modalOpen = false"
							variant="outline"
							type="button"
						>
							{{ __('Cancel') }}
						</x-button>
						<x-button type="submit">
							{{ __('Save') }}
						</x-button>
					</div>
				</form>
			</x-slot:modal>
		</x-modal>
	</div>
@endsection

@section('content')
	<div
		class="py-10"
		x-data="taskBoard()"
	>
		<div class="flex gap-4 overflow-x-auto pb-4">
			@foreach ($columns as $status => $column)
				<div class="min-w-[300px] max-w-[380px] flex-1">
					{{-- Column Header --}}
					<div
						class="mb-3 flex items-center justify-between rounded-lg px-3 py-2"
						style="background-color: {{ $column['color'] }}20"
					>
						<div class="flex items-center gap-2">
							<span
								class="inline-block size-3 rounded-full"
								style="background-color: {{ $column['color'] }}"
							></span>
							<h3 class="text-sm font-semibold m-0">{{ $column['label'] }}</h3>
						</div>
						<span class="rounded-full bg-foreground/10 px-2 py-0.5 text-xs font-medium">
							{{ $column['tasks']->count() }}
						</span>
					</div>

					{{-- Drop Zone --}}
					<div
						class="flex min-h-[200px] flex-col gap-2 rounded-lg border-2 border-dashed border-transparent p-1 transition-colors"
						data-status="{{ $status }}"
						x-on:dragover.prevent="onDragOver($event)"
						x-on:dragleave="onDragLeave($event)"
						x-on:drop="onDrop($event, '{{ $status }}')"
					>
						@foreach ($column['tasks'] as $task)
							<div
								class="group cursor-grab rounded-lg border bg-background p-3 shadow-sm transition-shadow hover:shadow-md active:cursor-grabbing dark:border-white/10 dark:bg-white/[3%]"
								draggable="true"
								data-task-id="{{ $task->id }}"
								x-on:dragstart="onDragStart($event, {{ $task->id }})"
								x-on:dragend="onDragEnd($event)"
							>
								<div class="mb-2 flex items-start justify-between gap-2">
									<span class="text-sm font-semibold">{{ $task->title }}</span>
									@if ($task->priority === 'high')
										<x-badge class="shrink-0 bg-red-500/10 text-red-500">{{ __('High') }}</x-badge>
									@elseif ($task->priority === 'medium')
										<x-badge class="shrink-0 bg-yellow-500/10 text-yellow-600">{{ __('Medium') }}</x-badge>
									@else
										<x-badge class="shrink-0 bg-blue-500/10 text-blue-500">{{ __('Low') }}</x-badge>
									@endif
								</div>
								<div class="mb-1.5">
									<x-badge class="bg-foreground/5 text-foreground/70 capitalize">
										{{ str_replace('_', ' ', $task->type) }}
									</x-badge>
								</div>
								@if ($task->contact)
									<p class="text-xs text-foreground/60">
										<x-tabler-user class="inline size-3" />
										{{ $task->contact->full_name }}
									</p>
								@endif
								@if ($task->deal)
									<p class="text-xs text-foreground/60">
										<x-tabler-trending-up class="inline size-3" />
										{{ $task->deal->title }}
									</p>
								@endif
								@if ($task->due_date)
									<p class="mt-1 text-xs {{ $task->due_date->isPast() && $task->status !== 'completed' ? 'font-semibold text-red-500' : 'text-foreground/50' }}">
										<x-tabler-calendar class="inline size-3" />
										{{ $task->due_date->format('M d, Y H:i') }}
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
		function taskBoard() {
			return {
				draggedTaskId: null,
				draggedFromStatus: null,

				onDragStart(event, taskId) {
					this.draggedTaskId = taskId;
					this.draggedFromStatus = event.target.closest('[data-status]')?.dataset.status ?? null;
					event.target.style.opacity = '0.5';
					event.dataTransfer.effectAllowed = 'move';
				},

				onDragEnd(event) {
					event.target.style.opacity = '1';
					this.draggedTaskId = null;
					document.querySelectorAll('[data-status]').forEach(el => {
						el.classList.remove('border-primary', 'bg-primary/5');
						el.classList.add('border-transparent');
					});
				},

				onDragOver(event) {
					const zone = event.currentTarget;
					zone.classList.remove('border-transparent');
					zone.classList.add('border-primary', 'bg-primary/5');

					if (!this.draggedTaskId) return;

					// Live preview: move the dragged card to the hovered position
					const card = document.querySelector(`[data-task-id="${this.draggedTaskId}"]`);
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

				onDrop(event, status) {
					event.preventDefault();
					const zone = event.currentTarget;
					zone.classList.add('border-transparent');
					zone.classList.remove('border-primary', 'bg-primary/5');

					if (!this.draggedTaskId) return;

					// Move the card visually to the drop position
					const card = document.querySelector(`[data-task-id="${this.draggedTaskId}"]`);
					if (!card) return;

					const afterCard = this.getDragAfterElement(zone, event.clientY);
					if (afterCard == null) {
						zone.appendChild(card);
					} else {
						zone.insertBefore(card, afterCard);
					}

					// Update column counts
					this.updateCounts();

					// Collect the new order of tasks in the target column
					const orderedIds = [...zone.querySelectorAll('[data-task-id]')]
						.map(el => el.dataset.taskId);

					// Update via AJAX
					$.ajax({
						type: 'POST',
						url: '{{ route("dashboard.user.crm.tasks.updateStatus") }}',
						data: {
							_token: '{{ csrf_token() }}',
							status: status,
							ordered_ids: orderedIds,
						},
						success: function(data) {
							toastr.success(data.message);
						},
						error: function() {
							toastr.error('{{ __("Failed to update task status.") }}');
							location.reload();
						}
					});
				},

				getDragAfterElement(zone, y) {
					const cards = [...zone.querySelectorAll('[data-task-id]:not([style*="opacity: 0.5"])')];

					return cards.reduce((closest, child) => {
						const box = child.getBoundingClientRect();
						const offset = y - box.top - box.height / 2;
						if (offset < 0 && offset > closest.offset) {
							return { offset: offset, element: child };
						}
						return closest;
					}, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
				},

				updateCounts() {
					document.querySelectorAll('[data-status]').forEach(zone => {
						const count = zone.querySelectorAll('[data-task-id]').length;
						const header = zone.previousElementSibling;
						if (header) {
							const badge = header.querySelector('span:last-child');
							if (badge) badge.textContent = count;
						}
					});
				}
			};
		}

		function crmSubmitForm(event, url) {
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
						$.each(err, function(index, value) {
							toastr.error(value);
						});
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
