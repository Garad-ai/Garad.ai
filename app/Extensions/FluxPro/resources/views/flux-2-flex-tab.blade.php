@if(setting('enabled_flux_2_flex', 1) == '1')
	<x-button
		class="lqd-image-generator-tabs-trigger py-2 text-2xs font-bold text-heading-foreground hover:shadow-none [&.active]:bg-foreground/10"
		data-generator-name="flux-2-flex"
		tag="button"
		type="button"
		variant="ghost"
		x-data="{}"
		::class="{ 'active': activeGenerator === 'flux-2-flex' }"
		x-bind:data-active="activeGenerator === 'flux-2-flex'"
		@click="changeActiveGenerator('flux-2-flex')"
	>
		{{ \App\Domains\Entity\Enums\EntityEnum::FLUX_2_FLEX->label() }}
	</x-button>
@endif

