@php
    $test_commands = ['Explain an Image', 'Summarize a book for research', 'Translate a book'];
    $disable_actions = $app_is_demo && (isset($category) && ($category?->slug == 'ai_vision' || $category?->slug == 'ai_pdf' || $category?->slug == 'ai_chat_image'));

    $example_prompts = collect([
        ['name' => 'Transcribe my class notes', 'prompt' => 'Transcribe my class notes'],
        ['name' => 'Morning Productivity Plan', 'prompt' => 'Morning Productivity Plan'],
        ['name' => 'Cold Email', 'prompt' => 'Cold Email'],
        ['name' => 'Newsletter', 'prompt' => 'Newsletter'],
        ['name' => 'Summarize', 'prompt' => 'Summarize'],
        ['name' => 'Study Vocabulary', 'prompt' => 'Study Vocabulary'],
        ['name' => 'Create a workout plan', 'prompt' => 'Create a workout plan'],
        ['name' => 'Translate This Book', 'prompt' => 'Translate This Book'],
        ['name' => 'Generate a cute panda image', 'prompt' => 'Generate a cute panda image'],
        ['name' => 'Plan a 3 day trip to Rome', 'prompt' => 'Plan a 3 day trip to Rome'],
        ['name' => 'Pick an outfit', 'prompt' => 'Pick an outfit'],
        ['name' => 'How can I learn coding?', 'prompt' => 'How can I learn coding?'],
        ['name' => 'Experience Tokyo', 'prompt' => 'Experience Tokyo'],
        ['name' => 'Create a 4 course menu', 'prompt' => 'Create a 4 course menu'],
        ['name' => 'Help me write a story', 'prompt' => 'Help me write a story'],
        ['name' => 'Translate', 'prompt' => 'Translate'],
    ])
        ->map(fn($item) => (object) $item)
        ->toArray();
    $example_prompts_json = json_encode($example_prompts, JSON_THROW_ON_ERROR);
    $example_prompts = json_decode(setting('ai_chat_pro_suggestions', $example_prompts_json), false, 512, JSON_THROW_ON_ERROR);

    // ai actions icons
    $magicIconRewrite =
        '<svg style="fill:none!important;" width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"> <g clip-path="url(#clip0_3443_202)"> <path d="M12.3125 6.55064L15.8125 9.94302M14.5 16.3038H18M7.5 18L16.6875 9.09498C16.9173 8.87223 17.0996 8.60779 17.224 8.31676C17.3484 8.02572 17.4124 7.71379 17.4124 7.39878C17.4124 7.08377 17.3484 6.77184 17.224 6.48081C17.0996 6.18977 16.9173 5.92533 16.6875 5.70259C16.4577 5.47984 16.1849 5.30315 15.8846 5.1826C15.5843 5.06205 15.2625 5 14.9375 5C14.6125 5 14.2907 5.06205 13.9904 5.1826C13.6901 5.30315 13.4173 5.47984 13.1875 5.70259L4 14.6076V18H7.5Z" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </g> <defs> <clipPath id="clip0_3443_202"> <rect width="22" height="22" fill="white"/> </clipPath> </defs> </svg> ';
    $magicIconSummarize =
        '<svg style="fill:none!important;" width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"> <g clip-path="url(#clip0_3443_208)"> <path d="M2.75 17.4167C4.00416 16.6926 5.42682 16.3114 6.875 16.3114C8.32318 16.3114 9.74584 16.6926 11 17.4167C12.2542 16.6926 13.6768 16.3114 15.125 16.3114C16.5732 16.3114 17.9958 16.6926 19.25 17.4167" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M2.75 5.5C4.00416 4.77591 5.42682 4.39471 6.875 4.39471C8.32318 4.39471 9.74584 4.77591 11 5.5C12.2542 4.77591 13.6768 4.39471 15.125 4.39471C16.5732 4.39471 17.9958 4.77591 19.25 5.5" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M2.75 5.5V17.4167" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M11 5.5V17.4167" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M19.25 5.5V17.4167" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </g> <defs> <clipPath id="clip0_3443_208"> <rect width="22" height="22" fill="white"/> </clipPath> </defs> </svg>';
    $magicIconMakeItLonger =
        '<svg style="fill:none!important;" width="19" height="20" viewBox="0 0 19 20" fill="none" xmlns="http://www.w3.org/2000/svg"> <g clip-path="url(#clip0_3443_218)"> <path d="M3.1665 12.375H15.8332" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M3.1665 4.45833C3.1665 4.24837 3.24991 4.047 3.39838 3.89854C3.54684 3.75007 3.74821 3.66666 3.95817 3.66666H7.12484C7.3348 3.66666 7.53616 3.75007 7.68463 3.89854C7.8331 4.047 7.9165 4.24837 7.9165 4.45833V7.625C7.9165 7.83496 7.8331 8.03632 7.68463 8.18479C7.53616 8.33326 7.3348 8.41666 7.12484 8.41666H3.95817C3.74821 8.41666 3.54684 8.33326 3.39838 8.18479C3.24991 8.03632 3.1665 7.83496 3.1665 7.625V4.45833Z" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M3.1665 16.3333H12.6665" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </g> <defs> <clipPath id="clip0_3443_218"> <rect width="19" height="19" fill="white" transform="translate(0 0.5)"/> </clipPath> </defs> </svg>';
    $magicIconMakeItShorter =
        '<svg style="fill:none!important;" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"> <g clip-path="url(#clip0_3443_226)"> <path d="M2.25 5.25C2.25 5.84674 2.48705 6.41903 2.90901 6.84099C3.33097 7.26295 3.90326 7.5 4.5 7.5C5.09674 7.5 5.66903 7.26295 6.09099 6.84099C6.51295 6.41903 6.75 5.84674 6.75 5.25C6.75 4.65326 6.51295 4.08097 6.09099 3.65901C5.66903 3.23705 5.09674 3 4.5 3C3.90326 3 3.33097 3.23705 2.90901 3.65901C2.48705 4.08097 2.25 4.65326 2.25 5.25Z" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M2.25 12.75C2.25 13.3467 2.48705 13.919 2.90901 14.341C3.33097 14.7629 3.90326 15 4.5 15C5.09674 15 5.66903 14.7629 6.09099 14.341C6.51295 13.919 6.75 13.3467 6.75 12.75C6.75 12.1533 6.51295 11.581 6.09099 11.159C5.66903 10.7371 5.09674 10.5 4.5 10.5C3.90326 10.5 3.33097 10.7371 2.90901 11.159C2.48705 11.581 2.25 12.1533 2.25 12.75Z" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M6.4502 6.45L14.2502 14.25" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M6.4502 11.55L14.2502 3.75" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </g> <defs> <clipPath id="clip0_3443_226"> <rect width="18" height="18" fill="white"/> </clipPath> </defs> </svg>';
    $magicIconImprove =
        '<svg style="fill:none!important;" width="21" height="22" viewBox="0 0 21 22" fill="none" xmlns="http://www.w3.org/2000/svg"> <g clip-path="url(#clip0_3443_235)"> <path d="M6.125 11L10.5 15.375L19.25 6.625" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M1.75 11L6.125 15.375M10.5 11L14.875 6.625" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </g> <defs> <clipPath id="clip0_3443_235"> <rect width="21" height="21" fill="white" transform="translate(0 0.5)"/> </clipPath> </defs> </svg>';
    $magicIconTranslate =
        '<svg style="fill:none!important;" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"> <g clip-path="url(#clip0_3443_242)"> <path d="M11.4723 12.6C10.921 12.3966 10.4191 12.0785 9.99984 11.6667C9.22097 10.9032 8.17381 10.4756 7.08317 10.4756C5.99253 10.4756 4.94537 10.9032 4.1665 11.6667V4.16667C4.94537 3.40323 5.99253 2.9756 7.08317 2.9756C8.17381 2.9756 9.22097 3.40323 9.99984 4.16667C10.7787 4.93012 11.8259 5.35774 12.9165 5.35774C14.0071 5.35774 15.0543 4.93012 15.8332 4.16667V11.25" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M4.1665 17.5V11.6667" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M12.5 15.8333L14.1667 17.5L17.5 14.1667" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </g> <defs> <clipPath id="clip0_3443_242"> <rect width="20" height="20" fill="white"/> </clipPath> </defs> </svg>';
    $magicIconFixGrammer =
        '<svg style="fill:none!important;" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"> <g clip-path="url(#clip0_3443_250)"> <path d="M3.75 11.25V5.625C3.75 4.92881 4.02656 4.26113 4.51884 3.76884C5.01113 3.27656 5.67881 3 6.375 3C7.07119 3 7.73887 3.27656 8.23116 3.76884C8.72344 4.26113 9 4.92881 9 5.625V11.25" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M3.75 7.5H9" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M7.5 13.5L9.75 15.75L15 10.5" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </g> <defs> <clipPath id="clip0_3443_250"> <rect width="18" height="18" fill="white"/> </clipPath> </defs> </svg>';
    $magicIconMarkdown =
        '<svg width="21" height="13" viewBox="0 0 24 15" fill="currentColor" xmlns="http://www.w3.org/2000/svg"> <path d="M22.2675 0.0999756H1.7325C0.77625 0.0999756 0 0.876225 0 1.82872V13.135C0 14.0912 0.77625 14.8675 1.7325 14.8675H22.2712C23.2275 14.8675 24.0037 14.0912 24 13.1387V1.82872C24 0.876225 23.2237 0.0999756 22.2675 0.0999756ZM12.6937 11.4062H10.3875V6.90622L8.08125 9.78997L5.775 6.90622V11.4062H3.46125V3.56122H5.7675L8.07375 6.44497L10.38 3.56122H12.6862V11.4062H12.6937ZM17.7675 11.5225L14.3062 7.48372H16.6125V3.56122H18.9187V7.48372H21.225L17.7675 11.5225Z" /></svg>';
    $magicIconSearch =
        '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M14.0022 14.0053L9.66704 9.67022M1 6.05766C1 6.72184 1.13082 7.37951 1.38499 7.99314C1.63916 8.60676 2.01171 9.16431 2.48135 9.63396C2.951 10.1036 3.50855 10.4761 4.12217 10.7303C4.7358 10.9845 5.39347 11.1153 6.05766 11.1153C6.72184 11.1153 7.37951 10.9845 7.99314 10.7303C8.60676 10.4761 9.16431 10.1036 9.63396 9.63396C10.1036 9.16431 10.4761 8.60676 10.7303 7.99314C10.9845 7.37951 11.1153 6.72184 11.1153 6.05766C11.1153 5.39347 10.9845 4.7358 10.7303 4.12217C10.4761 3.50855 10.1036 2.951 9.63396 2.48135C9.16431 2.01171 8.60676 1.63916 7.99314 1.38499C7.37951 1.13082 6.72184 1 6.05766 1C5.39347 1 4.7358 1.13082 4.12217 1.38499C3.50855 1.63916 2.951 2.01171 2.48135 2.48135C2.01171 2.951 1.63916 3.50855 1.38499 4.12217C1.13082 4.7358 1 5.39347 1 6.05766Z" fill="none" stroke="#9A34CD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>';
    $magicIconSimplify =
        '<svg style="fill:none!important;" xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 22 22"  fill="none"  stroke="#9934cd" stroke-width="1.5" stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-file-text"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg>';
    $magicIconChangeStyle =
        '<svg style="fill:none!important;" xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="#9934cd"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-blockquote"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 15h15" /><path d="M21 19h-15" /><path d="M15 11h6" /><path d="M21 7h-6" /><path d="M9 9h1a1 1 0 1 1 -1 1v-2.5a2 2 0 0 1 2 -2" /><path d="M3 9h1a1 1 0 1 1 -1 1v-2.5a2 2 0 0 1 2 -2" /></svg>';
    $magicIconChangeTone =
        '<svg style="fill:none!important;" xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="#9934cd"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-float-center"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M4 7l1 0" /><path d="M4 11l1 0" /><path d="M19 7l1 0" /><path d="M19 11l1 0" /><path d="M4 15l16 0" /><path d="M4 19l16 0" /></svg>';

    $lang_with_flags = [];
    foreach (LaravelLocalization::getSupportedLocales() as $lang => $properties) {
        $lang_with_flags[] = [
            'lang' => $lang,
            'name' => $properties['native'],
            'flag' => country2flag(substr($properties['regional'], strrpos($properties['regional'], '_') + 1)),
        ];
    }
