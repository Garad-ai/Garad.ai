@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('CRM Calendar'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('View your tasks, deals, and projects on a calendar.'))

@section('titlebar_actions')
	<div class="flex items-center gap-2" x-data="{ types: ['tasks', 'deals', 'projects', 'activities'] }">
		<button
			class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
			:class="types.includes('tasks') ? 'border-purple-500/30 bg-purple-500/10 text-purple-600 dark:text-purple-400' : 'border-border text-foreground/50 hover:text-foreground'"
			@click="types.includes('tasks') ? types.splice(types.indexOf('tasks'), 1) : types.push('tasks'); $dispatch('crm-calendar-filter', { types })"
		>
			<span class="inline-block size-2 rounded-full bg-purple-500"></span>
			{{ __('Tasks') }}
		</button>
		<button
			class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
			:class="types.includes('deals') ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'border-border text-foreground/50 hover:text-foreground'"
			@click="types.includes('deals') ? types.splice(types.indexOf('deals'), 1) : types.push('deals'); $dispatch('crm-calendar-filter', { types })"
		>
			<span class="inline-block size-2 rounded-full bg-emerald-500"></span>
			{{ __('Deals') }}
		</button>
		<button
			class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
			:class="types.includes('projects') ? 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400' : 'border-border text-foreground/50 hover:text-foreground'"
			@click="types.includes('projects') ? types.splice(types.indexOf('projects'), 1) : types.push('projects'); $dispatch('crm-calendar-filter', { types })"
		>
			<span class="inline-block size-2 rounded-full bg-blue-500"></span>
			{{ __('Projects') }}
		</button>
		<button
			class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
			:class="types.includes('activities') ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'border-border text-foreground/50 hover:text-foreground'"
			@click="types.includes('activities') ? types.splice(types.indexOf('activities'), 1) : types.push('activities'); $dispatch('crm-calendar-filter', { types })"
		>
			<span class="inline-block size-2 rounded-full bg-amber-500"></span>
			{{ __('Activities') }}
		</button>
	</div>
@endsection

@push('css')
	<style>
		#crm-calendar .fc-toolbar.fc-header-toolbar {
			margin-bottom: 2rem;
		}

		#crm-calendar .fc-header-toolbar .fc-toolbar-chunk:first-child {
			border-bottom: 1px solid hsl(var(--border));
		}

		#crm-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button {
			padding: 15px 8px;
			border-block: 1px solid transparent;
			border-radius: 0 !important;
			margin-bottom: -1px;
			color: hsl(var(--foreground) / 65%);
		}

		#crm-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button:hover {
			background: none;
			color: hsl(var(--foreground));
		}

		#crm-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button:focus {
			box-shadow: none !important;
		}

		#crm-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button.fc-button-active {
			border-bottom-color: currentColor;
			background: none;
			color: hsl(var(--heading-foreground));
		}

		#crm-calendar .fc-toolbar-title {
			font-size: 17px;
			font-weight: 500;
		}

		#crm-calendar .fc-event {
			padding: 0;
			background: none;
		}

		.fc-event.lqd-fc-filter-out {
			pointer-events: none;
		}

		.fc-event.lqd-fc-filter-out .lqd-crm-event {
			opacity: 0.15;
			transform: scale(0.95);
		}

		.lqd-crm-event[data-event-type="task"] {
			background-color: hsl(270 60% 95%);
			color: hsl(270 60% 35%);
		}

		.lqd-crm-event[data-event-type="deal"] {
			background-color: hsl(152 60% 93%);
			color: hsl(152 60% 28%);
		}

		.lqd-crm-event[data-event-type="project"] {
			background-color: hsl(211 70% 93%);
			color: hsl(211 70% 30%);
		}

		.theme-dark .lqd-crm-event[data-event-type="task"] {
			background-color: hsl(270 60% 95% / 12%);
			color: hsl(270 80% 80%);
		}

		.theme-dark .lqd-crm-event[data-event-type="deal"] {
			background-color: hsl(152 60% 93% / 12%);
			color: hsl(152 70% 70%);
		}

		.theme-dark .lqd-crm-event[data-event-type="project"] {
			background-color: hsl(211 70% 93% / 12%);
			color: hsl(211 80% 75%);
		}

		.lqd-crm-event[data-event-type="call"] {
			background-color: hsl(38 92% 92%);
			color: hsl(30 80% 32%);
		}

		.lqd-crm-event[data-event-type="meeting"] {
			background-color: hsl(346 77% 94%);
			color: hsl(346 65% 38%);
		}

		.theme-dark .lqd-crm-event[data-event-type="call"] {
			background-color: hsl(38 92% 92% / 12%);
			color: hsl(38 90% 72%);
		}

		.theme-dark .lqd-crm-event[data-event-type="meeting"] {
			background-color: hsl(346 77% 94% / 12%);
			color: hsl(346 80% 76%);
		}
	</style>
