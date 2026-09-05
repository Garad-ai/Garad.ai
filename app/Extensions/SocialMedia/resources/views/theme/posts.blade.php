@php
	use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
	use Illuminate\Support\Carbon;
	use App\Extensions\SocialMedia\System\Enums\StatusEnum;
	use App\Helpers\Classes\Helper;

	$startDate = Carbon::now()->subDays(30);
	$endDate = Carbon::now();
	$query = SocialMediaPost::query()
		->where('user_id', auth()->id())
		->selectRaw(
			"COUNT(*) as all_posts,
			SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
			SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_posts,
			SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_posts",
		)
		->whereBetween('scheduled_at', [$startDate, $endDate])
		->first();


		 $demoData = random_int(40, 80);

		$publishedPosts = random_int(0, $demoData - 5);

		$posts= [
			'all_posts'       => Helper::appIsDemo() ? $demoData : ($query->all_posts ?? 0),
			'published_posts' => Helper::appIsDemo() ? $publishedPosts : ($query->published_posts ?? 0),
			'scheduled_posts' => Helper::appIsDemo() ? ($demoData - $publishedPosts) : ($query->scheduled_posts ?? 0),
			'failed_posts'    => $query->failed_posts ?? 0,
		];

	$posts_stats = [
		'last_30_days' => $posts,
	];
