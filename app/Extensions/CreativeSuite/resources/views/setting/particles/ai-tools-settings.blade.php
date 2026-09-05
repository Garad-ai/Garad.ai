@php
    $csImageModels = [
        'flux-pro/kontext' => 'Flux Pro Kontext Max',
        'flux-pro/kontext/max/multi' => 'Flux Pro Kontext Max Multi',
        'flux-pro/kontext/text-to-image' => 'Flux Pro Kontext Text to Image',
    ];
@endphp

<div class="space-y-5">
    <x-forms.input
        id="creative_suite_ai_model"
        type="select"
        size="lg"
        label="{{ __('Creative Suite Image Model') }}"
        tooltip="{{ __('Default image model for Creative Suite editing tools (Edit with AI, Reimagine, Remove Background). Uses FalAI.') }}"
        name="creative_suite_ai_model"
    >
        @foreach ($csImageModels as $key => $label)
            <option
                value="{{ $key }}"
                {{ setting('creative_suite_ai_model', 'flux-pro/kontext') === $key ? 'selected' : null }}
            >
                {{ $label }}
            </option>
        @endforeach
    </x-forms.input>

    @includeIf('creative-suite-ai-template::setting.particles.ai-tools-settings')
</div>
