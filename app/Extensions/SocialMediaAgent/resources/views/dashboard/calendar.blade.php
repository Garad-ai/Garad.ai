@php
    use Illuminate\Support\Str;
@endphp

@push('css')
    <style>
        #social-media-agent-calendar {
            --fc-border-color: hsl(0 0% 100% / 5%);
            --fc-neutral-bg-color: hsl(0 0% 100% / 5%);
        }

        #social-media-agent-calendar .fc-toolbar-title {
            color: currentColor;
            font-size: 14px;
            font-weight: 500;
            font-family: var(--font-body);
        }

        #social-media-agent-calendar .fc-header-toolbar .fc-button-group .fc-button {
            display: inline-grid;
            place-items: center;
            width: 20px;
            height: 20px;
            padding: 0;
            border-radius: 4px !important;
            color: currentColor;
        }

        #social-media-agent-calendar .fc-header-toolbar .fc-button-group .fc-button:not(:disabled):hover {
            background-color: #fff;
            color: #000;
        }

        #social-media-agent-calendar .fc-col-header-cell-cushion {
            padding: 17px 10px;
            font-size: 14px;
            font-weight: 600;
            color: currentColor;
        }

        #social-media-agent-calendar .fc-daygrid-event-harness {
            padding: 4px 4px 1px;
        }

        #social-media-agent-calendar .fc-daygrid-event {
            white-space: normal;
        }

        #social-media-agent-calendar .fc-event {
            padding: 15px;
            background-color: hsl(0 0% 100% / 10%);
            transition: background 0.3s;
        }

        #social-media-agent-calendar .lqd-event-title,
        #social-media-agent-calendar .lqd-event-info {
            color: #fff;
        }

        #social-media-agent-calendar .fc-event:hover {
            background-color: hsl(0 0% 100% / 20%);
        }
    </style>
@endpush

<div
    class="mb-10 overflow-hidden rounded-2xl bg-cover bg-top p-5 text-white md:p-8"
    style="background-image: url({{ asset('vendor/social-media-agent/images/img-5.jpg') }})"
>
    <div
        class="min-h-[450px]"
        id="social-media-agent-calendar"
        x-data="socialMediaAgentDashboardCalendar"
    >
        {{-- Skeleton loader --}}
        <div class="mb-5 h-[1lh] w-full animate-pulse rounded-md bg-white/10"></div>
        <div class="grid grid-cols-7">
            @for ($i = 0; $i < 7; $i++)
                <div class="h-[calc(450px-1lh-1.25rem)] animate-pulse border border-e-0 border-white/5 first:rounded-s-lg last:rounded-e-lg last:border-e"></div>
            @endfor
        </div>
    </div>
</div>

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/fullcalendar/index.global.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/core.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/dom.min.js') }}"></script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('socialMediaAgentDashboardCalendar', () => ({
                calendar: null,

                init() {
                    this.initCalendar();
                },

                initCalendar() {
                    const floatingUi = window.FloatingUIDOM;
                    const {
                        computePosition,
                        flip,
                        offset,
                        shift,
                    } = floatingUi;

                    this.calendar = new FullCalendar.Calendar(this.$el, {
                        initialView: this.getCalendarView(),
                        height: '450px',
                        titleFormat: {
                            month: 'short',
                            day: 'numeric'
                        },
                        dayHeaderContent: arg => {
                            const weekday = arg.date.toLocaleDateString('en-US', {
                                weekday: 'short'
                            });
                            const day = arg.date.toLocaleDateString('en-US', {
                                day: 'numeric'
                            });
                            return {
                                html: `<span class="text-white/50 text-xs font-semibold">${weekday}</span> <span class="inline-grid place-items-center size-5 text-xs font-semibold rounded-full text-white ${arg.isToday ? 'bg-white/10' : ''}">${day}</span>`
                            };
                        },
                        headerToolbar: {
                            start: 'title prev,next',
                            center: '',
                            end: ''
                        },
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
                            },
                            dayGridTwoDay: {
                                type: 'dayGridWeek',
                                duration: {
                                    days: 2
                                },
                            }
                        },
                        eventContent: arg => {
                            return {
                                html: `<div class="lqd-event-content">
	<figure class="lqd-event-platform-image mb-2.5">
		<img width="18" height="18" src="${arg.event.extendedProps.platformImageMono}" />
	</figure>

	<p class="lqd-event-title text-[12px] leading-4 font-semibold mb-2">${arg.event.extendedProps.platformUsername}</p>

	<p class="lqd-event-info text-3xs leading-4 font-medium opacity-55 mb-0">${arg.event.extendedProps.time} <span class="text-xl align-text-top leading-[0] mx-1">.</span> ${arg.event.extendedProps.status}</p>

	<div class="lqd-event-card invisible fixed left-0 inset-auto bottom-full !z-[100] w-[min(400px,calc(100vw-60px))] !translate-x-0 translate-y-1 rounded-[10px] bg-background p-3.5 opacity-0 shadow-md shadow-black/5 transition before:absolute before:-inset-2.5">
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
				class="w-full rounded-lg shadow-sm shadow-black/5 overflow-hidden relative z-1 grid place-items-center grid-cols-1 mb-3"
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
				${arg.event.extendedProps.mediaUrls.map((media, index) => `<img class="w-full h-auto col-start-1 col-end-1 row-start-1 row-end-1" x-show="currentIndex == ${index}" x-transition.opacity src="${media}">`).join('')}
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

                            computePosition(eventEl, eventPopup, {
                                placement: 'top',
                                strategy: 'fixed',
                                middleware: [
                                    flip({
                                        boundary: this.$el.querySelector('.fc-scroller')
                                    }),
                                    shift({
                                        boundary: this.$el,
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
                            this.calendar.changeView(this.getCalendarView())
                        }
                    });

                    this.calendar.render();
                },

                getCalendarView() {
                    return window.innerWidth <= 480 ? 'dayGridTwoDay' : window.innerWidth <= 869 ? 'dayGridThreeDay' : 'dayGridWeek'
                },
            }))
        });
    </script>
@endpush
