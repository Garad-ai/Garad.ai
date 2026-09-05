<template x-for="(message, index) in (callsList.find(c => c.id == activeCall)?.transcripts ?? []).filter(m => !transcriptSearch || m.message?.toLowerCase().includes(transcriptSearch.toLowerCase()))">
    <div
        class="lqd-ext-phone-history-message flex gap-2"
        :class="{ 'flex-row-reverse': message.role === 'agent' }"
        :key="'message-' + index"
    >
        <div class="lqd-ext-phone-history-message-content-wrap space-y-1.5 lg:max-w-[60%]">
            <div
                class="lqd-ext-phone-history-message-content rounded-2xl px-4 pb-2.5 pt-3"
                :class="{
                    'bg-heading-foreground/5 text-heading-foreground': message.role === 'user',
                    'bg-secondary text-secondary-foreground ms-auto dark:bg-zinc-700 dark:text-primary-foreground': message.role === 'agent'
                }"
            >
                <p
                    class="prose m-0 w-full whitespace-normal font-body text-sm text-current dark:prose-invert"
                    x-text="message.message"
                ></p>
                <div
                    class="mt-1.5 flex items-center gap-1 text-[11px] opacity-50"
                    :class="{ 'justify-end': message.role === 'agent' }"
                >
                    <span
                        class="capitalize"
                        x-text="message.role === 'agent' ? '{{ __('Agent') }}' : '{{ __('User') }}'"
                    ></span>
                    <span>·</span>
                    <span x-text="getDiffHumanTime(message.created_at)"></span>
                </div>
            </div>
        </div>
    </div>
</template>

<template x-if="!callsList.find(c => c.id == activeCall)?.transcripts?.length && activeCall">
    <p class="text-center opacity-50">{{ __('No transcript available for this call.') }}</p>
</template>
