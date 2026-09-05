<x-card
    class="overflow-hidden !bg-transparent !shadow"
    class:body="max-sm:p-9"
    size="lg"
>
    <div class="flex items-center py-6 max-lg:pb-28">
        <div class="w-full lg:w-5/12">
            <h2 class="mb-4">
                @lang('Add New Account')
            </h2>

            <p class="mb-4 text-xs">
                @lang('You can connect and manage multiple social media accounts from here.')
            </p>


			<x-dropdown.dropdown
			>
				<x-slot:trigger
					variant="outline"
					hover-variant="primary"
					size="lg"
				>
					<x-tabler-plus class="size-4" />
					@lang('Link a social account')
				</x-slot:trigger>

				<x-slot:dropdown
					class="min-w-52 overflow-hidden p-2"
				>
					@foreach ($platforms as $platform)
						@php
							$image = 'vendor/social-media/icons/' . $platform->value . '.svg';
							$image_dark_version = 'vendor/social-media/icons/' . $platform->value . '-light.svg';
							$darkImageExists = file_exists(public_path($image_dark_version));
							$is_connected = $platform->platform()?->isConnected();
						@endphp
						<x-button
							@class([
								'w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline',
								'opacity-50 pointer-events-none saturate-0' => !$is_connected,
							])
							variant="link"
							href="{{ route('social-media.oauth.connect.' . $platform->value) }}?extension=agent"
						>
							<img
								@class([
									'w-6 h-auto',
									'dark:hidden' => $darkImageExists,
								])
								src="{{ asset($image) }}"
								alt="{{ $platform->label() }}"
							/>
							@if ($darkImageExists)
								<img
									class="hidden h-auto w-6 dark:block"
									src="{{ asset($image_dark_version) }}"
									alt="{{ $platform->label() }}"
								/>
							@endif
							{{ $platform->label() }}
						</x-button>
					@endforeach
				</x-slot:dropdown>
			</x-dropdown.dropdown>
        </div>

        <figure class="absolute -end-28 max-lg:-bottom-20 lg:top-1/2 lg:-translate-y-1/2">
            <img
                width="501"
                height="233"
                src="{{ asset('vendor/social-media-agent/images/img-9.png') }}"
                alt="{{ __('Social media logos') }}"
                aria-hidden="true"
            >
        </figure>
    </div>
</x-card>
