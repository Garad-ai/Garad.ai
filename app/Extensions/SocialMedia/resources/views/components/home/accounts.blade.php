@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
@endphp

<div class="lqd-social-media-accounts-grid-wrap">
    <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <h3 class="m-0">
            @lang('Accounts')
        </h3>
    </div>

    <div class="lqd-social-media-accounts-grid grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
        @foreach ($platforms as $platform)
            @php
                $image = 'vendor/social-media/icons/' . $platform->value . '.svg';
                $image_dark_version = 'vendor/social-media/icons/' . $platform->value . '-light.svg';
                $darkImageExists = file_exists(public_path($image_dark_version));
                $is_connected = $platform?->platform()?->isConnected();
            @endphp
            <x-card
                class="lqd-social-media-account-card relative flex flex-col hover:scale-105 hover:shadow-lg hover:shadow-black/5"
                class:body="pt-9 px-5 flex flex-col items-start grow static"
            >
                <x-slot:head
                    class="flex items-center justify-between gap-3 border-none px-5 pb-0 pt-4"
                >
                    <figure class="w-9">
                        <img
                            @class(['w-full max-h-9 h-auto', 'dark:hidden' => $darkImageExists])
                            src="{{ asset($image) }}"
                            alt="{{ $platform->label() }}"
                        />
                        @if ($darkImageExists)
                            <img
                                class="hidden h-auto w-full dark:block"
                                src="{{ asset($image_dark_version) }}"
                                alt="{{ $platform->label() }}"
                            />
                        @endif
                    </figure>

                    {{-- <x-dropdown.dropdown
						anchor="end"
						offsetY="15px"
					>
						<x-slot:trigger>
							<span class="sr-only">
								@lang('Actions')
							</span>
							<x-tabler-dots-vertical class="size-4" />
						</x-slot:trigger>

						<x-slot:dropdown
							class="p-2"
						>
							<x-button
								class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline"
								variant="link"
							>
								<x-tabler-pencil class="size-4" />
								@lang('Edit')
							</x-button>
							<x-button
								class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline"
								variant="link"
							>
								<x-tabler-trash class="size-4" />
								@lang('Delete')
							</x-button>
						</x-slot:dropdown>
					</x-dropdown.dropdown> --}}
                </x-slot:head>

                <h4 class="mb-2.5 text-lg">
                    {{ $platform->label() }}
                </h4>

                <p class="mb-2.5 text-sm font-medium opacity-80">
                    @if ($is_connected)
                        @lang('Account'): {{ $platform->platform()->username() }}
                    @else
                        ---
                    @endif
                </p>

                <p @class([
                    'mb-0 mt-auto inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[12px] font-medium leading-none',
                    'text-green-500' => $is_connected,
                    'text-heading-foreground bg-heading-foreground/10' => !$is_connected,
                ])>
                    @if ($is_connected)
                        <x-tabler-check class="size-4" />
                        @lang('Active')
                    @else
                        @lang('Passive')
                    @endif
                </p>

                <a
                    class="absolute inset-0 z-10"
                    href="{{ route('dashboard.user.social-media.platforms') }}"
                ></a>
            </x-card>
        @endforeach
    </div>
</div>
