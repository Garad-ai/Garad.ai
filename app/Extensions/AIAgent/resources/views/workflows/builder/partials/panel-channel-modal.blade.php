{{-- Channel Manager Modal --}}
<div
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    x-show="channelModal.open"
    x-cloak
    @keydown.escape.window="channelModal.open && (channelModal.open = false)"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-foreground/30 backdrop-blur-sm"
        @click="channelModal.open = false"
    ></div>

    {{-- Modal --}}
    <div
        class="relative z-10 flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl"
        style="max-height: min(600px, 90dvh)"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        {{-- Header --}}
        <div class="flex shrink-0 items-center justify-between border-b border-border px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-heading-foreground">{{ __('Channels') }}</h3>
                <p class="mt-0.5 text-xs text-foreground/50">{{ __('Manage your connected messaging channels.') }}</p>
            </div>
            <button
                class="grid size-7 place-items-center rounded-lg text-foreground/40 hover:bg-foreground/5 hover:text-foreground"
                type="button"
                @click="channelModal.open = false"
            >
                <x-tabler-x class="size-4" />
            </button>
        </div>

        {{-- Tabs --}}
        <div class="flex shrink-0 border-b border-border">
            <button
                class="flex-1 border-b-2 px-4 py-3 text-xs font-medium transition-colors"
                :class="channelModal.tab === 'list' ?
                    'border-primary text-primary' :
                    'border-transparent text-foreground/50 hover:text-foreground'"
                type="button"
                @click="channelModal.tab = 'list'"
            >
                {{ __('My Channels') }}
                <span
                    class="ms-1.5 inline-flex items-center justify-center rounded-full bg-foreground/10 px-1.5 py-0.5 text-[10px] font-semibold leading-none"
                    x-text="channels.length"
                ></span>
            </button>
            <button
                class="flex-1 border-b-2 px-4 py-3 text-xs font-medium transition-colors"
                :class="channelModal.tab === 'create' ?
                    'border-primary text-primary' :
                    'border-transparent text-foreground/50 hover:text-foreground'"
                type="button"
                @click="channelModal.tab = 'create'; channelModal.errors = {}"
            >
                {{ __('Connect New') }}
            </button>
        </div>

        {{-- ── Tab: My Channels ─────────────────────────────────────── --}}

    </div>
</div>
