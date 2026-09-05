<template x-if="!['ai_call','send_message','generate_report','external_chatbot','marketing_bot','social_media_agent','crm_agent','path','gmail','outlook'].includes(selectedStep.type)">
    <div>
        <x-forms.input
            class="font-mono text-xs"
            type="textarea"
            rows="12"
            label="{{ __('Configuration (JSON)') }}"
            ::value="JSON.stringify(selectedStep.config ?? {}, null, 2)"
            @blur="try { selectedStep.config = JSON.parse($event.target.value); } catch (e) { toastr.error('Invalid JSON'); }"
        />
        <p class="mt-1 text-[10px] text-foreground/40">{{ __('This action type has no dedicated UI. Edit config directly.') }}</p>
    </div>
</template>
