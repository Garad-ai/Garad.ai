@php
    $classes = @twMerge('lqd-focus-mode-switch size-6 max-lg:size-10 relative hidden items-center justify-center hover:scale-95 hover:bg-transparent max-lg:rounded-full max-lg:border max-lg:dark:bg-white/[3%] md:flex', $class);
@endphp

<x-button
    :class="$classes"
    variant="link"
    href="#"
    title="{{ __('Focus Mode') }}"
    x-data="{}"
    @click.prevent="$store.focusMode.toggle()"
>
    <x-tabler-focus-2
        class="size-5"
        stroke-width="1.5"
    />
    <span
        class="duration-250 absolute start-1/2 top-1/2 inline-block h-[90%] w-[1.5px] -translate-x-1/2 -translate-y-1/2 rotate-0 scale-0 rounded bg-current transition-transform group-[&.focus-mode]/body:-rotate-45 group-[&.focus-mode]/body:scale-100"
        aria-hidden="true"
    ></span>
</x-button>
