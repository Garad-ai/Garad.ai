@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Projects'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Manage your projects and track progress.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		@if ($viewMode === 'list')
			<x-button
				variant="ghost-shadow"
				href="{{ route('dashboard.user.crm.projects.board') }}"
			>
				<x-tabler-layout-kanban class="size-4" />
				{{ __('Board View') }}
			</x-button>
		@else
			<x-button
				variant="ghost-shadow"
				href="{{ route('dashboard.user.crm.projects.index') }}"
			>
				<x-tabler-list class="size-4" />
				{{ __('List View') }}
			</x-button>
		@endif
		<x-modal title="{{ __('Add Project') }}">
			<x-slot:trigger>
				<x-tabler-plus class="size-4" />
				{{ __('Add Project') }}
			</x-slot:trigger>

			<x-slot:modal>
				<form
					class="flex flex-col gap-5"
					onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.projects.store') }}')"
				>
					@csrf
					<x-forms.input
						size="lg"
						label="{{ __('Project Name') }}"
						name="name"
						required
						placeholder="{{ __('Enter project name') }}"
					/>
					<x-forms.input
						size="lg"
						label="{{ __('Description') }}"
						name="description"
						type="textarea"
						rows="2"
						placeholder="{{ __('Project description') }}"
					></x-forms.input>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="select"
							label="{{ __('Status') }}"
							name="status"
						>
							<option value="not_started">{{ __('Not Started') }}</option>
							<option value="in_progress">{{ __('In Progress') }}</option>
							<option value="on_hold">{{ __('On Hold') }}</option>
							<option value="completed">{{ __('Completed') }}</option>
							<option value="cancelled">{{ __('Cancelled') }}</option>
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
							<option value="urgent">{{ __('Urgent') }}</option>
						</x-forms.input>
					</div>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Category') }}"
						name="category"
					>
						<option value="">{{ __('Select category') }}</option>
						<option value="implementation">{{ __('Implementation') }}</option>
						<option value="consulting">{{ __('Consulting') }}</option>
						<option value="development">{{ __('Development') }}</option>
						<option value="design">{{ __('Design') }}</option>
						<option value="marketing">{{ __('Marketing') }}</option>
						<option value="support">{{ __('Support') }}</option>
						<option value="other">{{ __('Other') }}</option>
					</x-forms.input>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="date"
							label="{{ __('Start Date') }}"
							name="start_date"
						/>
						<x-forms.input
							size="lg"
							type="date"
							label="{{ __('Due Date') }}"
							name="due_date"
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
							placeholder="0.00"
						/>
						<x-forms.input
							size="lg"
							type="select"
							label="{{ __('Currency') }}"
							name="currency"
						>
							<option value="USD">USD</option>
							<option value="EUR">EUR</option>
							<option value="GBP">GBP</option>
							<option value="TRY">TRY</option>
						</x-forms.input>
					</div>
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

		@if ($viewMode === 'board')
			{{-- Kanban Board --}}
			<div
				x-data="projectBoard()"
			>
				<div class="flex gap-4 overflow-x-auto pb-4">
					@foreach ($columns as $status => $column)
						<div class="min-w-[300px] max-w-[380px] flex-1">
							<div
								class="mb-3 flex items-center justify-between rounded-lg px-3 py-2"
								style="background-color: {{ $column['color'] }}20"
							>
								<div class="flex items-center gap-2">
									<span
										class="inline-block size-3 rounded-full"
										style="background-color: {{ $column['color'] }}"
									></span>
									<h3 class="text-sm font-semibold">{{ $column['label'] }}</h3>
								</div>
								<span class="rounded-full bg-foreground/10 px-2 py-0.5 text-xs font-medium">
									{{ $column['projects']->count() }}
								</span>
							</div>

							<div
								class="flex min-h-[200px] flex-col gap-2 rounded-lg border-2 border-dashed border-transparent p-1 transition-colors"
								data-status="{{ $status }}"
								x-on:dragover.prevent="onDragOver($event)"
								x-on:dragleave="onDragLeave($event)"
								x-on:drop="onDrop($event, '{{ $status }}')"
							>
								@foreach ($column['projects'] as $project)
									<a
										class="group cursor-grab rounded-lg border bg-background p-3 shadow-sm transition-shadow hover:shadow-md active:cursor-grabbing dark:border-white/10 dark:bg-white/[3%]"
										draggable="true"
										data-project-id="{{ $project->id }}"
										x-on:dragstart="onDragStart($event, {{ $project->id }})"
										x-on:dragend="onDragEnd($event)"
										href="{{ route('dashboard.user.crm.projects.show', $project->id) }}"
									>
										<div class="mb-2 flex items-start justify-between gap-2">
											<span class="text-sm font-semibold">{{ $project->name }}</span>
											@if ($project->priority === 'urgent')
												<x-badge class="shrink-0 bg-purple-500/10 text-purple-500">{{ __('Urgent') }}</x-badge>
											@elseif ($project->priority === 'high')
												<x-badge class="shrink-0 bg-red-500/10 text-red-500">{{ __('High') }}</x-badge>
											@elseif ($project->priority === 'medium')
												<x-badge class="shrink-0 bg-yellow-500/10 text-yellow-600">{{ __('Medium') }}</x-badge>
											@else
												<x-badge class="shrink-0 bg-blue-500/10 text-blue-500">{{ __('Low') }}</x-badge>
											@endif
										</div>
										@if ($project->contact || $project->company)
											<p class="mb-1 text-xs text-foreground/60">
												<x-tabler-building class="inline size-3" />
												{{ $project->company?->name ?? $project->contact?->full_name }}
											</p>
										@endif
										@if ($project->due_date)
											<p class="mb-2 text-xs {{ $project->due_date->isPast() && !in_array($project->status, ['completed','cancelled']) ? 'font-semibold text-red-500' : 'text-foreground/50' }}">
												<x-tabler-calendar class="inline size-3" />
												{{ $project->due_date->format('M d, Y') }}
											</p>
										@endif
										@php $progress = $project->progress; @endphp
										<div class="flex items-center gap-2">
											<div class="h-1.5 flex-1 rounded-full bg-foreground/10">
												<div
													class="h-1.5 rounded-full {{ $progress === 100 ? 'bg-green-500' : 'bg-primary' }}"
													style="width: {{ $progress }}%"
												></div>
											</div>
											<span class="text-xs text-foreground/50">{{ $progress }}%</span>
										</div>
										@if ($project->tasks->count() > 0)
											<p class="mt-1 text-xs text-foreground/50">
												{{ $project->tasks->where('status', 'completed')->count() }}/{{ $project->tasks->count() }} {{ __('tasks') }}
											</p>
										@endif
									</a>
								@endforeach
							</div>
						</div>
					@endforeach
				</div>
			</div>
		@else
			@php
				$sort_buttons = [
					['label' => __('Date'),     'sort' => 'created_at'],
					['label' => __('Name'),     'sort' => 'name'],
					['label' => __('Due Date'), 'sort' => 'due_date'],
					['label' => __('Priority'), 'sort' => 'priority'],
					['label' => __('Status'),   'sort' => 'status'],
					['label' => __('Budget'),   'sort' => 'budget'],
				];

				$filter_buttons = [
					['label' => __('All'),         'filter' => 'all'],
					['label' => __('Favorites'),   'filter' => 'favorites'],
					['label' => __('In Progress'), 'filter' => 'in_progress'],
					['label' => __('Completed'),   'filter' => 'completed'],
					['label' => __('Overdue'),     'filter' => 'overdue'],
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
									href="{{ route('dashboard.user.crm.projects.index', array_merge(request()->query(), ['sort' => $button['sort'], 'sort_dir' => ($sort === $button['sort'] && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
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
							href="{{ route('dashboard.user.crm.projects.index', array_merge(request()->query(), ['filter' => $button['filter']])) }}"
							variant="ghost"
						>
							{{ $button['label'] }}
						</x-button>
					@endforeach
				</div>
			</div>

			{{-- Table --}}
			<div x-data>
			@include('crm::partials.bulk-actions', ['bulkType' => 'project'])

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
						<th>{{ __('Project') }}</th>
						<th>{{ __('Client') }}</th>
						<th>{{ __('Status') }}</th>
						<th>{{ __('Progress') }}</th>
						<th>{{ __('Start Date') }}</th>
						<th>{{ __('Due Date') }}</th>
						<th class="text-end">{{ __('Actions') }}</th>
					</tr>
				</x-slot:head>

				<x-slot:body>
					@forelse ($list as $entry)
						@php $progress = $entry->progress; @endphp
						<tr class="{{ in_array($entry->status, ['completed', 'cancelled']) ? 'opacity-50' : '' }}">
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
								<a
									class="font-medium text-primary hover:underline"
									href="{{ route('dashboard.user.crm.projects.show', $entry->id) }}"
								>
									{{ $entry->name }}
								</a>
								@if ($entry->category)
									<p class="text-xs text-foreground/50 capitalize">{{ $entry->category }}</p>
								@endif
							</td>
							<td>
								{{ $entry->company?->name ?? $entry->contact?->full_name ?? '-' }}
							</td>
							<td>
								@switch($entry->status)
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
							</td>
							<td>
								<div class="flex items-center gap-2">
									<div class="h-1.5 w-20 rounded-full bg-foreground/10">
										<div
											class="h-1.5 rounded-full {{ $progress === 100 ? 'bg-green-500' : 'bg-primary' }}"
											style="width: {{ $progress }}%"
										></div>
									</div>
									<span class="text-xs text-foreground/50">{{ $progress }}%</span>
								</div>
							</td>
							<td>{{ $entry->start_date?->format('M d, Y') ?? '-' }}</td>
							<td>
								@if ($entry->due_date)
									<span class="{{ $entry->due_date->isPast() && !in_array($entry->status, ['completed','cancelled']) ? 'font-semibold text-red-500' : '' }}">
										{{ $entry->due_date->format('M d, Y') }}
									</span>
								@else
									-
								@endif
							</td>
							<td class="whitespace-nowrap text-end">
								<x-button
									class="size-9"
									size="none"
									variant="ghost-shadow"
									title="{{ __('Toggle favorite') }}"
									onclick="crmToggleFav('project', {{ $entry->id }}, this)"
								>
									@if ($entry->is_favorite)
										<x-tabler-star-filled class="size-4 text-amber-400" />
									@else
										<x-tabler-star class="size-4" />
									@endif
								</x-button>
								<x-modal
									class="inline-flex"
									title="{{ __('Edit Project') }} - {{ $entry->name }}"
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
											onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.projects.update', $entry->id) }}')"
										>
											@csrf
											@method('PUT')
											<x-forms.input
												size="lg"
												label="{{ __('Project Name') }}"
												name="name"
												required
												value="{{ $entry->name }}"
											/>
											<x-forms.input
												size="lg"
												label="{{ __('Description') }}"
												name="description"
												type="textarea"
												rows="2"
											>{{ $entry->description }}</x-forms.input>
											<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
												<x-forms.input
													size="lg"
													type="select"
													label="{{ __('Status') }}"
													name="status"
												>
													<option value="not_started" @selected($entry->status === 'not_started')>{{ __('Not Started') }}</option>
													<option value="in_progress" @selected($entry->status === 'in_progress')>{{ __('In Progress') }}</option>
													<option value="on_hold" @selected($entry->status === 'on_hold')>{{ __('On Hold') }}</option>
													<option value="completed" @selected($entry->status === 'completed')>{{ __('Completed') }}</option>
													<option value="cancelled" @selected($entry->status === 'cancelled')>{{ __('Cancelled') }}</option>
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
													<option value="urgent" @selected($entry->priority === 'urgent')>{{ __('Urgent') }}</option>
												</x-forms.input>
											</div>
											<x-forms.input
												size="lg"
												type="select"
												label="{{ __('Category') }}"
												name="category"
											>
												<option value="">{{ __('Select category') }}</option>
												<option value="implementation" @selected($entry->category === 'implementation')>{{ __('Implementation') }}</option>
												<option value="consulting" @selected($entry->category === 'consulting')>{{ __('Consulting') }}</option>
												<option value="development" @selected($entry->category === 'development')>{{ __('Development') }}</option>
												<option value="design" @selected($entry->category === 'design')>{{ __('Design') }}</option>
												<option value="marketing" @selected($entry->category === 'marketing')>{{ __('Marketing') }}</option>
												<option value="support" @selected($entry->category === 'support')>{{ __('Support') }}</option>
												<option value="other" @selected($entry->category === 'other')>{{ __('Other') }}</option>
											</x-forms.input>
											<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
												<x-forms.input
													size="lg"
													type="date"
													label="{{ __('Start Date') }}"
													name="start_date"
													value="{{ $entry->start_date?->format('Y-m-d') }}"
												/>
												<x-forms.input
													size="lg"
													type="date"
													label="{{ __('Due Date') }}"
													name="due_date"
													value="{{ $entry->due_date?->format('Y-m-d') }}"
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
													value="{{ $entry->budget }}"
												/>
												<x-forms.input
													size="lg"
													type="select"
													label="{{ __('Currency') }}"
													name="currency"
												>
													<option value="USD" @selected($entry->currency === 'USD')>USD</option>
													<option value="EUR" @selected($entry->currency === 'EUR')>EUR</option>
													<option value="GBP" @selected($entry->currency === 'GBP')>GBP</option>
													<option value="TRY" @selected($entry->currency === 'TRY')>TRY</option>
												</x-forms.input>
											</div>
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
												label="{{ __('Company') }}"
												name="crm_company_id"
											>
												<option value="">{{ __('Select a company') }}</option>
												@foreach ($companies as $company)
													<option
														value="{{ $company->id }}"
														@selected($entry->crm_company_id == $company->id)
													>
														{{ $company->name }}
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
												label="{{ __('Notes') }}"
												name="notes"
												type="textarea"
												rows="2"
											>{{ $entry->notes }}</x-forms.input>
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
									onclick="return confirm('{{ __('Are you sure you want to delete this project?') }}')"
									href="{{ route('dashboard.user.crm.projects.delete', $entry->id) }}"
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
								{{ __('No projects yet. Create your first one!') }}
							</td>
						</tr>
					@endforelse
				</x-slot:body>
			</x-table>
			</div>
		@endif
	</div>
