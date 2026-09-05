@php
    $current_theme = Theme::get() ?? 'default';
    $is_auth = Auth::check();
@endphp

@if ($current_theme !== 'oupi' || ($current_theme === 'oupi' && !$is_auth))
    <div @class([
        'lqd-chat-pro-header relative end-0 start-0 top-0 z-40 h-[--header-h] justify-between gap-3 bg-background px-3.5 transition-all md:px-5 lg:absolute lg:start-[--sidebar-w] lg:z-10 xl:px-8',
        'hidden lg:flex' => Auth::check(),
        'flex border-b max-md:-mx-2 md:max-lg:-mx-5' => !Auth::check(),
    ])>
        @include('ai-chat-pro::includes.header.header-left-col')
        @include('ai-chat-pro::includes.header.header-mid-col')
        @include('ai-chat-pro::includes.header.header-right-col')
    </div>
@elseif($current_theme === 'oupi' && $is_auth)
    @include('ai-chat-pro::includes.header.header-left-col-oupi')

    @push('site-header-actions')
        @if (!in_array($category?->slug, ['ai_pdf', 'ai_vision', 'ai_chat_image'], true))
            @auth
                <span
                    class="relative inline-grid size-9 cursor-pointer place-items-center rounded-full border text-heading-foreground has-[input:checked]:border-primary has-[input:checked]:bg-primary has-[input:checked]:text-primary-foreground has-[input:checked]:shadow-lg"
                >
                    <input
                        class="realtime-checkbox peer absolute inset-0 z-0 size-full cursor-pointer opacity-0"
                        id="realtime"
                        type="checkbox"
                        name="realtime"
                        @change.prevent="[...document.querySelectorAll('.realtime-checkbox')].filter(el => el !== this).forEach(checkbox => checkbox.checked = this.checked)"
                        onchange="const checked = document.querySelector('#realtime').checked; if ( checked ) { toastr.success('Real-Time data activated') } else { toastr.warning('Real-Time data deactivated') }"
                    >
                    <x-tabler-world-download class="size-[18px]" />
                </span>
            @else
                <span
                    class="relative inline-grid size-9 cursor-pointer place-items-center rounded-full border text-heading-foreground"
                    @click.prevent="toastr.warning('{{ __('Login to use Real-Time data') }}');"
                >
                    <x-tabler-world-download class="size-[18px]" />
                </span>
            @endauth
        @endif
    @endpush
@endif