@endphp

@push('css')
    @vite('resources/views/default/scss/tiptap.scss')
@endpush

<div
    class="flex h-[inherit] rounded-b-[inherit] rounded-t-[inherit]"
    x-data="aiChatProCanvasData"
>
    <div
        class="conversation-area flex h-[inherit] !w-full flex-shrink-0 flex-col justify-between overflow-y-auto rounded-b-[inherit] rounded-t-[inherit] max-md:max-h-full"
        id="chat_area_to_hide"
        :class="{ 'hidden lg:flex lg:w-1/2': isCanvasActive, 'flex !w-full': !isCanvasActive }"
    >
        @if (view()->hasSection('chat_head'))
            @yield('chat_head')
        @else
            @include('panel.user.openai_chat.components.chat_head')
        @endif

        <div class="relative flex grow flex-col">
            <div @class([
                'grid place-items-center w-full overflow-x-hidden',
                'h-full' => $category?->slug != 'ai_vision',
            ])>
                <div
                    class="pointer-events-none invisible col-start-1 col-end-1 row-start-1 row-end-1 flex w-full scale-[1.1] flex-col items-center overflow-hidden py-10 opacity-0 transition-all group-[&.conversation-not-started]/chats-wrap:pointer-events-auto group-[&.conversation-not-started]/chats-wrap:visible group-[&.conversation-not-started]/chats-wrap:scale-100 group-[&.conversation-not-started]/chats-wrap:opacity-100">
                    <h2 class="mb-8 px-5 text-center text-[26px] font-medium leading-[1.1em] md:text-[30px]">
                        <span class="text-[0.7em] opacity-50">
                            {{ __("I'm") }} {{ $category->name }}
                        </span>
                        <br>
                        {{ __('Ask me anything') }}
                    </h2>

                    <div
                        class="flex w-full gap-4 [--mask-from:7rem] [--mask-to:calc(100%-7rem)]"
                        style="mask-image: linear-gradient(to right, transparent, black var(--mask-from), black var(--mask-to), transparent);"
                        x-data="marquee({ pauseOnHover: true })"
                    >
                        <div class="lqd-marquee-viewport relative flex w-full overflow-hidden">
                            <div class="lqd-marquee-slider flex w-full gap-4 py-2 lg:px-14">
                                @foreach ($example_prompts ?? [] as $prompt)
                                    <button
                                        class="lqd-marquee-cell inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl bg-surface-background px-2.5 py-3 text-base font-semibold leading-[1.15em] transition-all hover:-translate-y-1 hover:shadow dark:bg-heading-foreground/5 dark:hover:bg-white lg:text-[1.2vw]"
                                        data-prompt="{{ __($prompt?->prompt) }}"
                                        type="button"
                                        @click.prevent="prompt = $event.currentTarget.getAttribute('data-prompt'); $nextTick(() => { $refs.prompt.focus(); $refs.prompt.dispatchEvent(new Event('input',{bubbles:true})); });"
                                    >
                                        <span class="bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to bg-clip-text text-transparent">
                                            {{ __($prompt?->name) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    @class([
                        'chats-container text-xs p-8 max-md:p-4 overflow-x-hidden col-start-1 col-end-1 row-start-1 row-end-1 w-full transition-all group-[&.conversation-not-started]/chats-wrap:scale-95 group-[&.conversation-not-started]/chats-wrap:opacity-0 group-[&.conversation-not-started]/chats-wrap:invisible group-[&.conversation-not-started]/chats-wrap:pointer-events-none',
                        'md:mb-auto md:pb-6 relative z-10' => $category?->slug == 'ai_vision',
                        'h-full' => $category?->slug != 'ai_vision',
                    ])
                    :class="isCanvasActive ? '!w-full' : ''"
                >
                    @if (view()->hasSection('chat_area'))
                        @yield('chat_area')
                    @else
                        @include('panel.user.openai_chat.components.chat_area')
                    @endif

                    @if ($category?->slug == 'ai_vision' && ((isset($lastThreeMessage) && $lastThreeMessage->count() == 0) || !isset($lastThreeMessage)))
                        <div
                            class="flex flex-col items-center justify-center gap-y-3"
                            id="sugg"
                        >
                            <div class="flex flex-wrap items-center gap-2 text-2xs font-medium leading-relaxed text-heading-foreground">
                                {{ __('Upload an image and ask me anything') }}
                                <x-tabler-chevron-down class="size-4" />
                            </div>

                            @foreach ($test_commands as $command)
                                <x-button
                                    class="font-normal"
                                    tag="button"
                                    variant="secondary"
                                    onclick="addText('{{ __($command) }}');"
                                >
                                    {{ __($command) }}
                                </x-button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if ($category?->slug == 'ai_vision' && ((isset($lastThreeMessage) && $lastThreeMessage->count() == 0) || !isset($lastThreeMessage)))
                <div
                    class="relative z-10 mt-auto flex items-center justify-center px-4 pb-5 md:px-8"
                    id="mainupscale_src"
                    ondrop="dropHandler(event, 'upscale_src');"
                    ondragover="dragOverHandler(event);"
                >
                    <label
                        class="flex w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-foreground/10 bg-background px-4 py-8 transition-colors hover:bg-foreground/10"
                        for="upscale_src"
                    >
                        <div class="flex flex-col items-center justify-center">
                            <x-tabler-cloud-upload
                                class="mb-4 size-11"
                                stroke-width="1.5"
                            />

                            <span class="mb-1 block text-sm font-semibold">
                                {{ __('Drop your image here or browse') }}
                            </span>

                            <span class="file-name mb-0 block text-2xs">
                                @if ($category?->slug != 'ai_vision' && $category?->slug != 'ai_pdf')
                                    {{ __('(Only jpg, png, webp will be accepted)') }}
                                @else
                                    {{ __('(Only jpg, png and webp will be accepted)') }}
                                @endif
                            </span>
                        </div>
                        <input
                            class="hidden"
                            id="upscale_src"
                            type="file"
                            accept="@if ($category?->slug == 'ai_vision' || $category?->slug == 'ai_pdf') .png, .jpg, .jpeg, .pdf @else .png, .jpg, .jpeg @endif"
                            onchange="handleFileSelect('upscale_src')"
                        />
                    </label>
                </div>
            @endif
        </div>

        {{-- @if ($category?->slug == 'ai_realtime_voice_chat')
			@includeIf('openai-realtime-chat::chat-button', ['compact' => false, 'category_slug' => $category?->slug, 'messages' => $chat->messages])
		@endif --}}

        @if (setting('realtime_voice_chat', 0))
            <div
                class="lqd-audio-vis-wrap group/audio-vis pointer-events-none invisible absolute start-0 top-0 z-2 flex h-full w-full flex-col items-center justify-between gap-y-5 overflow-hidden bg-background/10 px-5 py-28 opacity-0 backdrop-blur-lg transition-all [&.active]:visible [&.active]:opacity-100"
                data-state="idle"
            >
                <div></div>
                <div
                    class="invisible relative grid w-full scale-110 place-content-center place-items-center opacity-0 blur-lg transition-all duration-300 group-[&.active]/audio-vis:visible group-[&.active]/audio-vis:scale-100 group-[&.active]/audio-vis:opacity-100 group-[&.active]/audio-vis:blur-0">
                    <div class="lqd-audio-vis-circ absolute left-1/2 top-1/2 col-start-1 col-end-1 row-start-1 row-end-1 -translate-x-1/2 -translate-y-1/2">
                        <div
                            class="inline-flex size-40 animate-spin rounded-full bg-gradient-to-b from-[#C13CFF] to-[#00BFFF] opacity-50 blur-3xl [animation-duration:2s] lg:size-[200px]">
                        </div>
                    </div>
                    <div
                        class="lqd-audio-vis-bars col-start-1 col-end-1 row-start-1 row-end-1 flex h-8 scale-75 items-center gap-[3px] text-heading-foreground opacity-0 transition-all group-[&[data-state=playing]]/audio-vis:scale-100 group-[&[data-state=playing]]/audio-vis:opacity-100">
                        <div class="lqd-audio-vis-bar inline-flex min-h-[7px] w-[7px] origin-center rounded-full bg-current"></div>
                        <div class="lqd-audio-vis-bar inline-flex min-h-[7px] w-[7px] origin-center rounded-full bg-current"></div>
                        <div class="lqd-audio-vis-bar inline-flex min-h-[7px] w-[7px] origin-center rounded-full bg-current"></div>
                        <div class="lqd-audio-vis-bar inline-flex min-h-[7px] w-[7px] origin-center rounded-full bg-current"></div>
                        <div class="lqd-audio-vis-bar inline-flex min-h-[7px] w-[7px] origin-center rounded-full bg-current"></div>
                    </div>
                    <div
                        class="lqd-audio-vis-dot-wrap col-start-1 col-end-1 row-start-1 row-end-1 flex scale-75 animate-bounce items-center gap-[3px] text-heading-foreground opacity-0 transition-all group-[&[data-state=idle]]/audio-vis:scale-100 group-[&[data-state=recording]]/audio-vis:scale-100 group-[&[data-state=idle]]/audio-vis:opacity-100 group-[&[data-state=recording]]/audio-vis:opacity-100 group-[&[data-state=recording]]/audio-vis:[animation-play-state:paused]">
                        <div class="lqd-audio-vis-dot inline-flex size-4 origin-center rounded-full bg-current">
                        </div>
                    </div>
                    <div
                        class="lqd-audio-vis-loader active col-start-1 col-end-1 row-start-1 row-end-1 flex scale-75 items-center text-heading-foreground opacity-0 transition-all group-[&[data-state=waiting]]/audio-vis:scale-100 group-[&[data-state=waiting]]/audio-vis:opacity-100">
                        <x-tabler-loader-2 class="size-4 animate-spin" />
                    </div>
                </div>
                <x-button
                    class="pointer-events-auto size-[50px] shrink-0 border border-heading-foreground/5 bg-transparent backdrop-blur-md backdrop-contrast-125 hover:bg-red-500 hover:text-white"
                    variant="ghost-shadow"
                    size="none"
                    @click.prevent="$dispatch('audio-vis', { action: 'stop' })"
                    x-data="{}"
                >
                    <span class="sr-only">
                        {{ __('Stop') }}
                    </span>
                    <x-tabler-x class="size-4" />
                </x-button>
            </div>
        @endif

        @if (view()->hasSection('chat_form'))
            @yield('chat_form')
        @else
            @include('ai-chat-pro::includes.chat_form')
        @endif
    </div>
    <div
        class="relative z-1 mx-1 w-px cursor-col-resize select-none bg-foreground/20 lg:mb-8"
        id="canvas_divider"
        x-cloak
        :class="isCanvasActive ? 'hidden lg:flex' : 'hidden'"
    >
        <span class="absolute left-1/2 top-1/2 h-5 w-2 -translate-x-1/2 -translate-y-1/2 select-none rounded-lg bg-foreground/50"></span>
    </div>
    <div
        class="conversation-area flex h-[inherit] min-w-0 grow flex-col overflow-y-auto overflow-x-hidden px-4 pb-10 lg:mb-8 lg:px-8"
        x-cloak
        :class="isCanvasActive ? 'flex' : 'hidden'"
        x-data="tiptapEditor({ element: $el.querySelector('#tiptap-editor-element') })"
    >
        {{-- tiptap editor header --}}
        <div class="flex items-center justify-between py-3 lg:py-6">
            <div
                class="group flex items-center gap-4"
                :class="{
                    'edit-mode': editMode
                }"
                x-data="{
                    editMode: false,
                    toggleEditMode() {
                        this.editMode = !this.editMode;
                        if (!this.editMode) {
                            saveTitle();
                        }
                    }
                }"
            >
                <button
                    @class([
                        'chat-item-update-title' => !$disable_actions,
                        'flex size-7 items-center relative z-1 justify-center rounded-button border bg-background transition-all dark:bg-primary dark:text-primary-foreground dark:border-primary hover:scale-110 group-[&.edit-mode]:bg-emerald-500 group-[&.edit-mode]:border-emerald-500 group-[&.edit-mode]:text-white shirink-0',
                    ])
                    @if ($disable_actions) onclick="return toastr.info('{{ __('This feature is disabled in Demo version.') }}')"
					@else
						@click.prevent="toggleEditMode()" @endif
                >
                    <x-tabler-pencil class="size-4 group-[&.edit-mode]:hidden" />
                    <x-tabler-check class="hidden size-4 group-[&.edit-mode]:block" />
                </button>

                <span
                    class="pointer-events-none text-xs font-medium group-[&.edit-mode]:pointer-events-auto"
                    id="editContentTitle"
                    style="word-break: break-word"
                    :contentEditable="editMode"
                    x-text="$store.aiChatProCanvasData.currentEditingContent?.title || 'Document'"
                >
                </span>
            </div>
            <div class="flex items-center gap-1 lg:gap-3 xl:gap-4">
                <button
                    class="inline-flex size-7 items-center justify-center rounded-sm transition-colors hover:bg-foreground/5"
                    title="{{ __('Undo') }}"
                    @click.prevent='$store.tiptapEditor.undo()'
                    :disabled="!$store.tiptapEditor.canUndo($store.tiptapEditor._updated_at)"
                >
                    <x-tabler-arrow-back-up class="size-5" />
                </button>
                <button
                    class="inline-flex size-7 items-center justify-center rounded-sm transition-colors hover:bg-foreground/5"
                    title="{{ __('Redo') }}"
                    @click.prevent='$store.tiptapEditor.redo()'
                    :disabled="!$store.tiptapEditor.canRedo($store.tiptapEditor._updated_at)"
                >
                    <x-tabler-arrow-forward-up class="size-5" />
                </button>
                <button
                    class="inline-flex size-7 items-center justify-center rounded-sm transition-colors hover:bg-foreground/5"
                    id="workbook_copy"
                    @click.prevent="navigator.clipboard.writeText($store.tiptapEditor.getTextContent()); toastr.success('{{ __('Copied to clipboard') }}')"
                    title="{{ __('Copy to clipboard') }}"
                >
                    <x-tabler-copy class="size-5" />
                </button>
                <x-dropdown.dropdown
                    anchor="end"
                    offsetY="1rem"
                >
                    <x-slot:trigger
                        class="px-2 py-1"
                        variant="link"
                        size="xs"
                        title="{{ __('Download') }}"
                    >
                        <x-tabler-download class="size-5" />
                    </x-slot:trigger>
                    <x-slot:dropdown
                        class="overflow-hidden"
                    >
                        <button
                            class="workbook_download flex w-full items-center gap-1 rounded-md p-2 font-medium hover:bg-foreground/5"
                            data-doc-type="doc"
                            @click.prevent="e => $store.tiptapEditor.download(e)"
                        >
                            <x-tabler-brand-office
                                class="size-6"
                                stroke-width="1.5"
                            />
                            MS Word
                        </button>
                        <button
                            class="workbook_download flex w-full items-center gap-1 rounded-md p-2 text-2xs font-medium hover:bg-foreground/5"
                            data-doc-type="html"
                            @click.prevent="e => $store.tiptapEditor.download(e)"
                        >
                            <x-tabler-brand-html5
                                class="size-6"
                                stroke-width="1.5"
                            />
                            HTML
                        </button>
                    </x-slot:dropdown>
                </x-dropdown.dropdown>

                {{-- begin: ai actions --}}
                <div class="group/top-dropdown relative">
                    <x-button
                        class="size-7"
                        variant="none"
                        size="none"
                        title="{{ __('AI Action') }}"
                    >
                        <svg
                            class="size-5"
                            width="17"
                            height="16"
                            viewBox="0 0 17 16"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M16.1681 6.15216L14.7761 6.43416V6.43616C14.1057 6.57221 13.4902 6.90274 13.0064 7.38647C12.5227 7.87021 12.1922 8.48572 12.0561 9.15617L11.7741 10.5482C11.7443 10.6852 11.6686 10.8079 11.5594 10.8958C11.4503 10.9838 11.3143 11.0318 11.1741 11.0318C11.0339 11.0318 10.8979 10.9838 10.7888 10.8958C10.6796 10.8079 10.6039 10.6852 10.5741 10.5482L10.2921 9.15617C10.1563 8.48561 9.82586 7.86997 9.34209 7.38619C8.85831 6.90241 8.24266 6.57197 7.57211 6.43616L6.18011 6.15416C6.0413 6.12574 5.91656 6.05026 5.82698 5.94048C5.7374 5.8307 5.68848 5.69336 5.68848 5.55166C5.68848 5.40997 5.7374 5.27263 5.82698 5.16285C5.91656 5.05307 6.0413 4.97759 6.18011 4.94916L7.57211 4.66716C8.24261 4.53124 8.85819 4.20076 9.34195 3.717C9.8257 3.23324 10.1562 2.61766 10.2921 1.94716L10.5741 0.555164C10.6039 0.418164 10.6796 0.295476 10.7888 0.207494C10.8979 0.119512 11.0339 0.0715332 11.1741 0.0715332C11.3143 0.0715332 11.4503 0.119512 11.5594 0.207494C11.6686 0.295476 11.7443 0.418164 11.7741 0.555164L12.0561 1.94716C12.1922 2.61761 12.5227 3.23312 13.0064 3.71686C13.4902 4.20059 14.1057 4.53112 14.7761 4.66716L16.1681 4.94716C16.3069 4.97559 16.4317 5.05107 16.5212 5.16085C16.6108 5.27063 16.6597 5.40797 16.6597 5.54966C16.6597 5.69136 16.6108 5.8287 16.5212 5.93848C16.4317 6.04826 16.3069 6.12374 16.1681 6.15216ZM5.98931 13.2052L5.61131 13.2822C5.14508 13.3767 4.71703 13.6055 4.38056 13.9418C4.04409 14.2781 3.81411 14.706 3.71931 15.1722L3.64231 15.5502C3.62171 15.6567 3.56468 15.7527 3.48102 15.8217C3.39735 15.8907 3.29227 15.9285 3.18381 15.9285C3.07534 15.9285 2.97026 15.8907 2.88659 15.8217C2.80293 15.7527 2.74591 15.6567 2.72531 15.5502L2.6483 15.1722C2.55362 14.7059 2.32368 14.2779 1.98719 13.9416C1.6507 13.6053 1.22258 13.3756 0.756305 13.2812L0.378305 13.2042C0.271814 13.1836 0.175815 13.1265 0.106785 13.0429C0.037755 12.9592 0 12.8541 0 12.7457C0 12.6372 0.037755 12.5321 0.106785 12.4485C0.175815 12.3648 0.271814 12.3078 0.378305 12.2872L0.756305 12.2102C1.22271 12.1157 1.65093 11.8858 1.98743 11.5493C2.32393 11.2128 2.5538 10.7846 2.6483 10.3182L2.72531 9.94016C2.74591 9.83367 2.80293 9.73767 2.88659 9.66864C2.97026 9.59961 3.07534 9.56186 3.18381 9.56186C3.29227 9.56186 3.39735 9.59961 3.48102 9.66864C3.56468 9.73767 3.62171 9.83367 3.64231 9.94016L3.71931 10.3182C3.81376 10.7847 4.04359 11.2131 4.38008 11.5497C4.71658 11.8864 5.14482 12.1165 5.61131 12.2112L5.98931 12.2882C6.0958 12.3088 6.1918 12.3658 6.26083 12.4495C6.32985 12.5331 6.36761 12.6382 6.36761 12.7467C6.36761 12.8551 6.32985 12.9602 6.26083 13.0439C6.1918 13.1275 6.0958 13.1846 5.98931 13.2052Z"
                                fill="url(#paint0_linear_3314_1636)"
                            />
                            <defs>
                                <linearGradient
                                    id="paint0_linear_3314_1636"
                                    x1="1.03221e-07"
                                    y1="3.30635"
                                    x2="13.3702"
                                    y2="15.6959"
                                    gradientUnits="userSpaceOnUse"
                                >
                                    <stop stop-color="#82E2F4" />
                                    <stop
                                        offset="0.502"
                                        stop-color="#8A8AED"
                                    />
                                    <stop
                                        offset="1"
                                        stop-color="#6977DE"
                                    />
                                </linearGradient>
                            </defs>
                        </svg>
                    </x-button>

                    <div
                        class="pointer-events-none invisible absolute end-0 top-full z-10 mt-4 w-44 translate-y-1 rounded-dropdown border bg-dropdown-background opacity-0 shadow-xl shadow-black/5 transition-all before:absolute before:inset-x-0 before:-top-4 before:bottom-full group-hover/top-dropdown:pointer-events-auto group-hover/top-dropdown:visible group-hover/top-dropdown:translate-y-0 group-hover/top-dropdown:opacity-100"
                        x-data="aiActions"
                    >
                        <x-button
                            class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                            variant="none"
                            @click.prevent="rewrite()"
                        >
                            {!! $magicIconRewrite !!}
                            {{ __('Rewrite') }}
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                            variant="none"
                            @click.prevent="summarize()"
                        >
                            {!! $magicIconSummarize !!}
                            {{ __('Summarize') }}
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                            variant="none"
                            @click.prevent="makeItLonger()"
                        >
                            {!! $magicIconMakeItLonger !!}
                            {{ __('Make it Longer') }}
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                            variant="none"
                            @click.prevent="makeItShorter()"
                        >
                            {!! $magicIconMakeItShorter !!}
                            {{ __('Make it Shorter') }}
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                            variant="none"
                            @click.prevent="improveWriting()"
                        >
                            {!! $magicIconImprove !!}
                            {{ __('Improve the content') }}
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                            variant="none"
                            @click.prevent="fixGrammarMistakes()"
                        >
                            {!! $magicIconFixGrammer !!}
                            {{ __('Fix the Grammer') }}
                        </x-button>
                        <x-button
                            class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                            variant="none"
                            @click.prevent="simplify()"
                        >
                            {!! $magicIconSimplify !!}
                            {{ __('Simplify') }}
                        </x-button>

                        <div class="group/dropdown relative">
                            <x-button
                                class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                                variant="none"
                                @click.prevent="simplify()"
                            >
                                {!! $magicIconTranslate !!}
                                {{ __('Translate') }}
                            </x-button>

                            <div
                                class="pointer-events-none invisible absolute -bottom-1 end-full z-10 min-w-44 translate-x-1 rounded-dropdown border border-dropdown-border bg-dropdown-background p-2 opacity-0 shadow-xl shadow-black/5 transition group-hover/dropdown:pointer-events-auto group-hover/dropdown:visible group-hover/dropdown:translate-x-0 group-hover/dropdown:opacity-100">
                                {{-- Search input with clear button --}}
                                <div class="relative">
                                    <x-tabler-search
                                        class="pointer-events-none absolute start-3 top-1/2 z-10 size-4 -translate-y-1/2 opacity-75"
                                        stroke-width="1.5"
                                    />
                                    <x-forms.input
                                        class="border-none bg-heading-foreground/5 py-1 ps-10 transition-colors max-lg:rounded-md"
                                        container-class="peer"
                                        x-model="searchLanguage"
                                        type="text"
                                        placeholder="{{ __('Search') }}"
                                    />
                                </div>
                                <div class="max-h-72 overflow-y-auto">
                                    {{-- list of langauge --}}
                                    <template x-for="lang in languages">
                                        <button
                                            class="w-full items-center gap-1 rounded-md p-2 text-start font-medium hover:bg-foreground/5"
                                            :class="lang.name.includes(searchLanguage) ? 'flex' : 'hidden'"
                                            @click.prevent="translateTo(lang)"
                                        >
                                            <span x-text="lang.flag"></span>
                                            <span x-text="lang.name"></span>
                                        </button>
                                    </template>
                                    <template x-if="!languages.some((lang) => lang.name.includes(searchLanguage))">
                                        <div class="w-full py-3 text-center">
                                            <span>No Result</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="group/dropdown relative">
                            <x-button
                                class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                                variant="none"
                                @click.prevent="simplify()"
                            >
                                {!! $magicIconChangeStyle !!}
                                {{ __('Change Style To') }}
                            </x-button>

                            <div
                                class="pointer-events-none invisible absolute -bottom-1 end-full z-10 min-w-44 translate-x-1 rounded-dropdown border border-dropdown-border bg-dropdown-background p-2 opacity-0 shadow-xl shadow-black/5 transition group-hover/dropdown:pointer-events-auto group-hover/dropdown:visible group-hover/dropdown:translate-x-0 group-hover/dropdown:opacity-100">
                                <div class="max-h-72 overflow-y-auto">
                                    {{-- list of styles --}}
                                    <template x-for="style in styles">
                                        <button
                                            class="flex w-full items-center gap-1 rounded-md p-2 text-start font-medium hover:bg-foreground/5"
                                            @click.prevent="changeStyle(style)"
                                            x-text="style"
                                        >
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="group/dropdown relative">
                            <x-button
                                class="w-full justify-start rounded-dropdown text-start hover:bg-foreground/5"
                                variant="none"
                                @click.prevent="simplify()"
                            >
                                {!! $magicIconChangeTone !!}
                                {{ __('Change Tone To') }}
                            </x-button>

                            <div
                                class="pointer-events-none invisible absolute bottom-0 end-full z-10 min-w-44 translate-x-1 rounded-dropdown border border-dropdown-border bg-dropdown-background p-2 opacity-0 shadow-xl shadow-black/5 transition group-hover/dropdown:pointer-events-auto group-hover/dropdown:visible group-hover/dropdown:translate-x-0 group-hover/dropdown:opacity-100">
                                <div class="max-h-72 overflow-y-auto">
                                    {{-- list of tones --}}
                                    <template x-for="tone in tones">
                                        <button
                                            class="flex w-full items-center gap-1 rounded-md p-2 text-start font-medium hover:bg-foreground/5"
                                            @click.prevent="changeTone(tone)"
                                            x-text="tone"
                                        >
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- end: ai actions --}}
                <button
                    class="inline-flex size-7 items-center justify-center rounded-sm transition-colors hover:bg-foreground/5"
                    title="{{ __('Close') }}"
                    @click.prevent="setCanvasActive(false);"
                >
                    <x-tabler-x class="size-5" />
                </button>
            </div>
        </div>
        <div
            class="h-auto [&>div:nth-child(1)]:h-full [&>div:nth-child(1)]:outline-none"
            id="tiptap-editor-element"
            style="word-break: break-word"
        >
        </div>
        <div class="hidden">
            {{-- Floating menus --}}
            <x-tiptap.floating-menus />

            {{-- Bubble menus --}}
            <x-tiptap.bubble-menus />
        </div>
    </div>
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiChatProCanvasData', () => ({
                    // current editing data
                    currentEditingContent: null,
                    // canvas is active or not
                    isCanvasActive: false,
                    // divider operation
                    dividerInfo: {
                        // if canvas resize is dragging
                        isDragging: false,
                        // container of divider
                        container: null,
                        // container rect
                        containerRect: null,
                        // divider
                        dividerDom: null,
                        // min width
                        minWidth: 415,
                        // max width
                        maxWidth: null,
                        // initialize the values
                        init() {
                            this.dividerDom = document.getElementById('canvas_divider');
                            this.container = this.dividerDom.parentElement;

                            this.containerRect = this.container.getBoundingClientRect();
                            this.maxWidth = this.containerRect.width - this.minWidth;
                        }
                    },
                    init() {
                        Alpine.store('aiChatProCanvasData', this);

                        // divider init
                        this.dividerInfo.init();

                        // add event listeners
                        this.addEventListeners();
                    },
                    // set active for canvas window
                    async setCanvasActive(active = true) {
                        const tiptapContent = {};
                        const editButton = this.$event.currentTarget;
                        const parentMessageItem = editButton?.closest('.lqd-chat-ai-bubble');
                        const message_id = parentMessageItem?.getAttribute('data-message-id');
                        const content = parentMessageItem?.querySelector('.chat-content')?.innerHTML?.replace('[DONE]', '');
                        const title = parentMessageItem?.getAttribute('title') ?? '{{ __('Document') }}';

                        tiptapContent.message_item = parentMessageItem;
                        tiptapContent.message_id = message_id;
                        tiptapContent.content = content;
                        tiptapContent.title = title;

                        if (active) {
                            tiptapContent.scrollTop = document.querySelector('.conversation-area')?.scrollTop;

                            this.currentEditingContent = tiptapContent;
                            Alpine.store('tiptapEditor').setContent(tiptapContent.content);
                        } else if (this.currentEditingContent?.content != null && this.currentEditingContent?.message_id != null) {
                            @auth
                            if (window.confirm('{{ __('Do you want to save?') }}')) {
                                Alpine.store('appLoadingIndicator').show();
                                try {
                                    const res = await fetch('{{ route('tiptap-content-store') }}', {
                                        method: 'post',
                                        headers: {
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            'message_id': this.currentEditingContent.message_id,
                                            'content': Alpine.store('tiptapEditor').getHtmlContent(),
                                            'type': 'output'
                                        })
                                    });

                                    const resData = await res.json();

                                    if (!res.ok || resData.status === 'error') {
                                        throw new Error(resData.message || '{{ __('Something went wrong, Please contact support for assistance') }}');
                                    }

                                    toastr.success('{{ __('Saved Succesfully') }}');
                                    this.currentEditingContent.content = Alpine.store('tiptapEditor').getHtmlContent();
                                    this.currentEditingContent.message_item.querySelector('.chat-content').innerHTML = this.currentEditingContent.content;

                                    const allLinks = this.currentEditingContent.message_item.querySelectorAll('.chat-content a');
                                    allLinks.forEach(link => {
                                        const images = link.querySelectorAll('img');
                                        if (images.length === 1 && link.children.length === 1) {
                                            link.setAttribute('data-fslightbox', 'gallery');
                                        }
                                    });

                                    if (typeof window.refreshFsLightbox === 'function') {
                                        refreshFsLightbox();
                                    }
                                } catch (error) {
                                    toastr.error(error.message || error);
                                    console.error(error);
                                }
                                Alpine.store('appLoadingIndicator').hide();
                            }
                        @else
                            toastr.warning("{{ __('You need to be logged in to save the canvas data.') }}");
                        @endauth
                    }


                    this.isCanvasActive = active;

                    if (this.currentEditingContent.scrollTop != null) {
                        setTimeout(() => {
                            document.querySelector('.conversation-area')?.scrollTo({
                                top: this.currentEditingContent.scrollTop
                            });
                        }, 150);
                    }
                },
                // add event listeners
                addEventListeners() {
                    // mouse down event
                    document.getElementById('canvas_divider')?.addEventListener('mousedown', () => {
                        this.dividerInfo.isDragging = true;
                        document.body.style.cursor = 'col-resize';
                    });

                    // mouse up event
                    window.addEventListener('mouseup', () => {
                        if (this.dividerInfo.isDragging) {
                            this.dividerInfo.isDragging = false;
                            document.body.style.cursor = 'default';
                        }
                    });

                    // mouse move event
                    window.addEventListener('mousemove', (e) => this.dividerMouseMoveHandler(e));
                },
                /**
                 * ====================================
                 * Event Handlers
                 * ====================================
                 */
                dividerMouseMoveHandler(e) {
                    if (!this.dividerInfo.isDragging) return;
                    const newWidth = Math.min(this.dividerInfo.maxWidth, Math.max(this.dividerInfo.minWidth, (e.clientX - this.dividerInfo.containerRect.left)));
                    this.dividerInfo.container.firstElementChild.style.width = `${newWidth}px`;
                },
                // save title
                async saveTitle() {
                    this.currentEditingContent.title = document.getElementById('editContentTitle')?.innerText;
                    try {
                        const res = await fetch('{{ route('tiptap-title-save') }}', {
                            method: 'post',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                'message_id': this.currentEditingContent.message_id,
                                'title': this.currentEditingContent.title
                            })
                        });

                        const resData = await res.json();

                        if (!res.ok || resData.status == 'error') {
                            throw new Error(resData.message || '{{ __('Something went wrong, Please contact support for assistance') }}');
                        }

                        toastr.success('{{ __('Saved Succesfully') }}');
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error);
                    }
                }
            }));

        // AI Actions
        Alpine.data('aiActions', () => ({
            // language
            languages: @json($lang_with_flags),
            searchLanguage: '',
            // styles
            styles: [
                'Professional',
                'Conversational',
                'Humorous',
                'Empathic',
                'Simple',
                'Academic',
                'Creative',
            ],
            // tones
            tones: [
                'Formal',
                'Informal',
                'Conversational',
                'Technical',
                'Humorous',
                'Serious',
                'Creative',
                'Analytical',
                'Friendly',
                'Assertive',
                'Encouraging',
                'Instructive',
                'Persuasive',
                'Urgent',
                'Optimistic',
                'Pessimistic',
                'Neutral',
            ],
            // actions
            rewrite() {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt',
                    'Rewrite below content professionally. Must detect the content language and ensure that the response is also in same content language.');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }

                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            summarize() {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt',
                    'Summarize below content professionally. Keep origin language.');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            makeItLonger() {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Make below content longer');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            makeItShorter() {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Make below content shorter');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            improveWriting() {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Improve writing of  below content');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            simplify() {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Simplify below content');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            fixGrammarMistakes() {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Fix grammatical mistakes in below content');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            translateTo(language) {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Translate below content to ' + language.lang);
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());
                formData.append('language', language.value);

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            changeStyle(style) {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Change style of below content to ' + style + ' style.\n');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
            changeTone(tone) {
                if (Alpine.store('tiptapEditor').getSelectedContent().trim().length == 0) {
                    toastr.warning('Please select text');
                    return;
                }

                // selected range
                const range = Alpine.store('tiptapEditor').getSelectedRange();

                Alpine.store('appLoadingIndicator').show();
                let formData = new FormData();
                formData.append('prompt', 'Change tone of below content to ' + tone + ' tone.\n');
                formData.append('content', Alpine.store('tiptapEditor').getSelectedContent());

                $.ajax({
                    type: 'post',
                    url: '/dashboard/user/openai/update-writing',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(data) {
                        Alpine.store('tiptapEditor').replaceSelectedContent(range, data.result);
                        Alpine.store('appLoadingIndicator').hide();
                    },
                    error: function(data) {
                        if (data.status === 401) {
                            toastr.warning("{{ __('You need to be logged in to use AI actions.') }}");
                        }
                        Alpine.store('appLoadingIndicator').hide();
                    }
                });
            },
        }));
        });
    </script>
@endpush
