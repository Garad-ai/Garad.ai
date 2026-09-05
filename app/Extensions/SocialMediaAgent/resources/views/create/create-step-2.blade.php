<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 2"
    x-cloak
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-data="{
        showBrandDescription: false
    }"
    x-on:social-media-agent-force-description.window="
        showBrandDescription = true;
        $nextTick(() => { $refs.brandDescription?.focus(); });
    "
>
    <h2 class="mb-0 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Add URL or explain your brand')
        </span>
        @lang('Tell us about your business')
    </h2>

    <div
        x-show="!showBrandDescription"
        x-collapse
    >
        <div class="relative mt-4">
            <x-forms.input
				id="web-site-url"
                class="min-h-[70px] rounded-[10px] border border-transparent bg-foreground/5 px-6 py-2 backdrop-blur-xl transition-all focus-visible:border-foreground/5"
                type="url"
                placeholder="{{ __('Website URL (Optional)') }}"
                x-model="formData.site_url"
                @input="handleSiteUrlInput($event?.target?.value)"
                @keydown.enter="nextStep()"
            />
            <span
                class="absolute end-5 top-1/2 inline-flex -translate-y-1/2"
                x-cloak
                x-show="scraping"
            >
                <x-tabler-loader-2 class="size-6 animate-spin" />
            </span>
            <button
                class="absolute end-5 top-1/2 inline-grid size-9 -translate-y-1/2 place-items-center hover:scale-110"
                type="button"
                x-cloak
                x-show="formData.site_url.trim() && !scraping && !scraped"
                @click.prevent="if ( formData.site_url && !scraping ) { scrapeWebsite() }"
            >
                <x-tabler-zoom-scan class="size-6" />
            </button>
            <p
                class="mt-2 flex items-center gap-1 text-xs font-medium text-green-600"
                x-cloak
                x-show="scraped"
            >
                <x-tabler-check class="size-4" />
                <span x-text="`{{ __('Scraped') }} ${formData.scraped_content?.pages_count || 0} {{ __('pages') }}`"></span>
            </p>
        </div>
    </div>

    <button
        class="mt-4 flex w-full items-center gap-8 text-xs"
        type="button"
        @click.prevent="showBrandDescription = !showBrandDescription"
    >
        <span class="inline-flex h-px grow bg-foreground/5"></span>
        <span class="inline-flex items-center gap-1 font-medium">
            <span x-show="!showBrandDescription">
                {{ __('No Website?') }}
                <span class="text-blue-500 underline">
                    {{ __('Add brand description') }}
                </span>
            </span>
            <span x-show="showBrandDescription">
                {{ __('Have a website?') }}
                <span class="text-blue-500 underline">
                    {{ __('Add URL') }}
                </span>
            </span>
            <x-tabler-chevron-down
                class="size-4"
                ::class="{ 'rotate-180': showBrandDescription }"
            />
        </span>
        <span class="inline-flex h-px grow bg-foreground/5"></span>
    </button>

    <div
        x-show="showBrandDescription"
        x-collapse
    >
        <x-forms.input
            class="mt-5 min-h-[70px] rounded-[10px] border border-transparent bg-foreground/5 px-6 py-4 backdrop-blur-xl transition-all focus-visible:border-foreground/5"
            type="textarea"
            x-model="formData.site_description"
            x-ref="brandDescription"
            rows="7"
            placeholder="{{ __('Explain your brand, products and services') }}"
			@keydown.enter="nextStep()"
        ></x-forms.input>
    </div>

    <div class="mt-2">
        @include('social-media-agent::create.step-error', ['step' => 2])
    </div>

    <x-button
        class="mt-7 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground disabled:from-foreground/5 disabled:via-foreground/5 disabled:to-foreground/5"
        type="button"
        @click.prevent="nextStep()"
        ::disabled="scraping"
    >
        @lang('Continue')
        <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
