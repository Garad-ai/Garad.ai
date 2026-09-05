<section
    class="site-section relative py-28 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="clients"
>
    <div class="container">
        <p class="mb-8 text-center text-sm font-medium text-heading-foreground">
            {{ __('Trusted by these amazing companies') }}
        </p>
        <div class="flex items-center justify-center gap-20 opacity-80 max-lg:gap-12 max-md:flex-wrap max-sm:gap-4">
            @foreach ($clients as $entry)
                <img
                    src="{{ url('') . isset($entry->avatar) ? (str_starts_with($entry->avatar, 'asset') ? custom_theme_url($entry->avatar) : '/clientAvatar/' . $entry->avatar) : custom_theme_url('assets/img/auth/default-avatar.png') }}"
                    alt="{{ __($entry->alt) }}"
                    title="{{ __($entry->title) }}"
                >
                @if (!$loop->last)
                    <span class="h-9 w-px bg-heading-foreground/10"></span>
                @endif
            @endforeach
        </div>
    </div>
</section>
