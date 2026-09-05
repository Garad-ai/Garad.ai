<nav
    class="lqd-cs-nav fixed inset-x-0 top-0 z-20 flex min-h-[--header-h] gap-6 border-b border-heading-foreground/5 bg-background/90 px-6 py-2.5 shadow-lg shadow-black/5 backdrop-blur-xl backdrop-saturate-[120%]"
    x-data="{ mobileDropdownOpen: false }"
>
    <div class="flex grow items-center gap-6">
        <div class="inline-grid place-items-center">
            <x-button
                class="col-start-1 col-end-1 row-start-1 row-end-1 size-[34px] hover:translate-y-0"
                variant="outline"
                hover-variant="primary"
                size="none"
                title="{{ __('Back') }}"
                @click.prevent="switchView('<')"
                x-show="currentView !== 'home'"
                x-cloak
            >
                <x-tabler-chevron-left class="size-4" />
            </x-button>

            <x-button
                class="col-start-1 col-end-1 row-start-1 row-end-1 size-[34px] hover:translate-y-0"
                variant="outline"
                hover-variant="primary"
                size="none"
                title="{{ __('Go to Dashboard') }}"
                href="{{ route('dashboard.user.index') }}"
                x-show="currentView === 'home'"
            >
                <x-tabler-x class="size-4" />
            </x-button>
        </div>

        <div class="inline-grid place-items-start">
            <form
                class="relative col-start-1 col-end-1 row-start-1 row-end-1 hidden items-center gap-1 lg:[&.active]:flex"
                action="#"
                :class="{ 'active': currentView === 'editor' }"
                @submit.prevent="updateDocumentName"
            >
                <input
                    type="hidden"
                    name="id"
                    x-model="currentDocId"
                >
                <input
                    class="min-h-8 border-b bg-transparent px-1 text-2xs font-medium focus:outline-none"
                    type="text"
                    name="name"
                    x-model="currentDocName"
                    placeholder="{{ __('Document Name') }}"
                >
                <button
                    class="inline-grid size-7 shrink-0 place-items-center rounded-full border bg-background shadow-sm"
                    type="submit"
                >
                    <x-tabler-edit
                        class="col-start-1 col-end-1 row-start-1 row-end-1 size-4"
                        x-show="!updatingName"
                    />
                    <x-tabler-refresh
                        class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 animate-spin"
                        x-show="updatingName"
                    />
                    <span class="sr-only">{{ __('Rename') }}</span>
                </button>
            </form>
            <div
                class="col-start-1 col-end-1 row-start-1 row-end-1"
                :class="{ 'lg:hidden': currentView === 'editor' }"
            >
                <x-header-logo />
            </div>
        </div>
    </div>

    <div class="flex select-none items-center justify-end gap-6 max-lg:gap-3">
        <div
            class="flex items-center gap-2.5"
            x-show="currentView === 'editor'"
            x-cloak
        >
            <x-button
                class="inline-grid size-[34px] place-content-center rounded-full border hover:border-primary hover:bg-primary hover:text-primary-foreground"
                variant="outline"
                title="{{ __('Undo') }}"
                type="button"
                disabled
                ::disabled="historyPointer === -1"
                @click.prevent="undo"
            >
                <x-tabler-arrow-back-up class="size-5" />
            </x-button>
            <x-button
                class="inline-grid size-[34px] place-content-center rounded-full border hover:border-primary hover:bg-primary hover:text-primary-foreground"
                variant="outline"
                title="{{ __('Redo') }}"
                type="button"
                disabled
                ::disabled="historyPointer >= history.length - 1"
                @click.prevent="redo"
            >
                <x-tabler-arrow-forward-up class="size-5" />
            </x-button>
        </div>
        <x-button
            class="text-2xs lg:hidden"
            variant="link"
            @click.prevent="$nextTick(() => mobileDropdownOpen = !mobileDropdownOpen)"
        >
            @lang('More')
            <x-tabler-dots class="size-4" />
        </x-button>

        <div
            class="flex items-center gap-4 max-lg:absolute max-lg:end-16 max-lg:top-[calc(100%+0.625rem)] max-lg:z-20 max-lg:hidden max-lg:max-h-[75vh] max-lg:flex-col max-lg:items-start max-lg:gap-3 max-lg:overflow-y-auto max-lg:rounded-xl max-lg:bg-background max-lg:px-5 max-lg:py-4 max-lg:shadow-lg max-lg:shadow-black/5 max-lg:[&.active]:flex"
            :class="{ active: mobileDropdownOpen }"
            @click.outside="mobileDropdownOpen = false"
        >
            <p class="w-full border-b pb-2.5 text-2xs/none font-medium text-foreground/65 lg:hidden">
                {{ __('Actions') }}
            </p>

            <form
                class="relative mb-3 flex items-center gap-1 lg:hidden"
                action="#"
                x-cloak
                x-show="currentView === 'editor'"
                @submit.prevent="updateDocumentName"
            >
                <input
                    type="hidden"
                    name="id"
                    x-model="currentDocId"
                >
                <input
                    class="min-h-8 grow border-b bg-transparent px-1 text-2xs font-medium focus:outline-none"
                    type="text"
                    name="name"
                    x-model="currentDocName"
                    placeholder="{{ __('Document Name') }}"
                >
                <button
                    class="inline-grid size-7 shrink-0 place-items-center rounded-full border bg-background shadow-sm"
                    type="submit"
                >
                    <x-tabler-edit
                        class="col-start-1 col-end-1 row-start-1 row-end-1 size-4"
                        x-show="!updatingName"
                    />
                    <x-tabler-refresh
                        class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 animate-spin"
                        x-show="updatingName"
                    />
                    <span class="sr-only">{{ __('Rename') }}</span>
                </button>
            </form>

            <div
                class="pointer-events-none flex gap-4 opacity-50"
                x-show="currentView === 'editor'"
                x-cloak
                :class="{ 'opacity-50 pointer-events-none': busy || showWelcomeScreen }"
            >
                <x-button
                    class="text-2xs"
                    variant="link"
                    @click.prevent="fitToScreen"
                >
                    @lang('Fit to Screen')
                </x-button>

                <button
                    class="inline-grid size-5 place-content-center rounded-full bg-heading-foreground/5 text-heading-foreground transition-all hover:scale-110 hover:bg-heading-foreground hover:text-heading-background disabled:pointer-events-none disabled:opacity-50"
                    type="button"
                    @click.prevent="zoomOut"
                    :disabled="reachedMinZoom"
                >
                    <x-tabler-minus class="size-3" />
                </button>
                <span class="text-2xs font-medium">
                    <input
                        class="w-[3ch] bg-transparent"
                        x-model.number="zoomLevel"
                        :min="minZoom"
                        :max="maxZoom"
                        type="text"
                        value="100"
                    />%
                </span>
                <button
                    class="inline-grid size-5 place-content-center rounded-full bg-heading-foreground/5 text-heading-foreground transition-all hover:scale-110 hover:bg-heading-foreground hover:text-heading-background disabled:pointer-events-none disabled:opacity-50"
                    type="button"
                    @click.prevent="zoomIn"
                    :disabled="reachedMaxZoom"
                >
                    <x-tabler-plus class="size-3" />
                </button>
            </div>

            <x-button
                class="pointer-events-none shrink-0 text-2xs opacity-50 max-lg:flex-row-reverse"
                variant="link"
                ::class="{ 'opacity-50 pointer-events-none': busy || showWelcomeScreen }"
                @click.prevent="handleExport"
                x-show="currentView === 'editor'"
                x-cloak
            >
                @lang('Export')
                <span class="inline-grid size-[34px] place-content-center rounded-full border">
                    <x-tabler-upload class="size-4" />
                </span>
            </x-button>

            <x-button
                class="shrink-0 text-2xs max-lg:flex-row-reverse"
                variant="link"
                @click.prevent="handleImport"
            >
                @lang('Import')
                <span class="inline-grid size-[34px] place-content-center rounded-full border">
                    <x-tabler-download class="size-4" />
                </span>
            </x-button>

            <x-button
                class="pointer-events-none shrink-0 text-2xs opacity-50 max-lg:flex-row-reverse"
                variant="link"
                ::class="{ 'opacity-50 pointer-events-none': busy || showWelcomeScreen }"
                @click.prevent="resetCanvas"
                x-show="currentView === 'editor'"
                x-cloak
            >
                @lang('New Project')
                <span class="inline-grid size-[34px] place-content-center rounded-full border">
                    <x-tabler-refresh class="size-4 group-hover:animate-spin group-hover:[animation-iteration-count:1]" />
                </span>
            </x-button>

            <x-button
                class="pointer-events-none text-2xs opacity-50 max-lg:flex-row-reverse"
                variant="link"
                ::class="{ 'opacity-50 pointer-events-none': busy || showWelcomeScreen }"
                @click.prevent="downloadImage"
                x-show="currentView === 'editor'"
                x-cloak
            >
                @lang('Download')
                <span class="inline-grid size-[34px] place-content-center rounded-full border">
                    <x-tabler-circle-chevron-down class="size-5" />
                </span>
            </x-button>

            <x-button
                class="pointer-events-none text-2xs opacity-50 max-lg:flex-row-reverse"
                variant="link"
                ::class="{ 'opacity-50 pointer-events-none': busy || showWelcomeScreen }"
                @click.prevent="saveDocument"
                x-show="currentView === 'editor'"
                x-cloak
            >
                @lang('Save')
                <span class="inline-grid size-[34px] place-content-center rounded-full border">
                    <x-tabler-device-floppy class="size-5" />
                </span>
            </x-button>

            {{-- <x-button
                class="text-2xs"
                variant="link"
                @click.prevent="showImageDetails = !showImageDetails; zoomLevel = 1;"
            >
                @lang('Details')
                <x-tabler-dots class="size-5 opacity-70" />
            </x-button> --}}

        </div>

    </div>
</nav>