@endpush

@section('content')
	<div class="py-10">
		<div
			id="crm-calendar"
			x-data="crmCalendar"
			@crm-calendar-filter.window="updateFilters"
		></div>
	</div>

	<div
		x-data="crmCalendarTaskModal"
		@crm-calendar-open-task-edit.window="openTask($event.detail.taskId)"
	>
		<template x-teleport="body">
			<div
				x-show="modalOpen"
				x-transition.opacity
				class="fixed inset-0 z-[999] flex items-center justify-center overflow-y-auto overscroll-contain px-4"
				:class="{ 'hidden': !modalOpen }"
				@keyup.escape="if (!saving) modalOpen = false"
			>
				<div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
				<div
					class="relative z-[100] max-h-[95vh] min-w-[min(100%,540px)] overflow-y-auto overscroll-contain rounded-xl bg-background shadow-2xl shadow-black/10"
					@click.outside="if (!saving) modalOpen = false"
					x-show="modalOpen"
					x-transition
					x-cloak
				>
					<div class="flex flex-wrap items-center gap-3 border-b px-4 py-2 relative">
						<h4 class="m-0" x-text="'{{ __('Edit Task') }}' + (task.title ? ' - ' + task.title : '')"></h4>
						<button
							class="size-8 ms-auto inline-flex items-center justify-center rounded-lg transition-all hover:bg-foreground/20"
							type="button"
							@click.prevent="if (!saving) modalOpen = false"
						>
							<x-tabler-x class="size-5" />
						</button>
					</div>
					<div class="p-10">
						<template x-if="loading">
							<div class="flex items-center justify-center py-10">
								<div class="size-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
							</div>
						</template>
						<template x-if="!loading && task.id">
							<form class="flex flex-col gap-5" @submit.prevent="saveTask()">
								<div>
									<label class="mb-1.5 block text-sm font-medium">{{ __('Task Title') }}</label>
									<input
										type="text"
										x-model="task.title"
										required
										class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
									/>
								</div>
								<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
									<div>
										<label class="mb-1.5 block text-sm font-medium">{{ __('Type') }}</label>
										<select x-model="task.type" class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
											<option value="task">{{ __('Task') }}</option>
											<option value="call">{{ __('Call') }}</option>
											<option value="meeting">{{ __('Meeting') }}</option>
											<option value="email">{{ __('Email') }}</option>
											<option value="follow_up">{{ __('Follow Up') }}</option>
										</select>
									</div>
									<div>
										<label class="mb-1.5 block text-sm font-medium">{{ __('Priority') }}</label>
										<select x-model="task.priority" class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
											<option value="low">{{ __('Low') }}</option>
											<option value="medium">{{ __('Medium') }}</option>
											<option value="high">{{ __('High') }}</option>
										</select>
									</div>
								</div>
								<div>
									<label class="mb-1.5 block text-sm font-medium">{{ __('Status') }}</label>
									<select x-model="task.status" class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
										<option value="pending">{{ __('Pending') }}</option>
										<option value="completed">{{ __('Completed') }}</option>
										<option value="cancelled">{{ __('Cancelled') }}</option>
									</select>
								</div>
								<div>
									<label class="mb-1.5 block text-sm font-medium">{{ __('Due Date') }}</label>
									<input
										type="datetime-local"
										x-model="task.due_date"
										class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
									/>
								</div>
								<div>
									<label class="mb-1.5 block text-sm font-medium">{{ __('Contact') }}</label>
									<select x-model="task.crm_contact_id" class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
										<option value="">{{ __('Select a contact') }}</option>
										<template x-for="contact in contacts" :key="contact.id">
											<option :value="contact.id" x-text="contact.name"></option>
										</template>
									</select>
								</div>
								<div>
									<label class="mb-1.5 block text-sm font-medium">{{ __('Deal') }}</label>
									<select x-model="task.crm_deal_id" class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
										<option value="">{{ __('Select a deal') }}</option>
										<template x-for="deal in deals" :key="deal.id">
											<option :value="deal.id" x-text="deal.title"></option>
										</template>
									</select>
								</div>
								<div>
									<label class="mb-1.5 block text-sm font-medium">{{ __('Description') }}</label>
									<textarea
										x-model="task.description"
										rows="2"
										class="w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
									></textarea>
								</div>
								<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
									<button
										@click.prevent="if (!saving) modalOpen = false"
										type="button"
										class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-4 py-2.5 text-sm font-medium transition hover:bg-foreground/5"
									>
										{{ __('Cancel') }}
									</button>
									<button
										type="submit"
										:disabled="saving"
										class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
									>
										<template x-if="saving">
											<div class="size-4 animate-spin rounded-full border-2 border-primary-foreground border-t-transparent"></div>
										</template>
										{{ __('Save') }}
									</button>
								</div>
							</form>
						</template>
					</div>
				</div>
			</div>
		</template>
	</div>
