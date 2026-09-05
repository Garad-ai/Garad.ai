@php
    // Also needed by chat_head below; this view is re-rendered standalone over
    // AJAX when switching chats, so it cannot rely on index.blade.php's scope.
    $disable_actions = $app_is_demo && (isset($category) && in_array($category->slug, ['ai_vision', 'ai_pdf', 'ai_chat_image']));

    $example_prompts = collect([
        ['name' => '📊 Pipeline summary', 'prompt' => 'Give me a summary of my sales pipeline by stage.'],
        ['name' => '⏰ Overdue tasks', 'prompt' => 'Which of my tasks are overdue? List them by priority.'],
        ['name' => '🏆 Top deals', 'prompt' => 'What are my top 5 deals by value and what stage are they in?'],
        ['name' => '👤 Add a contact', 'prompt' => 'Create a new contact named Jane Smith at Acme Inc.'],
        ['name' => '🧾 Draft an invoice', 'prompt' => 'Create a draft invoice for my most recent deal.'],
        ['name' => '📁 Project health', 'prompt' => 'Which projects are at risk of missing their due date?'],
        ['name' => '💰 Outstanding balance', 'prompt' => 'How much is currently outstanding across all invoices?'],
        ['name' => '📑 Pitch deck', 'prompt' => 'Generate a modern pitch deck for my largest open deal.'],
    ])
        ->map(fn($item) => (object) $item)
        ->toArray();
    $example_prompts_json = json_encode($example_prompts, JSON_THROW_ON_ERROR);
    $example_prompts = json_decode(setting('crm_assistant_example_prompts', $example_prompts_json), false, 512, JSON_THROW_ON_ERROR);
@endphp

<div
    class="conversation-area flex h-[inherit] grow flex-col justify-between overflow-y-auto rounded-b-[inherit] rounded-t-[inherit] max-md:max-h-full"
    id="chat_area_to_hide"
>

    @if (view()->hasSection('chat_head'))
        @yield('chat_head')
    @else
        @include('panel.user.openai_chat.components.chat_head')
    @endif

    <div class="relative flex grow flex-col">

        <div class="grid h-full w-full place-items-center overflow-x-hidden">
            <div
                class="pointer-events-none invisible col-start-1 col-end-1 row-start-1 row-end-1 flex w-full scale-[1.1] flex-col items-center overflow-hidden py-10 opacity-0 transition-all group-[&.conversation-not-started]/chats-wrap:pointer-events-auto group-[&.conversation-not-started]/chats-wrap:visible group-[&.conversation-not-started]/chats-wrap:scale-100 group-[&.conversation-not-started]/chats-wrap:opacity-100 lg:-mt-7">
                <figure
                    class="mx-auto mb-6 hidden max-w-[300px] lg:block"
                    aria-hidden="true"
                >
                    <img
                        src="{{ @asset('/vendor/crm/images/img-1.png') }}"
                        alt="{{ __('CRM Assistant') }}"
                        width="300"
                        height="265"
                    >
                </figure>
                <h2 class="mb-8 px-5 text-center text-[26px] font-medium leading-[1.1em] md:text-[34px] lg:text-[clamp(34px,2vw,40px)]">
                    <span class="text-[0.7em]">
                        <span class="opacity-50">
                            @lang('Hello :username', ['username' => auth()->user()?->name ?? ''])
                        </span>
                        👋
                    </span>
                    <br>
                    {{ __('Ask me anything about your CRM.') }}
                </h2>

                <div
                    class="flex w-full gap-4 [--mask-from:7rem] [--mask-to:calc(100%-7rem)]"
                    style="mask-image: linear-gradient(to right, transparent, black var(--mask-from), black var(--mask-to), transparent);"
                    x-data="marquee({ pauseOnHover: true })"
                >
                    <div class="lqd-marquee-viewport relative flex w-full overflow-hidden">
                        <div class="lqd-marquee-slider flex w-full gap-4 py-2 lg:px-14">
                            @for ($i = 0; $i < 3; $i++)
                                @foreach ($example_prompts ?? [] as $prompt)
                                    <button
                                        class="lqd-marquee-cell inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl bg-foreground/5 px-5 py-4 text-sm font-normal leading-[1.15em] transition-all hover:-translate-y-1 hover:bg-primary hover:text-primary-foreground hover:shadow dark:bg-heading-foreground/5"
                                        data-prompt="{{ __($prompt?->prompt) }}"
                                        type="button"
                                        @click.prevent="prompt = $event.currentTarget.getAttribute('data-prompt'); $nextTick(() => { $refs.prompt.focus(); $refs.prompt.dispatchEvent(new Event('input',{bubbles:true})); });"
                                    >
                                        {{ __($prompt?->name) }}
                                    </button>
                                @endforeach
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="chats-container col-start-1 col-end-1 row-start-1 row-end-1 h-full w-full overflow-x-hidden p-8 text-xs transition-all group-[&.conversation-not-started]/chats-wrap:pointer-events-none group-[&.conversation-not-started]/chats-wrap:invisible group-[&.conversation-not-started]/chats-wrap:scale-95 group-[&.conversation-not-started]/chats-wrap:opacity-0 max-md:p-4">

                @if (view()->hasSection('chat_area'))
                    @yield('chat_area')
                @else
                    @include('panel.user.openai_chat.components.chat_area')
                @endif
            </div>
        </div>
    </div>

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
        @include('crm::assistant.includes.chat_form')
    @endif
</div>