@endphp
<div class="col-span-full grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4 lg:py-2.5">
	<div class="col-span-full flex items-center gap-7">
		<h3 class="m-0">
			@lang('Overview')
		</h3>

		<span class="inline-flex h-px grow bg-border"></span>

		<x-button class="!text-2xs" variant="link" href="{{ route('dashboard.user.social-media.index') }}">
			{{ __('View All') }}
			<x-tabler-circle-chevron-right class="size-5" stroke-width="1.5"/>
		</x-button>
	</div>

	<x-card
		class="lqd-social-media-overview-card flex flex-col justify-center text-heading-foreground transition-transform hover:-translate-y-1"
		class:body="flex flex-col justify-center rounded-[inherit]">
		<x-outline-glow class="rounded-[inherit] opacity-0 transition-opacity group-hover/card:opacity-100"/>
		<svg class="mb-8" width="32" height="36" viewBox="0 0 32 36" fill="none"
			 xmlns="http://www.w3.org/2000/svg" stroke="url(#social-posts-overview-gradient)" stroke-width="2"
			 stroke-linecap="round" stroke-linejoin="round">
			<path
				d="M13.8772 5.16667H1.16667V34.5H25V19.8333M17.6667 27.1667H8.5M19.5 5.16667V1.5M25 7L28.6667 3.33333M26.8333 12.5H30.5M8.5 19.8333H17.6667V12.5H8.5V19.8333Z"/>
		</svg>

		<x-number-counter
			class="mb-2.5 self-start text-2xl font-semibold text-heading-foreground group-hover/card:motion-preset-pulse group-hover/card:motion-duration-500 group-hover/card:motion-loop-once"
			:value="$posts_stats['last_30_days']['all_posts']" :dynamic-value-listener="'all_posts'"/>
		<p class="m-0 text-sm font-medium opacity-70">
			@lang('All Posts')
		</p>
	</x-card>

	<x-card
		class="lqd-social-media-overview-card flex flex-col justify-center text-heading-foreground transition-transform hover:-translate-y-1"
		class:body="flex flex-col justify-center rounded-[inherit]">
		<x-outline-glow class="rounded-[inherit] opacity-0 transition-opacity group-hover/card:opacity-100"
						class:inner="[animation-delay:0.5s!important]"/>
		<svg class="mb-8" width="36" height="36" viewBox="0 0 36 36" fill="none"
			 xmlns="http://www.w3.org/2000/svg" stroke="url(#social-posts-overview-gradient)" stroke-width="2"
			 stroke-linecap="round" stroke-linejoin="round">
			<path
				d="M23.5 21.6667L18 18V8.83333M1.5 18C1.5 20.1668 1.92678 22.3124 2.75599 24.3143C3.58519 26.3161 4.80057 28.1351 6.33274 29.6673C7.8649 31.1994 9.68385 32.4148 11.6857 33.244C13.6876 34.0732 15.8332 34.5 18 34.5C20.1668 34.5 22.3124 34.0732 24.3143 33.244C26.3161 32.4148 28.1351 31.1994 29.6673 29.6673C31.1994 28.1351 32.4148 26.3161 33.244 24.3143C34.0732 22.3124 34.5 20.1668 34.5 18C34.5 15.8332 34.0732 13.6876 33.244 11.6857C32.4148 9.68385 31.1994 7.8649 29.6673 6.33274C28.1351 4.80057 26.3161 3.58519 24.3143 2.75599C22.3124 1.92678 20.1668 1.5 18 1.5C15.8332 1.5 13.6876 1.92678 11.6857 2.75599C9.68385 3.58519 7.8649 4.80057 6.33274 6.33274C4.80057 7.8649 3.58519 9.68385 2.75599 11.6857C1.92678 13.6876 1.5 15.8332 1.5 18Z"/>
		</svg>

		<x-number-counter
			class="mb-2.5 self-start text-2xl font-semibold text-heading-foreground group-hover/card:motion-preset-pulse group-hover/card:motion-duration-500 group-hover/card:motion-loop-once"
			:value="$posts_stats['last_30_days']['published_posts']" :options="['delay' => 100]" :dynamic-value-listener="'published_posts'"/>
		<p class="m-0 text-sm font-medium opacity-70">
			@lang('Published Posts')
		</p>
	</x-card>

	<x-card
		class="lqd-social-media-overview-card flex flex-col justify-center text-heading-foreground transition-transform hover:-translate-y-1"
		class:body="flex flex-col justify-center rounded-[inherit]">
		<x-outline-glow class="rounded-[inherit] opacity-0 transition-opacity group-hover/card:opacity-100"
						class:inner="[animation-delay:1s!important]"/>
		<svg class="mb-8" width="25" height="36" viewBox="0 0 25 36" fill="none"
			 xmlns="http://www.w3.org/2000/svg" stroke="url(#social-posts-overview-gradient)" stroke-width="2"
			 stroke-linecap="round" stroke-linejoin="round">
			<path
				d="M2.2015 8.81998H22.3682M12.2848 17.9866C9.36745 17.9866 6.56955 19.1456 4.50665 21.2085C2.44375 23.2714 1.28483 26.0693 1.28483 28.9866V32.6533C1.28483 33.1395 1.47798 33.6059 1.8218 33.9497C2.16562 34.2935 2.63193 34.4866 3.11816 34.4866H21.4515C21.9377 34.4866 22.404 34.2935 22.7479 33.9497C23.0917 33.6059 23.2848 33.1395 23.2848 32.6533V28.9866C23.2848 26.0693 22.1259 23.2714 20.063 21.2085C18.0001 19.1456 15.2022 17.9866 12.2848 17.9866ZM12.2848 17.9866C9.36745 17.9866 6.56955 16.8277 4.50665 14.7648C2.44375 12.7019 1.28483 9.90402 1.28483 6.98664V3.31998C1.28483 2.83375 1.47798 2.36743 1.8218 2.02361C2.16562 1.6798 2.63193 1.48664 3.11816 1.48664H21.4515C21.9377 1.48664 22.404 1.6798 22.7479 2.02361C23.0917 2.36743 23.2848 2.83375 23.2848 3.31998V6.98664C23.2848 9.90402 22.1259 12.7019 20.063 14.7648C18.0001 16.8277 15.2022 17.9866 12.2848 17.9866Z"/>
		</svg>
		<x-number-counter
			class="mb-2.5 self-start text-2xl font-semibold text-heading-foreground group-hover/card:motion-preset-pulse group-hover/card:motion-duration-500 group-hover/card:motion-loop-once"
			:value="$posts_stats['last_30_days']['scheduled_posts']" :options="['delay' => 200]" :dynamic-value-listener="'scheduled_posts'"/>
		<p class="m-0 text-sm font-medium opacity-70">
			@lang('Scheduled Posts')
		</p>
	</x-card>

	<x-card
		class="lqd-social-media-overview-card flex flex-col justify-center text-heading-foreground transition-transform hover:-translate-y-1"
		class:body="flex flex-col justify-center rounded-[inherit]">
		<x-outline-glow class="rounded-[inherit] opacity-0 transition-opacity group-hover/card:opacity-100"
						class:inner="[animation-delay:0.1s!important]"/>
		<svg class="mb-8" width="36" height="36" viewBox="0 0 36 36" fill="none"
			 xmlns="http://www.w3.org/2000/svg" stroke="url(#social-posts-overview-gradient)" stroke-width="2"
			 stroke-linecap="round" stroke-linejoin="round">
			<path
				d="M6.39346 29.6539C4.86129 28.1217 3.64591 26.3028 2.81671 24.3009C1.98751 22.299 1.56072 20.1535 1.56072 17.9866C1.56072 15.8198 1.98751 13.6742 2.81671 11.6724C3.64591 9.67049 4.86129 7.85155 6.39346 6.31938C7.92563 4.78721 9.74457 3.57183 11.7464 2.74263C13.7483 1.91343 15.8939 1.48664 18.0607 1.48664C20.2275 1.48664 22.3731 1.91343 24.375 2.74263C26.3769 3.57183 28.1958 4.78721 29.728 6.31938M6.39346 29.6539C7.92563 31.1861 9.74457 32.4015 11.7464 33.2307C13.7483 34.0599 15.8939 34.4866 18.0607 34.4866C20.2275 34.4866 22.3731 34.0599 24.375 33.2307C26.3769 32.4015 28.1958 31.1861 29.728 29.6539C31.2602 28.1217 32.4755 26.3028 33.3047 24.3009C34.1339 22.299 34.5607 20.1535 34.5607 17.9866C34.5607 15.8198 34.1339 13.6742 33.3047 11.6724C32.4755 9.67049 31.2602 7.85155 29.728 6.31938M6.39346 29.6539L29.728 6.31938"/>
		</svg>
		<x-number-counter
			class="mb-2.5 self-start text-2xl font-semibold text-heading-foreground group-hover/card:motion-preset-pulse group-hover/card:motion-duration-500 group-hover/card:motion-loop-once"
			:value="$posts_stats['last_30_days']['failed_posts']" :options="['delay' => 300]" :dynamic-value-listener="'failed_posts'"/>
		<p class="m-0 text-sm font-medium opacity-70">
			@lang('Failed Posts')
		</p>
	</x-card>
