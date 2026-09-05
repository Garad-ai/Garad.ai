@php
    use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
    use Illuminate\Support\Carbon;
    use App\Extensions\SocialMedia\System\Enums\StatusEnum;

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

    $posts = [
        'all_posts' => $query->all_posts ?? 0,
        'published_posts' => $query->published_posts ?? 0,
        'scheduled_posts' => $query->scheduled_posts ?? 0,
        'failed_posts' => $query->failed_posts ?? 0,
    ];

    $posts_stats = [
        'last_30_days' => $posts,
    ];
@endphp

<x-card
    class="w-full"
    class:body="grid grid-cols-1 gap-5 md:grid-cols-2 lg:max-xl:grid-cols-3 lg:grid-cols-4"
    id="summary"
>
    <x-slot:head
        class="border-0 px-7 pb-0 pt-5"
    >
        <div class="flex items-center justify-between">
            <h4 class="m-0 text-[17px]"> @lang('Social Media Posts')</h4>
            <x-button
                variant="link"
                href="{{ route('dashboard.user.social-media.post.index') }}"
            >
                <span class="text-nowrap font-bold text-foreground"> {{ __('View All') }} </span>
                <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
            </x-button>
        </div>
    </x-slot:head>

    @forelse (SocialMediaPost::query()->where('user_id', auth()->id())->take(4)->get() as $post)
        <x-card
            class="lqd-social-media-post relative flex flex-col hover:-translate-y-1"
            class:body="px-5 flex flex-col gap-4 justify-between"
        >
            <div>
                <span class="relative z-2 inline-grid size-6 place-items-center rounded-lg [&_svg]:size-[18px]">
                    {!! getSocialMediaIcon($post?->getPlatformEnum()?->value) !!}
                </span>
            </div>

            <div class="lqd-social-media-post-details flex flex-col font-medium">
                <div class="lqd-social-media-post-details-masked mb-2.5">
                    @if (isset($post['image']))
                        <figure class="lqd-social-media-post-fig mb-2.5 aspect-[1/0.5] w-full overflow-hidden rounded-lg shadow-sm">
                            <img
                                class="lqd-social-media-post-img h-full w-full object-cover object-center"
                                src="{{ $post['image'] }}"
                                alt="@lang('Social Media Post')"
								loading="lazy"
								decoding="async"
                            />
                        </figure>
                    @endif
                    @if (isset($post['content']))
                        <p class="lqd-social-media-post-content mb-0">
                            {{ str()->words($post['content'], isset($post['image']) ? 11 : 23) }}
                        </p>
                    @endif
                </div>
                <span class="mb-2.5 text-[14px] font-medium leading-4 text-foreground/50">
                    {{ $post->created_at->format('F j, Y \a\t ga') }}
                </span>
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
                            <x-tabler-check class="size-3.5" />
                        @elseif ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::scheduled)
                            <x-tabler-clock class="size-3.5" />
                        @else
                            <x-tabler-circle-dashed class="size-3.5" />
                        @endif
                        {{ str()->title($post['status']->value) }}
                    </span>
                </div>
            </div>

            <a
                class="absolute inset-0 z-0"
                href="{{ route('dashboard.user.social-media.post.index', ['show' => $post['id']]) }}"
            ></a>
        </x-card>
    @empty
        <h4 class="col-span-full text-lg">
            @lang('No posts have been added yet.')
        </h4>
    @endforelse
</x-card>
