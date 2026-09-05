@php
    $default_lang_code = app()->getLocale();
    $default_lang_name = LaravelLocalization::getSupportedLocales()[app()->getLocale()]['native'];
    $default_theme = __('Pearl');
    $default_presentation_count = 4;
    $default_text_length = __('Medium');
@endphp

<div class="lqd-adv-img-editor-form-wrap pb-6 pt-16">
    <h1 class="mb-11 text-center">
        <img
            class="mx-auto mb-4 w-32"
            src="{{ custom_theme_url('assets/img/ai-presentation/presentation.png') }}"
            alt="Background image"
        >
        @lang('Create Exceptional <span class="opacity-50">Presentations</span>')
    </h1>

    <form
        class="lqd-adv-img-editor-form relative mx-auto max-w-4xl"
        id="submitForm"
         method="POST"
        action="{{ route('dashboard.user.ai-presentation.generate') }}"
        enctype="multipart/form-data"
        x-data="presentationForm"
    >
        @csrf

        <div class="mb-4 rounded-3xl bg-foreground/5 p-5">
            <x-forms.input
                class:container="w-full"
                class="w-full resize-none rounded-none border-none bg-transparent bg-none p-0 font-medium backdrop-blur-lg placeholder:text-foreground focus:ring-0 sm:text-xs"
                id="description"
                type="textarea"
                name="description"
                rows="3"
                placeholder="{{ __('Create a presentation for') }}"
                x-model="prompt"
            />
            
            <div class="mt-3" id="presentationAttachments" style="display:none;">
</div>
            
            <div class="mt-6 flex flex-wrap items-center gap-3">

    <!-- Attach Button -->
    <button
        type="button"
        id="presentationAttach"
        class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-foreground/10 hover:bg-foreground/20"
    >
        +
    </button>

    <!-- Hidden File Input -->
    <input
        type="file"
        id="presentationFiles"
        name="presentationFiles[]"
        multiple
        hidden
        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.json,.xml,.html,.md,.png,.jpg,.jpeg,.webp"
    />

    @include('ai-presentation::home.params.language-dropdown')
    @include('ai-presentation::home.params.theme-modal')
    @include('ai-presentation::home.params.presentation-count-dropdown')
    @include('ai-presentation::home.params.long-dropdown')

    <!-- Submit Button -->
    <button
        class="ms-auto flex size-10 items-center justify-center rounded-full bg-foreground text-background transition-opacity hover:opacity-90"
        type="submit"
        title="{{ __('Generate Presentation') }}"
    >
        <x-tabler-arrow-up class="size-5" />
    </button>

</div>
        </div>

        @include('ai-presentation::home.advanced-options')
    </form>
</div>

@push('script')

    <script>
    document.getElementById('presentationAttach').addEventListener('click', function () {
    document.getElementById('presentationFiles').click();
});