</div>
<div class="col-span-full grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4 lg:py-2.5">
	<div class="col-span-full flex items-center gap-7">
		<h3 class="m-0">
			@lang('Social Media Posts')
		</h3>

		<span class="inline-flex h-px grow bg-border"></span>

		<x-button class="!text-2xs" variant="link" href="{{ route('dashboard.user.social-media.post.index') }}">
			{{ __('View All') }}
			<x-tabler-circle-chevron-right class="size-5" stroke-width="1.5"/>
		</x-button>
	</div>

	@forelse (SocialMediaPost::query()->where('user_id', auth()->id())->take(4)->get() as $post)
		<x-card class="lqd-social-media-post relative flex flex-col hover:-translate-y-1"
				class:body="pt-24 px-5 flex flex-col static">
			<div class="absolute start-0 top-0 size-[77px]">
				<x-shape-cutout-2 style="--border-radius: 12px;" position="ts"/>

				<span
					class="relative z-2 inline-grid size-16 place-items-center rounded-lg border transition-all group-hover/card:border-primary group-hover/card:bg-primary group-hover/card:text-primary-foreground [&_svg]:size-[18px] group-hover/card:[&_svg]:fill-current"
				>
					{!! getSocialMediaIcon($post?->getPlatformEnum()?->value) !!}
            </span>
			</div>

			<div class="lqd-social-media-post-details flex grow flex-col font-medium text-heading-foreground">
				<div class="lqd-social-media-post-details-masked mb-2.5">
					@if (isset($post['image']))
						<figure
							class="lqd-social-media-post-fig mb-2.5 aspect-[1/0.5] w-full overflow-hidden rounded-lg shadow-sm">
							<img class="lqd-social-media-post-img h-full w-full object-cover object-center"
								 loading="lazy" decoding="async" src="{{ $post['image'] }}" alt="@lang('Social Media Post')"/>
						</figure>
					@endif
					@if (isset($post['content']))
						<p class="lqd-social-media-post-content">
							{{ str()->words($post['content'], isset($post['image']) ? 11 : 23) }}
						</p>
					@endif
				</div>
				<div class="lqd-social-media-post-status mt-auto text-[12px] leading-tight">
                    <span @class([
                        'lqd-social-media-post-status-pill inline-flex items-center gap-1.5 border py-1 rounded-full px-1.5',
                        'text-green-500' =>
                            $post['status'] ===
                            \App\Extensions\SocialMedia\System\Enums\StatusEnum::published,
                        'text-foreground' =>
                            $post['status'] ===
                            \App\Extensions\SocialMedia\System\Enums\StatusEnum::scheduled,
                    ])>
                        @if ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::published)
							<x-tabler-check class="size-3.5"/>
						@elseif ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::scheduled)
							<x-tabler-clock class="size-3.5"/>
						@else
							<x-tabler-circle-dashed class="size-3.5"/>
						@endif
						{{ str()->title($post['status']->value) }}
                    </span>
				</div>
			</div>

			<a class="absolute inset-0 z-0"
			   href="{{ route('dashboard.user.social-media.post.index', ['show' => $post['id']]) }}"></a>
		</x-card>
	@empty
		<h4 class="col-span-full text-lg">
			@lang('No posts have been added yet.')
		</h4>
	@endforelse
</div>
