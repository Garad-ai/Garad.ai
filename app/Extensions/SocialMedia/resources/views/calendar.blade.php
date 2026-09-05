@php
    $events = $items->map(function ($item) {
        $image = 'vendor/social-media/icons/' . $item->social_media_platform?->value . '.svg';
        $image_dark = 'vendor/social-media/icons/' . $item->social_media_platform?->value . '-light.svg';

        return [
            'title' => $item->social_media_platform?->value,
            'date' => $item->scheduled_at->format('Y-m-d'),
            'classNames' => 'lqd-event-' . $item->social_media_platform?->value,
            'extendedProps' => [
                'scheduled_at' => $item->scheduled_at->format('g:i a'),
                'content' => $item->content,
                'image' => $item->image,
                'platformImages' => [
                    'default' => asset($image),
                    'dark' => file_exists($image_dark) ? asset($image_dark) : null,
                ],
            ],
        ];
    });
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Calendar'))
@section('titlebar_actions')
    @include('social-media::components.create-post-dropdown', ['platforms' => \App\Extensions\SocialMedia\System\Enums\PlatformEnum::cases()])
@endsection
@section('titlebar_subtitle', '')

@section('content')
    <div class="py-10">
        <div id="calendar"></div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/fullcalendar/index.global.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/core.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/floating-ui/dom.min.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const floatingUi = window.FloatingUIDOM;
            const {
                computePosition,
                flip,
                offset,
                shift,
            } = floatingUi;
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    start: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                    center: 'title',
                    end: 'today prev,next'
                },
                events: @json($events),
                eventContent: (arg) => {
                    return {
                        html: `<div class="lqd-event-content flex items-center gap-1.5 max-sm:justify-center">
							<figure class="lqd-event-platform-image shrink-0">
								${arg.event.extendedProps.platformImages.dark ? `<img width="28" height="28" class="hidden dark:block" src="${arg.event.extendedProps.platformImages.dark}" />` : ''}
								<img width="28" height="28" class="${arg.event.extendedProps.platformImages.dark ? 'dark:hidden' : ''}" src="${arg.event.extendedProps.platformImages.default}" />
							</figure>
							<div class="lqd-event-info grow">
								<div class="lqd-event-scheduled-at">${arg.event.extendedProps.scheduled_at}</div>
								<div class="lqd-event-title capitalize">${arg.event.title}</div>
							</div>

							<div class="lqd-event-card invisible whitespace-normal fixed left-0 inset-auto bottom-full !z-[100] w-[min(288px,calc(100vw-30px))] rounded-[10px] !translate-x-0 bg-background px-2.5 py-5 opacity-0 shadow-md shadow-black/5 transition before:absolute before:-inset-2.5">
								<div class="lqd-card text-card-foreground w-full transition-all group/card lqd-card-outline border border-card-border lqd-card-roundness-default rounded-xl">
									<div class="lqd-card-head border-b border-card-border px-5 py-3.5 relative transition-border border-none pb-0">
										<figure>
											${arg.event.extendedProps.platformImages.dark ? `<img width="28" height="28" class="hidden dark:block" src="${arg.event.extendedProps.platformImages.dark}" />` : ''}
											<img width="28" height="28" class="${arg.event.extendedProps.platformImages.dark ? 'dark:hidden' : ''}" src="${arg.event.extendedProps.platformImages.default}" />
										</figure>
									</div>
									<div class="lqd-card-body relative only:grow lqd-card-md py-4 px-5">
										${arg.event.extendedProps.image ? `<figure class="mb-4 w-full"><img class="w-full rounded h-28 object-cover object-center" src="${arg.event.extendedProps.image}"></figure>` : ''}
										<p class="mb-0 max-h-24 overflow-hidden whitespace-normal text-ellipsis text-2xs/6 font-medium text-heading-foreground" style="mask-image: linear-gradient(to bottom, black 50%, transparent)">
											${arg.event.extendedProps.content}
										</p>
									</div>
								</div>
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
            });
            calendar.render();
        });
    </script>
@endpush
