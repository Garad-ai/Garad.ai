{{--
    Cloned into every `.crm-assistant-action-card` container that the markdown-it
    rule emits from the assistant's <<<ACTIONS>>> block. Full-width confirmation
    card using design tokens, with executed / cancelled resting states so a
    reloaded conversation shows the outcome instead of live buttons.
--}}
<template id="crm-assistant-action-card-template">
    <div class="w-full overflow-hidden rounded-2xl border border-primary/15 bg-primary/[.04] dark:border-white/10 dark:bg-white/[.03]">
        <div class="flex items-center gap-2 border-b border-primary/10 px-5 py-3 dark:border-white/5">
            <x-tabler-list-check class="size-4 shrink-0 text-primary" />
            <span class="text-xs font-semibold text-primary dark:text-heading-foreground">
                {{ __('Proposed Actions') }}
            </span>

            <span
                class="ms-auto inline-flex items-center gap-1 rounded-full bg-green-500/10 px-2.5 py-1 text-[10px] font-bold uppercase leading-none text-green-600 dark:text-green-400"
                x-show="state === 'executed'"
                x-cloak
            >
                <x-tabler-check class="size-3" />
                {{ __('Executed') }}
            </span>

            <span
                class="ms-auto inline-flex items-center gap-1 rounded-full bg-foreground/10 px-2.5 py-1 text-[10px] font-bold uppercase leading-none text-foreground/60"
                x-show="state === 'cancelled'"
                x-cloak
            >
                <x-tabler-x class="size-3" />
                {{ __('Cancelled') }}
            </span>
        </div>

        <ul class="m-0 flex list-none flex-col divide-y divide-primary/5 px-5 dark:divide-white/5">
            <template
                x-for="(act, aidx) in actions"
                :key="aidx"
            >
                <li class="m-0 flex items-center gap-3 p-0 py-2.5 text-[13px]">
                    <span
                        class="inline-flex min-w-16 shrink-0 items-center justify-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase leading-none"
                        :class="badgeClass(act.action)"
                        x-text="act.action"
                    ></span>
                    <span
                        class="leading-snug text-heading-foreground/80"
                        x-text="actionLabel(act)"
                    ></span>
                </li>
            </template>
        </ul>

        <div
            class="border-t border-primary/10 px-5 py-2.5 text-2xs text-red-600 dark:border-white/5 dark:text-red-400"
            x-show="errorMessage"
            x-cloak
        >
            <span x-text="errorMessage"></span>
        </div>

        <div
            class="flex items-center gap-2 border-t border-primary/10 px-5 py-3 dark:border-white/5"
            x-show="state === 'pending'"
        >
            <x-button
                {{-- text color repeated after text-2xs: twMerge drops the variant's own text-primary-foreground otherwise --}}
                class="!text-primary-foreground no-underline"
                size="sm"
                variant="primary"
                @click.prevent="confirmActions()"
                ::disabled="executing"
            >
                <span x-show="!executing">{{ __('Confirm') }}</span>
                <span
                    class="flex items-center gap-2"
                    x-show="executing"
                    x-cloak
                >
                    <span class="inline-block size-3 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                    {{ __('Executing...') }}
                </span>
            </x-button>
            <x-button
                class="no-underline"
                size="sm"
                variant="ghost-shadow"
                @click.prevent="cancelActions()"
                ::disabled="executing"
            >
                {{ __('Cancel') }}
            </x-button>
        </div>
    </div>
</template>
