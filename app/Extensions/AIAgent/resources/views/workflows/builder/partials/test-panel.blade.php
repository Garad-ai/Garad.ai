{{-- Right Test Panel --}}
<div
    class="group/test relative z-20 flex shrink-0 flex-col gap-5 overflow-hidden overflow-y-auto border-s border-border bg-background py-4 transition-all duration-200 max-lg:absolute max-lg:inset-x-0 max-lg:bottom-0 max-lg:h-[calc(100vh-var(--header-h)-6rem)] max-lg:translate-y-full max-lg:border-t max-lg:shadow-2xl max-lg:shadow-black/5 lg:w-0 lg:py-6 max-lg:[&.is-open]:translate-y-0 lg:[&.is-open]:w-[--sidebar-w]"
    :class="{ 'is-open': testPanelOpen }"
>

    {{-- Panel Header --}}
    <div class="mb-5 flex shrink-0 items-center gap-2 px-4 lg:px-6">
        <div class="min-w-0 flex-1">
            <p class="mb-1 text-base font-medium">
                {{ __('Test Agent') }}
            </p>

            <p class="m-0 text-2xs">
                {{ __('Send a test message') }}
            </p>
        </div>

        {{-- Close button --}}
        <x-button
            class="inline-grid size-[30px] place-items-center"
            variant="ghost"
            size="none"
            type="button"
            @click="closeTestPanel()"
        >
            <x-tabler-x class="size-4" />
            <span class="sr-only">{{ __('Close') }}</span>
        </x-button>
    </div>

    {{-- Disabled state: non-channel trigger --}}
    <template x-if="testChannelType === null">
        <x-empty-state
            icon="tabler-message-off"
            :title="__('Channel trigger required')"
            :description="__('Switch the trigger to Channel Message to test here.')"
        />
    </template>

    {{-- Active state: channel trigger --}}
    <template x-if="testChannelType !== null">
        <div class="px-4 lg:px-6">
            <div
                class="mb-5 flex h-[570px] min-h-0 flex-1 flex-col overflow-hidden rounded-xl bg-cover bg-center shadow-2xl shadow-black/10"
                :style="{
                    'background-image': ['whatsapp', 'telegram'].includes(testChannelType) ?
                        `url({{ custom_theme_url('/vendor/ai-agent/images/bg/') }}${testChannelType}-chat-bg.jpg)` : null
                }"
            >

                {{-- Connection warning banner --}}
                <template x-if="testChannelStatus && testChannelStatus.connected === false">
                    <x-alert
                        class="shrink-0 items-center gap-4 rounded-none border-x-0 border-t-0 text-xs"
                        variant="danger-fill"
                        size="md"
                        icon="tabler-alert-triangle"
                    >
                        <p x-text="testChannelStatus.message"></p>
                    </x-alert>
                </template>

                {{-- Channel top bar --}}
                <div class="flex shrink-0 flex-col bg-background p-5">
                    <p
                        class="m-0 truncate text-2xs font-semibold leading-tight"
                        x-text="testChannelName || '{{ __('Channel') }}'"
                    ></p>
                    <p class="m-0 text-2xs leading-tight opacity-70">
                        <template x-if="testChannelType === 'telegram'">
                            <span>{{ __('bot') }}</span>
                        </template>
                        <template x-if="testChannelType === 'whatsapp'">
                            <span>{{ __('online') }}</span>
                        </template>
                        <template x-if="testChannelType !== 'telegram' && testChannelType !== 'whatsapp'">
                            <span>{{ __('web') }}</span>
                        </template>
                    </p>
                </div>

                {{-- Messages area --}}
                <div
                    class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-5"
                    x-ref="testMessagesWrap"
                >
                    {{-- Loading state --}}
                    <template x-if="testLoading">
                        <div class="flex items-center justify-center py-10">
                            <x-tabler-refresh class="size-5 animate-spin text-foreground/30" />
                        </div>
                    </template>

                    {{-- Empty state --}}
                    <template x-if="testMessages.length === 0 && !testSending && !testLoading">
                        <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                            <x-tabler-messages class="size-8 opacity-75" />
                            <p class="text-xs">{{ __('Send a message to test the workflow.') }}</p>
                        </div>
                    </template>

                    {{-- Message bubbles --}}
                    <div class="flex flex-col gap-1">
                        <template
                            x-for="(message, index) in testMessages"
                            :key="message.id"
                        >
                            <div
                                class="flex w-full"
                                :class="message.direction === 'inbound' ? 'justify-end' : 'justify-start'"
                            >
                                <div
                                    class="max-w-[85%] px-5 py-3 text-xs leading-snug shadow-sm"
                                    :class="{
                                        'rounded-tl-2xl rounded-tr-2xl rounded-bl-2xl rounded-br-sm': message.direction === 'inbound',
                                        'rounded-tl-2xl rounded-tr-2xl rounded-br-2xl rounded-bl-sm': message.direction === 'outbound',
                                    }"
                                    :style="message.direction === 'inbound' ?
                                        (testChannelType === 'telegram' ?
                                            'background: #5288c1; color: #fff' :
                                            testChannelType === 'whatsapp' ?
                                            'background: #d9fdd3; color: #111b21' :
                                            '') :
                                        (testChannelType === 'telegram' || testChannelType === 'whatsapp' ?
                                            'background: #ffffff; color: #111b21' :
                                            '')"
                                    :class="message.direction === 'inbound' ?
                                        (testChannelType !== 'telegram' && testChannelType !== 'whatsapp' ? 'bg-primary text-primary-foreground' : '') :
                                        (testChannelType !== 'telegram' && testChannelType !== 'whatsapp' ? 'bg-secondary text-secondary-foreground' : '')"
                                >
                                    <p
                                        class="m-0 whitespace-pre-wrap break-words"
                                        x-text="message.content"
                                    ></p>
                                    <p
                                        class="mb-0 mt-0.5 select-none text-[11px] opacity-70"
                                        :class="{
                                            'text-end': message.direction === 'inbound',
                                        }"
                                        x-text="message.created_at ? new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''"
                                    ></p>
                                </div>
                            </div>
                        </template>

                        {{-- Typing / running indicator --}}
                        <template x-if="testSending">
                            <div class="flex justify-start">
                                <div
                                    class="rounded-bl-sm rounded-br-2xl rounded-tl-2xl rounded-tr-2xl px-4 py-3"
                                    :style="testChannelType === 'telegram' || testChannelType === 'whatsapp' ? 'background: #ffffff' : ''"
                                    :class="testChannelType !== 'telegram' && testChannelType !== 'whatsapp' ? 'bg-secondary' : ''"
                                >
                                    <div class="flex items-center gap-1">
                                        <span
                                            class="size-1.5 animate-bounce rounded-full bg-current opacity-40"
                                            style="animation-delay: 0ms"
                                        ></span>
                                        <span
                                            class="size-1.5 animate-bounce rounded-full bg-current opacity-40"
                                            style="animation-delay: 150ms"
                                        ></span>
                                        <span
                                            class="size-1.5 animate-bounce rounded-full bg-current opacity-40"
                                            style="animation-delay: 300ms"
                                        ></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- View conversation link --}}
                    <template x-if="testConversationId && !testSending">
                        <div class="pt-3 text-center">
                            <a
                                class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-[10px] backdrop-blur-lg backdrop-brightness-125 backdrop-saturate-50 transition-colors"
                                :class="testChannelType === 'telegram' ? 'bg-[#0088cc]/10 text-[#0088cc] hover:bg-[#0088cc]/20' : testChannelType === 'whatsapp' ?
                                    'bg-[#25D366]/10 text-[#25D366] hover:bg-[#25D366]/20' : 'bg-foreground/5 text-foreground/40 hover:text-primary'"
                                href="{{ route('dashboard.user.ai-agent.messages.index') }}"
                                target="_blank"
                            >
                                <x-tabler-external-link class="size-3" />
                                <span>{{ __('View in Messages') }}</span>
                            </a>
                        </div>
                    </template>

                    {{-- Input bar --}}
                    <div class="sticky bottom-0 -mx-2 mt-auto shrink-0">
                        <form
                            class="relative m-0 flex"
                            @submit.prevent="
							const text = $refs.testInput?.value?.trim();
							if (text) { $refs.testInput.value = ''; sendTestMessage(text); }
						"
                        >
                            <textarea
                                class="max-h-24 min-h-[52px] flex-1 resize-none rounded-full border-none bg-background py-4 pe-12 ps-5 text-sm text-foreground"
                                rows="1"
                                x-ref="testInput"
                                placeholder="{{ __('Message') }}"
                                ::disabled="testSending"
                                @keydown.enter.prevent="
								if (!$event.shiftKey) {
									const text = $el.value?.trim();
									if (text) { $el.value = ''; sendTestMessage(text); }
								} else {
									$el.value += '\n';
								}
							"
                            ></textarea>

                            <button
                                class="absolute end-1 top-1/2 z-1 inline-grid size-10 -translate-y-1/2 place-items-center"
                                type="submit"
                                ::disabled="testSending"
                            >
                                <svg
                                    width="15"
                                    height="12"
                                    viewBox="0 0 15 12"
                                    fill="currentColor"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M0 12V7.5L6 6L0 4.5V0L14.25 6L0 12Z"
                                        fill="black"
                                    />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="flex w-full items-center gap-4 text-3xs">
                <span class="inline-block h-px grow bg-border"></span>
                <span class="inline-flex items-center gap-2">
                    <img
                        class="size-4 rounded-sm object-contain object-center"
                        :src="`{{ custom_theme_url('/vendor/ai-agent/images/platforms/') }}${testChannelType}.png`"
                        :alt="testChannelType"
                    >
                    <span>
                        {{ __('Testing on') }}
                        <span
                            class="capitalize"
                            x-text="testChannelType"
                        ></span>
                    </span>
                </span>
                <span class="inline-block h-px grow bg-border"></span>
            </div>
        </div>
    </template>
</div>
