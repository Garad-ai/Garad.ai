@php
    $videoPlatforms = ['youtube', 'youtube-shorts'];
@endphp

<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 1"
    x-cloak
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-data="{
        dropdownOpen: false
    }"
    @keydown.enter.prevent
>
    <h2 class="mb-4 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Where would you like to publish this post?')
        </span>
        @lang('Select an account')
    </h2>

    @if ($platforms->isEmpty())
        <div class="min-h-[72px] rounded-[10px] bg-foreground/5 px-6 py-[18px] text-center backdrop-blur-xl transition-all">
            <p class="mb-3 text-pretty text-sm font-medium opacity-50">
                @lang('No connected platforms found. Please connect your social media accounts first.')
            </p>
            <x-button
                class="underline"
                variant="link"
                href="{{ route('dashboard.user.social-media.agent.accounts') }}"
            >
                @lang('Connect Platforms')
            </x-button>
        </div>
    @else
        <div class="mb-8">
            <div
                class="relative select-none"
                @click.outside="dropdownOpen = false"
            >
                <div
                    class="flex min-h-[72px] flex-wrap items-center gap-3 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-[18px] backdrop-blur-xl transition-all"
                    :class="{ 'rounded-b-none border-foreground/5': dropdownOpen && formData.platform_ids.length < platforms.length }"
                    @click.prevent="dropdownOpen = !dropdownOpen"
                >
                    <p
                        class="m-0 flex min-h-9 items-center"
                        x-show="!formData.platform_ids.length"
                    >
                        @lang('Select an platform')
                    </p>
                    <template
                        x-for="platformId in formData.platform_ids"
                        key="platformId"
                    >
                        <div
                            class="flex items-center gap-2 rounded-full bg-background px-2.5 py-[9px] text-2xs font-medium transition"
                            x-transition:enter-start="opacity-0 scale-95 blur-sm"
                            x-transition:enter-end="opacity-100 scale-100 blur-0"
                            x-transition:leave-start="opacity-100 scale-100 blur-0"
                            x-transition:leave-end="opacity-0 scale-95 blur-sm"
                        >
                            <figure class="w-5 transition-all group-hover:scale-125">
                                <img
                                    class="h-auto w-full"
                                    :class="{ 'dark:hidden': getPlatformById(platformId).image_dark_version }"
                                    :src="getPlatformById(platformId).image"
                                    :alt="getPlatformById(platformId).name"
                                />
                                <template x-if="getPlatformById(platformId).image_dark_version">
                                    <img
                                        class="hidden h-auto w-full dark:block"
                                        :src="getPlatformById(platformId).image_dark_version"
                                        :alt="getPlatformById(platformId).name"
                                    />
                                </template>
                            </figure>
                            <span x-text="getPlatformById(platformId).credentials?.username ?? '{{ __('Unknown') }}'"></span>
                            <button
                                class="inline-grid size-[17px] place-items-center rounded-full border p-0 transition hover:scale-110 hover:border-red-500 hover:bg-red-500 hover:text-white"
                                @click.prevent.stop="formData.platform_ids = formData.platform_ids.filter(id => id !== platformId)"
                            >
                                <x-tabler-x class="size-2.5" />
                            </button>
                        </div>
                    </template>
                </div>

                <div
                    class="absolute inset-x-0 top-full z-5 flex origin-top flex-wrap gap-3 rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                    x-show="dropdownOpen && formData.platform_ids.length < {{ $platforms->count() }}"
                    x-transition:enter-start="opacity-0 scale-95 blur-sm"
                    x-transition:enter-end="opacity-100 scale-100 blur-0"
                    x-transition:leave-start="opacity-100 scale-100 blur-0"
                    x-transition:leave-end="opacity-0 scale-95 blur-sm"
                >

                    @foreach ($platforms as $platform)
                        @if (in_array($platform->platform, $videoPlatforms))
                            @continue
                        @endif
                        @php
                            $image = 'vendor/social-media/icons/' . $platform->platform . '.svg';
                            $image_dark_version = 'vendor/social-media/icons/' . $platform->platform . '-light.svg';
                            $darkImageExists = file_exists(public_path($image_dark_version));
                        @endphp

                        <div
                            class="flex cursor-pointer items-center gap-2 rounded-full bg-background px-3.5 py-[9px] text-2xs font-medium transition hover:-translate-y-0.5 hover:scale-105 hover:shadow-lg hover:shadow-black/5"
                            {{-- @click.prevent="if ( !formData.platform_ids.includes({{ $platform->id }}) ) { formData.platform_ids.push({{ $platform->id }}) };" --}}
                            @click.prevent="formData.platform_ids = [{{ $platform->id }}]; dropdownOpen = false;"
                            x-show="!formData.platform_ids.includes({{ $platform->id }})"
                            x-transition:enter-start="opacity-0 scale-95 blur-sm"
                            x-transition:enter-end="opacity-100 scale-100 blur-0"
                            x-transition:leave-start="opacity-100 scale-100 blur-0"
                            x-transition:leave-end="opacity-0 scale-95 blur-sm"
                        >
                            <figure class="w-5 transition-all group-hover:scale-125">
                                <img
                                    @class(['w-full h-auto', 'dark:hidden' => $darkImageExists])
                                    src="{{ asset($image) }}"
                                    alt="{{ $platform->name }}"
                                />
                                @if ($darkImageExists)
                                    <img
                                        class="hidden h-auto w-full dark:block"
                                        src="{{ asset($image_dark_version) }}"
                                        alt="{{ $platform->name }}"
                                    />
                                @endif
                            </figure>
                            {{ data_get($platform->credentials, 'username', 'Unknown') }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-2">
                @include('social-media-agent::create.step-error', ['step' => 1])
            </div>
        </div>

        <x-button
            class="mb-3 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground"
            @click.prevent="nextStep()"
        >
            @lang('Continue')
            <x-tabler-arrow-right class="size-4" />
        </x-button>

        <x-button
            class="mb-3 w-full py-[18px] text-xs font-medium leading-none"
            href="{{ route('dashboard.user.social-media.agent.accounts') }}"
            variant="outline"
        >
            <x-tabler-plus class="size-4" />
            @lang('Link a social account')
        </x-button>
    @endif
</div>
