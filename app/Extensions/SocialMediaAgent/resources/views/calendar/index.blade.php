@php
    use Illuminate\Support\Str;

    $theme = \Theme::get();
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Planner'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('View and plan your content'))
@section('titlebar_actions')
    @include('social-media-agent::components.titlebar-actions')
@endsection

@if ($theme !== 'social-media-agent-dashboard')
    @section('titlebar_actions')
        @include('social-media-agent::calendar.calendar-filters', ['agents' => $agents])
    @endsection
@endif

@push('css')
    <style>
        #social-media-agent-calendar .fc-toolbar.fc-header-toolbar {
            margin-bottom: 2rem;
        }

        #social-media-agent-calendar .fc-header-toolbar .fc-toolbar-chunk:first-child {
            border-bottom: 1px solid hsl(var(--border));
        }

        #social-media-agent-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button {
            padding: 15px 8px;
            border-block: 1px solid transparent;
            border-radius: 0 !important;
            margin-bottom: -1px;
            color: hsl(var(--foreground) / 65%);
        }

        #social-media-agent-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button:hover {
            background: none;
            color: hsl(var(--foreground));
        }

        #social-media-agent-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button:focus {
            box-shadow: none !important;
        }

        #social-media-agent-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(1) .fc-button.fc-button-active {
            border-bottom-color: currentColor;
            background: none;
            color: hsl(var(--heading-foreground));
        }

        #social-media-agent-calendar .fc-toolbar-title {
            font-size: 17px;
            font-weight: 500;
        }

        #social-media-agent-calendar .fc-event {
            padding: 0;
            background: none;
        }

        .fc-event.lqd-fc-filter-out {
            pointer-events: none;
        }

        .fc-event.lqd-fc-filter-out .lqd-event-content {
            opacity: 0.5;
            transform: scale(0.9);
        }

        .lqd-event-content[data-platform=instagram] {
            background-color: #FAE8F4;
            color: #832764;
        }

        .lqd-event-content[data-platform=facebook] {
            background-color: #E5F0FC;
            color: #254491;
        }

        .lqd-event-content[data-platform=linkedin] {
            background-color: #E5F0FC;
            color: #254491;
        }

        .theme-dark .lqd-event-content[data-platform=instagram] {
            background-color: hsl(320 64% 95% / 15%);
            color: #FFF3FB;
        }

        .theme-dark .lqd-event-content[data-platform=facebook] {
            background-color: hsl(211 79% 94% / 15%);
            color: #F1F5FF;
        }

        .theme-dark .lqd-event-content[data-platform=linkedin] {
            background-color: hsl(211 79% 94% / 15%);
            color: #F1F5FF;
        }
    </style>
@endpush

