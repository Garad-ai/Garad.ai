@php
	$url_show_query = request()->query('show');
@endphp

<div
	class="lqd-posts-item relative grid w-full items-center gap-4 border-b p-3 text-2xs font-medium transition-all last:border-b-0 hover:bg-foreground/5 group-[&[data-view-mode=grid]]:min-h-48 group-[&[data-view-mode=grid]]:gap-0 group-[&[data-view-mode=grid]]:bg-card-background group-[&[data-view-mode=grid]]:pb-1">
	<div
		class="lqd-posts-item-content sort-name grid grid-flow-col-dense items-center justify-start gap-3 text-sm transition-border group-[&[data-view-mode=grid]]:mb-1 group-[&[data-view-mode=grid]]:block group-[&[data-view-mode=grid]]:h-28 group-[&[data-view-mode=grid]]:items-start group-[&[data-view-mode=grid]]:overflow-hidden group-[&[data-view-mode=grid]]:border-b group-[&[data-view-mode=grid]]:pb-3 group-[&[data-view-mode=grid]]:pt-3 group-[&[data-view-mode=grid]]:text-2xs">
		{{ data_get($item['credentials'], 'name') }}
	</div>

	<p @class([
        'lqd-posts-item-type sort-file inline-flex w-auto m-0 items-center gap-1.5 justify-self-start whitespace-nowrap rounded-full border px-2 py-1 text-[12px] font-medium leading-none',
        'text-green-500' => $item->isConnected(),
        'text-yellow-700' =>! $item->isConnected(),
    ])>
		@lang($item->isConnected() ? trans('Active') : 'Inactive')
	</p>

	<p class="lqd-posts-item-date sort-date m-0 group-[&[data-view-mode=list]]:font-normal">
		{{ date('M j Y', strtotime($item->connected_at)) }}
		<span class="opacity-50 group-[&[data-view-mode=grid]]:hidden">
            , {{ date('H:i', strtotime($item->connected_at)) }}
        </span>
	</p>

	@php
		$image = 'vendor/social-media/icons/' . $item->platform . '.svg';
		$image_dark_version = 'vendor/social-media/icons/' . $item->platform . '-light.svg';
		$darkImageExists = file_exists(public_path($image_dark_version));
	@endphp
	<figure class="lqd-posts-item-cost sort-cost">
		<img
			@class([
				'w-8 h-auto',
				'dark:hidden' => $darkImageExists,
			])
			src="{{ asset($image) }}"
			alt="{{ $item->platform }}"
		/>
		@if ($darkImageExists)
			<img
				class="hidden h-auto w-8 dark:block"
				src="{{ asset($image_dark_version) }}"
				alt="{{ $item->platform }}"
			/>
		@endif
	</figure>

	<div class="lqd-posts-item-actions flex items-center justify-end gap-2 font-normal">
		<x-button
			class="z-10 size-9 group-[&[data-view-mode=grid]]:hidden"
			size="none"
			variant="ghost-shadow"
			title="{{ __('View') }}"
			href="{{ route('social-media.oauth.connect.'.$item->platform) .'?platform_id='.$item->getKey() }}"
			onclick="return confirm('{{ __('Are you sure? This is permanent..') }}')"
		>

			@if($item->expires_at < now()->subMinutes(10))
				<x-tabler-reload class="size-5 text-red-500" />
			@else
				<x-tabler-reload class="size-5 text-green-500" />
			@endif
		</x-button>

		<x-button
			class="z-10 size-9 group-[&[data-view-mode=grid]]:hidden"
			size="none"
			variant="ghost-shadow"
			title="{{ __('View') }}"
			href="{{ route('dashboard.user.social-media.platforms.disconnect', $item->getKey()) }}"
			onclick="return confirm('{{ __('Are you sure? This is permanent..') }}')"
		>
			<x-tabler-x class="size-5" />
		</x-button>

	</div>
</div>
