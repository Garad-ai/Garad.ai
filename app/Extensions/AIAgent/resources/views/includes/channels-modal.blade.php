<x-modal
    class:modal-content="flex flex-col p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none h-[min(670px,calc(100vh-4rem))] !min-w-0 w-[min(940px,100%)]"
    class:modal-head="p-0 shrink-0"
    class:modal-title="text-[12px] font-semibold"
    class:modal-body="p-0 flex flex-col grow"
    class:modal-container="flex flex-col grow w-full"
    class:close-btn="size-7"
    class:close-btn-icon="size-4"
    id="ai-agent-channels-modal"
>
    <x-slot:head-content>
        <div class="flex gap-5">
            <button
                class="active py-2 text-[12px] font-semibold text-heading-foreground opacity-50 transition [&.active]:opacity-100"
                @click.prevent="channelModal.tab = 'create'; channelModal.errors = {}"
                :class="{ 'active': channelModal.tab === 'create' }"
            >
                {{ __('Add Channel') }}
            </button>

            <button
                class="py-2 text-[12px] font-semibold text-heading-foreground opacity-50 transition [&.active]:opacity-100"
                @click.prevent="channelModal.tab = 'list'"
                :class="{ 'active': channelModal.tab === 'list' }"
            >
                {{ __('My Channels') }}
            </button>
        </div>
    </x-slot:head-content>

    <x-slot:modal>
        <form
            class="flex grow flex-col gap-4 py-5"
            x-show="channelModal.tab === 'create'"
            @submit.prevent="createChannel()"
        >
            {{-- Error banner --}}
            <template x-if="channelModal.errors._global">
                <p
                    class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600"
                    x-text="channelModal.errors._global"
                ></p>
            </template>

            {{-- Type --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach (array_values(array_filter(\App\Extensions\AIAgent\System\Enums\ChannelEnum::cases(), fn($c) => $c !== \App\Extensions\AIAgent\System\Enums\ChannelEnum::MagicAi && $c->isActive())) as $channelType)
                    <button
                        class="flex items-center gap-3 rounded-[10px] border px-4 py-5 text-start transition active:scale-95 md:px-6 [&.active]:border-primary [&.active]:ring-1 [&.active]:ring-primary"
                        type="button"
                        :class="{ 'active': channelModal.type === '{{ $channelType->value }}' }"
                        @click.prevent="channelModal.type = '{{ $channelType->value }}'"
                    >
                        <img
                            class="size-5 object-contain object-center"
                            src="{{ custom_theme_url('/vendor/ai-agent/images/platforms/' . $channelType->value . '.png') }}"
                            alt="{{ $channelType->label() }}"
                            width="40"
                            height="40"
                        >
                        {{ $channelType->label() }}
                    </button>
                @endforeach
            </div>

            {{-- Name --}}
            <x-forms.input
                type="text"
                label="{{ __('Channel Name') }}"
                x-bind:placeholder="channelModal.namePlaceholders[channelModal.type] || '{{ __('e.g. My Bot') }}'"
                x-model="channelModal.name"
                required
            >
                <template x-if="channelModal.errors.name">
                    <p
                        class="mt-1 text-xs text-red-500"
                        x-text="channelModal.errors.name[0]"
                    ></p>
                </template>
            </x-forms.input>

            {{-- Telegram credentials --}}
            <x-forms.input
                type="text"
                label="{{ __('Bot Token') }}"
                placeholder="{{ __('Paste the token from @BotFather') }}"
                x-model="channelModal.credentials.telegram_token"
                x-show="channelModal.type === 'telegram'"
            >
                <p class="mt-1 text-xs text-foreground/50">
                    {{ __('Create a bot via') }}
                    <a
                        class="text-primary underline"
                        href="https://t.me/BotFather"
                        target="_blank"
                    >@BotFather</a>
                    {{ __('and paste the token here. The webhook will be registered automatically.') }}
                </p>
                <template x-if="channelModal.errors['credentials.telegram_token']">
                    <p
                        class="mt-1 text-xs text-red-500"
                        x-text="channelModal.errors['credentials.telegram_token'][0]"
                    ></p>
                </template>
            </x-forms.input>

            @if (class_exists(\App\Extensions\AIAgentWhatsappChannel\System\Connectors\WhatsappConnector::class))
                {{-- WhatsApp credentials --}}
                <div
                    class="flex flex-col gap-3"
                    x-show="channelModal.type === 'whatsapp'"
                >
                    <x-forms.input
                        type="text"
                        label="{{ __('Phone Number ID') }}"
                        placeholder="{{ __('e.g. 123456789012345') }}"
                        x-model="channelModal.credentials.whatsapp_phone_number_id"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Access Token') }}"
                        placeholder="{{ __('System User Access Token from Meta Business') }}"
                        x-model="channelModal.credentials.whatsapp_access_token"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Webhook Verify Token') }}"
                        placeholder="{{ __('A secret string you choose for webhook verification') }}"
                        x-model="channelModal.credentials.whatsapp_verify_token"
                    />
                </div>
            @endif

            @if (class_exists(\App\Extensions\AIAgentSlackChannel\System\Connectors\SlackConnector::class))
                {{-- Slack credentials --}}
                <div
                    class="flex flex-col gap-3"
                    x-show="channelModal.type === 'slack'"
                >
                    <x-forms.input
                        type="text"
                        label="{{ __('Bot Token') }}"
                        placeholder="xoxb-..."
                        x-model="channelModal.credentials.slack_bot_token"
                    />
                    <x-forms.input
                        type="text"
                        label="{{ __('Signing Secret') }}"
                        placeholder="{{ __('From Slack App → Basic Information → App Credentials') }}"
                        x-model="channelModal.credentials.slack_signing_secret"
                    />
                </div>
            @endif

            <x-button
                class="mt-auto w-full"
                type="submit"
                variant="secondary"
                ::disabled="channelModal.loading"
            >
                <template x-if="channelModal.loading">
                    <x-tabler-loader-2 class="size-4 animate-spin" />
                </template>
                <span>
                    {{ __('Connect') }}
                </span>
                <span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                    <x-tabler-chevron-right class="size-4" />
                </span>
            </x-button>
        </form>

        <div
            class="flex grow flex-col gap-4 py-5"
            x-show="channelModal.tab === 'list'"
        >
            {{-- Empty state --}}
            <template x-if="channels.length === 0">
                <div class="flex flex-col items-center justify-center gap-4 p-3 text-center">
                    <x-empty-state
                        icon="tabler-plug-x"
                        title="{{ __('No channels yet') }}"
                        description="{{ __('Connect your first channel to start receiving messages.') }}"
                    />

                    <x-button
                        variant="outline"
                        type="button"
                        hover-variant="primary"
                        @click.prevent="channelModal.tab = 'create'"
                    >
                        {{ __('Connect a channel') }}
                    </x-button>
                </div>
            </template>

            {{-- Channel list --}}
            <template x-if="channels.length > 0">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                    <template
                        x-for="ch in channels"
                        :key="ch.id"
                    >
                        <div class="flex items-center justify-between gap-3.5 rounded-lg border px-4 py-2.5 md:ps-6">
                            {{-- Icon + info --}}
                            <div class="flex min-w-0 items-center gap-3">
                                <figure
                                    class="shrink-0"
                                    aria-hidden="true"
                                >
                                    <img
                                        class="size-5 object-contain object-center"
                                        :src="`{{ custom_theme_url('/vendor/ai-agent/images/platforms') }}/${ch.type}.png`"
                                        width="40"
                                        height="40"
                                        :alt="ch.type_label || ch.type"
                                        aria-hidden="true"
                                    >
                                </figure>
                                <div class="min-w-0">
                                    <p
                                        class="m-0 truncate text-sm font-medium text-heading-foreground"
                                        x-text="ch.name"
                                        :title="ch.name"
                                    ></p>
                                    <p
                                        class="m-0 truncate text-3xs opacity-85"
                                        x-text="ch.type_label || ch.type"
                                    ></p>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex shrink-0 items-center gap-0.5">
                                {{-- Copy webhook URL (WhatsApp / Slack) --}}
                                <template x-if="ch.webhook_url">
                                    <button
                                        class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-[11px] font-medium text-foreground/50 transition-colors hover:bg-foreground/5 hover:text-foreground"
                                        type="button"
                                        @click="navigator.clipboard.writeText(ch.webhook_url); toastr.success('{{ __('Webhook URL copied.') }}')"
                                        title="{{ __('Copy webhook URL') }}"
                                    >
                                        <x-tabler-copy class="size-3.5" />
                                        <span class="sr-only">
                                            {{ __('Copy URL') }}
                                        </span>
                                    </button>
                                </template>

                                {{-- Refresh webhook (Telegram) --}}
                                <template x-if="ch.refresh_webhook_url">
                                    <button
                                        class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-[11px] font-medium text-foreground/50 transition-colors hover:bg-foreground/5 hover:text-foreground"
                                        type="button"
                                        @click="refreshChannelWebhook(ch.refresh_webhook_url)"
                                        title="{{ __('Refresh webhook') }}"
                                    >
                                        <x-tabler-refresh class="size-3.5" />
                                        <span class="sr-only">
                                            {{ __('Refresh webhook') }}
                                        </span>
                                    </button>
                                </template>

                                {{-- Delete --}}
                                <button
                                    class="grid size-7 place-items-center rounded-lg text-foreground/30 transition-colors hover:bg-red-50 hover:text-red-500"
                                    type="button"
                                    @click="deleteChannel(ch.id)"
                                    title="{{ __('Remove channel') }}"
                                >
                                    <x-tabler-trash class="size-3.5" />
                                    <span class="sr-only">
                                        {{ __('Remove channel') }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </x-slot:modal>
</x-modal>
