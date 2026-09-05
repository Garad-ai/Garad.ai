@php
    use App\Domains\Engine\Enums\EngineEnum;
    use App\Domains\Entity\Enums\EntityEnum;
    use App\Enums\AITokenType;
    use App\Models\Setting;
    use App\Models\SettingTwo;

    $setting = $setting ?? Setting::getCache();
    $settings_two = $settings_two ?? SettingTwo::getCache();

    $supportedCouncilEngines = collect([
        EngineEnum::OPEN_AI,
        EngineEnum::ANTHROPIC,
        EngineEnum::GEMINI,
        // EngineEnum::DEEP_SEEK,
        // EngineEnum::X_AI,
        // EngineEnum::OPEN_ROUTER,
    ]);

    $availableEngines = $supportedCouncilEngines
        ->filter(fn (EngineEnum $engine) => $engine->getEnabledModels()->isNotEmpty())
        ->values();

    $defaultAiEngine = setting('default_ai_engine', EngineEnum::OPEN_AI->value);
    $selectedEngine = setting('ai_chat_pro_council_synthesis_engine', $defaultAiEngine);

    if (! $availableEngines->first(fn (EngineEnum $engine) => $engine->value === $selectedEngine)) {
        $selectedEngine = $availableEngines->first()?->value ?? EngineEnum::OPEN_AI->value;
    }

    $modelsByEngine = $availableEngines->mapWithKeys(function (EngineEnum $engine) use ($setting, $settings_two) {
        $defaultModels = collect($engine->getDefaultModels($setting, $settings_two))
            ->filter(fn ($model) => $model instanceof EntityEnum)
            ->values();

        $extraModels = $engine->getListableActiveModels($setting, $settings_two)
            ->pluck('key')
            ->filter(fn ($model) => $model instanceof EntityEnum)
            ->values();

        $models = $defaultModels
            ->merge($extraModels)
            ->filter(fn (EntityEnum $model) => $model->engine() === $engine && $model->tokenType() === AITokenType::WORD && ! $model->isEmbedding() && ! $model->isDeprecated())
            ->unique(fn (EntityEnum $model) => $model->slug())
            ->values()
            ->map(fn (EntityEnum $model) => [
                'value' => $model->slug(),
                'label' => $model->label(),
            ]);

        return [$engine->value => $models->values()->all()];
    });

    $savedModel = setting('ai_chat_pro_council_synthesis_model', '');
    $selectedModel = $savedModel;
    $enginesOptions = $availableEngines
        ->map(fn (EngineEnum $engine) => [
            'value' => $engine->value,
            'label' => $engine->label(),
        ])
        ->values()
        ->all();

    $selectedEngineModels = collect($modelsByEngine[$selectedEngine] ?? []);
    if (! $selectedEngineModels->firstWhere('value', $selectedModel)) {
        $selectedModel = match ($selectedEngine) {
            EngineEnum::OPEN_ROUTER->value => setting('default_open_router_model', EntityEnum::PERPLEXITY_LLAMA_31_SONAR_8B->slug()),
            default => EngineEnum::fromSlug($selectedEngine)->getDefaultWordModel($setting)->slug(),
        };

        if (! $selectedEngineModels->firstWhere('value', $selectedModel)) {
            $selectedModel = (string) ($selectedEngineModels->first()['value'] ?? '');
        }
    }

    $alpineInitPayload = [
        'selectedEngine' => $selectedEngine,
        'selectedModel' => $selectedModel,
        'modelsByEngine' => $modelsByEngine,
        'engines' => $enginesOptions,
    ];
@endphp

<div>
	<x-form-step
		auto-increment
		label="{{ __('Council Settings') }}"
	/>

	<x-card class="mb-2 max-md:text-center">
		<x-form.group class="grid grid-cols-1 gap-4">
			<x-form.checkbox
				class="border-input rounded-input border !px-2.5 !py-3"
				name="ai_chat_pro_model_council_feature"
				label="{{ __('Enable Model Council') }}"
				checked="{{ (bool) setting('ai_chat_pro_model_council_feature', '1') }}"
				tooltip="{{ __('Run one prompt across multiple models and show a synthesized consensus.') }}"
			/>

			<x-forms.input
				type="number"
				min="2"
				max="5"
				name="ai_chat_pro_council_max_models"
				value="{{ (int) setting('ai_chat_pro_council_max_models', 5) }}"
				label="{{ __('Maximum Council Models') }}"
				tooltip="{{ __('Default is 5. Users must select between 2 and this limit when Council mode is enabled.') }}"
			/>
		</x-form.group>

		<div
			class="mt-5"
			x-data="modelCouncilSettings"
			x-init="initFromJsonScript('model-council-settings-init')"
		>
			<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
				<div>
					<label class="form-label">
						{{ __('Synthesis Engine') }}
						<x-info-tooltip text="{{ __('Defaults to your Default AI Engine setting.') }}" />
					</label>
					<select
						class="form-select"
						name="ai_chat_pro_council_synthesis_engine"
						x-model="selectedEngine"
						@change="syncModels"
					>
						<template
							x-for="engine in engines"
							:key="engine.value"
						>
							<option
								:value="engine.value"
								x-text="engine.label"
							></option>
						</template>
					</select>
				</div>

				<div>
					<label class="form-label">
						{{ __('Synthesis Model') }}
						<x-info-tooltip text="{{ __('Defaults to the selected engine\'s default word model.') }}" />
					</label>
					<select
						class="form-select"
						name="ai_chat_pro_council_synthesis_model"
						x-model="selectedModel"
					>
						<template
							x-for="model in models"
							:key="model.value"
						>
							<option
								:value="model.value"
								x-text="model.label"
							></option>
						</template>
					</select>
				</div>
			</div>
		</div>

		<script
			id="model-council-settings-init"
			type="application/json"
		>{!! json_encode($alpineInitPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) !!}</script>
	</x-card>
</div>

@pushOnce('script')
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('modelCouncilSettings', () => ({
                    selectedEngine: '',
                    selectedModel: '',
                    modelsByEngine: {},
                    engines: [],
                    models: [],
                    initFromJsonScript(scriptId) {
                        let payload = {};

                        try {
                            payload = JSON.parse(document.getElementById(scriptId)?.textContent || '{}');
                        } catch (e) {
                            payload = {};
                        }

                        this.init(
                            payload.selectedEngine || '',
                            payload.selectedModel || '',
                            payload.modelsByEngine || {},
                            payload.engines || []
                        );
                    },
                    init(initialEngine, initialModel, modelsByEngine, engines) {
                        this.modelsByEngine = modelsByEngine || {};
                        this.engines = engines || [];
                        this.models = this.modelsByEngine?.[initialEngine] || [];

                        this.$nextTick(() => {
                            this.selectedEngine = initialEngine;
                            this.$nextTick(() => {
                                const hasModel = this.models.find(model => model.value === initialModel);
                                this.selectedModel = hasModel ? initialModel : (this.models[0]?.value || '');
                            });
                        });
                    },
                    syncModels() {
                        this.models = this.modelsByEngine?.[this.selectedEngine] || [];

                        this.$nextTick(() => {
                            if (!this.models.find(model => model.value === this.selectedModel)) {
                                this.selectedModel = this.models[0]?.value || '';
                            }
                        });
                    },
                }));
            });
        })();
    </script>
@endPushOnce