@endsection

@push('script')
	<script src="{{ custom_theme_url('/assets/libs/fullcalendar/index.global.min.js') }}"></script>
	<script src="{{ custom_theme_url('/assets/libs/floating-ui/core.min.js') }}"></script>
	<script src="{{ custom_theme_url('/assets/libs/floating-ui/dom.min.js') }}"></script>

	<script>
		document.addEventListener('alpine:init', () => {
			const floatingUi = window.FloatingUIDOM;
			const { computePosition, flip, offset, shift } = floatingUi;

			Alpine.data('crmCalendar', () => ({
				calendar: null,
				calendarEl: document.querySelector('#crm-calendar'),
				activeTypes: ['tasks', 'deals', 'projects', 'activities'],

				get calendarInitialView() {
					return window.innerWidth <= 480 ? 'dayGridTwoDay' : window.innerWidth <= 869 ? 'dayGridThreeDay' : 'dayGridMonth';
				},
				get calendarViews() {
					return window.innerWidth <= 480
						? 'dayGridTwoDay,timeGridDay,listWeek'
						: window.innerWidth <= 869
							? 'dayGridThreeDay,timeGridWeek,timeGridDay,listWeek'
							: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek';
				},
				get calendarHeaderToolbar() {
					return {
						start: this.calendarViews,
						center: 'title',
						end: 'today prev,next'
					};
				},

				init() {
					this.calendar = new FullCalendar.Calendar(this.calendarEl, {
						initialView: this.calendarInitialView,
						headerToolbar: this.calendarHeaderToolbar,
						contentHeight: 'auto',
						eventSources: [{
							id: 'crm-events',
							events: async ({ startStr, endStr }, successCallback, failureCallback) => {
								const params = new URLSearchParams({
									start: startStr,
									end: endStr,
									types: this.activeTypes.join(','),
								});

								try {
									const response = await fetch(`{{ route('dashboard.user.crm.calendar.events') }}?${params}`);

									if (!response.ok) throw new Error('{{ __('Failed to fetch events') }}');

									const data = await response.json();
									if (!data.success) throw new Error(data.message || '{{ __('Failed to fetch events') }}');

									successCallback(data.events);
									this.applyFilters();
								} catch (error) {
									if (typeof toastr !== 'undefined') toastr.error(error.message);
									failureCallback(error);
								}
							},
						}],
						views: {
							dayGridThreeDay: {
								type: 'dayGridWeek',
								duration: { days: 3 },
								buttonText: '{{ __('3 day') }}'
							},
							dayGridTwoDay: {
								type: 'dayGridWeek',
								duration: { days: 2 },
								buttonText: '{{ __('2 day') }}'
							},
						},

						eventContent: arg => {
							const ep = arg.event.extendedProps;
							const eventType = ep.eventType;
							let icon = '';

							if (eventType === 'task') {
								icon = this.taskIcon(ep.type);
							} else if (eventType === 'deal') {
								icon = `<span class="inline-block size-2 rounded-full shrink-0" style="background:${ep.stageColor || '#8b5cf6'}"></span>`;
							} else if (eventType === 'project') {
								icon = this.statusIcon(ep.status);
							} else if (eventType === 'call' || eventType === 'meeting') {
								icon = this.taskIcon(eventType);
							}

							return {
								html: `<div class="lqd-crm-event rounded-lg transition flex gap-1.5 items-center p-2 sm:p-2.5 overflow-hidden min-w-0" data-event-type="${eventType}" data-entity-id="${ep.entityId}">
	${icon}
	<p class="text-[11px] leading-none font-semibold truncate mb-0 min-w-0">${this.escHtml(arg.event.title)}</p>

	<div class="lqd-crm-event-card text-foreground invisible whitespace-normal fixed left-0 inset-auto bottom-full !z-[100] w-[min(340px,calc(100vw-30px))] !translate-x-0 translate-y-1 rounded-xl bg-background p-4 opacity-0 shadow-lg shadow-black/5 transition before:absolute before:-inset-2.5">
		<div class="flex items-center justify-between gap-2 mb-3">
			<div class="flex items-center gap-2">
				${icon}
				<span class="text-xs font-semibold capitalize">${eventType}</span>
			</div>
			${this.statusBadge(eventType, ep)}
		</div>

		<p class="font-semibold text-sm mb-2">${this.escHtml(arg.event.title)}</p>

		${ep.description ? `<p class="text-xs opacity-65 mb-3 line-clamp-3">${this.escHtml(ep.description)}</p>` : ''}

		<div class="flex flex-col gap-1.5 text-xs">
			${eventType === 'task' ? this.taskPopoverDetails(ep, arg.event) : ''}
			${eventType === 'deal' ? this.dealPopoverDetails(ep, arg.event) : ''}
			${eventType === 'project' ? this.projectPopoverDetails(ep, arg.event) : ''}
		</div>
	</div>
</div>`
							};
						},

						eventMouseEnter: event => {
							const eventEl = event.el;
							const eventPopup = event.el.querySelector('.lqd-crm-event-card');
							if (!eventPopup) return;
							const scroller = eventEl.closest('.fc-scroller');

							computePosition(eventEl, eventPopup, {
								placement: 'top',
								strategy: 'fixed',
								middleware: [
									flip({ boundary: scroller }),
									shift({ boundary: scroller, padding: 5 }),
									offset(5)
								],
							}).then(({ x, y }) => {
								Object.assign(eventPopup.style, {
									inset: 'auto',
									transform: 'none',
									left: `${x}px`,
									top: `${y}px`,
								});
							});
						},

						eventClick: info => {
							const ep = info.event.extendedProps;
							const entityType = ep.eventType;
							const entityId = ep.entityId;

							if (entityType === 'deal') {
								window.location.href = '{{ route("dashboard.user.crm.deals.edit", "__ID__") }}'.replace('__ID__', entityId);
							} else if (entityType === 'project') {
								window.location.href = '{{ route("dashboard.user.crm.projects.show", "__ID__") }}'.replace('__ID__', entityId);
							} else if (entityType === 'task') {
								window.dispatchEvent(new CustomEvent('crm-calendar-open-task-edit', { detail: { taskId: entityId } }));
							} else if (entityType === 'call' || entityType === 'meeting') {
								if (ep.notableType === 'deal') {
									window.location.href = '{{ route("dashboard.user.crm.deals.edit", "__ID__") }}'.replace('__ID__', ep.notableId);
								} else if (ep.notableType === 'contact') {
									window.location.href = '{{ route("dashboard.user.crm.contacts.show", "__ID__") }}'.replace('__ID__', ep.notableId);
								}
							}
						},

						windowResize: () => {
							this.calendar.changeView(this.calendarInitialView);
							this.calendar.setOption('headerToolbar', this.calendarHeaderToolbar);
						}
					});

					this.calendar.render();
				},

				updateFilters(event) {
					const newTypes = event?.detail?.types || ['tasks', 'deals', 'projects', 'activities'];
					this.activeTypes = [...newTypes];
					this.applyFilters();
				},

				applyFilters() {
					this.calendar.getEvents().forEach(event => {
						const eventType = event.extendedProps.eventType;
						const classNames = event.classNames;
						const typeKey = (eventType === 'call' || eventType === 'meeting') ? 'activities' : eventType + 's';
						const isActive = this.activeTypes.includes(typeKey);

						if (!isActive) {
							if (!classNames.includes('lqd-fc-filter-out')) {
								event.setProp('classNames', [...classNames, 'lqd-fc-filter-out']);
							}
						} else {
							event.setProp('classNames', classNames.filter(cl => cl !== 'lqd-fc-filter-out'));
						}
					});
				},

				// --- Helper methods ---

				escHtml(str) {
					if (!str) return '';
					return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
				},

				taskIcon(type) {
					const icons = {
						task: '<svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 5.5l1.5 1.5l2.5 -2.5"/><path d="M3.5 11.5l1.5 1.5l2.5 -2.5"/><path d="M3.5 17.5l1.5 1.5l2.5 -2.5"/><path d="M11 6l9 0"/><path d="M11 12l9 0"/><path d="M11 18l9 0"/></svg>',
						call: '<svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg>',
						meeting: '<svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"/><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0 -3 -3.85"/></svg>',
						email: '<svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z"/><path d="M3 7l9 6l9 -6"/></svg>',
						follow_up: '<svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20l-3 -3h-2a3 3 0 0 1 -3 -3v-6a3 3 0 0 1 3 -3h10a3 3 0 0 1 3 3v6a3 3 0 0 1 -3 3h-2l-3 3"/></svg>',
					};
					return icons[type] || icons.task;
				},

				statusIcon(status) {
					const colors = {
						not_started: '#94a3b8',
						in_progress: '#3b82f6',
						on_hold: '#f59e0b',
						completed: '#22c55e',
						cancelled: '#ef4444',
					};
					return `<span class="inline-block size-2.5 rounded-full shrink-0" style="background:${colors[status] || '#94a3b8'}"></span>`;
				},

				priorityLabel(priority) {
					const cls = {
						low: 'text-foreground/40',
						medium: 'text-amber-500',
						high: 'text-orange-500 font-semibold',
						urgent: 'text-red-500 font-semibold',
					};
					return priority ? `<span class="${cls[priority] || ''} capitalize">${this.escHtml(priority)}</span>` : '';
				},

				statusBadge(eventType, ep) {
					if (eventType === 'task') {
						const cls = ep.status === 'completed' ? 'bg-green-500/10 text-green-600' : ep.status === 'cancelled' ? 'bg-red-500/10 text-red-600' : 'bg-amber-500/10 text-amber-600';
						return `<span class="rounded-full px-2 py-0.5 text-[10px] font-medium capitalize ${cls}">${ep.status || 'pending'}</span>`;
					}
					if (eventType === 'deal') {
						return `<span class="rounded-full px-2 py-0.5 text-[10px] font-medium" style="background:${ep.stageColor || '#8b5cf6'}20;color:${ep.stageColor || '#8b5cf6'}">${this.escHtml(ep.stage || '')}</span>`;
					}
					if (eventType === 'project') {
						const cls = { not_started: 'bg-slate-500/10 text-slate-600', in_progress: 'bg-blue-500/10 text-blue-600', on_hold: 'bg-amber-500/10 text-amber-600', completed: 'bg-green-500/10 text-green-600', cancelled: 'bg-red-500/10 text-red-600' };
						const label = (ep.status || 'not_started').replace(/_/g, ' ');
						return `<span class="rounded-full px-2 py-0.5 text-[10px] font-medium capitalize ${cls[ep.status] || cls.not_started}">${label}</span>`;
					}
					return '';
				},

				taskPopoverDetails(ep, event) {
					let html = '';
					if (ep.type) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Type') }}</span><span class="capitalize">${this.escHtml(ep.type.replace(/_/g, ' '))}</span></div>`;
					if (ep.priority) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Priority') }}</span>${this.priorityLabel(ep.priority)}</div>`;
					if (ep.contact) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Contact') }}</span><span>${this.escHtml(ep.contact)}</span></div>`;
					if (ep.deal) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Deal') }}</span><span class="truncate max-w-[160px]">${this.escHtml(ep.deal)}</span></div>`;
					if (ep.project) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Project') }}</span><span class="truncate max-w-[160px]">${this.escHtml(ep.project)}</span></div>`;
					html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Due') }}</span><span>${new Date(event.start).toLocaleDateString()}${ep.time && ep.time !== '00:00' ? ' ' + ep.time : ''}</span></div>`;
					return html;
				},

				dealPopoverDetails(ep, event) {
					let html = '';
					html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Value') }}</span><span class="font-semibold">${this.escHtml(ep.currency || 'USD')} ${Number(ep.value || 0).toLocaleString()}</span></div>`;
					if (ep.contact) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Contact') }}</span><span>${this.escHtml(ep.contact)}</span></div>`;
					if (ep.company) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Company') }}</span><span>${this.escHtml(ep.company)}</span></div>`;
					html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Close Date') }}</span><span>${new Date(event.start).toLocaleDateString()}</span></div>`;
					return html;
				},

				projectPopoverDetails(ep, event) {
					let html = '';
					if (ep.priority) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Priority') }}</span>${this.priorityLabel(ep.priority)}</div>`;
					if (ep.contact) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Contact') }}</span><span>${this.escHtml(ep.contact)}</span></div>`;
					if (ep.company) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Company') }}</span><span>${this.escHtml(ep.company)}</span></div>`;
					if (ep.taskCount !== undefined) {
						const pct = ep.taskCount > 0 ? Math.round((ep.tasksDone / ep.taskCount) * 100) : 0;
						html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Tasks') }}</span><span>${ep.tasksDone}/${ep.taskCount} (${pct}%)</span></div>`;
					}
					if (ep.budget > 0) html += `<div class="flex justify-between"><span class="opacity-50">{{ __('Budget') }}</span><span>${this.escHtml(ep.currency || 'USD')} ${Number(ep.budget).toLocaleString()}</span></div>`;
					return html;
				},
			}));
		});

		document.addEventListener('alpine:init', () => {
			Alpine.data('crmCalendarTaskModal', () => ({
				modalOpen: false,
				loading: false,
				saving: false,
				task: {},
				contacts: [],
				deals: [],

				async openTask(taskId) {
					this.modalOpen = true;
					this.loading = true;
					this.task = {};
					this.contacts = [];
					this.deals = [];

					try {
						const response = await fetch('{{ route("dashboard.user.crm.api.tasks.show", "__ID__") }}'.replace('__ID__', taskId), {
							headers: {
								'X-Requested-With': 'XMLHttpRequest',
							},
						});
						const data = await response.json();

						if (!data.success) throw new Error(data.message || '{{ __("Failed to load task") }}');

						this.task = data.task;
						this.contacts = data.contacts;
						this.deals = data.deals;
					} catch (error) {
						if (typeof toastr !== 'undefined') toastr.error(error.message);
						this.modalOpen = false;
					} finally {
						this.loading = false;
					}
				},

				async saveTask() {
					this.saving = true;

					try {
						const formData = new FormData();
						formData.append('_method', 'PUT');
						formData.append('title', this.task.title || '');
						formData.append('type', this.task.type || 'task');
						formData.append('priority', this.task.priority || 'medium');
						formData.append('status', this.task.status || 'pending');
						formData.append('description', this.task.description || '');

						if (this.task.due_date) {
							formData.append('due_date', this.task.due_date);
						}
						formData.append('crm_contact_id', this.task.crm_contact_id || '');
						formData.append('crm_deal_id', this.task.crm_deal_id || '');

						const response = await fetch('{{ route("dashboard.user.crm.tasks.update", "__ID__") }}'.replace('__ID__', this.task.id), {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
								'X-Requested-With': 'XMLHttpRequest',
							},
							body: formData,
						});

						const data = await response.json();

						if (!response.ok) throw new Error(data.message || '{{ __("Failed to update task") }}');

						if (typeof toastr !== 'undefined') toastr.success(data.message);

						this.modalOpen = false;
						window.location.reload();
					} catch (error) {
						if (typeof toastr !== 'undefined') toastr.error(error.message);
					} finally {
						this.saving = false;
					}
				},
			}));
		});
	</script>
@endpush
