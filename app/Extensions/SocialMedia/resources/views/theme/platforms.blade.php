@php
	use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
@endphp
@foreach (PlatformEnum::all() as $platform)
	<x-card
		class:body="p-8 static flex flex-col"
		class="relative flex hover:-translate-y-1"
	>
		<div class="absolute end-0 top-0 flex size-[70px] justify-end">
			<x-shape-cutout-2
				style="--border-radius: 12px;"
				position="te"
			/>

			<span
				class="relative z-2 inline-grid size-[55px] place-items-center rounded-lg outline outline-[1px] outline-border transition-all group-hover/card:bg-primary group-hover/card:text-primary-foreground group-hover/card:outline-primary"
				title="{{ __('Add') }} {{ $platform->label() }} {{ __('Post') }}"
			>
                                <x-tabler-plus
									class="size-6"
									stroke-width="1.5"
								/>
                            </span>
		</div>

		<figure
			class="relative mb-[70px] inline-grid size-10 place-items-center rounded-full border shadow-[0_1px_0_hsl(var(--background)),0_2px_0_hsl(var(--border))] transition-all group-hover/card:scale-110 group-hover/card:shadow-[0_1px_0_hsl(var(--border)),0_2px_0_hsl(var(--border))]"
		>
			{!! getSocialMediaIcon($platform->value) !!}
		</figure>

		<h5 class="mt-auto">
			{{ $platform->label() }}
			{{ __('Post') }}
		</h5>

		<a
			class="absolute inset-0 z-2"
			href="{{ route('dashboard.user.social-media.post.create', ['platform' => $platform->value]) }}"
		></a>
	</x-card>
@endforeach
