<div class="row gap-y-7">
	<div class="col-12">
		<x-form-step
			class="mb-0"
			step="3"
			label="{{ __('Multi Model Options') }}"
		/>
	</div>
	<div
		class="col-12 col-sm-12 space-y-5"
	>
		<x-form.group
			no-group-label
			error="plan.hidden"
		>
			<x-form.checkbox
				class:container="mb-4"
				wire:model="plan.multi_model_support"
				label="{{ __('Multi Model Support') }}"
				tooltip="{{ __('Enable or disable multi model support for this plan.') }}"
				size="lg"
				checked="{{ $plan?->multi_model_support == 1 }}"
				switcher
			/>
		</x-form.group>
	</div>
</div>
