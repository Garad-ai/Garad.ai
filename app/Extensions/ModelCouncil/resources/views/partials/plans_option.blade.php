<x-form.group
    class="col-span-2 sm:col-span-1"
    no-group-label
    error="plan.model_council_support"
>
    <x-form.checkbox
        class="border-input rounded-input border !px-2.5 !py-3"
        wire:model="plan.model_council_support"
        label="{{ __('Model Council Support') }}"
        tooltip="{{ __('Enable or disable Model Council support for this plan.') }}"
    />
</x-form.group>
