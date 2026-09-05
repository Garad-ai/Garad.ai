@php
	$isInLandingPage = isset($landingPage) && $landingPage == true;
	$isPreview = isset($preview) && $preview == true;
	$bannerLink = $bannerInfo?->link ?? null;
@endphp

<{{ $bannerLink && !$isPreview ? 'a' : 'div' }}
	@if($bannerLink && !$isPreview)
	href="{{ $bannerLink }}"
	target="_blank"
	rel="noopener noreferrer"
	@endif
	@class([
		'group/promo fixed bottom-20 left-1/2 z-[10000] flex w-[900px] max-w-[calc(100vw-40px)] -translate-x-1/2 items-center justify-center gap-3 rounded-xl px-8 py-4 text-xs transition-colors max-md:flex-col xl:max-w-screen-xl xl:whitespace-nowrap max-md:[&.collapsed]/promo:left-5 max-md:[&.collapsed]/promo:size-12 max-md:[&.collapsed]/promo:max-w-none max-md:[&.collapsed]/promo:translate-x-0 max-md:[&.collapsed]/promo:justify-center max-md:[&.collapsed]/promo:overflow-hidden max-md:[&.collapsed]/promo:rounded-full max-md:[&.collapsed]/promo:p-0 max-md:[&.expanded]:items-start max-md:[&.expanded]:p-5 [&.in-landingpage]:bottom-8',
		'[--navbar-w:var(--navbar-fixed-width,var(--navbar-width,0px))] lg:left-[calc(50%+(var(--navbar-w)/2))] lg:-translate-x-1/2 lg:max-w-[calc(90vw-var(--navbar-w))]' => !$isInLandingPage,
		'cursor-pointer' => $bannerLink && !$isPreview, // only show pointer if clickable
	])
	:class="{ 'collapsed': !mobile.expanded, 'expanded': mobile.expanded, 'in-landingpage': {{ $isInLandingPage ? 'true' : 'false' }} }"
	:style="{ color: textColor, background: backgroundColor }"
	x-cloak
	x-show="isActive"
	x-transition:enter-start="opacity-0 scale-90 -translate-x-1/2"
	x-transition:enter-end="opacity-100 scale-100 -translate-x-1/2"
	x-transition:leave-start="opacity-100 scale-100 -translate-x-1/2"
	x-transition:leave-end="opacity-0 scale-90 -translate-x-1/2"
	x-data="promoBannerData"
	@if(!$bannerLink || $isPreview)
		@click.prevent="mobile.expanded = true"
	@endif
	@click.outside="mobile.expanded = false"
	>
<div
	class="w-5 shrink-0 max-md:group-[&.collapsed]/promo:w-6"
	x-show="iconPath"
>
	<img
		class="h-auto w-full"
		:src="iconPath"
		x-show="!showFallbackImage"
		x-on:error="showFallbackImage = true"
	>
	<svg
		class="h-auto w-full"
		x-cloak
		x-show="showFallbackImage"
		xmlns="http://www.w3.org/2000/svg"
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="currentColor"
	>
		<path
			d="M11 14v8h-4a3 3 0 0 1 -3 -3v-4a1 1 0 0 1 1 -1h6zm8 0a1 1 0 0 1 1 1v4a3 3 0 0 1 -3 3h-4v-8h6zm-2.5 -12a3.5 3.5 0 0 1 3.163 5h.337a2 2 0 0 1 2 2v1a2 2 0 0 1 -2 2h-7v-5h-2v5h-7a2 2 0 0 1 -2 -2v-1a2 2 0 0 1 2 -2h.337a3.486 3.486 0 0 1 -.337 -1.5c0 -1.933 1.567 -3.5 3.483 -3.5c1.755 -.03 3.312 1.092 4.381 2.934l.136 .243c1.033 -1.914 2.56 -3.114 4.291 -3.175l.209 -.002zm-9 2a1.5 1.5 0 0 0 0 3h3.143c-.741 -1.905 -1.949 -3.02 -3.143 -3zm8.983 0c-1.18 -.02 -2.385 1.096 -3.126 3h3.143a1.5 1.5 0 1 0 -.017 -3z"
		/>
	</svg>
</div>

<span
	class="text-lg font-bold max-md:group-[&.collapsed]/promo:hidden"
	x-text="title"
></span>

<span
	class="max-md:group-[&.collapsed]/promo:hidden"
	x-text="description"
></span>

<div
	class="flex shrink-0 font-medium tabular-nums max-md:group-[&.collapsed]/promo:hidden md:ms-auto"
	x-show="enableCountdown && endDate && remainTime"
>
        <span
			class="tabular-nums"
			x-text="remainTime"
		></span>
</div>

<button
	class="inline-grid size-6 shrink-0 place-items-center max-md:absolute max-md:-end-3 max-md:-top-3 max-md:rounded-full max-md:bg-background max-md:text-foreground max-md:shadow-lg max-md:shadow-black/5 max-md:group-[&.collapsed]/promo:hidden md:ms-auto"
	type="button"
	@if (!$isPreview) @click.prevent.stop="dismiss" @endif
