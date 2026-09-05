@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
@endphp

<div class="lqd-social-media-posts-grid-wrap">
    <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <h3 class="m-0">
            @lang('Social Media Posts')
        </h3>

        <x-button
            class="text-2xs"
            variant="link"
            href="{{ route('dashboard.user.social-media.post.index') }}"
        >
            @lang('View All')
            <x-tabler-chevron-right class="size-4" />
        </x-button>
    </div>

    @if (filled($posts))
        <div class="lqd-social-media-posts-grid grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($posts as $post)
                @php
                    $image = 'vendor/social-media/icons/' . $post['platform']?->platform . '.svg';
                    $image_dark_version = 'vendor/social-media/icons/' . $post['platform']?->platform . '-light.svg';
                    $darkImageExists = file_exists(public_path($image_dark_version));
                    $images = $post['images'] ?? null;
                    if (is_string($images)) {
                        $images = json_decode($images, true);
                    }
                    $postImages = is_array($images) && count($images) ? $images : ($post['image'] ? [$post['image']] : []);
                @endphp
                <x-card
                    class="lqd-social-media-post hover:scale-105 hover:shadow-lg hover:shadow-black/5"
                    class:body="pt-4 px-5"
                    x-data="{ currentSlide: 0, totalSlides: {{ count($postImages ?: []) }} }"
                >
                    <x-slot:head
                        class="flex items-center justify-start gap-3 border-none px-5 pb-0 pt-4"
                    >
                        <figure class="w-7 shrink-0">
                            <img
                                @class(['w-full h-auto', 'dark:hidden' => $darkImageExists])
                                src="{{ asset($image) }}"
                                alt="{{ $post['platform']?->platformLabel() }}"
                            />
                            @if ($darkImageExists)
                                <img
                                    class="hidden h-auto w-full dark:block"
                                    src="{{ asset($image_dark_version) }}"
                                    alt="{{ $post['platform']?->platformLabel() }}"
                                />
                            @endif
                        </figure>
                        {{--					--}}{{-- <x-dropdown.dropdown --}}
                        {{--					anchor="end" --}}
                        {{--					offsetY="15px" --}}
                        {{--				> --}}
                        {{--					<x-slot:trigger> --}}
                        {{--						<span class="sr-only"> --}}
                        {{--							@lang('Actions') --}}
                        {{--						</span> --}}
                        {{--						<x-tabler-dots-vertical class="size-4" /> --}}
                        {{--					</x-slot:trigger> --}}

                        {{--					<x-slot:dropdown --}}
                        {{--						class="p-2" --}}
                        {{--					> --}}
                        {{--						<x-button --}}
                        {{--							class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline" --}}
                        {{--							variant="link" --}}
                        {{--						> --}}
                        {{--							<x-tabler-pencil class="size-4" /> --}}
                        {{--							@lang('Edit') --}}
                        {{--						</x-button> --}}
                        {{--						<x-button --}}
                        {{--							class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline" --}}
                        {{--							variant="link" --}}
                        {{--						> --}}
                        {{--							<x-tabler-trash class="size-4" /> --}}
                        {{--							@lang('Delete') --}}
                        {{--						</x-button> --}}
                        {{--					</x-slot:dropdown> --}}
                        {{--				</x-dropdown.dropdown> --}}
                    </x-slot:head>

                    <div class="lqd-social-media-post-details font-medium text-heading-foreground">
                        <div
                            class="lqd-social-media-post-details-masked mb-3 max-h-36 overflow-hidden"
                            style="mask-image: linear-gradient(to bottom, black 70%, transparent)"
                        >
                            @if ($post['video'])
                                <figure class="lqd-social-media-post-fig mb-4 aspect-[1/0.5] w-full overflow-hidden rounded-lg shadow-sm">
                                    <video
                                        class="lqd-social-media-post-video h-full w-full object-cover object-center"
                                        src="{{ $post['video'] }}"
                                        loading="lazy"
                                    ></video>
                                </figure>
                            @elseif(count($postImages) > 1)
                                <div class="relative mb-4">
                                    <div class="relative aspect-[1/0.5] w-full overflow-hidden rounded-lg shadow-sm">
                                        @foreach ($postImages as $idx => $img)
                                            <img
                                                class="lqd-social-media-post-img absolute inset-0 h-full w-full object-cover object-center"
                                                src="{{ $img }}"
                                                alt="@lang('Social Media Post')"
                                                loading="lazy"
                                                decoding="async"
                                                x-show="currentSlide === {{ $idx }}"
                                                x-transition:enter="transition ease-out duration-300"
                                                x-transition:enter-start="opacity-0"
                                                x-transition:enter-end="opacity-100"
                                            >
                                        @endforeach
                                    </div>

                                    <button
                                        class="absolute left-1 top-1/2 flex size-6 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                                        x-show="currentSlide > 0"
                                        @click.prevent="currentSlide--"
                                        type="button"
                                    >
                                        <x-tabler-chevron-left class="size-3" />
                                    </button>
                                    <button
                                        class="absolute right-1 top-1/2 flex size-6 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                                        x-show="currentSlide < totalSlides - 1"
                                        @click.prevent="currentSlide++"
                                        type="button"
                                    >
                                        <x-tabler-chevron-right class="size-3" />
                                    </button>

                                    <div class="absolute bottom-1.5 left-1/2 flex -translate-x-1/2 gap-1">
                                        @foreach ($postImages as $idx => $img)
                                            <button
                                                class="size-1.5 rounded-full transition-all"
                                                :class="currentSlide === {{ $idx }} ? 'bg-white scale-110' : 'bg-white/50'"
                                                @click.prevent="currentSlide = {{ $idx }}"
                                                type="button"
                                            ></button>
                                        @endforeach
                                    </div>

                                    <span class="absolute right-1.5 top-1.5 rounded-full bg-black/50 px-1.5 py-0.5 text-[10px] text-white">
                                        <span x-text="currentSlide + 1"></span>/{{ count($postImages) }}
                                    </span>
                                </div>
                            @elseif(count($postImages) === 1)
                                <figure class="lqd-social-media-post-fig mb-4 aspect-[1/0.5] w-full overflow-hidden rounded-lg shadow-sm">
                                    <img
                                        class="lqd-social-media-post-img h-full w-full object-cover object-center"
                                        src="{{ $postImages[0] }}"
                                        alt="@lang('Social Media Post')"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                </figure>
                            @endif
                            @if (isset($post['content']))
                                <p class="lqd-social-media-post-content text-2xs/4">
                                    {{ $post['content'] }}
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-row items-center justify-between">
                            <p class="lqd-social-media-post-date mb-2.5 text-xs opacity-70">
                                {{ $post['created_at']->diffForHumans() }}
                            </p>
                        </div>
                        <div class="lqd-social-media-post-status text-[12px] leading-none">
                            <span @class([
                                'lqd-social-media-post-status-pill inline-flex items-center gap-1.5 border rounded-full px-2',
                                'text-green-500' =>
                                    $post['status'] ===
                                    \App\Extensions\SocialMedia\System\Enums\StatusEnum::published,
                                'text-foreground' =>
                                    $post['status'] ===
                                    \App\Extensions\SocialMedia\System\Enums\StatusEnum::scheduled,
                            ])>
                                @if ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::published)
                                    <x-tabler-check class="w-4" />
                                @elseif ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::scheduled)
                                    <x-tabler-clock class="w-4" />
                                @else
                                    <x-tabler-circle-dashed class="w-4" />
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
            @endforeach
        </div>
    @else
        <h4 class="col-span-full text-lg">
            @lang('No posts have been added yet.')
        </h4>
    @endif
</div>
