<div class="mt-10 rounded-2xl border border-border bg-card p-6">
    <div class="mb-6 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                {{ __('Marketing Bot Limits') }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ __('Define contact storage, monthly message quota, and channel access for this plan. Set -1 for unlimited, 0 to disable.') }}
            </p>
        </div>
    </div>

    <div class="row gap-y-5">
        <div class="col-12 col-md-6">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Max Contacts') }}"
                    tooltip="{{ __('Maximum number of contacts a user can store. Use -1 for unlimited.') }}"
                    error="plan.marketing_bot_limits.max_contacts"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.marketing_bot_limits.max_contacts"
                        placeholder="{{ __('e.g. 500 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <x-form.group
                    label="{{ __('Monthly Messages') }}"
                    tooltip="{{ __('Maximum number of outbound campaign messages per month. Use -1 for unlimited.') }}"
                    error="plan.marketing_bot_limits.monthly_messages"
                >
                    <x-form.text
                        type="number"
                        min="-1"
                        step="1"
                        size="lg"
                        wire:model.number="plan.marketing_bot_limits.monthly_messages"
                        placeholder="{{ __('e.g. 1000 (use -1 for unlimited, 0 to disable)') }}"
                    />
                </x-form.group>
            </div>
        </div>

        <div class="col-12">
            <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                <p class="mb-1 text-sm font-medium">{{ __('Enabled Channels') }}</p>
                <p class="mb-4 text-xs text-muted-foreground">{{ __('Select which messaging channels are available on this plan.') }}</p>
                <div class="flex flex-wrap gap-5">
                    <x-forms.input
                        type="checkbox"
                        switcher
                        value="whatsapp"
                        wire:model="plan.marketing_bot_limits.channels"
                        label="{{ __('WhatsApp') }}"
                        size="md"
                    />
                    <x-forms.input
                        type="checkbox"
                        switcher
                        value="telegram"
                        wire:model="plan.marketing_bot_limits.channels"
                        label="{{ __('Telegram') }}"
                        size="md"
                    />
                </div>
                @error('plan.marketing_bot_limits.channels')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</div>
