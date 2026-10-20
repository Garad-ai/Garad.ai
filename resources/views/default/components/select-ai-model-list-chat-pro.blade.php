<div
    class="garad-claude-model-picker"
    x-data="{
        openPopover: null,
        initPicker() {
            const input = document.getElementById('garad_reasoning_level');
            if (input && !input.value) {
                input.value = localStorage.getItem('garad_reasoning_level') || 'medium';
            }
        },
        isModelOpen() { return this.openPopover === 'model'; },
        toggleModel() { this.openPopover = this.isModelOpen() ? null : 'model'; }
    }"
    x-init="initPicker()"
    @click.outside="openPopover = null"
    @keydown.escape.window="openPopover = null"
>
    <div x-data="modelList" class="garad-claude-model-inner">
        <button
            class="garad-claude-model-trigger"
            type="button"
            @click.prevent.stop="toggleModel()"
            :aria-expanded="isModelOpen()"
            title="{{ __('Choose AI model') }}"
        >
            <x-tabler-sparkles class="size-4" />
            <span class="garad-claude-model-trigger-copy">
                <span class="garad-claude-model-trigger-label" x-text="selectedModelLabel || '{{ __('Select model') }}'">
                    {{ $selectedModel->model()?->selected_title ?? $selectedModel->enum()?->value ?? __('Select model') }}
                </span>
            </span>
            <x-tabler-chevron-down class="size-3.5 garad-model-chevron" />
        </button>

        <div class="garad-claude-model-menu" :style="isModelOpen() ? 'display:block;' : 'display:none;'" x-cloak>
            <div class="garad-claude-model-menu-title">{{ __('Models') }}</div>
            <div class="garad-claude-model-menu-subtitle">{{ __('Choose from your real available models. Your selection will be used for the next response.') }}</div>
            <div class="garad-claude-model-list">
                @foreach ($fullModels as $engine)
                    @php
                        $garadModelValue = (string) (is_object($engine) ? ($engine->value ?? '') : $engine);
                        $garadModelLabel = $garadModelValue;
                        $garadModelInactive = false;
                        $garadProvider = __('AI Model');
                        $garadMetaLine = __('Available');
                        $garadCapabilityText = __('General purpose');

                        try {
                            $garadModelEnum = \App\Domains\Entity\Enums\EntityEnum::fromSlug($garadModelValue);
                            $garadModelDriver = \App\Domains\Entity\Facades\Entity::driver($garadModelEnum);
                            $garadModelLabel = $garadModelDriver->model()?->selected_title ?? $garadModelEnum?->value ?? $garadModelValue;
                            $garadModelInactive = ! $garadModelDriver->isUnlimitedCredit() && $garadModelDriver->creditBalance() <= 0;
                            $garadProvider = $garadModelDriver->model()?->engine?->label() ?? __('AI Model');
                        } catch (\Throwable) {
                            try {
                                $garadModelLabel = method_exists($engine, 'label') ? (string) $engine->label() : $garadModelValue;
                            } catch (\Throwable) {
                                $garadModelLabel = $garadModelValue;
                            }
                        }

                        $garadLabelLower = \Illuminate\Support\Str::lower($garadModelLabel . ' ' . $garadModelValue);
                        if (\Illuminate\Support\Str::contains($garadLabelLower, ['claude', 'gpt-5', 'gpt 5', 'reason', 'thinking', 'sonnet'])) {
                            $garadCapabilityText = __('Strong reasoning');
                        } elseif (\Illuminate\Support\Str::contains($garadLabelLower, ['vision', 'omni', 'multimodal', 'gemini'])) {
                            $garadCapabilityText = __('Vision and multimodal');
                        } elseif (\Illuminate\Support\Str::contains($garadLabelLower, ['mini', 'flash', 'haiku', 'fast'])) {
                            $garadCapabilityText = __('Fast responses');
                        } elseif (\Illuminate\Support\Str::contains($garadLabelLower, ['code', 'coder', 'dev'])) {
                            $garadCapabilityText = __('Coding and technical work');
                        }

                        $garadMetaLine = $garadModelInactive
                            ? __('No credits available')
                            : $garadProvider . ' · ' . $garadCapabilityText;
                    @endphp
                    @if ($garadModelValue !== '')
                        <button
                            type="button"
                            @class(['garad-claude-model-option', 'is-disabled' => $garadModelInactive])
                            @disabled($garadModelInactive)
                            @click.prevent.stop="
                                updateSelectedModels({ value: {{ json_encode($garadModelValue) }}, label: {{ json_encode($garadModelLabel) }} });
                                $nextTick(() => {
                                    const select = document.getElementById('chatbot_front_model');
                                    if (select) {
                                        Array.from(select.options).forEach(option => {
                                            option.selected = selectedModels.some(model => model?.value === option.value);
                                        });
                                        select.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                });
                                if (!canMultiModelSelect && !isCouncilEnabled()) openPopover = null;
                            "
                        >
                            <span class="garad-model-option-copy">
                                <strong>{{ $garadModelLabel }}</strong>
                                <small>{{ $garadMetaLine }}</small>
                            </span>
                            <x-tabler-check
                                class="size-4 garad-model-option-check"
                                x-show="selectedModels.some(model => model?.value === {{ json_encode($garadModelValue) }})"
                                x-cloak
                            />
                        </button>
                    @endif
                @endforeach
            </div>

            @if ($isMultiModelExtensionEnabled || $isCouncilExtensionEnabled)
                <div class="garad-model-footer-note">
                    @if ($isCouncilExtensionEnabled)
                        <span>{{ __('Advanced multi-model and council features remain available from Garad tools when enabled for your plan.') }}</span>
                    @else
                        <span>{{ __('Your available plan models are shown above.') }}</span>
                    @endif
                </div>
            @endif
        </div>

        <select
            id="chatbot_front_model"
            class="hidden"
            name="chatbot_front_model"
            @if ($supportsMultiSelection) multiple @endif
            aria-hidden="true"
        >
            @foreach ($fullModels as $model)
                @php
                    $value = (string) (is_object($model) ? ($model->value ?? '') : $model);
                    $label = $value;
                    try {
                        $enum = \App\Domains\Entity\Enums\EntityEnum::fromSlug($value);
                        $driver = \App\Domains\Entity\Facades\Entity::driver($enum);
                        $label = $driver->model()?->selected_title ?? $enum?->value ?? $value;
                    } catch (\Throwable) {
                        try {
                            $label = method_exists($model, 'label') ? (string) $model->label() : $value;
                        } catch (\Throwable) {
                        }
                    }
                @endphp
                @if ($value !== '')
                    <option
                        value="{{ $value }}"
                        data-label="{{ $label }}"
                        :selected="selectedModels.some(model => model?.value === {{ json_encode($value) }})"
                    >{{ $label }}</option>
                @endif
            @endforeach
        </select>
        <input id="chatbot_council_mode" name="chatbot_council_mode" type="hidden" :value="isCouncilEnabled() ? '1' : '0'" />
    </div>
</div>
