<div
    class="group relative flex items-center gap-2"
    x-data="{
        currentView: 'actions',
        prompt: '',
        get selectedNodesHasText() {
            return selectedNodes.find(node => node.getClassName() === 'Text')
        },
        get selectedNodesHasFill() {
            return selectedNodes.find(node => node.getClassName() === 'Image' || node.fillPatternImage())
        },
        get selectedNodesHasImageFill() {
            return selectedNodes.find(node => node.fillPatternImage())
        },
    }"
    x-effect="$dispatch('ai-edit-mode', { active: currentView !== 'actions' })"
    :class="{
        'active': activeDropdown === 'ai',
        'in-progress': selectedNodes.find(node => node.getAttr('aiTaskInProgress')),
        'w-[min(500px,90vw)]': currentView === 'editWithAI'
    }"
    x-show="selectedNodesHasText || selectedNodesHasFill"
    @click.outside="activeDropdown === 'ai' && (activeDropdown = null);"
    @nodes-selected.window="if ( currentView === 'editWithAI' && !selectedNodesHasFill ) { currentView = 'actions' }"
>
    {{-- Back button — visible only during inline prompt mode --}}
    <button
        class="inline-grid size-10 shrink-0 place-items-center rounded-lg bg-primary/5 transition-all hover:bg-primary hover:text-primary-foreground"
        type="button"
        x-cloak
        x-show="currentView !== 'actions'"
        @click.prevent="currentView = 'actions'"
    >
        <x-tabler-arrow-left class="size-4" />
    </button>

    {{-- AI sparkle trigger --}}
    <div class="relative inline-flex size-10 shrink-0">
        <div
            class="pointer-events-none absolute inset-2 z-0 hidden animate-spin-grow rounded-full bg-gradient-to-r from-orange-500/50 via-green-400/40 to-teal-400/40 opacity-0 blur transition-opacity group-[&.in-progress]:opacity-100">
        </div>

        <button
            class="relative z-1 inline-grid size-10 place-items-center rounded-lg bg-primary/5 transition-all group-[&.active]:bg-primary group-[&.in-progress:not(.active)]:bg-primary/10 group-[&.active]:shadow-xl group-[&.in-progress:not(.active)]:shadow-xl group-[&.active]:shadow-primary/30 group-[&.in-progress:not(.active)]:shadow-primary/10"
            :class="{ 'pointer-events-none': currentView !== 'actions' }"
            type="button"
            @click.prevent="if ( currentView === 'actions' ) { activeDropdown = activeDropdown === 'ai' ? null : 'ai' }"
        >
            <svg
                width="18"
                height="17"
                viewBox="0 0 18 17"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    class="origin-center transition-all group-[&.in-progress]:animate-spin group-[&.active]:fill-primary-foreground group-[&.in-progress]:[animation-duration:2s]"
                    style="transform-box: content-box;"
                    d="M3.83594 10.0078C3.9444 10.0078 4.05013 10.0452 4.13379 10.1143C4.21732 10.1832 4.27426 10.2794 4.29492 10.3857L4.37207 10.7646C4.46658 11.231 4.69585 11.6595 5.03223 11.9961C5.36871 12.3327 5.79721 12.5625 6.26367 12.6572L6.6416 12.7344C6.74808 12.755 6.84406 12.8119 6.91309 12.8955C6.98205 12.9791 7.01946 13.084 7.01953 13.1924C7.01953 13.3008 6.98212 13.4066 6.91309 13.4902C6.84407 13.5738 6.748 13.6308 6.6416 13.6514L6.26367 13.7285C5.79756 13.8231 5.36963 14.0515 5.0332 14.3877C4.69676 14.724 4.46689 15.152 4.37207 15.6182L4.29492 15.9961C4.27435 16.1024 4.21727 16.1986 4.13379 16.2676C4.05013 16.3366 3.9444 16.375 3.83594 16.375C3.72755 16.375 3.62267 16.3366 3.53906 16.2676C3.4554 16.1985 3.39853 16.1026 3.37793 15.9961L3.30078 15.6182C3.2061 15.152 2.9761 14.724 2.63965 14.3877C2.30316 14.0514 1.87448 13.822 1.4082 13.7275L1.03027 13.6504C0.923928 13.6297 0.827746 13.5728 0.758789 13.4893C0.689759 13.4056 0.652344 13.2999 0.652344 13.1914C0.652414 13.0831 0.689921 12.9781 0.758789 12.8945C0.827733 12.811 0.923941 12.7541 1.03027 12.7334L1.4082 12.6562C1.87461 12.5617 2.30315 12.3316 2.63965 11.9951C2.97603 11.6587 3.20623 11.2309 3.30078 10.7646L3.37793 10.3857C3.39861 10.2795 3.45553 10.1832 3.53906 10.1143C3.62262 10.0454 3.72767 10.0079 3.83594 10.0078Z"
                    fill="url(#paint0_linear_263_193)"
                />
                <path
                    class="origin-center transition-all group-[&.in-progress]:animate-spin group-[&.active]:fill-primary-foreground group-[&.in-progress]:[animation-duration:2s]"
                    style="transform-box: content-box;"
                    d="M11.8262 0.517578C11.9664 0.517578 12.1028 0.565351 12.2119 0.65332C12.321 0.741269 12.397 0.864041 12.4268 1.00098L12.708 2.39355C12.8441 3.0639 13.1755 3.67941 13.6592 4.16309C14.1429 4.64666 14.7584 4.97726 15.4287 5.11328L16.8203 5.39355C16.9591 5.42196 17.0843 5.49675 17.1738 5.60645C17.2634 5.71623 17.3125 5.8544 17.3125 5.99609C17.3124 6.13765 17.2633 6.27508 17.1738 6.38477C17.0842 6.49453 16.9591 6.57021 16.8203 6.59863L15.4287 6.87988V6.88184C14.7585 7.01784 14.1428 7.34854 13.6592 7.83203C13.1754 8.31577 12.8441 8.9321 12.708 9.60254L12.4268 10.9941C12.397 11.1311 12.321 11.2538 12.2119 11.3418C12.1028 11.4298 11.9664 11.4775 11.8262 11.4775C11.6861 11.4775 11.5505 11.4297 11.4414 11.3418C11.3323 11.2538 11.2563 11.1311 11.2266 10.9941L10.9443 9.60254C10.8085 8.93199 10.4779 8.31581 9.99414 7.83203C9.51042 7.34841 8.89502 7.01762 8.22461 6.88184L6.83203 6.60059C6.69344 6.57213 6.56899 6.4963 6.47949 6.38672C6.38998 6.27703 6.3409 6.13962 6.34082 5.99805C6.34082 5.85645 6.39002 5.71912 6.47949 5.60938C6.56897 5.49972 6.69341 5.424 6.83203 5.39551L8.22461 5.11328C8.89501 4.97735 9.51043 4.64676 9.99414 4.16309C10.4778 3.67941 10.8084 3.06391 10.9443 2.39355L11.2266 1.00098C11.2564 0.864075 11.3323 0.741251 11.4414 0.65332C11.5504 0.565537 11.6862 0.517643 11.8262 0.517578Z"
                    fill="url(#paint1_linear_263_193)"
                />
                {{-- blade-formatter-disable --}}
				<defs> <linearGradient id="paint0_linear_263_193" x1="0.652344" y1="3.75249" x2="14.0229" y2="16.1424" gradientUnits="userSpaceOnUse" > <stop stop-color="#82E2F4" /> <stop offset="0.502" stop-color="#8A8AED" /> <stop offset="1" stop-color="#6977DE" /> </linearGradient> <linearGradient id="paint1_linear_263_193" x1="0.652344" y1="3.75249" x2="14.0229" y2="16.1424" gradientUnits="userSpaceOnUse" > <stop stop-color="#82E2F4" /> <stop offset="0.502" stop-color="#8A8AED" /> <stop offset="1" stop-color="#6977DE" /> </linearGradient> </defs>
				{{-- blade-formatter-enable --}}
            </svg>
        </button>
    </div>

    {{-- Dropdown — only for action list --}}
    <div
        class="absolute -start-2 top-full mt-4 max-h-[calc(100vh-100px-var(--header-h,0px))] min-w-[min(200px,90vw)] max-w-[90vw] overflow-y-auto rounded-xl bg-background px-5 py-4 shadow-lg shadow-black/5"
        x-cloak
        x-show="activeDropdown === 'ai' && currentView === 'actions'"
        x-transition
    >
        {{-- Text actions --}}
        <div
            class="flex flex-col gap-px"
            :class="{ 'opacity-50 pointer-events-none': selectedNodes.find(node => node.getClassName() === 'Text' && node.getAttr('aiTaskInProgress')) }"
            x-show="selectedNodesHasText"
        >
            <p class="w-full border-b pb-2.5 text-2xs/none font-medium text-foreground/65">
                {{ __('AI Text Actions') }}
            </p>

            <x-button
                class="-mx-2.5 justify-start gap-2 rounded-md py-1.5 text-start text-2xs hover:translate-y-0"
                variant="ghost"
                hover-variant="primary"
                size="sm"
                @click.prevent="aiTextAction({prompt: '{{ __('Make below content longer') }}'})"
            >
                {{-- blade-formatter-disable --}}
                <svg class="opacity-60" width="15" height="16" viewBox="0 0 15 16" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M1.16667 10.375H13.8333M1.16667 14.3333H10.6667M1.16667 2.45833C1.16667 2.24837 1.25007 2.04701 1.39854 1.89854C1.54701 1.75007 1.74837 1.66667 1.95833 1.66667H5.125C5.33496 1.66667 5.53633 1.75007 5.68479 1.89854C5.83326 2.04701 5.91667 2.24837 5.91667 2.45833V5.625C5.91667 5.83496 5.83326 6.03633 5.68479 6.18479C5.53633 6.33326 5.33496 6.41667 5.125 6.41667H1.95833C1.74837 6.41667 1.54701 6.33326 1.39854 6.18479C1.25007 6.03633 1.16667 5.83496 1.16667 5.625V2.45833Z"/> </svg>
                {{-- blade-formatter-enable --}}
                {{ __('Longer') }}
            </x-button>

            <x-button
                class="-mx-2.5 justify-start gap-2 rounded-md py-1.5 text-start text-2xs hover:translate-y-0"
                variant="ghost"
                hover-variant="primary"
                size="sm"
                @click.prevent="aiTextAction({prompt: '{{ __('Make below content shorter') }}'})"
            >
                {{-- blade-formatter-disable --}}
                <svg class="opacity-60" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M5.45 4.45L13.25 12.25M5.45 9.55L13.25 1.75M1.25 3.25C1.25 3.84674 1.48705 4.41903 1.90901 4.84099C2.33097 5.26295 2.90326 5.5 3.5 5.5C4.09674 5.5 4.66903 5.26295 5.09099 4.84099C5.51295 4.41903 5.75 3.84674 5.75 3.25C5.75 2.65326 5.51295 2.08097 5.09099 1.65901C4.66903 1.23705 4.09674 1 3.5 1C2.90326 1 2.33097 1.23705 1.90901 1.65901C1.48705 2.08097 1.25 2.65326 1.25 3.25ZM1.25 10.75C1.25 11.3467 1.48705 11.919 1.90901 12.341C2.33097 12.7629 2.90326 13 3.5 13C4.09674 13 4.66903 12.7629 5.09099 12.341C5.51295 11.919 5.75 11.3467 5.75 10.75C5.75 10.1533 5.51295 9.58097 5.09099 9.15901C4.66903 8.73705 4.09674 8.5 3.5 8.5C2.90326 8.5 2.33097 8.73705 1.90901 9.15901C1.48705 9.58097 1.25 10.1533 1.25 10.75Z"/> </svg>
                {{-- blade-formatter-enable --}}
                {{ __('Shorter') }}
            </x-button>

            <x-button
                class="-mx-2.5 justify-start gap-2 rounded-md py-1.5 text-start text-2xs hover:translate-y-0"
                variant="ghost"
                hover-variant="primary"
                size="sm"
                @click.prevent="aiTextAction({prompt: '{{ __('Improve writing of below content') }}'})"
            >
                {{-- blade-formatter-disable --}}
                <svg class="opacity-60" width="16" height="15" viewBox="0 0 16 15" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"> <path d="M9.3125 2.55064L12.8125 5.94303M11.5 12.3038H15M4.5 14L13.6875 5.09498C13.9173 4.87223 14.0996 4.60779 14.224 4.31676C14.3484 4.02572 14.4124 3.71379 14.4124 3.39878C14.4124 3.08377 14.3484 2.77184 14.224 2.48081C14.0996 2.18977 13.9173 1.92533 13.6875 1.70259C13.4577 1.47984 13.1849 1.30315 12.8846 1.1826C12.5843 1.06205 12.2625 1 11.9375 1C11.6125 1 11.2907 1.06205 10.9904 1.1826C10.6901 1.30315 10.4173 1.47984 10.1875 1.70259L1 10.6076V14H4.5Z"/> </svg>
                {{-- blade-formatter-enable --}}
                {{ __('Better') }}
            </x-button>
        </div>

        {{-- Image/Fill actions --}}
        <div
            class="flex flex-col gap-px"
            :class="{
                'opacity-50 pointer-events-none': selectedNodes.find(node => (node.getClassName() === 'Image' || node.fillPatternImage()) && node.getAttr('aiTaskInProgress')),
                'mt-4': selectedNodesHasText
            }"
            x-show="!selectedNodesHasText && selectedNodesHasFill"
        >
            <p class="w-full border-b pb-2.5 text-2xs/none font-medium text-foreground/65">
                {{ __('AI Actions') }}
            </p>

            {{-- Edit with AI --}}
            <x-button
                class="-mx-2.5 justify-start gap-2 whitespace-nowrap rounded-md py-1.5 text-start text-2xs hover:translate-y-0 disabled:bg-transparent"
                variant="ghost"
                hover-variant="primary"
                size="sm"
                type="button"
                @click.prevent="currentView = 'editWithAI'; prompt = ''; activeDropdown = null;"
            >
                <x-tabler-message-circle class="size-4" />
                {{ __('Edit with AI') }}
            </x-button>

            {{-- Remove Background --}}
            <x-button
                class="-mx-2.5 justify-start gap-2 whitespace-nowrap rounded-md py-1.5 text-start text-2xs hover:translate-y-0 disabled:bg-transparent"
                variant="ghost"
                hover-variant="primary"
                size="sm"
                type="button"
                ::disabled="!selectedNodesHasImageFill"
                @click.prevent="$refs.aiQuickActionTool.value = 'remove_background'; $refs.aiQuickActionForm.requestSubmit()"
            >
                <x-tabler-scissors class="size-4" />
                {{ __('Remove Background') }}
            </x-button>

            {{-- Reimagine --}}
            <x-button
                class="-mx-2.5 justify-start gap-2 whitespace-nowrap rounded-md py-1.5 text-start text-2xs hover:translate-y-0 disabled:bg-transparent"
                variant="ghost"
                hover-variant="primary"
                size="sm"
                type="button"
                ::disabled="!selectedNodesHasImageFill"
                @click.prevent="$refs.aiQuickActionTool.value = 'reimagine'; $refs.aiQuickActionForm.requestSubmit()"
            >
                <x-tabler-brush class="size-4" />
                {{ __('Reimagine') }}
            </x-button>
        </div>
    </div>

    {{-- Hidden form for quick actions (Remove Background, Reimagine) --}}
    <form
        class="hidden"
        x-ref="aiQuickActionForm"
        action="{{ route('dashboard.user.creative-suite.ai.editor') }}"
        method="POST"
        enctype="multipart/form-data"
        @submit.prevent="aiImageAction"
    >
        <input
            type="hidden"
            name="selected_tool"
            x-ref="aiQuickActionTool"
        />
        <input
            type="hidden"
            name="description"
            value=""
        />
        <input
            type="hidden"
            name="ai_model"
            value="{{ setting('creative_suite_ai_model', 'flux-pro/kontext') }}"
        />
    </form>

    {{-- Inline form: Edit with AI --}}
    <form
        class="relative flex grow items-center gap-0.5"
        action="{{ route('dashboard.user.creative-suite.ai.editor') }}"
        method="POST"
        enctype="multipart/form-data"
        x-cloak
        x-show="currentView === 'editWithAI'"
        @submit.prevent="aiImageAction"
        x-trap="currentView === 'editWithAI'"
    >
        <x-forms.input
            class:container="grow w-full"
            class="h-10 w-full border-none bg-transparent bg-none px-2 py-0 placeholder:text-input-foreground sm:text-xs sm:font-medium"
            size="lg"
            name="description"
            placeholder="{{ __('What would you like to do?') }}"
            x-model="prompt"
        />
        <input
            type="hidden"
            name="selected_tool"
            value="edit_with_ai"
        />
        <input
            type="hidden"
            name="ai_model"
            value="{{ setting('creative_suite_ai_model', 'flux-pro/kontext') }}"
        />

        <x-button
            class="absolute end-1 top-1/2 size-9 -translate-y-1/2 rounded-input border-none bg-transparent p-0 text-foreground hover:-translate-y-1/2 hover:bg-primary hover:text-primary-foreground disabled:bg-transparent"
            variant="none"
            type="submit"
            ::disabled="!prompt.trim() || selectedNodes.every(node => node.getAttr('aiTaskInProgress'))"
            title="@lang('Generate')"
        >
            <svg
                width="19"
                height="19"
                viewBox="0 0 19 19"
                fill="currentColor"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16.2036 16.2419C14.3649 18.0806 12.1304 19 9.5 19C6.86962 19 4.62231 18.0806 2.75806 16.2419C0.919355 14.3777 0 12.1304 0 9.5C0 6.86962 0.919355 4.63508 2.75806 2.79637C4.62231 0.932124 6.86962 0 9.5 0C12.1304 0 14.3649 0.932124 16.2036 2.79637C18.0679 4.63508 19 6.86962 19 9.5C19 12.1304 18.0679 14.3777 16.2036 16.2419ZM14.9012 4.09879C13.42 2.59207 11.6196 1.83871 9.5 1.83871C7.38038 1.83871 5.5672 2.59207 4.06048 4.09879C2.5793 5.57997 1.83871 7.38038 1.83871 9.5C1.83871 11.6196 2.5793 13.4328 4.06048 14.9395C5.5672 16.4207 7.38038 17.1613 9.5 17.1613C11.6196 17.1613 13.42 16.4207 14.9012 14.9395C16.4079 13.4328 17.1613 11.6196 17.1613 9.5C17.1613 7.38038 16.4079 5.57997 14.9012 4.09879ZM10.2661 14.4032H8.73387C8.42742 14.4032 8.27419 14.25 8.27419 13.9435V12.0665C8.27419 10.6491 7.12512 9.5 5.70766 9.5C5.50336 9.5 5.3629 9.41062 5.28629 9.23186C5.20968 9.02755 5.23522 8.86156 5.3629 8.73387L9.19355 4.90323C9.39785 4.69893 9.60215 4.69893 9.80645 4.90323L13.6371 8.73387C13.7648 8.86156 13.7903 9.02755 13.7137 9.23186C13.6371 9.41062 13.4966 9.5 13.2923 9.5C11.8749 9.5 10.7258 10.6491 10.7258 12.0665V13.9435C10.7258 14.25 10.5726 14.4032 10.2661 14.4032Z"
                />
            </svg>
        </x-button>
    </form>

</div>
