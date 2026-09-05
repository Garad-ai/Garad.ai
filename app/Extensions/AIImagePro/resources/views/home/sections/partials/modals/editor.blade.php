<div class="grid grid-cols-1 gap-x-5 gap-y-7 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
    @foreach ($tools as $tool)
        <div
            class="group/item relative"
            title="{{ __($tool['description']) }}"
        >
            <figure
                class="relative mb-1.5 w-full overflow-hidden rounded-lg shadow-md shadow-black/10 transition group-hover/item:scale-105 group-hover/item:shadow-lg group-hover/item:shadow-black/5"
            >
                <img
                    class="scale-105 transition group-hover/item:scale-100"
                    src="{{ custom_theme_url($tool['image']) }}"
                    alt="{{ $tool['title'] }}"
                />

                @if ($tool['premium'])
                    <span class="absolute start-3 top-3 z-2 inline-flex items-center gap-1.5 rounded bg-secondary px-2 py-1 text-3xs font-medium text-secondary-foreground">
                        <svg
                            width="19"
                            height="15"
                            viewBox="0 0 19 15"
                            fill="none"
                            stroke="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M7.74854 7.49995L5.99854 5.57495L6.52354 4.69995M4.24854 1.375H14.7485L17.3735 5.75L9.93604 14.0625C9.87901 14.1207 9.81094 14.1669 9.73581 14.1985C9.66069 14.2301 9.58002 14.2463 9.49854 14.2463C9.41705 14.2463 9.33638 14.2301 9.26126 14.1985C9.18613 14.1669 9.11806 14.1207 9.06104 14.0625L1.62354 5.75L4.24854 1.375Z"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        @lang('Premium')
                    </span>
                @endif
            </figure>

            <p class="mb-0.5 w-full truncate text-3xs font-medium opacity-60">
                {{ __($tool['description']) }}
            </p>

            <p class="mb-0 text-2xs font-medium text-heading-foreground">
                {{ __($tool['title']) }}
            </p>

            <a
                class="absolute inset-0"
                href="{{ route('dashboard.user.openai.generator', ['slug' => 'ai_image_generator']) }}"
                @click.prevent="sessionStorage.setItem('pendingToolAction', JSON.stringify({ action: '{{ $tool['action'] }}', title: '{{ $tool['title'] }}'})); window.location.href = '/dashboard/user/advanced-image';"
            >
                <span class="sr-only">{{ __('View Template') }}</span>
            </a>
        </div>
    @endforeach
</div>
