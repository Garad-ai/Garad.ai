<button
    data-target="{{ $target }}"
    @class([
        'group grow [&.lqd-is-active]:bg-[#F8FAFB] text-[#768793] rounded-full px-8 py-2 transition-all max-md:w-full',
        'lqd-is-active' => $active,
    ])
>
    <span class="group-[&.lqd-is-active]:text-gradient">
        {{ ucfirst($label) }}
    </span>
    @if (!empty($badge))
        <span class="text-gradient ms-1 inline-block text-2xs leading-none">
            {{ $badge }}
        </span>
    @endif
</button>
