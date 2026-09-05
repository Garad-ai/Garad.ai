<div class="col-md-12 ">
	<div class="mb-3">
		<x-card
			class="w-full"
			size="sm"
		>
			<x-forms.input
				id="enabled_flux_2_flex"
				type="checkbox"
				switcher
				type="checkbox"
				:checked="setting('enabled_flux_2_flex', '1') == 1"
				label="{{ __('Enabled Flux 3 Flex model') }}"
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
