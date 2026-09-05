@php
    use App\Helpers\Classes\MarketplaceHelper;
    use Illuminate\Support\Facades\Auth;

    $canvas_enabled = \App\Helpers\Classes\MarketplaceHelper::isRegistered('canvas') && (bool) setting('ai_chat_pro_canvas', 1);
    $dr_canvas_enabled = !$canvas_enabled && \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-deep-research') && (bool) setting('deep_research_auto_canvas', 1);
    $temp_chat_enabled = \App\Helpers\Classes\MarketplaceHelper::isRegistered('chat-pro-temp-chat');
    $realtime_chat_enabled = \App\Helpers\Classes\MarketplaceHelper::isRegistered('openai-realtime-chat');
    $deep_research_registered = \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-deep-research');
    $deep_research_enabled = $deep_research_registered && (auth()->user()?->isAdmin() || auth()->user()?->relationPlan?->checkOpenAiItem('deep_research'));
    $prompt_library_enabled = setting('user_prompt_library') == null || setting('user_prompt_library');
    $fileChatAllowed = MarketplaceHelper::isRegistered('ai-chat-pro-file-chat') && (int) setting('chatpro_file_chat_allowed', 1) === 1;

    $input_padding_end_classname = 'pe-[70px]';
    $input_padding_end_max_lg_classname = 'max-lg:pe-[50px]';

    if ($temp_chat_enabled) {
        $input_padding_end_classname = 'pe-[125px]';
        $input_padding_end_max_lg_classname = 'max-lg:pe-[100px]';
    }
    if ($realtime_chat_enabled) {
        $input_padding_end_classname = 'pe-[180px]';
        $input_padding_end_max_lg_classname = 'max-lg:pe-[150px]';
    }

    $prompt_filters = [
        'all' => __('All'),
        'favorite' => __('Favorite'),
    ];
@endphp

