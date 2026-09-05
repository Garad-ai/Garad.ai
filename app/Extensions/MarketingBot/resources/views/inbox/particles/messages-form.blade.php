<div class="sticky bottom-0 lg:px-8 lg:py-7">
    <x-progressive-blur class="max-lg:hidden" />

    <form
        class="lqd-chat-form gap-y-1 border-t bg-foreground/5 backdrop-blur-2xl backdrop-contrast-[105%] lg:rounded-xl lg:border-t-0"
        id="chat_form"
        @submit.prevent="onSendMessage"
        x-transition
    >
        <textarea
            class="min-h-12 w-full resize-none border-none bg-transparent px-6 pt-4 placeholder:text-foreground/70 focus:outline-none sm:text-xs"
            id="message"
            rows="2"
            name="message"
            @keydown.enter.prevent="onMessageFieldHitEnter"
            @input="onMessageFieldInput"
            @input.throttle.50ms="$el.scrollTop = $el.scrollHeight"
            x-ref="message"
            placeholder="{{ __('Message') }}"
        ></textarea>

        <div class="flex w-full items-center justify-between gap-2 px-6 pb-4">
            <x-button
                class="ms-auto bg-background text-[12px] font-medium text-foreground shadow-lg disabled:shadow-none max-lg:!bg-transparent max-lg:shadow-none max-lg:hover:text-foreground"
                hover-variant="primary"
                type="submit"
                x-ref="submitBtn"
                disabled
                ::disabled="!$refs.message.value.trim()"
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
