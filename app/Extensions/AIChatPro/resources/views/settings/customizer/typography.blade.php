@php
    $typographyDefaults = [
        'body' => ['fontSize' => ['value' => 14, 'unit' => 'px'], 'lineHeight' => ['value' => 1.43, 'unit' => 'em']],
        'h1' => ['fontSize' => ['value' => 32, 'unit' => 'px'], 'lineHeight' => ['value' => 1.1, 'unit' => 'em']],
        'h2' => ['fontSize' => ['value' => 21, 'unit' => 'px'], 'lineHeight' => ['value' => 1.33, 'unit' => 'em']],
        'h3' => ['fontSize' => ['value' => 18, 'unit' => 'px'], 'lineHeight' => ['value' => 1.56, 'unit' => 'em']],
        'h4' => ['fontSize' => ['value' => 15, 'unit' => 'px'], 'lineHeight' => ['value' => 1.53, 'unit' => 'em']],
        'h5' => ['fontSize' => ['value' => 14, 'unit' => 'px'], 'lineHeight' => ['value' => 1.43, 'unit' => 'em']],
        'h6' => ['fontSize' => ['value' => 12, 'unit' => 'px'], 'lineHeight' => ['value' => 1.67, 'unit' => 'em']],
    ];
    $savedTypography = json_decode(setting('ai_chat_pro_typography', '{}'), true);
    $typographyProps = array_replace_recursive($typographyDefaults, is_array($savedTypography) ? $savedTypography : []);
@endphp

<div
    class="mb-2"
    x-data="aiChatProTypographyCustomizer"
