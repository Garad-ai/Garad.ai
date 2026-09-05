@php
    $inContent = false;
    $attributes = null;
@endphp

<form
    class="header-search group relative mb-7 transition-all max-lg:invisible max-lg:fixed max-lg:bottom-16 max-lg:left-0 max-lg:z-[99] max-lg:m-0 max-lg:me-0 max-lg:!w-full max-lg:origin-bottom max-lg:-translate-y-2 max-lg:scale-95 max-lg:opacity-0 max-lg:[&.lqd-is-active]:visible max-lg:[&.lqd-is-active]:translate-y-0 max-lg:[&.lqd-is-active]:scale-100 max-lg:[&.lqd-is-active]:opacity-100"
>
    <div class="relative w-full max-lg:bg-white max-lg:p-3 max-lg:dark:bg-zinc-800">
        <x-tabler-search
            class="{{ @twMerge('lqd-header-search-icon pointer-events-none absolute start-3 top-1/2 z-10 w-5 -translate-y-1/2 opacity-75 max-lg:start-6', $attributes?->get('class:icon')) }}"
            stroke-width="1.5"
        />
        <x-forms.input
            name="search"
            value="{{ request('search') }}"
            @class([
                'header-search-input ps-10 max-lg:rounded-md rounded-full border-clay bg-clay transition-colors',
            ])
            type="text"
            placeholder="{{ __('Search') }}"
        />
    </div>
</form>
