<div class="py-12 md:pb-32 md:pt-20">
    <div class="container max-w-5xl">
        <div class="mx-auto mb-12 text-center md:w-1/2">
            <h2 class="m-0">
                <span class="block">
                    <span class="text-[0.7em] opacity-60">
                        @lang('Have a question?')
                    </span>
                    👋
                </span>
                @lang('We’ve got you covered.')
            </h2>
        </div>

        <div x-data="{ activeIndex: -1 }">
            @foreach ($faq as $item)
                <div
                    class="group border-b"
                    :class="{ 'active': activeIndex == {{ $loop->index }} }"
                >
                    <h4 class="m-0">
                        <button
                            class="flex w-full items-center justify-between gap-1 px-5 py-8 text-start text-base font-medium leading-tight"
                            type="button"
                            @click="activeIndex = activeIndex == {{ $loop->index }} ? -1 : {{ $loop->index }}"
                        >
                            {{ $item->question }}

                            <x-tabler-chevron-down class="size-5 shrink-0 transition-transform group-[&.active]:rotate-180" />
                        </button>
                    </h4>
                    <div
                        x-show="activeIndex == {{ $loop->index }}"
                        x-collapse
                        x-cloak
                    >
                        <div class="px-5 pb-7">
                            <p>
                                {{ $item->answer }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