@section('content')
    <div @class([
        'pb-10',
        'group-[&.focus-mode]/body:pt-8' =>
            $theme === 'social-media-agent-dashboard',
        'pt-10' => $theme !== 'social-media-agent-dashboard',
    ])>
        @if ($theme === 'social-media-agent-dashboard')
            @include('social-media-agent::calendar.titlebar')
        @endif

        <div
            id="social-media-agent-calendar"
            x-data="socialMediaAgentCalendar"
            @filter-calendar-by-agent.window="filterByAgent"
        ></div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/fullcalendar/index.global.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/core.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/dom.min.js') }}"></script>

    <script>
        document.addEventListener('alpine:init', () => {
            const floatingUi = window.FloatingUIDOM;
            const {
                computePosition,
                flip,
                offset,
                shift,
            } = floatingUi;

            Alpine.data('socialMediaAgentCalendar', () => ({
                calendar: null,
                calendarEl: document.querySelector('#social-media-agent-calendar'),
                filters: {
                    agentIds: []
                },

                get calendarInitialView() {
                    return window.innerWidth <= 480 ? 'dayGridTwoDay' : window.innerWidth <= 869 ? 'dayGridThreeDay' : 'dayGridMonth'
                },
                get calendarViews() {
                    return window.innerWidth <= 480 ?
                        'dayGridTwoDay,timeGridDay,listWeek' : window.innerWidth <= 869 ?
                        'dayGridThreeDay,timeGridWeek,timeGridDay,listWeek' : 'dayGridMonth,timeGridWeek,timeGridDay,listWeek';
                },
                get calendarHeaderToolbar() {
                    return {
                        start: this.calendarViews,
                        center: 'title',
                        end: 'today prev,next'
                    }
                },

                init() {
                    this.calendar = new FullCalendar.Calendar(this.calendarEl, {
                        initialView: this.calendarInitialView,
                        headerToolbar: this.calendarHeaderToolbar,
                        contentHeight: 'auto',
                        eventSources: [{
                            id: 'social-media-agent-calendar-events',
                            events: async ({
                                startStr,
                                endStr
                            }, successCallback, failureCallback) => {
                                const params = new URLSearchParams({
                                    start_date: startStr,
                                    end_date: endStr,
                                    per_page: 1000,
                                    date_column: 'scheduled_at'
                                });

                                try {
                                    const response = await fetch(`{{ route('dashboard.user.social-media.agent.api.posts') }}?${params}`);

                                    if (!response.ok) {
                                        throw new Error('{{ __('Failed to fetch posts') }}');
                                    }

                                    const data = await response.json();

                                    if (!data.success) {
                                        throw new Error(data.message || '{{ __('Failed to fetch posts') }}');
                                    }

                                    const userLocale = navigator.languages && navigator.languages.length ? navigator.languages[0] : navigator
                                        .language;
                                    const events = data.posts.data.map(post => {
                                        const platform = post.platform?.platform;
                                        const scheduledAt = post.scheduled_at ? new Date(post.scheduled_at) : null;
                                        const dateStr = scheduledAt?.toISOString().split('T')[0];
                                        const timeStr = scheduledAt?.toLocaleTimeString(userLocale, {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                            hour12: false
                                        });

                                        return {
                                            id: post.id,
                                            title: post.agent?.name || '',
                                            start: dateStr,
                                            end: dateStr,
                                            allDay: true,
                                            classNames: ['group'],
                                            extendedProps: {
                                                agentId: post.agent_id,
                                                content: post.content,
                                                mediaUrls: post.media_urls || [],
                                                platform: platform,
                                                platformImage: `{{ asset('vendor/social-media/icons') }}/${platform}.svg`,
                                                platformImageMono: `{{ asset('vendor/social-media/icons') }}/${platform}-mono-light.svg`,
                                                platformUsername: post.platform?.credentials?.username || '',
                                                time: timeStr,
                                                status: post.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '',
                                                scheduledAtTimestamp: scheduledAt?.getTime() || 0,
                                            },
                                        };
                                    });

                                    successCallback(events);

                                    this.applyFilters();
                                } catch (error) {
                                    toastr.error(error.message || '{{ __('Failed to load calendar events') }}');
                                    failureCallback(error);
                                }
                            },
                        }],
                        eventOrder: (a, b) => a.extendedProps.scheduledAtTimestamp - b.extendedProps.scheduledAtTimestamp,
                        views: {
                            dayGridThreeDay: {
                                type: 'dayGridWeek',
                                duration: {
                                    days: 3
                                },
                                buttonText: '{{ __('3 day') }}'
                            },
                            dayGridTwoDay: {
                                type: 'dayGridWeek',
                                duration: {
                                    days: 2
                                },
                                buttonText: '{{ __('2 day') }}'
                            },
                        },
                        eventContent: arg => {
                            return {
                                html: `<div class="lqd-event-content rounded-full transition flex whitespace-nowrap gap-1.5 items-center p-2.5 rounded-full bg-foreground/5" data-platform="${arg.event.extendedProps.platform}" data-agent-id="${arg.event.extendedProps.agentId}">
	<figure class="lqd-event-platform-image shrink-0">
		<img width="18" height="18" src="${arg.event.extendedProps.platformImage}" />
	</figure>

	<p class="lqd-event-info text-[12px] leading-none shrink-0 font-medium mb-0">${arg.event.extendedProps.time}</p>

	<p class="lqd-event-title text-2xs leading-none font-semibold w-full truncate mb-0">${arg.event.extendedProps.platformUsername}</p>

	<div class="lqd-event-card text-foreground invisible whitespace-normal fixed left-0 inset-auto bottom-full !z-[100] w-[min(400px,calc(100vw-30px))] !translate-x-0 translate-y-1 rounded-[10px] bg-background p-3.5 opacity-0 shadow-md shadow-black/5 transition before:absolute before:-inset-2.5">
		<div class="flex gap-1 items-center justify-between mb-2 relative z-1">
			<div class="flex items-center gap-2.5">
				<img width="20" height="20" src="${arg.event.extendedProps.platformImage}" />
				${arg.event.extendedProps.platformUsername}
			</div>

			<span class="text-3xs opacity-55">
				${'{{ __('Post') }}'}
				<span class="text-xl align-text-top leading-[0] mx-1">.</span>
				${arg.event.extendedProps.status}
			</span>
		</div>
		{{-- blade-formatter-disable --}}
		${arg.event.extendedProps.mediaUrls?.length ?
			`<figure
				class="w-full rounded-lg aspect-video shadow-sm shadow-black/5 overflow-hidden relative z-1 grid place-items-center grid-cols-1 mb-3"
				x-data="{
					totalSlides: ${arg.event.extendedProps.mediaUrls?.length ?? 0},
					currentIndex: 0,
					prev() {
						this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
					},
					next() {
						this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
					}
				}"
			>
				${arg.event.extendedProps.mediaUrls.map((media, index) => `<img class="w-full h-auto col-start-1 col-end-1 absolute top-0 start-0 row-start-1 row-end-1 w-full h-full object-cover object-center" x-show="currentIndex == ${index}" x-transition.opacity src="${media}">`).join('')}
				${arg.event.extendedProps.mediaUrls.length >= 2 ? `
				<button class="absolute start-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95" title="${'{{ __('Previous Slide') }}'}" @click.prevent="prev()">
					<svg class="size-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 6l-6 6l6 6" /></svg>
				</button>
				<button class="absolute end-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95" title="${'{{ __('Next Slide') }}'}" @click.prevent="next()">
					<svg class="size-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 6l6 6l-6 6" /></svg>
				</button>
				<div
					class="absolute bottom-5 left-1/2 z-2 inline-flex -translate-x-1/2 gap-1.5 rounded-full border border-background/10 bg-background/10 p-1 backdrop-blur">
					${arg.event.extendedProps.mediaUrls.map((media, index) => `<button class="relative inline-flex size-2 rounded-full bg-white/50 transition before:absolute before:-inset-x-1 before:-inset-y-1 hover:bg-white/80 active:scale-95 [&.active]:w-4 [&.active]:bg-white" @click.prevent="currentIndex = ${index}" :class="{ active: currentIndex === ${index} }" ></button>`).join('')}
				</div>
				` : ''}
			</figure>`
			:
		''}
		{{-- blade-formatter-enable --}}
		<p class="opacity-65 mb-3 text-2xs/[1.4em] relative z-1">
			${arg.event.extendedProps.content}
		</p>

		<p class="opacity-55 mb-0 text-3xs relative z-1">
			${new Date(arg.event.start).toLocaleDateString()}
			${'{{ __('at') }}'}
			${arg.event.extendedProps.time}
		</p>
	</div>
</div>`
                            }
                        },

                        eventMouseEnter: event => {
                            const eventEl = event.el;
                            const eventPopup = event.el.querySelector('.lqd-event-card');
                            const scroller = eventEl.closest('.fc-scroller');

                            computePosition(eventEl, eventPopup, {
                                placement: 'top',
                                strategy: 'fixed',
                                middleware: [
                                    flip({
                                        boundary: scroller,
                                    }),
                                    shift({
                                        boundary: scroller,
                                        padding: 5
                                    }),
                                    offset(5)
                                ],
                            }).then(({
                                x,
                                y
                            }) => {
                                Object.assign(eventPopup.style, {
                                    inset: 'auto',
                                    transform: 'none',
                                    left: `${x}px`,
                                    top: `${y}px`,
                                });
                            });
                        },

                        windowResize: arg => {
                            this.calendar.changeView(this.calendarInitialView)
                            this.calendar.setOption('headerToolbar', this.calendarHeaderToolbar)
                        }
                    });

                    this.calendar.render();
                },

                filterByAgent(event) {
                    const agentId = event?.detail?.agentId;

                    if (typeof agentId === 'undefined') {
                        return toastr.error('{{ __('Agent id is not provided') }}');
                    }

                    const existingFilterIndex = this.filters.agentIds.findIndex(id => id == agentId);

                    if (existingFilterIndex !== -1) {
                        this.filters.agentIds.splice(existingFilterIndex, 1);
                    } else {
                        this.filters.agentIds.push(agentId);
                    }

                    this.applyFilters();
                },

                applyFilters() {
                    const hasFilters = Object.entries(this.filters).some(([key, value]) => Array.isArray(value) ? value.length : !!value);

                    if (!hasFilters) {
                        this.calendar.getEvents().forEach(event => {
                            const eventClassNames = event.classNames;
                            event.setProp('classNames', eventClassNames.filter(cl => cl !== 'lqd-fc-filter-out'))
                        });

                        return;
                    };

                    this.calendar.getEvents().forEach(event => {
                        const agentId = event.extendedProps.agentId;
                        const eventClassNames = event.classNames;
                        let hasActiveFilter = false;

                        // check for other filters here
                        if (this.filters.agentIds.includes(agentId)) {
                            hasActiveFilter = true;
                        }

                        if (!hasActiveFilter) {
                            if (!eventClassNames.includes('lqd-fc-filter-out')) {
                                event.setProp('classNames', [...eventClassNames, 'lqd-fc-filter-out'])
                            }
                        } else {
                            event.setProp('classNames', eventClassNames.filter(cl => cl !== 'lqd-fc-filter-out'))
                        }
                    })
                }
            }))
        })
    </script>
@endpush
