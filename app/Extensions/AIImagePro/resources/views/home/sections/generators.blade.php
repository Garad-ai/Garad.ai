<div class="pb-12 md:pb-20">
    <div class="container max-w-5xl">
        <div
            class="mb-14"
            x-data="{ activeIndex: 0 }"
        >
            <div class="flex gap-8 border-b pb-px max-md:overflow-x-auto max-md:whitespace-nowrap md:justify-center">
                @foreach ($generatorsList as $item)
                    <button
                        @class([
                            '-mb-px border-b border-transparent px-2 py-4 text-2xs font-semibold text-heading-foreground opacity-60 transition [&.active]:border-b-current [&.active]:opacity-100',
                            'active' => $loop->first,
                        ])
                        type="button"
                        @click="activeIndex = {{ $loop->index }}"
                        :class="{ 'active': activeIndex === {{ $loop->index }} }"
                    >
                        {{ $item->menu_title }}
                    </button>
                @endforeach
            </div>

            <div class="mt-10">
                <div class="grid grid-cols-1 place-items-start">
                    @foreach ($generatorsList as $item)
                        <div
                            class="col-start-1 col-end-1 row-start-1 row-end-1 flex w-full flex-wrap items-center justify-between gap-y-8"
                            x-show="activeIndex === {{ $loop->index }}"
                            @if (!$loop->first) x-cloak @endif
                            x-transition.opacity
                        >
                            <div class="w-full lg:w-1/2">
                                <img
                                    class="h-auto w-full rounded-xl"
                                    src="{{ custom_theme_url($item->image, true) }}"
                                    alt="{{ __($item->image_title) }}"
                                >
                            </div>

                            <div class="w-full lg:w-5/12">
                                <h5 class="mb-3.5 text-[19px] font-semibold">
                                    {!! $item->title !!}
                                </h5>

                                <p class="mb-3.5 text-pretty text-[18px] leading-[1.4em]">
                                    {!! $item->text !!}
                                </p>

                                {{-- TODO: add links --}}
                                <x-button
                                    class="text-sm font-medium"
                                    size="xl"
                                    href="#"
                                >
                                    {{ __('Try it free') }}
                                </x-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex h-px grow bg-foreground/10"></span>
            {{-- TODO: add link --}}
            <a
                class="text-[12px] text-heading-foreground"
                href="#"
            >
                <strong class="me-1.5">
                    {{ __('Need Help?') }}
                </strong>
                @lang('Learn more about <u>Image Pro</u>')
            </a>
            <span class="inline-flex h-px grow bg-foreground/10"></span>
        </div>
    </div>
</div>
