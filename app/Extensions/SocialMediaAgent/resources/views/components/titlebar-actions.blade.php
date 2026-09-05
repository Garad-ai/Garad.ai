@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
    use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
    use Illuminate\Support\Str;

    $new_chat_url = route('dashboard.user.openai.chat.chat');

    if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('social-media-agent')) {
        $new_chat_url = route('dashboard.user.social-media.agent.chat.index');
    }
    if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro')) {
        $new_chat_url = route('dashboard.user.social-media.agent.chat.index');
    }

    $platforms_list = SocialMediaPlatform::query()->where('user_id', Auth::id())->get()->groupBy('platform')->map(fn($items) => $items->first())->values();

    $videoPlatforms = ['youtube', 'youtube-shorts'];
@endphp

<x-button
    variant="ghost-shadow"
    href="{{ $new_chat_url }}"
>
    <svg
        width="11"
        height="15"
        viewBox="0 0 11 15"
        fill="currentColor"
        xmlns="http://www.w3.org/2000/svg"
    >
        <path
            d="M10.8661 7.34374L3.86607 14.8437C3.79189 14.9229 3.69398 14.9758 3.5871 14.9944C3.48022 15.0131 3.37019 14.9964 3.27359 14.947C3.177 14.8977 3.09909 14.8182 3.05162 14.7206C3.00415 14.6231 2.9897 14.5127 3.01045 14.4062L3.9267 9.82312L0.324825 8.47062C0.24747 8.44169 0.178487 8.39404 0.12404 8.33194C0.0695919 8.26984 0.0313755 8.19522 0.0128044 8.11474C-0.00576672 8.03427 -0.00411393 7.95044 0.0176149 7.87076C0.0393438 7.79108 0.0804719 7.71803 0.137325 7.65812L7.13732 0.15812C7.2115 0.0789551 7.30942 0.026065 7.4163 0.00743103C7.52317 -0.0112029 7.63321 0.00543054 7.72981 0.0548213C7.8264 0.104212 7.90431 0.18368 7.95178 0.281234C7.99925 0.378788 8.0137 0.489134 7.99295 0.59562L7.0742 5.18374L10.6761 6.53437C10.7529 6.5635 10.8213 6.61109 10.8753 6.67295C10.9293 6.7348 10.9673 6.80901 10.9858 6.88902C11.0044 6.96902 11.0029 7.05236 10.9816 7.13167C10.9603 7.21098 10.9197 7.28382 10.8636 7.34374H10.8661Z"
        />
    </svg>
    {{ __('New Chat') }}
</x-button>

@if (request()->route()->getName() === 'dashboard.user.social-media.agent.agents')
    <x-button href="{{ route('dashboard.user.social-media.agent.create') }}">
        {{ __('Add Agent') }}
    </x-button>
@else
    <div class="group relative">
        <x-button href="{{ route('dashboard.user.social-media.agent.posts') }}">
            {{ __('New Post') }}
        </x-button>

        @if ($platforms_list && $platforms_list->count() > 0)
            <div
                class="invisible absolute end-0 top-full z-10 mt-4 flex w-fit origin-top -translate-y-1 scale-95 items-center justify-between gap-3 rounded-[20px] bg-background/70 p-7 opacity-0 shadow-2xl shadow-black/10 backdrop-blur-xl transition-all before:absolute before:inset-x-0 before:bottom-full before:h-4 group-hover:visible group-hover:translate-y-0 group-hover:scale-100 group-hover:opacity-100 dark:border dark:border-foreground/5 lg:gap-7"
                x-data="{}"
            >
                @foreach ($platforms_list as $platform)
                    @if (in_array($platform->platform, $videoPlatforms))
                        @continue
                    @endif
                    @php
                        $image = asset('vendor/social-media/icons/' . $platform->platform . '.svg');
                        $image_dark_version = asset('vendor/social-media/icons/' . $platform->platform . '-mono-light.svg');
                        $platformLabel = PlatformEnum::tryFrom($platform->platform)?->label() ?? Str::headline(str_replace('-', ' ', $platform->platform));
                    @endphp

                    <a
                        class="size-8 shrink-0 transition hover:scale-110"
                        @if (request()->route()->getName() === 'dashboard.user.social-media.agent.posts') href="#" @click.prevent="$dispatch('create-post-modal-show', {show: true, platformId: {{ $platform->id }}})"
						@else href="{{ route('dashboard.user.social-media.agent.posts', ['create_post_for' => $platform->id]) }}" @endif
                        title="@lang('Compose a post on :platform', ['platform' => $platformLabel])"
                    >
                        <img
                            class="size-full dark:hidden"
                            src="{{ $image }}"
                            alt="@lang(':platform logo', ['platform' => $platformLabel])"
                        />
                        <img
                            class="hidden size-full dark:block"
                            src="{{ $image_dark_version }}"
                            alt="@lang(':platform logo', ['platform' => $platformLabel])"
                        />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