@endsection

@push('script')
	<script>
		function projectBoard() {
			return {
				draggedProjectId: null,

				onDragStart(event, projectId) {
					this.draggedProjectId = projectId;
					event.target.style.opacity = '0.5';
					event.dataTransfer.effectAllowed = 'move';
				},

				onDragEnd(event) {
					event.target.style.opacity = '1';
					this.draggedProjectId = null;
					document.querySelectorAll('[data-status]').forEach(el => {
						el.classList.remove('border-primary', 'bg-primary/5');
						el.classList.add('border-transparent');
					});
				},

				onDragOver(event) {
					const zone = event.currentTarget;
					zone.classList.remove('border-transparent');
					zone.classList.add('border-primary', 'bg-primary/5');
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

					if (!this.draggedProjectId) return;

					const card = document.querySelector(`[data-project-id="${this.draggedProjectId}"]`);
					if (card) {
						zone.appendChild(card);
					}

					this.updateCounts();

					$.ajax({
						type: 'POST',
						url: '{{ route("dashboard.user.crm.projects.updateStatus") }}',
						data: {
							_token: '{{ csrf_token() }}',
							project_id: this.draggedProjectId,
							status: status,
						},
						success: function(data) {
							toastr.success(data.message);
						},
						error: function() {
							toastr.error('{{ __("Failed to update project status.") }}');
							location.reload();
						}
					});
				},

				updateCounts() {
					document.querySelectorAll('[data-status]').forEach(zone => {
						const count = zone.querySelectorAll('[data-project-id]').length;
						const header = zone.previousElementSibling;
						if (header) {
							const badge = header.querySelector('span:last-child');
							if (badge) badge.textContent = count;
						}
					});
				}
			};
		}

		function crmToggleFav(type, id, btn) {
			$.ajax({
				type: 'POST',
				url: '{{ route("dashboard.user.crm.toggleFavorite") }}',
				data: { type: type, id: id, _token: '{{ csrf_token() }}' },
				success: function (data) {
					btn.innerHTML = data.is_favorite
						? '<svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z"/></svg>'
						: '<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/></svg>';
				},
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
