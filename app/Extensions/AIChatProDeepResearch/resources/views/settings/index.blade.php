<div x-data="{ deepResearchEngine: '{{ setting('deep_research_default_engine', 'openai') }}' }">
    <x-form-step
        auto-increment
        label="{{ __('Deep Research Settings') }}"
    />

    <x-card class="mb-2 max-md:text-center">
        <x-form.group class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-form.checkbox
                class="border-input col-span-2 w-full rounded-input border !px-2.5 !py-3"
                name="deep_research_auto_canvas"
                label="{{ __('Auto-open in Canvas') }}"
                checked="{{ (bool) setting('deep_research_auto_canvas', '1') }}"
                tooltip="{{ __('Automatically open research results in Canvas when completed.') }}"
            />

            <div class="max-md:col-span-2">
                <label class="form-label">{{ __('Default AI Engine') }}</label>
                <select
                    class="form-select"
                    name="deep_research_default_engine"
                    x-model="deepResearchEngine"
                >
                    <option value="openai">{{ __('OpenAI') }}</option>
                    <option value="gemini">{{ __('Gemini') }}</option>
                </select>
            </div>

            <div
                class="max-md:col-span-2"
                x-show="deepResearchEngine === 'openai'"
            >
                <label class="form-label">{{ __('Default OpenAI Research Model') }}</label>
                <select
                    class="form-select"
                    name="deep_research_openai_model"
                >
                    <option
                        value="o3-deep-research"
                        {{ setting('deep_research_openai_model', 'o3-deep-research') === 'o3-deep-research' ? 'selected' : '' }}
                    >
                        {{ __('o3 Deep Research') }}
                    </option>
                    <option
                        value="o4-mini-deep-research"
                        {{ setting('deep_research_openai_model', 'o3-deep-research') === 'o4-mini-deep-research' ? 'selected' : '' }}
                    >
                        {{ __('o4-mini Deep Research') }}
                    </option>
                </select>
            </div>
        </x-form.group>
    </x-card>
</div>