>
	<span class="sr-only">{{ __('Close') }}</span>
	<svg
		width="21"
		height="20"
		viewBox="0 0 21 20"
		fill="currentColor"
		xmlns="http://www.w3.org/2000/svg"
	>
		<path
			d="M6.9 15L10.5 11.4L14.1 15L15.5 13.6L11.9 10L15.5 6.4L14.1 5L10.5 8.6L6.9 5L5.5 6.4L9.1 10L5.5 13.6L6.9 15ZM10.5 20C9.11667 20 7.81667 19.7375 6.6 19.2125C5.38333 18.6875 4.325 17.975 3.425 17.075C2.525 16.175 1.8125 15.1167 1.2875 13.9C0.7625 12.6833 0.5 11.3833 0.5 10C0.5 8.61667 0.7625 7.31667 1.2875 6.1C1.8125 4.88333 2.525 3.825 3.425 2.925C4.325 2.025 5.38333 1.3125 6.6 0.7875C7.81667 0.2625 9.11667 0 10.5 0C11.8833 0 13.1833 0.2625 14.4 0.7875C15.6167 1.3125 16.675 2.025 17.575 2.925C18.475 3.825 19.1875 4.88333 19.7125 6.1C20.2375 7.31667 20.5 8.61667 20.5 10C20.5 11.3833 20.2375 12.6833 19.7125 13.9C19.1875 15.1167 18.475 16.175 17.575 17.075C16.675 17.975 15.6167 18.6875 14.4 19.2125C13.1833 19.7375 11.8833 20 10.5 20Z"
		/>
	</svg>
</button>
</{{ $bannerLink && !$isPreview ? 'a' : 'div' }}>

@pushOnce('script')
	<script>
		document.addEventListener('alpine:init', () => {
			Alpine.data('promoBannerData', () => ({
				iconPath: "{{ isset($bannerInfo) && $bannerInfo?->icon ? asset($bannerInfo?->icon) : asset('vendor/discount-manager/images/gift.svg') }}",
				title: "{{ $bannerInfo?->title ?? __('Limited Time Offer') }}",
				description: "{{ $bannerInfo?->description ?? __('Enjoy half off your first month with a Premium subscription!') }}",
				textColor: "{{ $bannerInfo?->text_color ?? '#ffffff' }}",
				backgroundColor: "{{ $bannerInfo?->background_color ?? '#404654' }}",
				endDate: "{{ $bannerInfo?->end_date ?? '' }}",
				enableCountdown: {{ $bannerInfo?->enable_countdown ?? false ? 'true' : 'false' }},
				remainTime: '',
				intervalId: null,
				isActive: {{ $isPreview || ($bannerInfo?->active ?? true) ? 'true' : 'false' }},
				showFallbackImage: false,
				mobile: { expanded: false },

				init() {
					@if (!$isPreview)
					const isDismissed = localStorage.getItem('lqdPromoDismissed');
					if (isDismissed === 'yes') {
						this.isActive = false;
					}
					@endif

					Alpine.store('promoBannerData', this);

					if (this.enableCountdown && this.endDate) {
						this.setCountdown(this.endDate);
					}
				},

				setCountdown(date) {
					if (this.intervalId) {
						clearInterval(this.intervalId);
						this.intervalId = null;
					}

					if (!date || !this.enableCountdown) {
						this.remainTime = '';
						return;
					}

					const endDate = new Date(date);
					if (isNaN(endDate.getTime())) {
						this.remainTime = '';
						return;
					}

					this.intervalId = setInterval(() => {
						const now = new Date();
						const diff = endDate - now;

						if (diff <= 0) {
							this.convertRemainTimeHumanLike(0);
							clearInterval(this.intervalId);
							this.intervalId = null;
							return;
						}

						this.convertRemainTimeHumanLike(diff);
					}, 1000);

					const now = new Date();
					const diff = endDate - now;
					this.convertRemainTimeHumanLike(Math.max(0, diff));
				},

				setEndDate(date) {
					this.endDate = date;
					if (this.enableCountdown) {
						this.setCountdown(date);
					}
				},

				toggleCountdown(enabled) {
					this.enableCountdown = enabled;
					if (enabled && this.endDate) {
						this.setCountdown(this.endDate);
					} else {
						if (this.intervalId) {
							clearInterval(this.intervalId);
							this.intervalId = null;
						}
						this.remainTime = '';
					}
				},

				convertRemainTimeHumanLike(diff) {
					if (diff <= 0) {
						this.remainTime = @json("⏰ " . __("Time's Up!"));
						return;
					}

					const diffSecs = Math.floor(diff / 1000);
					const days = Math.floor(diffSecs / (60 * 60 * 24));
					const hours = Math.floor((diffSecs % (60 * 60 * 24)) / (60 * 60));
					const mins = Math.floor((diffSecs % (60 * 60)) / 60);
					const secs = diffSecs % 60;

					const pad = (num) => num.toString().padStart(2, '0');

					let timeString = '{{ __('⏰ Expires in ') }}';

					if (days > 0) timeString += `${days}d `;
					if (hours > 0) timeString += `${pad(hours)}h `;
					if (mins > 0) timeString += `${pad(mins)}m `;
					timeString += `${pad(secs)}s`;

					this.remainTime = timeString;
				},

				updateIcon(newPath) {
					this.iconPath = newPath;
				},

				dismiss() {
					this.isActive = false;
					localStorage.setItem('lqdPromoDismissed', 'yes');
				}
			}));
		});
	</script>
@endPushOnce
