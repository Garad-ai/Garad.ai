@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $item->name)
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Project details and tasks.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.projects.index') }}"
		>
			<x-tabler-arrow-left class="size-4" />
			{{ __('Back') }}
		</x-button>
		<x-modal title="{{ __('Edit Project') }}">
			<x-slot:trigger variant="ghost-shadow">
				<x-tabler-pencil class="size-4" />
				{{ __('Edit') }}
			</x-slot:trigger>

			<x-slot:modal>
				<form
					class="flex flex-col gap-5"
					onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.projects.update', $item->id) }}')"
				>
					@csrf
					@method('PUT')
					<x-forms.input
						size="lg"
						label="{{ __('Project Name') }}"
						name="name"
						required
						value="{{ $item->name }}"
					/>
					<x-forms.input
						size="lg"
						label="{{ __('Description') }}"
						name="description"
						type="textarea"
						rows="2"
					>{{ $item->description }}</x-forms.input>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="select"
							label="{{ __('Status') }}"
							name="status"
						>
							<option value="not_started" @selected($item->status === 'not_started')>{{ __('Not Started') }}</option>
							<option value="in_progress" @selected($item->status === 'in_progress')>{{ __('In Progress') }}</option>
							<option value="on_hold" @selected($item->status === 'on_hold')>{{ __('On Hold') }}</option>
							<option value="completed" @selected($item->status === 'completed')>{{ __('Completed') }}</option>
							<option value="cancelled" @selected($item->status === 'cancelled')>{{ __('Cancelled') }}</option>
						</x-forms.input>
						<x-forms.input
							size="lg"
							type="select"
							label="{{ __('Priority') }}"
							name="priority"
						>
							<option value="low" @selected($item->priority === 'low')>{{ __('Low') }}</option>
							<option value="medium" @selected($item->priority === 'medium')>{{ __('Medium') }}</option>
							<option value="high" @selected($item->priority === 'high')>{{ __('High') }}</option>
							<option value="urgent" @selected($item->priority === 'urgent')>{{ __('Urgent') }}</option>
						</x-forms.input>
					</div>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Category') }}"
						name="category"
					>
						<option value="">{{ __('Select category') }}</option>
						<option value="implementation" @selected($item->category === 'implementation')>{{ __('Implementation') }}</option>
						<option value="consulting" @selected($item->category === 'consulting')>{{ __('Consulting') }}</option>
						<option value="development" @selected($item->category === 'development')>{{ __('Development') }}</option>
						<option value="design" @selected($item->category === 'design')>{{ __('Design') }}</option>
						<option value="marketing" @selected($item->category === 'marketing')>{{ __('Marketing') }}</option>
						<option value="support" @selected($item->category === 'support')>{{ __('Support') }}</option>
						<option value="other" @selected($item->category === 'other')>{{ __('Other') }}</option>
					</x-forms.input>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="date"
							label="{{ __('Start Date') }}"
							name="start_date"
							value="{{ $item->start_date?->format('Y-m-d') }}"
						/>
						<x-forms.input
							size="lg"
							type="date"
							label="{{ __('Due Date') }}"
							name="due_date"
							value="{{ $item->due_date?->format('Y-m-d') }}"
						/>
					</div>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="number"
							label="{{ __('Budget') }}"
							name="budget"
							step="0.01"
							min="0"
							value="{{ $item->budget }}"
						/>
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
					</div>
					<x-forms.input
						size="lg"
						label="{{ __('Notes') }}"
						name="notes"
						type="textarea"
						rows="2"
					>{{ $item->notes }}</x-forms.input>
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
			variant="ghost-shadow"
			hover-variant="danger"
			id="crm_delete_project_btn"
		>
			<x-tabler-trash class="size-4" />
			{{ __('Delete') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div class="py-10">
		{{-- Summary Cards --}}
		@php
			$totalTasks = $item->tasks->count();
			$completedTasks = $item->tasks->where('status', 'completed')->count();
			$progress = $item->progress;
			$daysRemaining = $item->due_date ? (int) now()->diffInDays($item->due_date, false) : null;
		@endphp
		<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
			{{-- Status --}}
			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Status') }}</p>
				<div class="flex items-center gap-2">
					@switch($item->status)
						@case('not_started')
							<x-badge class="bg-gray-500/10 text-gray-500">{{ __('Not Started') }}</x-badge>
							@break
						@case('in_progress')
							<x-badge class="bg-blue-500/10 text-blue-500">{{ __('In Progress') }}</x-badge>
							@break
						@case('on_hold')
							<x-badge class="bg-yellow-500/10 text-yellow-600">{{ __('On Hold') }}</x-badge>
							@break
						@case('completed')
							<x-badge class="bg-green-500/10 text-green-500">{{ __('Completed') }}</x-badge>
							@break
						@case('cancelled')
							<x-badge class="bg-red-500/10 text-red-500">{{ __('Cancelled') }}</x-badge>
							@break
					@endswitch
					@if ($item->priority === 'urgent')
						<x-badge class="bg-purple-500/10 text-purple-500">{{ __('Urgent') }}</x-badge>
					@elseif ($item->priority === 'high')
						<x-badge class="bg-red-500/10 text-red-500">{{ __('High') }}</x-badge>
					@endif
				</div>
			</x-card>

			{{-- Timeline --}}
			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Timeline') }}</p>
				<p class="text-lg font-semibold">
					@if ($item->start_date && $item->due_date)
						{{ $item->start_date->format('M d') }} - {{ $item->due_date->format('M d, Y') }}
					@elseif ($item->due_date)
						{{ __('Due') }} {{ $item->due_date->format('M d, Y') }}
					@else
						{{ __('No dates set') }}
					@endif
				</p>
				@if ($daysRemaining !== null && !in_array($item->status, ['completed', 'cancelled']))
					<p class="text-xs {{ $daysRemaining < 0 ? 'font-semibold text-red-500' : 'text-foreground/50' }}">
						{{ $daysRemaining < 0 ? abs($daysRemaining) . ' ' . __('days overdue') : $daysRemaining . ' ' . __('days remaining') }}
					</p>
				@endif
			</x-card>

			{{-- Task Progress --}}
			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Task Progress') }}</p>
				<p class="text-lg font-semibold">{{ $completedTasks }}/{{ $totalTasks }} {{ __('completed') }}</p>
				<div class="mt-1 flex items-center gap-2">
					<div class="h-2 flex-1 rounded-full bg-foreground/10">
						<div
							class="h-2 rounded-full transition-all {{ $progress === 100 ? 'bg-green-500' : 'bg-primary' }}"
							style="width: {{ $progress }}%"
						></div>
					</div>
					<span class="text-sm font-medium">{{ $progress }}%</span>
				</div>
			</x-card>

			{{-- Budget --}}
			<x-card class:body="flex flex-col gap-1 p-5">
				<p class="text-xs font-medium uppercase tracking-wider text-foreground/50">{{ __('Budget') }}</p>
				@if ($item->budget > 0)
					<p class="text-lg font-semibold">{{ $item->currency }} {{ number_format($item->budget, 2) }}</p>
				@else
					<p class="text-lg font-semibold text-foreground/30">{{ __('Not set') }}</p>
				@endif
				@if ($item->category)
					<p class="text-xs capitalize text-foreground/50">{{ $item->category }}</p>
				@endif
			</x-card>
		</div>

		<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
			{{-- Project Details --}}
			<x-card class:body="flex flex-col gap-3 p-5">
				<h3 class="mb-2 text-lg font-semibold">{{ __('Details') }}</h3>

				@if ($item->description)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Description') }}</p>
						<p class="text-sm">{{ $item->description }}</p>
					</div>
				@endif

				@if ($item->contact)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Contact') }}</p>
						<a
							class="font-medium text-primary hover:underline"
							href="{{ route('dashboard.user.crm.contacts.show', $item->contact->id) }}"
						>
							{{ $item->contact->full_name }}
						</a>
					</div>
				@endif

				@if ($item->company)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Company') }}</p>
						<a
							class="font-medium text-primary hover:underline"
							href="{{ route('dashboard.user.crm.companies.show', $item->company->id) }}"
						>
							{{ $item->company->name }}
						</a>
					</div>
				@endif

				@if ($item->deal)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Linked Deal') }}</p>
						<a
							class="font-medium text-primary hover:underline"
							href="{{ route('dashboard.user.crm.deals.edit', $item->deal->id) }}"
						>
							{{ $item->deal->title }}
							@if ($item->deal->value > 0)
								<span class="text-foreground/50">({{ $item->deal->currency }} {{ number_format($item->deal->value, 0) }})</span>
							@endif
						</a>
					</div>
				@endif

				@if ($item->notes)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Notes') }}</p>
						<p class="text-sm">{{ $item->notes }}</p>
					</div>
				@endif
			</x-card>

			{{-- Tasks --}}
			<div class="lg:col-span-2">
				<x-card class:body="p-0">
					<div class="flex items-center justify-between border-b px-5 py-4 dark:border-white/5">
						<h3 class="font-semibold">{{ __('Tasks') }} ({{ $totalTasks }})</h3>
						<x-modal title="{{ __('Add Task') }}">
							<x-slot:trigger
								size="sm"
								variant="ghost-shadow"
							>
								<x-tabler-plus class="size-3" />
								{{ __('Add Task') }}
							</x-slot:trigger>

							<x-slot:modal>
								<form
									class="flex flex-col gap-5"
									onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.projects.storeTask', $item->id) }}')"
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

					<div class="divide-y dark:divide-white/5">
						@forelse ($item->tasks->sortBy([['status', 'asc'], ['due_date', 'asc']]) as $task)
							<div class="flex items-center gap-3 px-5 py-3 {{ $task->status === 'completed' ? 'opacity-50' : '' }}">
								<button
									class="flex shrink-0 items-center justify-center"
									onclick="toggleProjectTask({{ $task->id }})"
									title="{{ $task->status === 'completed' ? __('Mark as pending') : __('Mark as complete') }}"
								>
									@if ($task->status === 'completed')
										<x-tabler-circle-check-filled class="size-5 text-green-500" />
									@else
										<x-tabler-circle class="size-5 text-foreground/30 hover:text-primary" />
									@endif
								</button>
								<div class="min-w-0 flex-1">
									<p class="text-sm font-medium {{ $task->status === 'completed' ? 'line-through' : '' }}">
										{{ $task->title }}
									</p>
									<div class="flex flex-wrap items-center gap-2 text-xs text-foreground/50">
										<x-badge class="bg-foreground/5 text-foreground/70 capitalize">
											{{ str_replace('_', ' ', $task->type) }}
										</x-badge>
										@if ($task->priority === 'high')
											<x-badge class="bg-red-500/10 text-red-500">{{ __('High') }}</x-badge>
										@endif
										@if ($task->contact)
											<span>
												<x-tabler-user class="inline size-3" />
												{{ $task->contact->full_name }}
											</span>
										@endif
										@if ($task->due_date)
											<span class="{{ $task->due_date->isPast() && $task->status !== 'completed' ? 'font-semibold text-red-500' : '' }}">
												<x-tabler-calendar class="inline size-3" />
												{{ $task->due_date->format('M d, Y') }}
											</span>
										@endif
									</div>
								</div>
							</div>
						@empty
							<p class="px-5 py-6 text-center text-sm text-foreground/60">
								{{ __('No tasks yet. Add your first task to this project!') }}
							</p>
						@endforelse
					</div>
				</x-card>
			</div>
		</div>
	</div>
@endsection

@push('script')
	<script>
		document.getElementById('crm_delete_project_btn')?.addEventListener('click', function() {
			if (!confirm('{{ __("Are you sure you want to delete this project?") }}')) return;

			const btn = this;
			btn.disabled = true;
			btn.innerHTML = '<svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

			$.ajax({
				type: 'GET',
				url: '{{ route("dashboard.user.crm.projects.delete", $item->id) }}',
				success: function(data) {
					toastr.success(data.message || '{{ __("Project deleted successfully.") }}');
					if (data.redirect) {
						setTimeout(function() { window.location.href = data.redirect; }, 300);
					} else {
						setTimeout(function() { window.location.href = '{{ route("dashboard.user.crm.projects.index") }}'; }, 300);
					}
				},
				error: function(data) {
					toastr.error(data.responseJSON?.message || '{{ __("An error occurred.") }}');
					btn.disabled = false;
					btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> {{ __("Delete") }}';
				}
			});
		});

		function toggleProjectTask(taskId) {
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
