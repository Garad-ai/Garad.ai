@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
    use App\Helpers\Classes\Helper;

    $url_show_query = request()->query('show');
    $postImages = is_array($post['images'] ?? null) ? $post['images'] : (filled($post['image']) ? [$post['image']] : []);
@endphp

<div
    class="lqd-posts-item lqd-social-media-post-item relative grid w-full items-center gap-4 border-b p-3 text-2xs font-medium transition-all last:border-b-0 hover:bg-foreground/5 group-[&[data-view-mode=grid]]:min-h-48 group-[&[data-view-mode=grid]]:gap-0 group-[&[data-view-mode=grid]]:bg-card-background group-[&[data-view-mode=grid]]:pb-1 max-lg:block max-lg:!min-w-0 max-lg:space-y-3"
    x-data="{ currentSlide: 0, totalSlides: {{ count($postImages) }} }"
    @mouseenter="if ($refs.video) { $refs.video.load(); $refs.video._playPromise = $refs.video.play(); }"
    @mouseleave="if ($refs.video) { ($refs.video._playPromise || Promise.resolve()).then(() => $refs.video.pause()).catch(() => {}); }"
>
    <div
        class="lqd-posts-item-content sort-name grid grid-flow-col-dense flex-wrap items-center justify-start gap-3 text-sm transition-border group-[&[data-view-mode=grid]]:mb-1 group-[&[data-view-mode=grid]]:block group-[&[data-view-mode=grid]]:h-28 group-[&[data-view-mode=grid]]:items-start group-[&[data-view-mode=grid]]:overflow-hidden group-[&[data-view-mode=grid]]:border-b group-[&[data-view-mode=grid]]:pb-3 group-[&[data-view-mode=grid]]:pt-3 group-[&[data-view-mode=grid]]:text-2xs max-lg:group-[&[data-view-mode=list]]:!flex">
        <span class="mb-1 block w-full text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 group-[&[data-view-mode=grid]]:hidden lg:hidden">
            {{ __('Content') }}
        </span>

        @if (filled($post['video']))
            <video
                class="lqd-posts-item-video h-8 w-[38px] shrink-0 rounded-[4px] object-cover object-center shadow group-[&[data-view-mode=grid]]:mb-2 group-[&[data-view-mode=grid]]:aspect-video group-[&[data-view-mode=grid]]:h-auto group-[&[data-view-mode=grid]]:w-full group-[&[data-view-mode=grid]]:rounded-md"
                src="{{ custom_theme_url($post['video']) }}"
                preload="metadata"
                muted
                x-ref="video"
            ></video>
        @elseif (count($postImages) > 1)
            {{-- Grid View: Carousel for multiple images --}}
            <div class="relative hidden shrink-0 group-[&[data-view-mode=grid]]:mb-2 group-[&[data-view-mode=grid]]:block group-[&[data-view-mode=grid]]:w-full">
                <div class="relative aspect-video w-full overflow-hidden rounded-md shadow">
                    @foreach ($postImages as $idx => $img)
                        <img
                            class="absolute inset-0 h-full w-full object-cover object-center"
                            src="{{ ThumbImage(custom_theme_url($img)) }}"
                            alt="{{ __('Post image') }}"
                            loading="lazy"
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

            {{-- List View: First image with badge --}}
            <div class="relative shrink-0 group-[&[data-view-mode=grid]]:hidden">
                <img
                    class="lqd-posts-item-img h-8 w-[38px] shrink-0 rounded-[4px] object-cover object-center shadow"
                    src="{{ ThumbImage(custom_theme_url($postImages[0])) }}"
                    alt="{{ __('Post image') }}"
                    loading="lazy"
                />
                <span class="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-primary text-[9px] font-bold leading-none text-primary-foreground">
                    {{ count($postImages) }}
                </span>
            </div>
        @elseif(count($postImages) === 1)
            <div class="relative shrink-0">
                <img
                    class="lqd-posts-item-img h-8 w-[38px] shrink-0 rounded-[4px] object-cover object-center shadow group-[&[data-view-mode=grid]]:mb-2 group-[&[data-view-mode=grid]]:aspect-video group-[&[data-view-mode=grid]]:h-auto group-[&[data-view-mode=grid]]:w-full group-[&[data-view-mode=grid]]:rounded-md"
                    src="{{ ThumbImage(custom_theme_url($postImages[0])) }}"
                    alt="{{ __('Post image') }}"
                    loading="lazy"
                />
            </div>
        @endif
        <div class="lqd-posts-item-content-inner grow overflow-hidden group-[&[data-view-mode=grid]]:h-full">
            <p
                class="lqd-posts-item-title m-0 overflow-hidden overflow-ellipsis whitespace-nowrap group-[&[data-view-mode=grid]]:h-full group-[&[data-view-mode=grid]]:whitespace-normal">
                {{ str()->limit(strip_tags($post['content']), 30) }}
            </p>
        </div>
    </div>

    <span class="mb-1 block w-full text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 group-[&[data-view-mode=grid]]:hidden lg:hidden">
        {{ __('Status') }}
    </span>
    <p @class([
        'lqd-posts-item-type sort-file inline-flex w-auto m-0 items-center gap-1.5 justify-self-start whitespace-nowrap rounded-full border px-2 py-1 text-[12px] font-medium leading-none',
        'text-green-500' => $post['status'] === 'published',
        'text-yellow-700' => $post['status'] === 'scheduled',
    ])>
        @if ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::published)
            <x-tabler-check class="size-4" />
        @elseif ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::scheduled)
            <x-tabler-clock class="size-4" />
        @else
            <x-tabler-circle-dashed class="size-4" />
        @endif
        @lang(str()->title($post->status->value))
    </p>

    <span class="mb-1 block w-full text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 group-[&[data-view-mode=grid]]:hidden lg:hidden">
        {{ __('Publish Date') }}
    </span>

    <p class="lqd-posts-item-date sort-date m-0 group-[&[data-view-mode=list]]:font-normal">
        {{ date('M j Y', strtotime($post->scheduled_at)) }}
        <span class="opacity-50 group-[&[data-view-mode=grid]]:hidden">
            , {{ date('H:i', strtotime($post->scheduled_at)) }}
        </span>
    </p>

    <p
        class="lqd-posts-item-publish-type sort-publish-type m-0 group-[&[data-view-mode=grid]]:text-end group-[&[data-view-mode=grid]]:text-2xs group-[&[data-view-mode=list]]:font-normal">
        <span class="mb-1 block w-full text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 group-[&[data-view-mode=grid]]:hidden lg:hidden">
            {{ __('Type') }}
        </span>

        {{ optional($post->post_type)->value ? Str::headline($post->post_type?->value) : __('Post') }}
    </p>

    <p class="lqd-posts-item-likes sort-likes m-0 group-[&[data-view-mode=grid]]:hidden group-[&[data-view-mode=list]]:font-normal">
        <span class="mb-1 block w-full text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 group-[&[data-view-mode=grid]]:hidden lg:hidden">
            {{ __('Like Count') }}
        </span>

        {{ data_get($post->post_metrics, 'like_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo() }}
    </p>

    @php
		$platformType = is_string($post->social_media_platform) ? $platform->social_media_platform : $post->platform?->platform;


	$image = 'vendor/social-media/icons/' . $platformType . '.svg';
	$image_dark_version = 'vendor/social-media/icons/' . $platformType . '-light.svg';
	$darkImageExists = file_exists(public_path($image_dark_version));
    @endphp
    <figure class="lqd-posts-item-cost sort-cost">
        <span class="mb-1 block w-full text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 group-[&[data-view-mode=grid]]:hidden lg:hidden">
            {{ __('Platform') }}
        </span>

        <img
            @class(['w-8 h-auto max-h-8', 'dark:hidden' => $darkImageExists])
            src="{{ asset($image) }}"
            alt="{{ $platformType }}"
        />
        @if ($darkImageExists)
            <img
                class="hidden h-auto max-h-8 w-8 dark:block"
                src="{{ asset($image_dark_version) }}"
                alt="{{ $platformType }}"
            />
        @endif
    </figure>

    <div class="lqd-posts-item-actions flex flex-wrap items-center gap-2 font-normal lg:flex-nowrap lg:justify-end">
        <span class="mb-1 block w-full text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 group-[&[data-view-mode=grid]]:hidden lg:hidden">
            {{ __('Actions') }}
        </span>

        {{-- Duplicate Modal --}}
        <x-modal
            class:modal-head="border-b-0"
            class:modal-body="pt-3"
            class:modal-container="max-w-[540px] lg:w-[540px]"
        >
            <x-slot:trigger
                class="z-10 size-9 group-[&[data-view-mode=grid]]:hidden"
                size="none"
                variant="ghost-shadow"
                href="#"
                title="{{ __('Duplicate') }}"
            >
                <x-tabler-copy
                    class="size-4"
                    stroke-width="2.5"
                />
            </x-slot:trigger>

            <x-slot:modal>
                <form
                    action="#"
                    x-ref="form"
                    x-on:submit.prevent="submitDuplicate"
                >
                    <h3 class="mb-3.5">
                        @lang('Duplicate Post')
                    </h3>
                    <p class="mb-7 text-heading-foreground/60">
                        @lang('Choose the platform to duplicate the post.')
                    </p>
                    <input
                        type="hidden"
                        name="route"
                        value="{{ route('dashboard.user.social-media.post.duplicate', $post->id) }}"
                    >

                    <x-forms.input
                        type="select"
                        size="lg"
                        name="platform_id"
                        label="{{ __('Platform') }}"
                    >
                        @foreach (PlatformEnum::cases() as $platform)
                            <option
                                {{ $platform->platform()?->isConnected() ? '' : 'disabled' }}
                                value="{{ $platform->platform()?->id }}"
                            >
                                {{ str()->title($platform->value) }} ({{ $platform->platform()?->isConnected() ? __('Connected') : __('Not Connected') }})
                            </option>
                        @endforeach
                    </x-forms.input>

                    <div class="flex justify-end space-x-2 pt-7">
                        <x-button
                            variant="ghost-shadow"
                            type="button"
                            title="{{ __('Cancel') }}"
                            @click.prevent="modalOpen = false"
                        >
                            @lang('Cancel')
                        </x-button>
                        <x-button
                            variant="primary"
                            type="button"
                            @click="submitDuplicate"
                            title="{{ __('Duplicate') }}"
                        >
                            @lang('Duplicate')
                        </x-button>
                    </div>
                </form>
            </x-slot:modal>
        </x-modal>

        @if ($post['status'] === 'published')
            <x-button
                class="z-10 size-9 group-[&[data-view-mode=grid]]:hidden"
                size="none"
                variant="ghost-shadow"
                href="#"
                title="{{ __('Copy Link') }}"
                @click.prevent="navigator.clipboard.writeText('{{ $post['link'] }}'); toastr.success('{{ __('Link copied to clipboard.') }}')"
            >
                <x-tabler-link class="size-5" />
            </x-button>
        @endif

        <x-button
            class="z-10 size-9 group-[&[data-view-mode=grid]]:hidden"
            size="none"
            variant="ghost-shadow"
            title="{{ __('View') }}"
            href="{{ route('dashboard.user.social-media.post.show', $post['id']) }}"
            x-target="lqd-social-media-post-content"
            @ajax:before="onAjaxBefore()"
            @ajax:success="onAjaxSuccess()"
            @ajax:error="onAjaxError()"
            x-ref="viewBtn-{{ $post['id'] }}"
            x-init="{{ $url_show_query ? 'true' : 'false' }} && '{{ $url_show_query ? $url_show_query : '' }}' === '{{ $post['id'] }}' && $nextTick(() => $el.click())"
        >
            <x-tabler-eye class="size-5" />
        </x-button>

        @if ($post['status'] !== 'published')
            <x-dropdown.dropdown
                class:dropdown-dropdown="group-[&[data-view-mode=grid]]:top-auto group-[&[data-view-mode=grid]]:bottom-full"
                anchor="end"
                offsetY="5px"
                triggerType="click"
            >
                <x-slot:trigger
                    class="before:-star[5%]-0 z-10 size-9 p-0 text-foreground/50 before:absolute before:-top-[5%] before:h-[120%] before:w-[120%] hover:bg-background group-[&[data-view-mode=grid]]:-me-3 group-[&[data-view-mode=grid]]:text-base group-[&[data-view-mode=grid]]:text-foreground"
                    variant="ghost"
                    size="xs"
                >
                    <x-tabler-dots-vertical class="size-5 group-[&[data-view-mode=grid]]:h-4 group-[&[data-view-mode=grid]]:w-4" />
                </x-slot:trigger>

                <x-slot:dropdown
                    class="overflow-hidden p-1 text-2xs font-medium group-[&[data-view-mode=grid]]:-me-3"
                >
                    <x-button
                        class="w-full justify-start rounded-none px-3 py-2 text-2xs shadow-none hover:translate-y-0 hover:bg-foreground/5 hover:text-inherit hover:shadow-none focus-visible:bg-foreground/5 focus-visible:text-inherit group-[&[data-view-mode=grid]]:flex"
                        size="none"
                        variant="ghost-shadow"
                        hover-variant="danger"
                        href="{{ route('dashboard.user.social-media.post.edit', $post['id']) }}"
                    >
                        <x-tabler-pencil class="size-5" />
                        @lang('Edit')
                    </x-button>

                    @if ($app_is_demo)
                        <x-button
                            class="w-full justify-start rounded-none px-3 py-2 text-2xs shadow-none hover:translate-y-0 hover:bg-foreground/5 hover:text-inherit hover:shadow-none focus-visible:bg-foreground/5 focus-visible:text-inherit group-[&[data-view-mode=grid]]:flex"
                            size="none"
                            variant="ghost-shadow"
                            hover-variant="danger"
                            onclick="return toastr.info('This feature is not available in demo mode.')"
                        >
                            <x-tabler-circle-minus class="size-4 text-red-600" />
                            @lang('Delete')
                        </x-button>
                    @else
                        <x-button
                            class="w-full justify-start rounded-none px-3 py-2 text-2xs shadow-none hover:translate-y-0 hover:bg-foreground/5 hover:text-inherit hover:shadow-none focus-visible:bg-foreground/5 focus-visible:text-inherit group-[&[data-view-mode=grid]]:flex"
                            size="none"
                            variant="ghost-shadow"
                            hover-variant="danger"
                            href="{{ route('dashboard.user.social-media.post.delete', $post['id']) }}"
                            onclick="return confirm('Are you sure?')"
                        >
                            <x-tabler-circle-minus class="size-4 text-red-600" />
                            @lang('Delete')
                        </x-button>
                    @endif

                </x-slot:dropdown>
            </x-dropdown.dropdown>
        @endif
    </div>
</div>
