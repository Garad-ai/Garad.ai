@php
    $primary_tools = array_filter($tools, fn($tool) => in_array($tool['action'], $primary_tool_keys));
    $secondary_tools = array_filter($tools, fn($tool) => !in_array($tool['action'], $primary_tool_keys));
@endphp

<div
    class="lqd-adv-img-editor-toolbar group/toolbar fixed inset-y-0 start-8 z-5 flex flex-col justify-center gap-3.5 py-[calc(var(--header-h)+1rem)] transition-all max-md:bottom-0 max-md:end-0 max-md:start-0 max-md:top-auto max-md:h-[70px] max-md:flex-row max-md:justify-normal max-md:overflow-x-auto max-md:bg-background/10 max-md:px-4 max-md:py-2 max-md:backdrop-blur-xl max-md:backdrop-saturate-[120%] md:pointer-events-none"
    :class="{
        'md:-translate-x-2 md:opacity-0 md:invisible': toolbarCollapsed
    }"
>
    @foreach ($primary_tools as $tool)
        <button
            class="group/btn pointer-events-auto relative inline-grid size-[52px] shrink-0 place-items-center rounded-full border transition-all motion-duration-[0.25s] motion-ease hover:scale-110 hover:border-primary hover:bg-primary hover:text-primary-foreground hover:shadow-xl hover:shadow-black/5 max-md:bg-background [&.active]:border-primary [&.active]:bg-primary [&.active]:text-primary-foreground md:group-[&.active]/editor:[&.lqd-invisible]:motion-scale-out-[0.5] md:group-[&.active]/editor:[&.lqd-invisible]:motion-opacity-out-[0%] md:group-[&.active]/editor:[&.lqd-visible]:motion-scale-in-[1.5] md:group-[&.active]/editor:[&.lqd-visible]:motion-opacity-in-[0%]"
            :style="{
                'animation-delay': currentToolsCat === 'primary' ? '{{ (count($primary_tools) - $loop->index) * 0.05 }}s' :
                    '{{ (count($secondary_tools) - $loop->index - 1) * 0.05 }}s'
            }"
            @click.prevent="selectedTool = '{{ $tool['action'] }}'"
            :class="{
                'active': selectedTool === '{{ $tool['action'] }}',
                'lqd-invisible': currentToolsCat !== 'primary',
                'lqd-visible': currentToolsCat === 'primary'
            }"
        >
            {!! $tool['icon'] !!}
            <span
                class="pointer-events-none invisible absolute start-full top-1/2 z-2 ms-3 inline-block origin-left -translate-y-1/2 scale-90 whitespace-nowrap rounded-full bg-heading-foreground px-3 py-2 text-2xs font-medium text-header-background opacity-0 blur-sm transition-all group-hover/btn:visible group-hover/btn:scale-100 group-hover/btn:opacity-100 group-hover/btn:blur-0 max-md:hidden"
            >
                {{ $tool['title'] }}
            </span>
        </button>
    @endforeach
    <div class="relative flex flex-col gap-3.5">
        <div
            class="flex gap-3.5 transition-all duration-500 md:absolute md:bottom-full md:mb-3.5 md:flex-col"
            :class="{ 'md:opacity-0 md:invisible md:pointer-events-none': currentToolsCat !== 'secondary' }"
        >
            @foreach ($secondary_tools as $tool)
                <button
                    class="group/btn pointer-events-auto relative inline-grid size-[52px] shrink-0 place-items-center rounded-full border transition-all motion-duration-[0.25s] motion-ease hover:scale-110 hover:border-primary hover:bg-primary hover:text-primary-foreground hover:shadow-xl hover:shadow-black/5 max-md:bg-background [&.active]:border-primary [&.active]:bg-primary [&.active]:text-primary-foreground md:group-[&.active]/editor:[&.lqd-invisible]:motion-scale-out-[0.5] md:group-[&.active]/editor:[&.lqd-invisible]:motion-opacity-out-[0%] md:group-[&.active]/editor:[&.lqd-visible]:motion-scale-in-[1.5] md:group-[&.active]/editor:[&.lqd-visible]:motion-opacity-in-[0%]"
                    :style="{
                        'animation-delay': currentToolsCat === 'secondary' ? '{{ (count($secondary_tools) - $loop->index) * 0.05 }}s' :
                            '{{ (count($primary_tools) - $loop->index - 1) * 0.05 }}s'
                    }"
                    @click.prevent="selectedTool = '{{ $tool['action'] }}'"
                    :class="{
                        'active': selectedTool === '{{ $tool['action'] }}',
                        'lqd-invisible': currentToolsCat !== 'secondary',
                        'lqd-visible': currentToolsCat === 'secondary'
                    }"
                >
                    {!! $tool['icon'] !!}
                    <span
                        class="pointer-events-none invisible absolute start-full top-1/2 z-2 ms-3 inline-block origin-left -translate-y-1/2 scale-90 whitespace-nowrap rounded-full bg-heading-foreground px-3 py-2 text-2xs font-medium text-header-background opacity-0 blur-sm transition-all group-hover/btn:visible group-hover/btn:scale-100 group-hover/btn:opacity-100 group-hover/btn:blur-0 max-md:hidden"
                    >
                        {{ $tool['title'] }}
                    </span>
                </button>
            @endforeach
        </div>

        <button
            class="group/btn pointer-events-auto relative inline-grid size-[52px] shrink-0 place-items-center rounded-full border transition-all motion-duration-[0.25s] hover:scale-110 hover:bg-primary hover:text-primary-foreground hover:shadow-xl hover:shadow-black/5 group-[&.active]/editor:motion-scale-in-[1.5] group-[&.active]/editor:motion-opacity-in-[0%] max-md:hidden"
            @click.prevent="switchToolsCat()"
        >
            <svg
                class="col-start-1 col-end-1 row-start-1 row-end-1 transition-all"
                width="4"
                height="16"
                viewBox="0 0 4 16"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                :class="{ '-rotate-90': currentToolsCat !== 'primary', 'scale-50': currentToolsCat !== 'primary', 'opacity-0': currentToolsCat !== 'primary' }"
            >
                <path
                    d="M2 15.2692C1.5875 15.2692 1.23442 15.1223 0.94075 14.8285C0.646917 14.5348 0.5 14.1817 0.5 13.7692C0.5 13.3567 0.646917 13.0035 0.94075 12.7097C1.23442 12.416 1.5875 12.2692 2 12.2692C2.4125 12.2692 2.76558 12.416 3.05925 12.7097C3.35308 13.0035 3.5 13.3567 3.5 13.7692C3.5 14.1817 3.35308 14.5348 3.05925 14.8285C2.76558 15.1223 2.4125 15.2692 2 15.2692ZM2 9.49996C1.5875 9.49996 1.23442 9.35305 0.94075 9.05921C0.646917 8.76555 0.5 8.41246 0.5 7.99996C0.5 7.58746 0.646917 7.23438 0.94075 6.94071C1.23442 6.64688 1.5875 6.49996 2 6.49996C2.4125 6.49996 2.76558 6.64688 3.05925 6.94071C3.35308 7.23438 3.5 7.58746 3.5 7.99996C3.5 8.41246 3.35308 8.76555 3.05925 9.05921C2.76558 9.35305 2.4125 9.49996 2 9.49996ZM2 3.73071C1.5875 3.73071 1.23442 3.58388 0.94075 3.29021C0.646917 2.99638 0.5 2.64321 0.5 2.23071C0.5 1.81821 0.646917 1.46513 0.94075 1.17146C1.23442 0.87763 1.5875 0.730713 2 0.730713C2.4125 0.730713 2.76558 0.87763 3.05925 1.17146C3.35308 1.46513 3.5 1.81821 3.5 2.23071C3.5 2.64321 3.35308 2.99638 3.05925 3.29021C2.76558 3.58388 2.4125 3.73071 2 3.73071Z"
                    fill="currentColor"
                />
            </svg>
            <x-tabler-arrow-left
                class="col-start-1 col-end-1 row-start-1 row-end-1 size-5 transition-all"
                ::class="{ 'rotate-90': currentToolsCat === 'primary', 'scale-50': currentToolsCat === 'primary', 'opacity-0': currentToolsCat === 'primary' }"
            />
            <span
                class="pointer-events-none invisible absolute start-full top-1/2 ms-3 inline-block origin-left -translate-y-1/2 scale-90 whitespace-nowrap rounded-full bg-heading-foreground px-3 py-2 text-2xs font-medium text-header-background opacity-0 blur-sm transition-all group-hover/btn:visible group-hover/btn:scale-100 group-hover/btn:opacity-100 group-hover/btn:blur-0 max-md:hidden"
            >
                @lang('Other Enhance Tools')
            </span>
        </button>
    </div>
</div>
