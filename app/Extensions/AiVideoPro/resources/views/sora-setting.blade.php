<div class="col-md-12">
	<div class="mb-3">
		<x-card
			class="w-full"
			size="sm"
		>
			<x-forms.input
				id="sora_active"
				type="checkbox"
				switcher
				type="checkbox"
				:checked="setting('sora_active', 1) == 1"
				label="{{ __('Enable Sora in AI Video Pro') }}"
			>
				<x-badge
					class="ms-2 text-2xs"
					variant="secondary"
				>
					@lang('New')
				</x-badge>
			</x-forms.input>
		</x-card>
	</div>
	<div class="mb-3">
		<x-card
			class="w-full"
			size="sm"
		>
			<x-forms.input
				id="gemini_omni_flash_active"
				type="checkbox"
				switcher
				:checked="setting('gemini_omni_flash_active', 1) == 1"
				label="{{ __('Enable Gemini Omni Flash in AI Video Pro') }}"
			>
				<x-badge
					class="ms-2 text-2xs"
					variant="secondary"
				>
					@lang('New')
				</x-badge>
			</x-forms.input>
		</x-card>
	</div>
</div>