>
    <input
        type="hidden"
        name="ai_chat_pro_typography"
        :value="propsJson"
    >

    <x-form-step
        auto-increment
        label="{{ __('Conversations Typography') }}"
    >
    </x-form-step>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <p class="m-0">
                {{ __('Customize conversations typography') }}
            </p>

            <x-modal
                class="shrink-0"
                class:modal-modal="overflow-hidden"
                class:modal-head="lg:p-0 lg:border-0"
                class:modal-content="w-[min(100%,985px)] h-[min(750px,95vh)] lg:overflow-visible"
                class:modal-body="lg:overflow-y-auto lg:h-full p-5 lg:[&>.container]:h-full lg:[&>.container]:flex lg:[&>.container]:flex-col"
                class:close-btn="lg:inline-grid lg:place-items-center lg:size-9 lg:rounded-full lg:bg-background lg:absolute lg:top-0 lg:-end-14 hover:bg-background hover:text-foreground"
            >
                <x-slot:trigger
                    variant="ghost-shadow"
                >
                    <x-tabler-brush class="size-4" />
                    {{ __('Open Customizer') }}
                </x-slot:trigger>
                <x-slot:modal>
                    <div class="flex gap-5 border-b border-foreground/5">
                        <button
                            class="py-3 text-[12px] font-semibold leading-tight text-foreground/50 transition-colors [&.active]:text-primary"
                            :class="{ 'active': activeTab === 'typography' }"
                            @click="activeTab = 'typography'"
                            type="button"
                        >
                            {{ __('Typography') }}
                        </button>
                    </div>

                    <div class="flex grow flex-wrap gap-5 md:flex-nowrap">
                        {{-- Left sidebar: controls --}}
                        <div class="w-full shrink-0 grow-0 py-5 md:w-72 md:border-e md:pe-4">
                            <div class="sticky top-0 space-y-1">
                                <template
                                    x-for="element in elements"
                                    :key="element.key"
                                >
                                    <div
                                        class="group"
                                        x-data="{ open: element.key === activeElement }"
                                    >
                                        {{-- Element header --}}
                                        <button
                                            class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-start text-xs font-medium transition-colors hover:bg-foreground/5"
                                            :class="{ 'bg-foreground/5': open }"
                                            type="button"
                                            @click="activeElement = element.key; open = !open"
                                        >
                                            <span x-text="element.label"></span>
                                            <x-tabler-chevron-down
                                                class="size-4 shrink-0 transition-transform"
                                                ::class="{ '-rotate-180': open }"
                                            />
                                        </button>

                                        {{-- Collapsible attribute controls --}}
                                        <div
                                            class="space-y-4 px-3 pb-3 pt-2"
                                            x-show="open"
                                            x-collapse
                                            x-cloak
                                        >
                                            <template
                                                x-for="attr in attributes"
                                                :key="attr.key"
                                            >
                                                <div class="flex flex-wrap items-center justify-between gap-1.5">
                                                    <p
                                                        class="mb-0 text-2xs font-medium"
                                                        x-text="attr.label"
                                                    ></p>

                                                    {{-- Numeric input + unit select --}}
                                                    <div class="inline-flex h-[25px] w-[85px] gap-0.5 rounded-lg bg-foreground/5">
                                                        <input
                                                            class="w-full appearance-none rounded-none border-none bg-transparent text-center text-2xs font-medium [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                            type="number"
                                                            :min="attr.min"
                                                            :max="attr.max"
                                                            :step="attr.step"
                                                            :value="getValue(element.key, attr.key)"
                                                            @input="setValue(element.key, attr.key, $event.target.value)"
                                                        >
                                                        <select
                                                            class="shrink-0 appearance-none rounded-none border-none bg-transparent pe-4 text-xs font-medium text-foreground/50 transition [background-position:right] [background-size:16px] focus-visible:text-foreground rtl:[background-position:left]"
                                                            :value="getUnit(element.key, attr.key)"
                                                            @change="setUnit(element.key, attr.key, $event.target.value)"
                                                        >
                                                            <template
                                                                x-for="u in attr.units"
                                                                :key="u"
                                                            >
                                                                <option
                                                                    :value="u"
                                                                    x-text="u"
                                                                    :selected="u === getUnit(element.key, attr.key)"
                                                                ></option>
                                                            </template>
                                                        </select>
                                                    </div>

                                                    {{-- Range slider with +/- buttons --}}
                                                    <div class="flex w-full items-center gap-4">
                                                        <button
                                                            class="inline-grid size-5 cursor-pointer place-items-center rounded transition"
                                                            type="button"
                                                            @click="decrement(element.key, attr.key)"
                                                        >
                                                            <x-tabler-minus class="size-3.5" />
                                                        </button>
                                                        <input
                                                            class="h-0.5 w-full appearance-none rounded-full bg-foreground/10 focus:outline-primary [&::-moz-range-thumb]:size-2.5 [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-none [&::-moz-range-thumb]:bg-foreground [&::-moz-range-thumb]:ring-[3px] [&::-moz-range-thumb]:ring-background active:[&::-moz-range-thumb]:scale-110 active:[&::-moz-range-thumb]:ring-foreground [&::-webkit-slider-thumb]:size-2.5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-none [&::-webkit-slider-thumb]:bg-foreground [&::-webkit-slider-thumb]:ring-[3px] [&::-webkit-slider-thumb]:ring-background active:[&::-webkit-slider-thumb]:scale-110 active:[&::-webkit-slider-thumb]:ring-foreground"
                                                            type="range"
                                                            :min="attr.min"
                                                            :max="attr.max"
                                                            :step="attr.step"
                                                            :value="getValue(element.key, attr.key)"
                                                            @input="setValue(element.key, attr.key, $event.target.value)"
                                                        />
                                                        <button
                                                            class="inline-grid size-5 cursor-pointer place-items-center rounded transition"
                                                            type="button"
                                                            @click="increment(element.key, attr.key)"
                                                        >
                                                            <x-tabler-plus class="size-3.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <hr class="!mt-4">

                                <x-button
                                    class="!mt-4 w-full"
                                    variant="outline"
                                    @click.prevent="restoreToDefaults"
                                >
                                    <x-tabler-restore class="size-4" />
                                    {{ __('Restore to Defaults') }}
                                </x-button>
                            </div>

                        </div>

                        {{-- Right panel: live preview --}}
                        <div class="lqd-chat-v2 flex-1">
                            <div class="border-t py-5 md:sticky md:top-0 md:border-t-0">
                                <h4 class="mb-3">
                                    {{ __('Preview') }}
                                </h4>
                                <div class="chat-content prose space-y-4 rounded-lg bg-foreground/[2%] !p-5 text-xs font-normal dark:prose-invert">
                                    <div :style="previewStyle('h1')">
                                        <h1
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Getting Started with AI
                                        </h1>
                                    </div>

                                    <div :style="previewStyle('body')">
                                        <p
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Artificial intelligence is transforming the way we work and create. From writing assistance to image generation, AI tools help you
                                            accomplish more in less time. This paragraph demonstrates how body text looks with your current typography settings.
                                        </p>
                                    </div>

                                    <div :style="previewStyle('h2')">
                                        <h2
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Key Features & Capabilities
                                        </h2>
                                    </div>

                                    <div :style="previewStyle('body')">
                                        <p
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Our platform offers a wide range of tools designed to boost your productivity. Whether you need help with content creation, data
                                            analysis,
                                            or creative projects, there is something here for everyone.
                                        </p>
                                    </div>

                                    <div :style="previewStyle('h3')">
                                        <h3
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Content Generation
                                        </h3>
                                    </div>

                                    <div :style="previewStyle('h4')">
                                        <h4
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Blog Posts & Articles
                                        </h4>
                                    </div>

                                    <div :style="previewStyle('body')">
                                        <p
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Generate high-quality written content for your blog, website, or social media channels with just a few clicks.
                                        </p>
                                    </div>

                                    <div :style="previewStyle('h5')">
                                        <h5
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Supported Output Formats
                                        </h5>
                                    </div>

                                    <div :style="previewStyle('h6')">
                                        <h6
                                            class="m-0"
                                            style="font-size: inherit; line-height: inherit;"
                                        >
                                            Note: All outputs can be exported as PDF or Markdown
                                        </h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot:modal>
            </x-modal>
        </div>
    </x-card>
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiChatProTypographyCustomizer', () => ({
                activeTab: 'typography',
                activeElement: 'body',

                elements: [{
                        key: 'body',
                        label: '{{ __('Body') }}'
                    },
                    {
                        key: 'h1',
                        label: '{{ __('Heading 1') }}'
                    },
                    {
                        key: 'h2',
                        label: '{{ __('Heading 2') }}'
                    },
                    {
                        key: 'h3',
                        label: '{{ __('Heading 3') }}'
                    },
                    {
                        key: 'h4',
                        label: '{{ __('Heading 4') }}'
                    },
                    {
                        key: 'h5',
                        label: '{{ __('Heading 5') }}'
                    },
                    {
                        key: 'h6',
                        label: '{{ __('Heading 6') }}'
                    },
                ],

                attributes: [{
                        key: 'fontSize',
                        label: '{{ __('Font Size') }}',
                        min: 8,
                        max: 72,
                        // step: 1,
                        units: ['px', 'em']
                    },
                    {
                        key: 'lineHeight',
                        label: '{{ __('Line Height') }}',
                        min: 0.1,
                        max: 100,
                        step: 0.1,
                        units: ['px', 'em']
                    },
                    // Future attributes: just add here
                    // { key: 'fontWeight', label: 'Font Weight', min: 100, max: 900, step: 100, units: [null] },
                    // { key: 'letterSpacing', label: 'Letter Spacing', min: -5, max: 20, step: 0.5, units: ['px', 'em'] },
                ],

                props: @json($typographyProps),

                get propsJson() {
                    return JSON.stringify(this.props);
                },

                getValue(elementKey, attrKey) {
                    return parseFloat(this.props[elementKey]?.[attrKey]?.value) || 0;
                },

                setValue(elementKey, attrKey, val) {
                    const attr = this.attributes.find(a => a.key === attrKey);
                    val = Math.max(attr.min, Math.min(attr.max, parseFloat(val) || 0));
                    this.props[elementKey][attrKey].value = val;
                },

                getUnit(elementKey, attrKey) {
                    return this.props[elementKey]?.[attrKey]?.unit || 'px';
                },

                setUnit(elementKey, attrKey, unit) {
                    this.props[elementKey][attrKey].unit = unit;
                },

                cssValue(elementKey, attrKey) {
                    const prop = this.props[elementKey]?.[attrKey];
                    if (!prop) return '';
                    return prop.value + prop.unit;
                },

                previewStyle(elementKey) {
                    return this.attributes
                        .map(attr => {
                            const cssProp = attr.key.replace(/([A-Z])/g, '-$1').toLowerCase();
                            return `${cssProp}: ${this.cssValue(elementKey, attr.key)}`;
                        })
                        .join('; ');
                },

                increment(elementKey, attrKey) {
                    const attr = this.attributes.find(a => a.key === attrKey);
                    this.setValue(elementKey, attrKey, this.getValue(elementKey, attrKey) + (attr.step ?? 1));
                },

                decrement(elementKey, attrKey) {
                    const attr = this.attributes.find(a => a.key === attrKey);
                    this.setValue(elementKey, attrKey, this.getValue(elementKey, attrKey) - (attr.step ?? 1));
                },

                restoreToDefaults() {
                    this.props = @json($typographyDefaults);
                }
            }));
        });
    </script>
@endpush
