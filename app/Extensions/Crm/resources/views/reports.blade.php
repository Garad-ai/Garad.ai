@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('CRM Reports'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Analytics and insights for your CRM data.'))
@section('titlebar_actions', '')

@section('content')
	<div class="py-10">

		{{-- ── KPI Stats Bar ─────────────────────────────────────── --}}
		<x-card
			class:body="flex justify-between flex-wrap md:flex-nowrap py-6 px-10 max-sm:gap-8"
		>
			<div class="flex gap-4 max-sm:w-full">
				<div class="flex grow flex-col gap-1">
					<div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
						<span class="size-2.5 rounded-sm bg-primary"></span>
						{{ __('Contacts') }}
					</div>
					<h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
						{{ number_format($totalContacts) }}
					</h3>
					<p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
						@lang('vs Last Week')
						<x-change-indicator value="{{ $contactsChange }}" />
					</p>
				</div>
			</div>

			<span class="h-px w-full bg-border sm:h-auto sm:w-px"></span>

			<div class="flex gap-4 max-sm:w-full">
				<div class="flex grow flex-col gap-1">
					<div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
						<span class="size-2.5 rounded-sm bg-secondary"></span>
						{{ __('Active Deals') }}
					</div>
					<h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
						{{ number_format($totalDeals) }}
					</h3>
					<p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
						@lang('vs Last Week')
						<x-change-indicator value="{{ $dealsChange }}" />
					</p>
				</div>
			</div>

			<span class="h-px w-full bg-border sm:h-auto sm:w-px"></span>

			<div class="flex gap-4 max-sm:w-full">
				<div class="flex grow flex-col gap-1">
					<div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
						<span class="size-2.5 rounded-sm bg-[#20C69F]"></span>
						{{ __('Pipeline Value') }}
					</div>
					<h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
						<span class="text-xl">$</span>{{ number_format($totalDealsValue, 0) }}
					</h3>
					<p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
						@lang('vs Last Week')
						<x-change-indicator value="{{ $valueChange }}" />
					</p>
				</div>
			</div>

			<span class="h-px w-full bg-border sm:h-auto sm:w-px"></span>

			<div class="flex gap-4 max-sm:w-full">
				<div class="flex grow flex-col gap-1">
					<div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
						<span class="size-2.5 rounded-sm bg-[#3C82F6]"></span>
						{{ __('Pending Tasks') }}
					</div>
					<h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
						{{ number_format($pendingTasks) }}
						@if ($overdueTasks > 0)
							<span class="text-sm font-medium text-red-500">{{ $overdueTasks }} {{ __('overdue') }}</span>
						@endif
					</h3>
					<p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
						@lang('Completed This Week')
						<x-change-indicator value="{{ $tasksChange }}" />
					</p>
				</div>
			</div>
		</x-card>

		{{-- ── Row 1: Pipeline + Revenue Forecast ────────────────── --}}
		<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
			{{-- Pipeline Overview --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-trending-up class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Pipeline Overview') }}</h4>
					</div>
					<x-button
						variant="link"
						href="{{ route('dashboard.user.crm.deals.index') }}"
					>
						<span class="text-nowrap font-bold">{{ __('View Deals') }}</span>
						<x-tabler-chevron-right class="size-4 rtl:rotate-180" />
					</x-button>
				</x-slot:head>

				@if ($totalDeals > 0)
					<div class="flex flex-col gap-4">
						<div class="flex w-full rounded-7xl border p-2.5">
							<div class="flex h-3 w-full flex-nowrap gap-0.5 overflow-hidden rounded-7xl">
								@foreach ($stageDistribution as $stage)
									@if ($stage['count'] > 0)
										<span style="width: {{ round(($stage['count'] / $totalDeals) * 100) }}%; background-color: {{ $stage['color'] }};"></span>
									@endif
								@endforeach
							</div>
						</div>

						<ul class="flex flex-col">
							@foreach ($stageDistribution as $stage)
								<li class="flex items-center justify-between border-b border-card-border py-2.5 last:border-b-0">
									<div class="flex items-center gap-2.5">
										<span class="size-2.5 rounded-sm" style="background-color: {{ $stage['color'] }}"></span>
										<p class="mb-0 text-[15px] font-medium text-foreground">{{ $stage['name'] }}</p>
									</div>
									<div class="flex items-center gap-3">
										<span class="text-foreground/50">{{ $stage['count'] }} {{ __('deals') }}</span>
										<span class="text-sm font-semibold text-heading-foreground">{{ $totalDeals > 0 ? round(($stage['count'] / $totalDeals) * 100) : 0 }}%</span>
									</div>
								</li>
							@endforeach
						</ul>
					</div>
				@else
					<div class="flex min-h-[200px] items-center justify-center text-sm text-foreground/50">
						{{ __('No deals yet.') }}
					</div>
				@endif
			</x-card>

			{{-- Revenue Forecast --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-chart-area-line class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Revenue Forecast') }}</h4>
					</div>
				</x-slot:head>

				<div class="flex flex-col gap-4">
					<div class="grid grid-cols-3 gap-3">
						<div class="rounded-xl bg-foreground/[.02] p-3 dark:bg-white/[2%]">
							<p class="mb-1 text-[11px] font-medium uppercase tracking-wider text-foreground/40">{{ __('Win Rate') }}</p>
							<p class="text-xl font-bold text-heading-foreground">{{ $winRate }}%</p>
							<p class="mt-0.5 text-[11px] text-foreground/40">{{ $wonCount }} {{ __('won') }} / {{ $lostCount }} {{ __('lost') }}</p>
						</div>
						<div class="rounded-xl bg-foreground/[.02] p-3 dark:bg-white/[2%]">
							<p class="mb-1 text-[11px] font-medium uppercase tracking-wider text-foreground/40">{{ __('Companies') }}</p>
							<p class="text-xl font-bold text-heading-foreground">{{ number_format($totalCompanies) }}</p>
							<p class="mt-0.5 text-[11px] text-foreground/40">{{ __('Total') }}</p>
						</div>
						<div class="rounded-xl bg-foreground/[.02] p-3 dark:bg-white/[2%]">
							<p class="mb-1 text-[11px] font-medium uppercase tracking-wider text-foreground/40">{{ __('Contacts') }}</p>
							<p class="text-xl font-bold text-heading-foreground">{{ number_format($totalContacts) }}</p>
							<p class="mt-0.5 text-[11px] text-foreground/40">{{ __('Total') }}</p>
						</div>
					</div>
					<div
						class="min-h-[280px] w-full [&_.apexcharts-legend-text]:!m-0 [&_.apexcharts-legend-text]:!pe-2 [&_.apexcharts-legend-text]:ps-2 [&_.apexcharts-legend-text]:!text-foreground"
						id="chart-revenue-forecast"
					></div>
				</div>
			</x-card>
		</div>

		{{-- ── Row 2: Win/Loss + Funnel ──────────────────────────── --}}
		<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
			{{-- Win/Loss Ratio --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-trophy class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Win / Loss Ratio') }}</h4>
					</div>
				</x-slot:head>

				@if ($wonCount + $lostCount > 0)
					<div class="w-full" id="chart-win-loss"></div>
				@else
					<div class="flex min-h-[200px] items-center justify-center text-sm text-foreground/50">
						{{ __('No won or lost deals yet.') }}
					</div>
				@endif
			</x-card>

			{{-- Deal Conversion Funnel --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-filter class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Deal Conversion Funnel') }}</h4>
					</div>
				</x-slot:head>

				@if ($totalDeals > 0)
					<div class="w-full" id="chart-funnel"></div>
				@else
					<div class="flex min-h-[200px] items-center justify-center text-sm text-foreground/50">
						{{ __('No deals yet.') }}
					</div>
				@endif
			</x-card>
		</div>

		{{-- ── Row 3: Velocity + Task Activity ───────────────────── --}}
		<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
			{{-- Pipeline Velocity --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-clock class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Pipeline Velocity') }}</h4>
					</div>
				</x-slot:head>

				@if ($hasStageHistory)
					<div class="w-full" id="chart-velocity"></div>
				@else
					<div class="flex min-h-[200px] flex-col items-center justify-center gap-2 text-center text-sm text-foreground/50">
						<x-tabler-clock-play class="size-8 text-foreground/20" />
						<p>{{ __('Pipeline velocity tracking has started.') }}</p>
						<p class="text-xs">{{ __('Data will appear as deals move between stages.') }}</p>
					</div>
				@endif
			</x-card>

			{{-- Task Activity --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-activity class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Task Activity') }}</h4>
					</div>
				</x-slot:head>

				<div class="w-full" id="chart-task-activity"></div>
			</x-card>
		</div>

		{{-- ── Row 4: Contact Growth + Deal Value by Stage ───────── --}}
		<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
			{{-- Contact Growth --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-users-group class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Contact Growth') }}</h4>
					</div>
				</x-slot:head>

				<div class="w-full" id="chart-contact-growth"></div>
			</x-card>

			{{-- Deal Value by Stage --}}
			<x-card
				class="flex flex-col"
				class:body="flex flex-col justify-center grow"
			>
				<x-slot:head class="flex items-center justify-between px-5 py-3.5">
					<div class="flex items-center gap-4">
						<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
							<x-tabler-coin class="size-6" stroke-width="1.5" />
						</x-lqd-icon>
						<h4 class="m-0 text-base font-medium">{{ __('Deal Value by Stage') }}</h4>
					</div>
				</x-slot:head>

				@if ($totalDeals > 0)
					<div class="w-full" id="chart-deal-value-stage"></div>
				@else
					<div class="flex min-h-[200px] items-center justify-center text-sm text-foreground/50">
						{{ __('No deals yet.') }}
					</div>
				@endif
			</x-card>
		</div>
	</div>
@endsection

@push('script')
	<script src="{{ custom_theme_url('/assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
	<script>
		(async () => {
			"use strict";
			await document.fonts.ready;

			const labelStyle = {
				fontFamily: 'inherit',
				fontSize: '12px',
				colors: 'hsl(var(--foreground) / 50%)',
			};
			const axisOff = { show: false };
			const hoverOff = { hover: { filter: { type: 'none' } } };

			const chartDefaults = {
				chart: {
					toolbar: { show: false },
					zoom: { enabled: false },
					fontFamily: 'inherit',
				},
				grid: { show: false },
				states: hoverOff,
			};

			// ── Revenue Forecast (Area) ──────────────────────────
			const revenueEl = document.querySelector("#chart-revenue-forecast");
			if (revenueEl) {
				new ApexCharts(revenueEl, {
					...chartDefaults,
					series: [
						{ name: '{{ __("Closed Revenue") }}', data: @json($closedRevenue) },
						{ name: '{{ __("Forecast") }}', data: @json($forecastRevenue) },
					],
					chart: {
						...chartDefaults.chart,
						type: 'area',
						height: 280,
					},
					colors: ['hsl(var(--primary))', '#20C69F'],
					dataLabels: { enabled: false },
					stroke: { width: 2, curve: 'smooth', dashArray: [0, 5] },
					xaxis: {
						categories: @json($revenueMonths),
						labels: { style: labelStyle },
						axisBorder: axisOff,
						axisTicks: axisOff,
					},
					yaxis: {
						labels: {
							formatter: (val) => '$' + Number(val).toLocaleString(),
							offsetX: -10,
							style: labelStyle,
						},
						axisBorder: axisOff,
						axisTicks: axisOff,
					},
					fill: {
						type: 'gradient',
						gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 100] },
					},
					legend: { show: true, position: 'top', horizontalAlign: 'right', fontFamily: 'inherit', fontSize: '12px' },
					tooltip: { y: { formatter: (val) => '$' + Number(val).toLocaleString() } },
				}).render();
			}

			// ── Win / Loss Ratio (Donut) ─────────────────────────
			@if ($wonCount + $lostCount > 0)
				const winLossEl = document.querySelector("#chart-win-loss");
				if (winLossEl) {
					new ApexCharts(winLossEl, {
						...chartDefaults,
						series: [{{ $wonCount }}, {{ $lostCount }}],
						labels: [@json(__('Won')), @json(__('Lost'))],
						colors: ['#20C69F', '#DC524C'],
						chart: { ...chartDefaults.chart, type: 'donut', height: 300 },
						plotOptions: {
							pie: {
								donut: {
									size: '85%',
									labels: {
										show: true,
										name: { show: false },
										value: {
											show: true,
											fontSize: '36px',
											fontFamily: 'var(--font-heading, inherit)',
											fontWeight: 700,
											color: 'hsl(var(--heading-foreground) / 70%)',
											formatter: () => '{{ $winRate }}%',
										},
									},
								},
							},
						},
						dataLabels: { enabled: false },
						legend: {
							show: true, position: 'bottom', fontFamily: 'inherit', fontSize: '13px',
							markers: { width: 10, height: 10, radius: 2 },
							formatter: function(seriesName, opts) { return seriesName + ': ' + opts.w.globals.series[opts.seriesIndex]; },
						},
						stroke: { colors: ['hsl(var(--border))'] },
						responsive: [{ breakpoint: 501, options: { chart: { height: 350 }, legend: { position: 'bottom' } } }],
					}).render();
				}
			@endif

			// ── Deal Conversion Funnel (Horizontal Bar) ──────────
			@if ($totalDeals > 0)
				const funnelEl = document.querySelector("#chart-funnel");
				if (funnelEl) {
					const funnelData = @json($funnelData);
					new ApexCharts(funnelEl, {
						...chartDefaults,
						series: [{ name: @json(__('Deals')), data: funnelData.map(d => d.count) }],
						colors: funnelData.map(d => d.color),
						chart: { ...chartDefaults.chart, type: 'bar', height: 300 },
						plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '35px', distributed: true } },
						stroke: { show: false, width: 0 },
						dataLabels: { enabled: true, style: { fontFamily: 'inherit', fontSize: '12px', fontWeight: 600 } },
						xaxis: {
							categories: funnelData.map(d => d.name),
							labels: { style: labelStyle },
							axisBorder: axisOff,
							axisTicks: axisOff,
						},
						yaxis: {
							labels: { style: { fontFamily: 'var(--font-heading, inherit)', fontSize: '13px', fontWeight: 500, colors: ['hsl(var(--foreground) / 50%)'] } },
							axisBorder: axisOff,
							axisTicks: axisOff,
						},
						legend: { show: false },
						tooltip: { y: { formatter: (val) => val + ' {{ __("deals") }}' } },
					}).render();
				}
			@endif

			// ── Pipeline Velocity (Bar) ──────────────────────────
			@if ($hasStageHistory)
				const velocityEl = document.querySelector("#chart-velocity");
				if (velocityEl) {
					const velocityData = @json($velocityData);
					new ApexCharts(velocityEl, {
						...chartDefaults,
						series: [{ name: @json(__('Avg Days')), data: velocityData.map(d => parseFloat(d.avg_days)) }],
						colors: velocityData.map(d => d.stage_color),
						chart: { ...chartDefaults.chart, type: 'bar', height: 300 },
						plotOptions: { bar: { borderRadius: 4, columnWidth: '60%', distributed: true } },
						stroke: { show: false, width: 0 },
						dataLabels: { enabled: true, formatter: (val) => val + 'd', style: { fontFamily: 'inherit', fontSize: '12px', fontWeight: 600 } },
						xaxis: {
							categories: velocityData.map(d => d.stage_name),
							labels: { style: { fontFamily: 'inherit', fontSize: '11px', colors: 'hsl(var(--foreground) / 50%)' } },
							axisBorder: axisOff,
							axisTicks: axisOff,
						},
						yaxis: {
							title: { text: @json(__('Days')), style: { fontFamily: 'inherit', fontSize: '12px' } },
							labels: { offsetX: -10, style: labelStyle },
							axisBorder: axisOff,
							axisTicks: axisOff,
						},
						legend: { show: false },
						tooltip: { y: { formatter: (val) => val + ' {{ __("days") }}' } },
					}).render();
				}
			@endif

			// ── Task Activity (Bar) ──────────────────────────────
			const taskEl = document.querySelector("#chart-task-activity");
			if (taskEl) {
				new ApexCharts(taskEl, {
					...chartDefaults,
					series: [{ name: @json(__('Completed Tasks')), data: @json($taskActivityCounts) }],
					colors: ['hsl(var(--primary))'],
					chart: { ...chartDefaults.chart, type: 'bar', height: 300 },
					plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
					stroke: { show: false, width: 0 },
					dataLabels: { enabled: false },
					xaxis: {
						categories: @json($taskActivityWeeks),
						labels: { style: labelStyle },
						axisBorder: axisOff,
						axisTicks: axisOff,
					},
					yaxis: {
						labels: { formatter: (val) => Math.round(val), offsetX: -10, style: labelStyle },
						axisBorder: axisOff,
						axisTicks: axisOff,
					},
					fill: { opacity: 1 },
					legend: { show: false },
				}).render();
			}

			// ── Contact Growth (Area) ────────────────────────────
			const contactGrowthEl = document.querySelector("#chart-contact-growth");
			if (contactGrowthEl) {
				new ApexCharts(contactGrowthEl, {
					...chartDefaults,
					series: [{ name: @json(__('New Contacts')), data: @json($contactGrowthCounts) }],
					colors: ['#3C82F6'],
					chart: { ...chartDefaults.chart, type: 'area', height: 300 },
					dataLabels: { enabled: false },
					stroke: { width: 2, curve: 'smooth' },
					xaxis: {
						categories: @json($contactGrowthMonths),
						labels: { style: labelStyle },
						axisBorder: axisOff,
						axisTicks: axisOff,
					},
					yaxis: {
						labels: { formatter: (val) => Math.round(val), offsetX: -10, style: labelStyle },
						axisBorder: axisOff,
						axisTicks: axisOff,
					},
					fill: {
						type: 'gradient',
						gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 100] },
					},
					legend: { show: false },
				}).render();
			}

			// ── Deal Value by Stage (Bar) ────────────────────────
			@if ($totalDeals > 0)
				const dealValueEl = document.querySelector("#chart-deal-value-stage");
				if (dealValueEl) {
					const dvData = @json($dealValueByStage);
					new ApexCharts(dealValueEl, {
						...chartDefaults,
						series: [{ name: @json(__('Value')), data: dvData.map(d => d.value) }],
						colors: dvData.map(d => d.color),
						chart: { ...chartDefaults.chart, type: 'bar', height: 300 },
						plotOptions: { bar: { borderRadius: 4, columnWidth: '60%', distributed: true } },
						stroke: { show: false, width: 0 },
						dataLabels: { enabled: false },
						xaxis: {
							categories: dvData.map(d => d.name),
							labels: { style: { fontFamily: 'inherit', fontSize: '11px', colors: 'hsl(var(--foreground) / 50%)' } },
							axisBorder: axisOff,
							axisTicks: axisOff,
						},
						yaxis: {
							labels: {
								formatter: (val) => '$' + Number(val).toLocaleString(),
								offsetX: -10,
								style: labelStyle,
							},
							axisBorder: axisOff,
							axisTicks: axisOff,
						},
						legend: { show: false },
						tooltip: { y: { formatter: (val) => '$' + Number(val).toLocaleString() } },
					}).render();
				}
			@endif
		})();
	</script>
@endpush
