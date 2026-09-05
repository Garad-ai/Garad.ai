<div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
	{{-- Average Engagement --}}
	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pb-0 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Average Engagement')
			</h4>
		</x-slot:head>

		<p class="flex items-center text-[24px] font-medium text-heading-foreground">
			<x-number-counter
				id="average-engagement-counter"
				value="{{ $app_is_demo ? rand(15, 85) : $stats['average_engagement'] }}%"
			/>
		</p>
	</x-card>

	{{-- Total Followers --}}
	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pb-0 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Total Followers')
			</h4>
		</x-slot:head>

		<p class="flex items-center text-[24px] font-medium text-heading-foreground">
			<x-number-counter
				id="total-followers-counter"
					value="{{ number_format($app_is_demo ? rand(10000, 999999) : ($stats['total_followers'] ?? 0)) }}"
				:options="['delay' => 100]"
			/>
		</p>
	</x-card>

	{{-- Total Posts --}}
	<x-card class:body="px-6">
		<x-slot:head
			class="flex items-center justify-between border-0 px-6 pb-0 pt-6"
		>
			<h4 class="m-0 text-sm font-medium">
				@lang('Total Posts')
			</h4>
		</x-slot:head>

		<p class="flex items-center text-[24px] font-medium text-heading-foreground">
			<x-number-counter
				id="total-followers-counter"
				value="{{ number_format($app_is_demo ? rand(50, 500) : ($stats['total_posts'] ?? 0)) }}"
				:options="['delay' => 200]"
			/>
		</p>
	</x-card>
</div>
