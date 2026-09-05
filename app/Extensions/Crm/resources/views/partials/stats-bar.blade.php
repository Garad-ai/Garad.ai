{{--
    4-column KPI stats bar for CRM index pages.
    Matches the CRM Dashboard KPI bar design pattern.

    Usage:
    @include('crm::partials.stats-bar', ['stats' => [
        ['label' => 'Total', 'value' => 42, 'color' => 'primary'],
        ['label' => 'Revenue', 'value' => '1,200', 'prefix' => '$', 'color' => 'emerald-500'],
        ...
    ]])
--}}
@php
	// Map color keys to full Tailwind classes so the compiler can detect them.
	$colorMap = [
		'primary'    => 'bg-primary',
		'secondary'  => 'bg-secondary',
		'emerald-500'=> 'bg-emerald-500',
		'amber-500'  => 'bg-amber-500',
		'red-500'    => 'bg-red-500',
		'[#3C82F6]'  => 'bg-[#3C82F6]',
		'[#20C69F]'  => 'bg-[#20C69F]',
	];
@endphp
<x-card class:body="flex flex-col gap-5 py-6 px-10 sm:flex-row sm:justify-between sm:gap-0">
	@foreach ($stats as $i => $stat)
		@if ($i > 0)
			<span class="h-px w-full bg-border sm:h-auto sm:w-px"></span>
		@endif
		<div class="flex gap-4">
			<div class="flex grow flex-col gap-1">
				<div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
					<span class="size-2.5 shrink-0 rounded-sm {{ $colorMap[$stat['color']] ?? 'bg-primary' }}"></span>
					{{ $stat['label'] }}
				</div>
				<h3 class="mb-0 flex items-center gap-2 text-2xl sm:text-[30px]">
					@if (isset($stat['prefix']))
						<span class="text-xl">{{ $stat['prefix'] }}</span>
					@endif
					{{ $stat['value'] }}
				</h3>
			</div>
		</div>
	@endforeach
</x-card>