<div
    class="lqd-chat-form-wrap sticky bottom-0 z-9 rounded-b-[inherit] bg-background md:before:pointer-events-none md:before:absolute md:before:inset-x-0 md:before:-top-14 md:before:bottom-0 md:before:z-0 md:before:bg-background md:before:transition-colors md:before:[mask-image:linear-gradient(to_bottom,transparent,black_30%)] lg:pb-8">
    {{-- using form element cause issues in webchat after analyzing a website --}}
    <div
        class="lqd-chat-form flex w-full flex-wrap items-end gap-3 self-end rounded-ee-[inherit] px-8 py-5 max-md:items-end max-md:p-4 max-sm:px-2 max-sm:py-3 md:relative md:z-1 md:px-5 lg:mx-auto lg:w-full lg:max-w-[820px] lg:p-0"
        id="chat_form"
    >
        @csrf

        @includeIf('chat-pro-temp-chat::temp-desc', ['compact' => true, 'tempChat' => $tempChat])

        @includeIf('ai-chat-pro-deep-research::deep-research-desc')

        <div
            class="lqd-chat-form-inputs-container flex min-h-[52px] w-full flex-col rounded-[26px] border border-solid border-heading-foreground/5 bg-background transition-colors lg:rounded-[35px] lg:border-none lg:bg-surface-background lg:shadow-[0_4px_60px_rgba(0,0,0,0.035)] dark:lg:bg-heading-foreground/15">
            <div
                class="hidden max-h-32 w-full flex-wrap gap-5 overflow-y-auto p-2.5 [&.active]:flex"
                id="chat-attachment-previews"
            ></div>

            <div class="relative flex grow items-center max-md:px-2">
                <input
                    id="selectImageInput"
                    type="file"
                    style="display: none;"
                    @if ($category->slug != 'ai_vision' && $category->slug != 'ai_pdf' && !$fileChatAllowed) accept="image/*" @endif
                />

                <x-forms.input
                    id="prompt"
                    @class([
                        'm-0 max-h-32 w-full border-none bg-transparent py-2 max-sm:ps-[38px] text-sm text-heading-foreground transition-[border,background,box-shadow,border-radius,padding,margin] placeholder:text-heading-foreground focus:outline-none focus:ring-0 group-[&.prompt-filled]/chats-wrap:pe-[125px] max-lg:group-[&.prompt-filled]/chats-wrap:pe-[100px] max-md:max-h-[200px] max-md:pe-11 max-md:text-[16px] max-md:group-[&.prompt-filled]/chats-wrap:pe-[75px] sm:text-sm lg:min-h-[70px] lg:py-6',
                        $input_padding_end_classname,
                        $input_padding_end_max_lg_classname,
                        'lg:ps-20 ps-11' => $category->slug !== 'ai_pdf',
                    ])
                    ::class="{ '!px-3 !mb-14 lg:!px-5 lg:!mb-[70px]': selectedTools.length }"
                    container-class="w-full"
                    type="textarea"
                    placeholder="{{ __('Type a message') }}"
                    name="prompt"
                    rows="1"
                    x-model="prompt"
                    x-ref="prompt"
                    ::bind="prompt"
                />

                <div
                    class="pointer-events-none absolute bottom-2 end-1.5 start-1.5 flex shrink-0 items-end justify-between gap-1 text-sm md:bottom-2 md:gap-1.5 lg:bottom-3.5 lg:end-5 lg:start-5 lg:gap-2.5">
                    <x-dropdown.dropdown
                        class="pointer-events-auto"
                        class:dropdown="w-[200px] p-4 max-h-72 overflow-y-auto lg:-ms-4 border-none"
                        triggerType="click"
                        offsetY="22px"
                    >
                        <x-slot:trigger
                            class="size-[34px] shrink-0 origin-center border border-foreground/5 p-0 lg:size-11"
                        >
                            <x-tabler-plus class="size-5 transition group-[&.lqd-is-active]/dropdown:rotate-45" />
                            <span class="sr-only">{{ __('Options') }}</span>
                        </x-slot:trigger>

                        <x-slot:dropdown>
                            <button
                                @class([
                                    'lqd-chat-attach flex items-center gap-2.5 text-2xs font-medium',
                                    'hidden' => $category->slug === 'ai_pdf',
                                ])
                                type="button"
                                @if ($app_is_demo) onclick='return toastr.info("@lang('This feature is disabled in Demo version.')")'
								@elseif(!auth()->check()) onclick='return toastr.warning("@lang('Login to upload a document or image.')")'
								@else id="chat_add_image" @endif
                                title="{{ __('Upload Files') }}"
                                @click="toggle(false)"
                            >
                                <x-tabler-paperclip class="size-4.5 shrink-0 opacity-75" />
                                <span class="truncate">
                                    {{ __('Upload Files') }}
                                </span>
                            </button>

                            <hr class="my-2.5">

                            <div
                                class="space-y-2 [&_button.active]:text-primary [&_button]:flex [&_button]:w-full [&_button]:items-center [&_button]:gap-2.5 [&_button]:truncate [&_button]:py-1 [&_button]:text-2xs [&_button]:font-medium">
                                @if (!in_array($category->slug, ['ai_pdf', 'ai_vision', 'ai_chat_image'], true))
                                    @auth
                                        <x-forms.input
                                            class="realtime-checkbox group hidden"
                                            class:label="group !text-2xs !font-medium text-foreground gap-2.5 py-1 [&:has(.active)]:text-primary"
                                            class:label-extra="m-0 order-first"
                                            id="realtime"
                                            type="checkbox"
                                            switcher
                                            label="{{ __('Web Search') }}"
                                            size="sm"
                                            name="realtime"
                                            ::class="{ 'active': hasToolSelected('realtime') }"
                                            @change.prevent="toggleToolSelection('realtime');"
                                            x-init="$watch('selectedTools', value => { console.log(value); if (!value.includes('realtime')) $el.checked = false })"
                                        >
                                            <x-slot:label-extra>
                                                <x-tabler-world class="size-4.5 shrink-0 opacity-75" />
                                            </x-slot:label-extra>

                                            <x-tabler-check class="invisible ms-auto size-4.5 shrink-0 peer-[&.active]:visible" />
                                        </x-forms.input>
                                    @else
                                        <x-forms.input
                                            class="realtime-checkbox hidden"
                                            class:label="group text-2xs font-medium text-foreground gap-2.5 py-1 [&.active]:text-primary"
                                            class:label-extra="m-0 order-first"
                                            id="realtime"
                                            type="checkbox"
                                            switcher
                                            label="{{ __('Web Search') }}"
                                            size="sm"
                                            name="realtime"
                                            @change.prevent="toastr.warning('{{ __('Login to use web search') }}'); $el.checked = false; return false;"
                                        >
                                            <x-slot:label-extra>
                                                <x-tabler-world class="size-4.5 shrink-0 opacity-75" />
                                            </x-slot:label-extra>

                                            <x-tabler-check class="invisible ms-auto size-4.5 shrink-0 group-[&.active]:visible" />
                                        </x-forms.input>
                                    @endauth
                                @endif

                                @if ($deep_research_registered)
                                    @includeIf('ai-chat-pro-deep-research::deep-research-button', ['category' => $category])
                                @endif

                                @if ($canvas_enabled)
                                    @includeIf('canvas::includes.canvas-button')
                                @elseif ($dr_canvas_enabled)
                                    @includeIf('ai-chat-pro-deep-research::includes.canvas-button')
                                @endif

                                @if ($prompt_library_enabled)
                                    <button
                                        class="lqd-chat-templates-trigger group"
                                        type="button"
                                        title="{{ __('Prompt Library') }}"
                                        @click.prevent="toggle(false); {{ auth()->check() ? 'togglePromptLibraryShow()' : 'toastr.warning(\'' . __('Login to access prompt library.') . '\')' }}"
                                    >
                                        <x-tabler-book class="size-4.5 shrink-0 opacity-75" />

                                        <span class="truncate">
                                            {{ __('Prompt library') }}
                                        </span>
                                    </button>
                                    @auth
                                        @include('panel.user.openai_chat.components.prompt_library_modal')
                                    @endauth
                                @endif

                                @includeFirst(['ai-chat-pro-skills::components.chat-skills-button', 'vendor.empty'])

                                {{-- Brand Voice --}}
                                <x-modal
                                    class="lqd-chat-brand-voice"
                                    class:modal-body="md:p-7 p-5"
                                    id="brandVoiceModal"
                                    title="{{ __('Brand Voice') }}"
                                    @click.prevent="toggle(false); {{ auth()->check() ? null : 'toastr.warning(\'' . __('Login to include your brand voice in the chat.') . '\')' }}"
                                >
                                    <x-slot:trigger
                                        custom
                                    >
                                        <button
                                            class="lqd-chat-brand-voice-trigger group"
                                            :class="{ 'active': hasToolSelected('brand_voice') }"
                                            type="button"
                                            variant="none"
                                            title="{{ __('Brand Voice') }}"
                                            @click.prevent="toggleModal()"
                                        >
                                            <x-tabler-target-arrow class="size-4.5 shrink-0 opacity-75" />

                                            <span class="truncate">
                                                {{ __('Brand Voice') }}
                                            </span>

                                            <x-tabler-check class="invisible ms-auto size-4.5 shrink-0 group-[&.active]:visible" />
                                        </button>
                                    </x-slot:trigger>
                                    @auth
                                        <x-slot:modal
                                            x-data="{
                                                onFormSubmit($event) {
                                                    const formData = new FormData($event.target);
                                                    const brandVoice = formData.get('chat_brand_voice');
                                                    const brandVoiceProd = formData.get('brand_voice_prod');

                                                    if (brandVoice.trim() && brandVoiceProd.trim()) {
                                                        addToolSelection('brand_voice');
                                                    } else {
                                                        removeToolSelection('brand_voice');
                                                    }

                                                    toggleModal();
                                                }
                                            }"
                                        >
                                            <form
                                                class="flex flex-col gap-6"
                                                @submit.prevent="onFormSubmit"
                                            >
                                                <x-forms.input
                                                    id="chat_brand_voice"
                                                    type="select"
                                                    size="lg"
                                                    name="chat_brand_voice"
                                                    label="{{ __('Select Company') }}"
                                                    onchange="getProductByBrand(this.value)"
                                                >
                                                    <option value="">
                                                        {{ __('Select Company') }}
                                                    </option>
                                                    @foreach (auth()->user()?->getCompanies() ?? [] as $company)
                                                        <option
                                                            data-tone_of_voice="{{ $company->tone_of_voice }}"
                                                            value="{{ $company->id }}"
                                                        >
                                                            {{ $company->name }}
                                                        </option>
                                                    @endforeach
                                                </x-forms.input>

                                                <x-forms.input
                                                    id="brand_voice_prod"
                                                    type="select"
                                                    size="lg"
                                                    name="brand_voice_prod"
                                                    label="{{ __('Select Product / Service') }}"
                                                >
                                                    <option value="">{{ __('Select Product') }}</option>
                                                </x-forms.input>

                                                <div class="border-t pt-3 text-end">
                                                    <x-button
                                                        @click.prevent="modalOpen = false"
                                                        type="button"
                                                        variant="outline"
                                                    >
                                                        {{ __('Cancel') }}
                                                    </x-button>

                                                    <x-button type="submit">
                                                        {{ __('Done') }}
                                                    </x-button>
                                                </div>
                                            </form>
                                        </x-slot:modal>
                                    @endauth
                                </x-modal>
                            </div>
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>

                    @include('ai-chat-pro::includes.selected-tools')

                    <div
                        class="ms-auto flex grow items-center justify-end gap-1 md:flex md:h-full md:gap-1.5 lg:gap-2.5"
                        id="chat-options"
                    >
                        @includeIf('chat-pro-temp-chat::temp_chat_button', ['compact' => true, 'tempChat' => $tempChat, 'category' => $category])

                        @auth
                            {{-- Record Audio --}}
                            <div class="pointer-events-auto group-[&.prompt-filled]/chats-wrap:hidden">
                                <x-button
                                    class="lqd-chat-record-trigger flex size-[34px] shrink-0 cursor-pointer items-center justify-center gap-2 border border-foreground/5 text-heading-foreground transition-all hover:border-primary hover:bg-primary hover:text-primary-foreground lg:size-11 [&.inactive]:hidden"
                                    id="voice_record_button"
                                    type="button"
                                    variant="none"
                                    size="none"
                                    title="{{ __('Record audio') }}"
                                >
                                    <x-tabler-microphone
                                        class="size-[23px]"
                                        stroke-width="1.5"
                                    />
                                </x-button>

                                <x-button
                                    class="lqd-chat-record-stop-trigger hidden size-[34px] shrink-0 cursor-pointer items-center justify-center gap-2 border border-foreground/5 text-heading-foreground transition-all hover:border-primary hover:bg-primary hover:text-primary-foreground lg:size-11 [&.active]:flex"
                                    id="voice_record_stop_button"
                                    type="button"
                                    variant="none"
                                    size="none"
                                    title="{{ __('Stop recording') }}"
                                >
                                    <x-tabler-player-pause-filled class="size-5" />
                                </x-button>
                            </div>
                        @endauth

                        @includeIf('openai-realtime-chat::chat-button', ['compact' => true, 'category_slug' => $category->slug, 'messages' => $chat->messages])

                        {{-- Send button --}}
                        <div class="pointer-events-auto hidden group-[&.prompt-filled]/chats-wrap:block">
                            <x-button
                                class="lqd-chat-send-btn aspect-square size-[34px] shrink-0 bg-heading-foreground/5 text-heading-foreground hover:bg-primary hover:text-primary-foreground lg:size-11"
                                id="{{ $category->slug == 'ai_vision' && $app_is_demo ? '' : 'send_message_button' }}"
                                size="none"
                                tag="button"
                                onclick="{!! $category->slug == 'ai_vision' && $app_is_demo ? 'return toastr.info(\'{{ __('This feature is disabled in Demo version.') }}\')' : '' !!}"
                                type="submit"
                            >
                                <svg
                                    class="size-4 rtl:-scale-x-100"
                                    width="15"
                                    height="12"
                                    viewBox="0 0 15 12"
                                    fill="currentColor"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path d="M0.25 12V7.5L6.25 6L0.25 4.5V0L14.5 6L0.25 12Z" />
                                </svg>
                            </x-button>
                            <x-button
                                class="lqd-chat-stop-btn hidden aspect-square size-[34px] shrink-0 bg-rose-500 lg:size-11 [&.active]:flex"
                                id="stop_button"
                                size="none"
                                tag="button"
                            >
                                <x-tabler-hand-stop
                                    class="size-6"
                                    stroke-width="1.5"
                                />
                            </x-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input
            id="chatType"
            type="hidden"
            value="socialMediaAgent"
        />
        <input
            id="assistant"
            type="hidden"
            value="{{ $category->assistant }}"
        />
        <input
            id="chatbot_id"
            type="hidden"
            value="{{ $category->chatbot_id }}"
        />
        <input
            id="category_id"
            type="hidden"
            value="{{ $category?->id }}"
        />
        <input
            id="chat_id"
            type="hidden"
            value="{{ isset($chat) ? $chat->id : null }}"
        />
        <input
            id="selected_skill_ids"
            type="hidden"
            value=""
        />

    </div>

    @includeFirst(['ai-chat-pro-skills::components.skills-modal', 'vendor.empty'])

    @guest
        <p class="relative z-2 mx-auto text-center text-2xs text-heading-foreground/30 max-lg:px-6 lg:mb-0 lg:mt-6 lg:max-w-screen-md">
            {{ __(setting('guest_user_bottom_text', 'Login to save your current session. © MagicAI 2025. All rights reserved.')) }}
            <br>
            <x-button
                class="text-4xs uppercase tracking-widest text-heading-foreground/50 hover:text-heading-foreground"
                href="{{ url('/terms') }}"
                variant="link"
            >
                {{ __('Terms Of Use') }}
            </x-button>
        </p>
    @endguest
</div>
