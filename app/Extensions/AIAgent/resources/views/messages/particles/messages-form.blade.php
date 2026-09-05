<div
    class="shrink-0 lg:px-8 lg:py-7"
    x-show="activeConv"
    x-cloak
>
    <form
        class="lqd-chat-form gap-y-1 border border-border bg-background lg:rounded-xl"
        id="chat_form"
        @submit.prevent="sendMessage"
        x-transition
    >
        <textarea
            class="min-h-12 w-full resize-none border-none bg-transparent px-6 pt-4 placeholder:text-foreground/70 focus:outline-none sm:text-xs"
            rows="2"
            name="message"
            @keydown.enter.prevent="onMessageFieldHitEnter"
            @input="onMessageFieldInput"
            @input.throttle.50ms="$el.scrollTop = $el.scrollHeight"
            x-ref="messageInput"
            placeholder="{{ __('Message') }}"
        ></textarea>

        <div class="flex w-full items-center justify-between gap-2 px-6 pb-4">
            <x-button
                class="ms-auto bg-background text-[12px] font-medium text-foreground shadow-lg disabled:shadow-none max-lg:!bg-transparent max-lg:shadow-none max-lg:hover:text-foreground"
                hover-variant="primary"
                type="submit"
                x-ref="submitBtn"
                disabled
                ::disabled="!$refs.messageInput?.value?.trim() || sending"
            >
                <span class="max-lg:hidden">
                    {{ __('Send') }}
                </span>
                <svg
                    class="fill-current lg:hidden"
                    width="19"
                    height="16"
                    viewBox="0 0 19 16"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path d="M0 16V10L8 8L0 6V0L19 8L0 16Z" />
                </svg>
            </x-button>
        </div>
    </form>
</div>
