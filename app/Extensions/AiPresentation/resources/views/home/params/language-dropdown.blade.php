<x-dropdown.dropdown
    anchor="start"
    offsetY="15px"
>
    <x-slot:trigger
        class="outline-foreground/[7%]"
        variant="outline"
    >
        <x-tabler-world class="size-4" />
        <span x-text="selectedLanguageName">
            {{ $default_lang_name }}
        </span>
    </x-slot:trigger>

    <!-- Keep everything under the same Alpine scope -->
    <x-slot:dropdown
        class="max-h-60 overflow-y-auto rounded-lg bg-background px-2 pb-2 shadow-lg"
    >
        <!-- Search input -->
        <div class="sticky top-0 z-10 bg-background py-2">
            <x-forms.input
                x-model="languageSearch"
                placeholder="{{ __('Search language...') }}"
                autocomplete="off"
            />
        </div>

        <!-- Filtered Language List -->
        <template
            x-for="lang in filteredLanguages"
            :key="lang.code"
        >
            <a
                class="flex items-center gap-2 border-b p-2 text-2xs font-medium text-heading-foreground transition-all last:border-0 hover:bg-foreground/5"
                href="#"
                @click.prevent="selectLanguage(lang.code, lang.name, lang.flag)"
            >
                <span
                    class="text-xl"
                    x-html="lang.flag"
                ></span>
                <span x-text="lang.name"></span>
            </a>
        </template>
    </x-slot:dropdown>
</x-dropdown.dropdown>

<input
    type="hidden"
    name="language"
    x-model="selectedLanguageCode"
>
