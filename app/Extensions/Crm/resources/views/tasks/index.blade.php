@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Tasks'))
@section('titlebar_subtitle', __('Track your calls, meetings, follow-ups, and to-dos.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.tasks.index') }}"
		>
			<x-tabler-layout-kanban class="size-4" />
			{{ __('Board View') }}
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
	<div class="py-10">
		<div class="mb-6">
			@include('crm::partials.stats-bar', ['stats' => $stats])
		</div>

		{{-- Filters --}}
		<form
			class="mb-6 flex flex-wrap items-end gap-3"
			method="GET"
		>
			<div class="min-w-[140px]">
				<x-forms.input
					id="filter_status"
					size="lg"
					type="select"
					label="{{ __('Status') }}"
					name="status"
				>
					<option value="">{{ __('All') }}</option>
					<option
						value="pending"
						@selected(($filters['status'] ?? '') === 'pending')
					>{{ __('Pending') }}</option>
					<option
						value="completed"
						@selected(($filters['status'] ?? '') === 'completed')
					>{{ __('Completed') }}</option>
					<option
						value="cancelled"
						@selected(($filters['status'] ?? '') === 'cancelled')
					>{{ __('Cancelled') }}</option>
				</x-forms.input>
			</div>

			<div class="min-w-[140px]">
				<x-forms.input
					id="filter_type"
					size="lg"
					type="select"
					label="{{ __('Type') }}"
					name="type"
				>
					<option value="">{{ __('All') }}</option>
					<option
						value="task"
						@selected(($filters['type'] ?? '') === 'task')
					>{{ __('Task') }}</option>
					<option
						value="call"
						@selected(($filters['type'] ?? '') === 'call')
					>{{ __('Call') }}</option>
					<option
						value="meeting"
						@selected(($filters['type'] ?? '') === 'meeting')
					>{{ __('Meeting') }}</option>
					<option
						value="email"
						@selected(($filters['type'] ?? '') === 'email')
					>{{ __('Email') }}</option>
					<option
						value="follow_up"
						@selected(($filters['type'] ?? '') === 'follow_up')
					>{{ __('Follow Up') }}</option>
				</x-forms.input>
			</div>

			<div class="min-w-[140px]">
				<x-forms.input
					id="filter_priority"
					size="lg"
					type="select"
					label="{{ __('Priority') }}"
					name="priority"
				>
					<option value="">{{ __('All') }}</option>
					<option
						value="low"
						@selected(($filters['priority'] ?? '') === 'low')
					>{{ __('Low') }}</option>
					<option
						value="medium"
						@selected(($filters['priority'] ?? '') === 'medium')
					>{{ __('Medium') }}</option>
					<option
						value="high"
						@selected(($filters['priority'] ?? '') === 'high')
					>{{ __('High') }}</option>
				</x-forms.input>
			</div>

			<x-button type="submit" variant="ghost-shadow" class="mb-1">
				<x-tabler-filter class="size-4" />
				{{ __('Apply Filter') }}
			</x-button>

			@if (!empty(array_filter($filters)))
				<x-button
					variant="ghost"
					class="mb-1"
					href="{{ route('dashboard.user.crm.tasks.list') }}"
				>
					{{ __('Clear') }}
				</x-button>
			@endif
		</form>

		<div x-data>
		@include('crm::partials.bulk-actions', ['bulkType' => 'task'])

		<x-table>
			<x-slot:head>
				<tr>
					<th class="w-8">
						<label
							class="relative z-10 inline-grid size-[18px] cursor-pointer select-none place-items-center rounded bg-foreground/5 text-primary before:absolute before:left-1/2 before:top-1/2 before:size-8 before:-translate-x-1/2 before:-translate-y-1/2"
							@click.prevent="$store.crmBulk.toggleAll()"
						>
							<input class="crm-bulk-cb-all peer invisible absolute z-10 size-0" type="checkbox" />
							<span class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block size-full rounded bg-primary/5 opacity-0 transition peer-checked:opacity-100"></span>
							<x-tabler-check class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-75 opacity-0 transition peer-checked:scale-100 peer-checked:opacity-100" stroke-width="2.5" />
							<x-tabler-minus class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-75 opacity-0 transition peer-[&.partial]:opacity-100" stroke-width="3" />
						</label>
					</th>
					<th class="w-8"></th>
					<th>{{ __('Title') }}</th>
					<th>{{ __('Type') }}</th>
					<th>{{ __('Priority') }}</th>
					<th>{{ __('Deal') }}</th>
					<th>{{ __('Due Date') }}</th>
					<th class="text-end">{{ __('Actions') }}</th>
				</tr>
			</x-slot:head>

			<x-slot:body>
				@forelse ($list as $entry)
					<tr class="{{ $entry->status === 'completed' ? 'opacity-50' : '' }}">
						<td>
							<label class="relative z-10 inline-grid size-[18px] cursor-pointer select-none place-items-center rounded bg-foreground/5 text-primary before:absolute before:left-1/2 before:top-1/2 before:size-8 before:-translate-x-1/2 before:-translate-y-1/2">
								<input
									class="crm-bulk-cb peer invisible absolute z-10 size-0"
									type="checkbox"
									data-id="{{ $entry->id }}"
									@change="$store.crmBulk.toggle({{ $entry->id }})"
								/>
								<span class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block size-full rounded bg-primary/5 opacity-0 transition peer-checked:opacity-100"></span>
								<x-tabler-check class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-75 opacity-0 transition peer-checked:scale-100 peer-checked:opacity-100" stroke-width="2.5" />
							</label>
						</td>
						<td>
							<button
								class="flex items-center justify-center"
								onclick="toggleTask({{ $entry->id }})"
								title="{{ $entry->status === 'completed' ? __('Mark as pending') : __('Mark as complete') }}"
							>
								@if ($entry->status === 'completed')
									<x-tabler-circle-check-filled class="size-5 text-green-500" />
								@else
									<x-tabler-circle class="size-5 text-foreground/30 hover:text-primary" />
								@endif
							</button>
						</td>
						<td class="font-medium {{ $entry->status === 'completed' ? 'line-through' : '' }}">
							{{ $entry->title }}
						</td>
						<td>
							<x-badge class="bg-foreground/5 text-foreground/70 capitalize">
								{{ str_replace('_', ' ', $entry->type) }}
							</x-badge>
						</td>
						<td>
							@if ($entry->priority === 'high')
								<x-badge class="bg-red-500/10 text-red-500">{{ __('High') }}</x-badge>
							@elseif ($entry->priority === 'medium')
								<x-badge class="bg-yellow-500/10 text-yellow-600">{{ __('Medium') }}</x-badge>
							@else
								<x-badge class="bg-blue-500/10 text-blue-500">{{ __('Low') }}</x-badge>
							@endif
						</td>
						<td>{{ $entry->deal?->title ?? '-' }}</td>
						<td>
							@if ($entry->due_date)
								<span class="{{ $entry->due_date->isPast() && $entry->status !== 'completed' ? 'font-semibold text-red-500' : '' }}">
									{{ $entry->due_date->format('M d, Y') }}
								</span>
							@else
								-
							@endif
						</td>
						<td class="whitespace-nowrap text-end">
							<x-modal
								class="inline-flex"
								title="{{ __('Edit Task') }} - {{ $entry->title }}"
							>
								<x-slot:trigger
									class="size-9"
									size="none"
									variant="ghost-shadow"
									title="{{ __('Edit') }}"
								>
									<x-tabler-pencil class="size-4" />
								</x-slot:trigger>

								<x-slot:modal>
									<form
										class="flex flex-col gap-5"
										onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.tasks.update', $entry->id) }}')"
									>
										@csrf
										@method('PUT')
										<x-forms.input
											size="lg"
											label="{{ __('Task Title') }}"
											name="title"
											required
											value="{{ $entry->title }}"
										/>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input
												size="lg"
												type="select"
												label="{{ __('Type') }}"
												name="type"
											>
												<option value="task" @selected($entry->type === 'task')>{{ __('Task') }}</option>
												<option value="call" @selected($entry->type === 'call')>{{ __('Call') }}</option>
												<option value="meeting" @selected($entry->type === 'meeting')>{{ __('Meeting') }}</option>
												<option value="email" @selected($entry->type === 'email')>{{ __('Email') }}</option>
												<option value="follow_up" @selected($entry->type === 'follow_up')>{{ __('Follow Up') }}</option>
											</x-forms.input>
											<x-forms.input
												size="lg"
												type="select"
												label="{{ __('Priority') }}"
												name="priority"
											>
												<option value="low" @selected($entry->priority === 'low')>{{ __('Low') }}</option>
												<option value="medium" @selected($entry->priority === 'medium')>{{ __('Medium') }}</option>
												<option value="high" @selected($entry->priority === 'high')>{{ __('High') }}</option>
											</x-forms.input>
										</div>
										<x-forms.input
											size="lg"
											type="select"
											label="{{ __('Status') }}"
											name="status"
										>
											<option value="pending" @selected($entry->status === 'pending')>{{ __('Pending') }}</option>
											<option value="completed" @selected($entry->status === 'completed')>{{ __('Completed') }}</option>
											<option value="cancelled" @selected($entry->status === 'cancelled')>{{ __('Cancelled') }}</option>
										</x-forms.input>
										<x-forms.input
											size="lg"
											type="datetime-local"
											label="{{ __('Due Date') }}"
											name="due_date"
											value="{{ $entry->due_date?->format('Y-m-d\TH:i') }}"
										/>
										<x-forms.input
											size="lg"
											type="select"
											label="{{ __('Contact') }}"
											name="crm_contact_id"
										>
											<option value="">{{ __('Select a contact') }}</option>
											@foreach ($contacts as $contact)
												<option
													value="{{ $contact->id }}"
													@selected($entry->crm_contact_id == $contact->id)
												>
													{{ $contact->full_name }}
												</option>
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
												<option
													value="{{ $deal->id }}"
													@selected($entry->crm_deal_id == $deal->id)
												>
													{{ $deal->title }}
												</option>
											@endforeach
										</x-forms.input>
										<x-forms.input
											size="lg"
											label="{{ __('Description') }}"
											name="description"
											type="textarea"
											rows="2"
										>{{ $entry->description }}</x-forms.input>
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
							<x-button
								class="size-9"
								variant="ghost-shadow"
								hover-variant="danger"
								size="none"
								onclick="return confirm('{{ __('Are you sure you want to delete this task?') }}')"
								href="{{ route('dashboard.user.crm.tasks.delete', $entry->id) }}"
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
							{{ __('No tasks yet. Create your first one!') }}
						</td>
					</tr>
				@endforelse
			</x-slot:body>
		</x-table>
		</div>
	</div>
@endsection

@push('script')
	<script>
		function toggleTask(taskId) {
			$.ajax({
				type: 'POST',
				url: '/dashboard/user/crm/tasks/' + taskId + '/toggle-complete',
				data: {
					_token: '{{ csrf_token() }}'
				},
				success: function(data) {
					toastr.success(data.message);
					location.reload();
				},
				error: function() {
					toastr.error('{{ __("Failed to update task.") }}');
				}
			});
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
