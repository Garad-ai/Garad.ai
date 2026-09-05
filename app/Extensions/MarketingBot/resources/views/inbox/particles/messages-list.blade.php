<template x-for="(message, index) in activeChat?.histories">
    <div
        class="lqd-ext-chatbot-history-message flex max-w-[430px] gap-2"
        data-sender="message.role"
        :class="{ 'flex-row-reverse ms-auto': message.role === 'assistant' }"
    >
        <template x-if="message.role === 'user'">
            <svg
                class="flex-shrink-0"
                width="32"
                height="31"
                viewBox="0 0 32 31"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0C7.44048 0 0.5 6.93943 0.5 15.5C0.5 24.0606 7.4398 31 16 31C24.5609 31 31.5 24.0606 31.5 15.5C31.5 6.93943 24.5609 0 16 0ZM16 4.63468C18.8323 4.63468 21.1274 6.93057 21.1274 9.76163C21.1274 12.5934 18.8323 14.8886 16 14.8886C13.1691 14.8886 10.874 12.5934 10.874 9.76163C10.874 6.93057 13.1691 4.63468 16 4.63468ZM15.9966 26.9475C13.1718 26.9475 10.5846 25.9187 8.58906 24.2158C8.10294 23.8012 7.82243 23.1931 7.82243 22.5552C7.82243 19.6839 10.1461 17.386 13.0179 17.386H18.9834C21.8559 17.386 24.1708 19.6839 24.1708 22.5552C24.1708 23.1938 23.8916 23.8005 23.4048 24.2151C21.41 25.9187 18.8221 26.9475 15.9966 26.9475Z"
                    fill="#879EC4"
                />
            </svg>
        </template>

        <div class="lqd-ext-chatbot-history-message-content-wrap max-w-full space-y-1 lg:max-w-[420px]">
            <div
                class="lqd-ext-chatbot-history-message-content w-full rounded-xl p-3 [&_.lqd-ext-chatbot-product-carousel-btn-prev]:start-3 [&_.lqd-ext-chatbot-product-carousel]:-mx-3"
                :class="{
                    'bg-heading-foreground/5 text-heading-foreground': message.role === 'user',
                    'bg-secondary text-secondary-foreground ms-auto dark:bg-zinc-700 dark:text-primary-foreground': message.role === 'assistant'
                }"
            >
                <pre
                    class="peer prose m-0 w-full whitespace-normal font-body text-sm text-current dark:prose-invert empty:hidden"
                    :class="{ 'text-heading-foreground': message.role === 'user', 'text-black': message.role === 'assistant' }"
                    x-html="getFormattedString(message.message)"
                ></pre>

                <template x-if="message.media_url">
                    <a
                        class="mt-2 block font-medium text-current underline underline-offset-2 peer-empty:mt-0"
                        :data-fslightbox="isImage ? 'gallery' : null"
                        :href="message.media_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        x-data="{ isImage(string) { return /\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)$/i.test(string) } }"
                    >
                        <template x-if="message.media_url && isImage(message.media_url)">
                            <img
                                class="mt-2 max-w-full rounded-lg peer-empty:mt-0"
                                :src="message.media_url"
                                :alt="message.media_name || 'Uploaded image'"
                                x-init="if ('refreshFsLightbox' in window) { refreshFsLightbox() }"
                            />
                        </template>
                        <template x-if="!message.media_url || !isImage(message.media_url)">
                            <span x-text="message.media_name ?? message.media_url"></span>
                        </template>
                    </a>
                </template>

                <p
                    class="mb-0 mt-1 flex items-center gap-1.5 text-3xs opacity-40"
                    :class="{ 'justify-end text-end': message.role === 'assistant' }"
                    x-show="(message.media_url && message.media_name) || message.message.trim()"
                >
                    <span x-text="message.role === 'user' ? (activeChat.conversation_name ?? '{{ __('Anonymous') }}') : '{{ __('Agent') }}'"></span>
                    <span class="inline-block size-0.5 rounded-full bg-current"></span>
                    <span x-text="getDiffHumanTime(message.created_at)"></span>
                </p>
            </div>
        </div>
    </div>
</template>

<template x-if="!fetching && !activeChat?.histories?.length">
    <h4>
        {{ __('No messages found.') }}
    </h4>
</template>