window.addEventListener('presentationAttachmentSelected', function(e){

    const box = document.getElementById('presentationAttachments');

    box.innerHTML = '';
    box.style.display = 'block';

    e.detail.forEach(file => {

        box.innerHTML += `
            <div class="mb-2 flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-3 py-2">

                <div class="flex items-center gap-2">
                    📄
                    <span>${file.title}.${file.extension}</span>
                </div>

                <button
                    type="button"
                    onclick="this.parentElement.remove()">
                    ✕
                </button>

            </div>
        `;

    });

});

        document.addEventListener('alpine:init', () => {
            Alpine.data('presentationForm', () => ({
                prompt: '',
                selectedLanguageCode: '{{ $default_lang_code }}',
                selectedLanguageName: '{{ $default_lang_name }}',
                selectedLanguageFlag: '', // This will be set when a language is selected
                presentationCount: {{ $default_presentation_count }},
                textLengthLabel: '{{ $default_text_length }}',
                customLengthValue: '',
                languageSearch: '',
                languages: [{
                        code: 'af',
                        name: 'Afrikaans',
                        flag: ''
                    },
                    {
                        code: 'sq',
                        name: 'Albanian',
                        flag: ''
                    },
                    {
                        code: 'ar',
                        name: 'Arabic',
                        flag: ''
                    },
                    {
                        code: 'ar-sa',
                        name: 'Arabic (Saudi Arabia)',
                        flag: ''
                    },
                    {
                        code: 'bn',
                        name: 'Bengali',
                        flag: ''
                    },
                    {
                        code: 'bs',
                        name: 'Bosnian',
                        flag: ''
                    },
                    {
                        code: 'bg',
                        name: 'Bulgarian',
                        flag: ''
                    },
                    {
                        code: 'ca',
                        name: 'Catalan',
                        flag: ''
                    },
                    {
                        code: 'hr',
                        name: 'Croatian',
                        flag: ''
                    },
                    {
                        code: 'cs',
                        name: 'Czech',
                        flag: ''
                    },
                    {
                        code: 'da',
                        name: 'Danish',
                        flag: ''
                    },
                    {
                        code: 'nl',
                        name: 'Dutch',
                        flag: ''
                    },
                    {
                        code: 'en-in',
                        name: 'English (India)',
                        flag: ''
                    },
                    {
                        code: 'en-gb',
                        name: 'English (UK)',
                        flag: ''
                    },
                    {
                        code: 'en',
                        name: 'English (US)',
                        flag: ''
                    },
                    {
                        code: 'et',
                        name: 'Estonian',
                        flag: ''
                    },
                    {
                        code: 'fi',
                        name: 'Finnish',
                        flag: ''
                    },
                    {
                        code: 'fr',
                        name: 'French',
                        flag: ''
                    },
                    {
                        code: 'de',
                        name: 'German',
                        flag: ''
                    },
                    {
                        code: 'el',
                        name: 'Greek',
                        flag: ''
                    },
                    {
                        code: 'gu',
                        name: 'Gujarati',
                        flag: ''
                    },
                    {
                        code: 'ha',
                        name: 'Hausa',
                        flag: ''
                    },
                    {
                        code: 'he',
                        name: 'Hebrew',
                        flag: ''
                    },
                    {
                        code: 'hi',
                        name: 'Hindi',
                        flag: ''
                    },
                    {
                        code: 'hu',
                        name: 'Hungarian',
                        flag: ''
                    },
                    {
                        code: 'is',
                        name: 'Icelandic',
                        flag: ''
                    },
                    {
                        code: 'id',
                        name: 'Indonesian',
                        flag: ''
                    },
                    {
                        code: 'it',
                        name: 'Italian',
                        flag: ''
                    },
                    {
                        code: 'ja',
                        name: 'Japanese (です/ます style)',
                        flag: ''
                    },
                    {
                        code: 'ja-da',
                        name: 'Japanese (だ/である style)',
                        flag: ''
                    },
                    {
                        code: 'kn',
                        name: 'Kannada',
                        flag: ''
                    },
                    {
                        code: 'kk',
                        name: 'Kazakh',
                        flag: ''
                    },
                    {
                        code: 'ko',
                        name: 'Korean',
                        flag: ''
                    },
                    {
                        code: 'lv',
                        name: 'Latvian',
                        flag: ''
                    },
                    {
                        code: 'lt',
                        name: 'Lithuanian',
                        flag: ''
                    },
                    {
                        code: 'mk',
                        name: 'Macedonian',
                        flag: ''
                    },
                    {
                        code: 'ms',
                        name: 'Malay',
                        flag: ''
                    },
                    {
                        code: 'ml',
                        name: 'Malayalam',
                        flag: ''
                    },
                    {
                        code: 'mr',
                        name: 'Marathi',
                        flag: ''
                    },
                    {
                        code: 'nb',
                        name: 'Norwegian',
                        flag: ''
                    },
                    {
                        code: 'fa',
                        name: 'Persian',
                        flag: ''
                    },
                    {
                        code: 'pl',
                        name: 'Polish',
                        flag: ''
                    },
                    {
                        code: 'pt-br',
                        name: 'Portuguese (Brazil)',
                        flag: ''
                    },
                    {
                        code: 'pt-pt',
                        name: 'Portuguese (Portugal)',
                        flag: ''
                    },
                    {
                        code: 'ro',
                        name: 'Romanian',
                        flag: ''
                    },
                    {
                        code: 'ru',
                        name: 'Russian',
                        flag: ''
                    },
                    {
                        code: 'sr',
                        name: 'Serbian',
                        flag: ''
                    },
                    {
                        code: 'zh-cn',
                        name: 'Simplified Chinese',
                        flag: ''
                    },
                    {
                        code: 'sl',
                        name: 'Slovenian',
                        flag: ''
                    },
                    {
                        code: 'es',
                        name: 'Spanish',
                        flag: ''
                    },
                    {
                        code: 'es-419',
                        name: 'Spanish (Latin America)',
                        flag: ''
                    },
                    {
                        code: 'es-mx',
                        name: 'Spanish (Mexico)',
                        flag: ''
                    },
                    {
                        code: 'es-es',
                        name: 'Spanish (Spain)',
                        flag: ''
                    },
                    {
                        code: 'sw',
                        name: 'Swahili',
                        flag: ''
                    },
                    {
                        code: 'sv',
                        name: 'Swedish',
                        flag: ''
                    },
                    {
                        code: 'tl',
                        name: 'Tagalog',
                        flag: ''
                    },
                    {
                        code: 'ta',
                        name: 'Tamil',
                        flag: ''
                    },
                    {
                        code: 'te',
                        name: 'Telugu',
                        flag: ''
                    },
                    {
                        code: 'th',
                        name: 'Thai',
                        flag: ''
                    },
                    {
                        code: 'zh-tw',
                        name: 'Traditional Chinese',
                        flag: ''
                    },
                    {
                        code: 'tr',
                        name: 'Turkish',
                        flag: ''
                    },
                    {
                        code: 'uk',
                        name: 'Ukrainian',
                        flag: ''
                    },
                    {
                        code: 'ur',
                        name: 'Urdu',
                        flag: ''
                    },
                    {
                        code: 'uz',
                        name: 'Uzbek',
                        flag: ''
                    },
                    {
                        code: 'vi',
                        name: 'Vietnamese',
                        flag: ''
                    },
                    {
                        code: 'cy',
                        name: 'Welsh',
                        flag: ''
                    },
                    {
                        code: 'yo',
                        name: 'Yoruba',
                        flag: ''
                    }
                ],

                // Filter languages, removing emoji/flag characters for accurate search
                get filteredLanguages() {
                    if (!this.languageSearch) return this.languages;
                    const s = this.languageSearch.toLowerCase().trim();

                    const removeFlags = (text) => {
                        return text.replace(/[\p{Emoji_Presentation}\p{Extended_Pictographic}]/gu, '').trim().toLowerCase();
                    };

                    return this.languages.filter(lang => {
                        const cleanName = removeFlags(lang.name);
                        return cleanName.includes(s);
                    });
                },

                selectLanguage(code, name, flag) {
                    this.selectedLanguageCode = code;
                    this.selectedLanguageName = name;
                    this.selectedLanguageFlag = flag || '';
                },

                setTextLength(value) {
                    if (typeof value === 'number' && value > 0) {
                        this.textLengthLabel = `Long (${value})`;
                    } else if (typeof value === 'string' && value.trim() !== '') {
                        this.textLengthLabel = value;
                    }
                    this.customLengthValue = '';
                }
            }))
        })
    </script>
@endpush
