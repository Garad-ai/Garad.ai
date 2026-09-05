<template x-for="(message, index) in filteredThread" :key="message.id">
    <div
        class="lqd-ext-chatbot-history-message flex max-w-[430px] gap-2"
        :class="{ 'flex-row-reverse ms-auto': message.direction === 'inbound' }"
    >
        <template x-if="message.direction === 'outbound'">
            <figure class="inline-grid size-6 shrink-0 place-items-center rounded-full bg-primary/20 font-heading text-[12px] font-semibold uppercase text-primary">
                <span x-text="(activeConv?.sender_id ?? '?').slice(0, 1).toUpperCase()"></span>
            </figure>
        </template>

        <div class="lqd-ext-chatbot-history-message-content-wrap max-w-full space-y-1 lg:max-w-[420px]">
            <div
                class="lqd-ext-chatbot-history-message-content w-full rounded-xl p-3"
                :class="{
                    'bg-heading-foreground/5 text-heading-foreground': message.direction === 'outbound',
                    'bg-secondary text-secondary-foreground ms-auto dark:bg-zinc-700 dark:text-primary-foreground': message.direction === 'inbound'
                }"
            >
                <pre
                    class="prose m-0 w-full whitespace-normal font-body text-sm text-current dark:prose-invert"
                    x-html="getFormattedString(message.content)"
                ></pre>

                <p
                    class="mb-0 mt-1 flex items-center gap-1.5 text-3xs opacity-40"
                    :class="{ 'justify-end text-end': message.direction === 'inbound' }"
                >
                    <span x-text="message.direction === 'inbound' ? (message.raw_payload ? (activeConv?.sender_id ?? '{{ __('User') }}') : '{{ __('Web') }}') : '{{ __('AI Agent') }}'"></span>
                    <span class="inline-block size-0.5 rounded-full bg-current"></span>
                    <span x-text="formatTime(message.created_at)"></span>
                </p>
            </div>
        </div>
    </div>
</template>

<template x-if="!threadFetching && !filteredThread.length && activeConv">
    <h4>{{ __('No messages found.') }}</h4>
</template>
