@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
    use Illuminate\Support\Str;

    $theme = setting('dash_theme', 'default');

    $compose_titles = collect($platforms)
        ->mapWithKeys(function ($platform) {
            $platformId = data_get($platform, 'id');

            if ($platformId === null) {
                return [];
            }

            $platformSlug = (string) data_get($platform, 'platform', '');
            $platformEnum = PlatformEnum::tryFrom($platformSlug);
            $platformName = $platformEnum?->label() ?? ($platformSlug !== '' ? Str::headline(str_replace('-', ' ', $platformSlug)) : __('Platform'));
            $firstCharacter = Str::lower(Str::substr($platformName, 0, 1));
            $article = in_array($firstCharacter, ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';

            return [
                $platformId => __('Compose :article :platform post', [
                    'article' => $article,
                    'platform' => $platformName,
                ]),
            ];
        })
        ->toArray();

    $agent_options = collect($agents ?? [])
        ->map(function ($agent) {
            $platformIds = array_map('intval', (array) ($agent->platform_ids ?? []));

            return [
                'id' => (int) $agent->id,
                'name' => $agent->name,
                'platform_ids' => $platformIds,
                'image' => $agent->image,
            ];
        })
        ->values()
        ->all();

    $default_platform_id = data_get($platforms, '0.id');
    $default_agent_id = data_get($agent_options, '0.id');

    $videoPlatforms = ['youtube', 'youtube-shorts'];
@endphp

<template x-teleport="body">
    <div
        @class([
            'social-media-agent-create-post-modal fixed start-0 top-0 z-10 h-screen w-screen overflow-y-auto overscroll-contain bg-background',
            'z-10' => $theme !== 'social-media-dashboard',
            'z-[999]' => $theme === 'social-media-dashboard',
        ])
        x-cloak
        x-show="createPostModalShow"
        x-transition.opacity
        x-data="socialMediaAgentCreatePostModal"
        @create-post-modal-show.window="createPostModalShow = {show: $event.detail.show, platformId: ($event.detail.platformId ?? platforms.at(0)?.id ?? 1)}"
    >
        <div class="container max-h-[calc(100vh-2rem)] overflow-y-auto">
            <div @class([
                'flex flex-wrap justify-between gap-y-8 py-12',
                'lg:ps-[--navbar-width]' => $theme !== 'social-media-dashboard',
            ])>
                <div class="w-full lg:w-7/12">
                    <form
                        action="#"
                        @submit.prevent="onCreatePostFormSubmit"
                    >
                        <x-button
                            class="mb-6"
                            variant="link"
                            @click.prevent="createPostModalShow = false"
                        >
                            <x-tabler-arrow-left class="size-4" />
                            @lang('Back')
                        </x-button>

                        <h3
                            class="mb-8"
                            x-text="composeTitles[formInputs.platform_id ?? 1]"
                        ></h3>

                        <x-forms.input
                            class:container="mb-7"
                            class:label="flex-row-reverse justify-between text-2xs font-medium text-heading-foreground"
                            type="checkbox"
                            label="{{ __('Personalized Content') }}"
                            switcher
                            switcher-fill
                            size="sm"
                            x-model.boolean="formInputs.personalized_content"
                        />

                        <div
                            class="relative mb-4 select-none"
                            x-data="{ dropdownOpen: false }"
                            @click.outside="dropdownOpen = false"
                        >
                            <div
                                class="flex min-h-[72px] flex-col gap-2.5 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-3 transition-all"
                                :class="{ 'rounded-b-none border-foreground/5': dropdownOpen }"
                                @click.prevent="dropdownOpen = !dropdownOpen"
                            >
                                <p class="m-0 w-full text-2xs font-medium opacity-50">
                                    @lang('Post Type')
                                </p>
                                <p
                                    class="m-0 text-xs font-medium capitalize text-heading-foreground"
                                    x-text="publishingTypeLabels[formInputs.publishing_type]"
                                ></p>
                                <x-tabler-chevron-down class="absolute end-4 top-1/2 size-4 -translate-y-1/2" />
                            </div>
                            <div
                                class="absolute inset-x-0 top-full z-5 flex origin-top flex-wrap gap-0.5 rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                                x-show="dropdownOpen"
                                x-transition:enter-start="opacity-0 scale-95 blur-sm"
                                x-transition:enter-end="opacity-100 scale-100 blur-0"
                                x-transition:leave-start="opacity-100 scale-100 blur-0"
                                x-transition:leave-end="opacity-0 scale-95 blur-sm"
                            >
                                <button
                                    class="w-full rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5"
                                    type="button"
                                    @click.prevent="formInputs.publishing_type = 'post'; dropdownOpen = false;"
                                >
                                    @lang('Post')
                                </button>
                                <button
                                    class="w-full rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5"
                                    type="button"
                                    @click.prevent="formInputs.publishing_type = 'story'; dropdownOpen = false;"
                                >
                                    @lang('Story')
                                </button>
                            </div>
                        </div>

                        <div
                            class="relative mb-4 select-none"
                            x-data="{ dropdownOpen: false }"
                            @click.outside="dropdownOpen = false"
                        >
                            <div
                                class="flex min-h-[72px] flex-col gap-2.5 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-3 transition-all"
                                :class="{
                                    'rounded-b-none border-foreground/5': dropdownOpen && agents.length,
                                    'opacity-50 cursor-not-allowed': !agents.length,
                                }"
                                @click.prevent="if (agents.length) { dropdownOpen = !dropdownOpen }"
                            >
                                <p class="m-0 w-full text-2xs font-medium opacity-50">
                                    @lang('Agent')
                                </p>
                                <p
                                    class="m-0"
                                    x-show="!formInputs.agent_id"
                                >
                                    @lang('Choose an Agent')
                                </p>
                                <template x-if="formInputs.agent_id">
                                    <div class="flex items-center gap-2 self-start rounded-full border bg-background px-2.5 py-[9px] text-2xs font-medium transition">
                                        <figure class="w-5">
                                            <img
                                                class="h-auto w-full rounded-full object-cover"
                                                :src="getAgentById(formInputs.agent_id)?.image"
                                                :alt="getAgentById(formInputs.agent_id)?.name"
                                            />
                                        </figure>
                                        <span x-text="getAgentById(formInputs.agent_id)?.name ?? '{{ __('Unknown Agent') }}'"></span>
                                        <button
                                            class="inline-grid size-[17px] place-items-center rounded-full border p-0 transition hover:scale-110 hover:border-red-500 hover:bg-red-500 hover:text-white"
                                            @click.prevent.stop="formInputs.agent_id = null"
                                        >
                                            <x-tabler-x class="size-2.5" />
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div
                                class="absolute inset-x-0 top-full z-5 flex origin-top flex-wrap gap-0.5 rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                                x-show="dropdownOpen"
                                x-transition:enter-start="opacity-0 scale-95 blur-sm"
                                x-transition:enter-end="opacity-100 scale-100 blur-0"
                                x-transition:leave-start="opacity-100 scale-100 blur-0"
                                x-transition:leave-end="opacity-0 scale-95 blur-sm"
                            >
                                <template x-if="!agents.length">
                                    <p class="w-full text-center text-2xs text-foreground/60">
                                        @lang('No agents available. Create one to get started.')
                                    </p>
                                </template>

                                <template
                                    x-for="agent in agents"
                                    :key="agent.id"
                                >
                                    <button
                                        class="group flex w-full items-center justify-between gap-2 rounded-lg border border-foreground/5 bg-background/90 px-4 py-2 text-start text-heading-foreground backdrop-blur-sm transition hover:z-1 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5 focus-visible:z-1 focus-visible:scale-[1.02] focus-visible:shadow-lg focus-visible:shadow-black/5"
                                        type="button"
                                        @click.prevent="formInputs.agent_id = agent.id; dropdownOpen = false;"
                                        :class="{
                                            'opacity-50 cursor-not-allowed': formInputs.platform_id && !agentSupportsPlatform(agent, formInputs.platform_id),
                                            'border-primary': formInputs.agent_id === agent.id,
                                        }"
                                        :disabled="formInputs.platform_id && !agentSupportsPlatform(agent, formInputs.platform_id)"
                                    >
                                        <div class="flex items-center gap-2">
                                            <figure class="w-5">
                                                <img
                                                    class="h-auto w-full rounded-full object-cover"
                                                    :src="agent.image"
                                                    :alt="agent.name"
                                                />
                                            </figure>
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-2xs font-medium"
                                                    x-text="agent.name"
                                                ></span>
                                                <span
                                                    class="text-3xs text-foreground/60"
                                                    x-show="formInputs.platform_id && !agentSupportsPlatform(agent, formInputs.platform_id)"
                                                >
                                                    @lang('Not connected to this account')
                                                </span>
                                            </div>
                                        </div>
                                        <x-tabler-check
                                            class="size-4 text-primary"
                                            x-show="formInputs.agent_id === agent.id"
                                        />
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div
                            class="relative mb-4 select-none"
                            x-data="{ dropdownOpen: false }"
                            @click.outside="dropdownOpen = false"
                        >
                            <div
                                class="flex min-h-[72px] flex-col gap-2.5 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-3 transition-all"
                                :class="{ 'rounded-b-none border-foreground/5': dropdownOpen }"
                                @click.prevent="dropdownOpen = !dropdownOpen"
                            >
                                <p class="m-0 w-full text-2xs font-medium opacity-50">
                                    @lang('Target Account')
                                </p>
                                <p
                                    class="m-0"
                                    x-show="!formInputs.platform_id"
                                >
                                    @lang('Choose an Account')
                                </p>
                                <template x-if="formInputs.platform_id">
                                    <div class="flex items-center gap-2 self-start rounded-full border bg-background px-2.5 py-[9px] text-2xs font-medium transition">
                                        <figure class="w-5 transition-all group-hover:scale-125">
                                            <img
                                                class="h-auto w-full"
                                                :class="{ 'dark:hidden': getPlatformById(formInputs.platform_id)?.image_dark_version }"
                                                :src="getPlatformById(formInputs.platform_id)?.image"
                                                :alt="getPlatformById(formInputs.platform_id)?.name"
                                            />
                                            <template x-if="getPlatformById(formInputs.platform_id)?.image_dark_version">
                                                <img
                                                    class="hidden h-auto w-full dark:block"
                                                    :src="getPlatformById(formInputs.platform_id)?.image_dark_version"
                                                    :alt="getPlatformById(formInputs.platform_id)?.name"
                                                />
                                            </template>
                                        </figure>
                                        <span x-text="getPlatformById(formInputs.platform_id)?.credentials?.username ?? '{{ __('Unknown') }}'"></span>
                                        <button
                                            class="inline-grid size-[17px] place-items-center rounded-full border p-0 transition hover:scale-110 hover:border-red-500 hover:bg-red-500 hover:text-white"
                                            @click.prevent.stop="formInputs.platform_id = ''; ensureAgentMatchesPlatform(formInputs.platform_id);"
                                        >
                                            <x-tabler-x class="size-2.5" />
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div
                                class="absolute inset-x-0 top-full z-5 flex origin-top flex-wrap gap-3 rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                                x-show="dropdownOpen"
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
                                        $image_dark_version = 'vendor/social-media/icons/' . $platform->platform . '-mono-light.svg';
                                        $darkImageExists = file_exists(public_path($image_dark_version));
                                    @endphp

                                    <div
                                        class="flex cursor-pointer items-center gap-2 rounded-full bg-background px-3.5 py-[9px] text-2xs font-medium transition hover:-translate-y-0.5 hover:scale-105 hover:shadow-lg hover:shadow-black/5"
                                        @click.prevent="formInputs.platform_id = {{ $platform->id }}; dropdownOpen = false; ensureAgentMatchesPlatform(formInputs.platform_id);"
                                        x-show="formInputs.platform_id != {{ $platform->id }}"
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
                        <div class="relative mb-4">
                            <div class="flex min-h-[72px] flex-col gap-2.5 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-3 transition-all">
                                <p class="m-0 flex w-full items-center text-2xs font-medium text-heading-foreground/50">
                                    @lang('Content')

                                    <button
                                        class="relative z-2 ms-auto inline-grid size-7 place-items-center text-heading-foreground transition hover:scale-110"
                                        :class="{ 'pointer-events-none opacity-50': generatingContent }"
                                        @click.prevent="generateAIContent"
                                    >
                                        <template x-if="!generatingContent">
                                            <svg
                                                width="17"
                                                height="16"
                                                viewBox="0 0 17 16"
                                                fill="currentColor"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    d="M3.18359 9.49023C3.29206 9.49023 3.39778 9.52765 3.48145 9.59668C3.56498 9.66563 3.62192 9.76185 3.64258 9.86816L3.71973 10.2471C3.81424 10.7134 4.04352 11.142 4.37988 11.4785C4.71637 11.8152 5.14487 12.045 5.61133 12.1396L5.98926 12.2168C6.09574 12.2374 6.19171 12.2943 6.26074 12.3779C6.32971 12.4615 6.36712 12.5664 6.36719 12.6748C6.36719 12.7833 6.32977 12.889 6.26074 12.9727C6.19173 13.0562 6.09564 13.1132 5.98926 13.1338L5.61133 13.2109C5.14521 13.3055 4.71729 13.5339 4.38086 13.8701C4.04441 14.2064 3.81454 14.6345 3.71973 15.1006L3.64258 15.4785C3.622 15.5849 3.56494 15.681 3.48145 15.75C3.39778 15.819 3.29206 15.8564 3.18359 15.8564C3.07521 15.8564 2.97032 15.819 2.88672 15.75C2.80306 15.681 2.74619 15.585 2.72559 15.4785L2.64844 15.1006C2.55376 14.6344 2.32375 14.2064 1.9873 13.8701C1.65082 13.5338 1.22213 13.3044 0.755859 13.21L0.37793 13.1328C0.27159 13.1122 0.175402 13.0552 0.106445 12.9717C0.0374154 12.888 0 12.7823 0 12.6738C6.31857e-05 12.5655 0.0375807 12.4605 0.106445 12.377C0.175388 12.2934 0.2716 12.2365 0.37793 12.2158L0.755859 12.1387C1.22227 12.0442 1.6508 11.814 1.9873 11.4775C2.32369 11.1411 2.55388 10.7133 2.64844 10.2471L2.72559 9.86816C2.74626 9.76186 2.80318 9.66561 2.88672 9.59668C2.97028 9.52784 3.07532 9.49028 3.18359 9.49023ZM11.1729 0C11.313 0 11.4494 0.0477726 11.5586 0.135742C11.6677 0.223691 11.7436 0.346462 11.7734 0.483398L12.0547 1.87598C12.1908 2.54632 12.5222 3.16183 13.0059 3.64551C13.4896 4.12908 14.1051 4.45969 14.7754 4.5957L16.167 4.87598C16.3057 4.90439 16.4309 4.97917 16.5205 5.08887C16.6101 5.19865 16.6592 5.33682 16.6592 5.47852C16.6591 5.62007 16.61 5.75751 16.5205 5.86719C16.4309 5.97695 16.3058 6.05263 16.167 6.08105L14.7754 6.3623V6.36426C14.1052 6.50026 13.4895 6.83096 13.0059 7.31445C12.5221 7.79819 12.1907 8.41452 12.0547 9.08496L11.7734 10.4766C11.7437 10.6135 11.6677 10.7362 11.5586 10.8242C11.4494 10.9122 11.313 10.96 11.1729 10.96C11.0328 10.9599 10.8971 10.9121 10.7881 10.8242C10.6789 10.7362 10.603 10.6136 10.5732 10.4766L10.291 9.08496C10.1552 8.41441 9.8246 7.79823 9.34082 7.31445C8.8571 6.83083 8.2417 6.50005 7.57129 6.36426L6.17871 6.08301C6.04012 6.05455 5.91567 5.97873 5.82617 5.86914C5.73666 5.75945 5.68758 5.62204 5.6875 5.48047C5.6875 5.33887 5.7367 5.20154 5.82617 5.0918C5.91565 4.98214 6.04009 4.90642 6.17871 4.87793L7.57129 4.5957C8.24169 4.45977 8.85711 4.12918 9.34082 3.64551C9.82449 3.16184 10.155 2.54634 10.291 1.87598L10.5732 0.483398C10.603 0.346497 10.679 0.223672 10.7881 0.135742C10.8971 0.0479587 11.0329 6.45207e-05 11.1729 0Z"
                                                />
                                            </svg>
                                        </template>
                                        <x-tabler-loader-2
                                            class="size-4 animate-spin"
                                            x-show="generatingContent"
                                            x-cloak
                                        />
                                    </button>
                                </p>
                                <textarea
                                    class="m-0 border-none bg-transparent bg-none text-foreground focus-visible:outline-none"
                                    rows="7"
                                    x-model="formInputs.content"
                                ></textarea>
                            </div>
                        </div>

                        <div
                            class="relative mb-4 flex flex-col items-center gap-1 rounded-xl bg-foreground/5 p-8"
                            x-data="{
                                uploading: false,
                                async handleFiles(event) {
                                    const files = event.target.files;

                                    if (!files?.length) return;

                                    const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
                                    if (!imageFiles.length) return;

                                    this.uploading = true;

                                    const formData = new FormData();
                                    imageFiles.forEach(file => formData.append('image[]', file));

                                    try {
                                        const response = await fetch('{{ route('dashboard.user.social-media.agent.api.upload-image') }}', {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                                            },
                                            body: formData
                                        });

                                        const data = await response.json();

                                        if (data.success && data.items?.length) {
                                            formInputs.media_urls = data.items;
                                        } else {
                                            console.error('Upload failed:', data.message);
                                        }
                                    } catch (error) {
                                        console.error('Upload error:', error);
                                    } finally {
                                        this.uploading = false;
                                        if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                                    }
                                }
                            }"
                        >
                            <div
                                class="flex w-full items-center gap-7"
                                :class="{ 'opacity-50': uploadingMedia || generatingImage }"
                            >
                                <x-tabler-loader-2
                                    class="size-6 shrink-0 animate-spin"
                                    x-show="uploadingMedia || generatingImage"
                                    x-cloak
                                />
                                <svg
                                    class="shrink-0"
                                    width="27"
                                    height="22"
                                    viewBox="0 0 27 22"
                                    fill="currentColor"
                                    xmlns="http://www.w3.org/2000/svg"
                                    x-show="!uploadingMedia"
                                >
                                    <path
                                        d="M17.3082 12.422C17.3082 14.7497 15.4147 16.6433 13.087 16.6433C10.7593 16.6433 8.86648 14.7497 8.86648 12.422C8.86648 10.0944 10.7593 8.20081 13.087 8.20081C15.4147 8.20081 17.3082 10.0951 17.3082 12.422ZM26.174 6.42809V18.4175C26.174 20.0158 24.8781 21.3117 23.2798 21.3117H2.89423C1.29589 21.3117 0 20.0158 0 18.4175V6.42809C0 4.82975 1.29589 3.53386 2.89423 3.53386H6.45414V2.53245C6.45414 1.13382 7.58723 0 8.9866 0H17.1874C18.5868 0 19.7199 1.13382 19.7199 2.53245V3.53314H23.2798C24.8781 3.53386 26.174 4.82975 26.174 6.42809ZM19.4789 12.422C19.4789 8.8976 16.6115 6.03014 13.087 6.03014C9.56327 6.03014 6.69581 8.8976 6.69581 12.422C6.69581 15.9465 9.56327 18.814 13.087 18.814C16.6115 18.814 19.4789 15.9465 19.4789 12.422Z"
                                    />
                                </svg>
                                <span
                                    class="text-sm font-medium"
                                    x-text="uploadingMedia ? '{{ __('Uploading...') }}' : '{{ __('Select Image') }}'"
                                ></span>

                                <button
                                    class="relative z-2 ms-auto inline-grid size-7 place-items-center transition hover:scale-110"
                                    :class="{ 'pointer-events-none opacity-50': generatingImage }"
                                    @click.prevent="generateAIImage"
                                >
                                    <template x-if="!generatingImage">
                                        <svg
                                            width="17"
                                            height="16"
                                            viewBox="0 0 17 16"
                                            fill="currentColor"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <path
                                                d="M3.18359 9.49023C3.29206 9.49023 3.39778 9.52765 3.48145 9.59668C3.56498 9.66563 3.62192 9.76185 3.64258 9.86816L3.71973 10.2471C3.81424 10.7134 4.04352 11.142 4.37988 11.4785C4.71637 11.8152 5.14487 12.045 5.61133 12.1396L5.98926 12.2168C6.09574 12.2374 6.19171 12.2943 6.26074 12.3779C6.32971 12.4615 6.36712 12.5664 6.36719 12.6748C6.36719 12.7833 6.32977 12.889 6.26074 12.9727C6.19173 13.0562 6.09564 13.1132 5.98926 13.1338L5.61133 13.2109C5.14521 13.3055 4.71729 13.5339 4.38086 13.8701C4.04441 14.2064 3.81454 14.6345 3.71973 15.1006L3.64258 15.4785C3.622 15.5849 3.56494 15.681 3.48145 15.75C3.39778 15.819 3.29206 15.8564 3.18359 15.8564C3.07521 15.8564 2.97032 15.819 2.88672 15.75C2.80306 15.681 2.74619 15.585 2.72559 15.4785L2.64844 15.1006C2.55376 14.6344 2.32375 14.2064 1.9873 13.8701C1.65082 13.5338 1.22213 13.3044 0.755859 13.21L0.37793 13.1328C0.27159 13.1122 0.175402 13.0552 0.106445 12.9717C0.0374154 12.888 0 12.7823 0 12.6738C6.31857e-05 12.5655 0.0375807 12.4605 0.106445 12.377C0.175388 12.2934 0.2716 12.2365 0.37793 12.2158L0.755859 12.1387C1.22227 12.0442 1.6508 11.814 1.9873 11.4775C2.32369 11.1411 2.55388 10.7133 2.64844 10.2471L2.72559 9.86816C2.74626 9.76186 2.80318 9.66561 2.88672 9.59668C2.97028 9.52784 3.07532 9.49028 3.18359 9.49023ZM11.1729 0C11.313 0 11.4494 0.0477726 11.5586 0.135742C11.6677 0.223691 11.7436 0.346462 11.7734 0.483398L12.0547 1.87598C12.1908 2.54632 12.5222 3.16183 13.0059 3.64551C13.4896 4.12908 14.1051 4.45969 14.7754 4.5957L16.167 4.87598C16.3057 4.90439 16.4309 4.97917 16.5205 5.08887C16.6101 5.19865 16.6592 5.33682 16.6592 5.47852C16.6591 5.62007 16.61 5.75751 16.5205 5.86719C16.4309 5.97695 16.3058 6.05263 16.167 6.08105L14.7754 6.3623V6.36426C14.1052 6.50026 13.4895 6.83096 13.0059 7.31445C12.5221 7.79819 12.1907 8.41452 12.0547 9.08496L11.7734 10.4766C11.7437 10.6135 11.6677 10.7362 11.5586 10.8242C11.4494 10.9122 11.313 10.96 11.1729 10.96C11.0328 10.9599 10.8971 10.9121 10.7881 10.8242C10.6789 10.7362 10.603 10.6136 10.5732 10.4766L10.291 9.08496C10.1552 8.41441 9.8246 7.79823 9.34082 7.31445C8.8571 6.83083 8.2417 6.50005 7.57129 6.36426L6.17871 6.08301C6.04012 6.05455 5.91567 5.97873 5.82617 5.86914C5.73666 5.75945 5.68758 5.62204 5.6875 5.48047C5.6875 5.33887 5.7367 5.20154 5.82617 5.0918C5.91565 4.98214 6.04009 4.90642 6.17871 4.87793L7.57129 4.5957C8.24169 4.45977 8.85711 4.12918 9.34082 3.64551C9.82449 3.16184 10.155 2.54634 10.291 1.87598L10.5732 0.483398C10.603 0.346497 10.679 0.223672 10.7881 0.135742C10.8971 0.0479587 11.0329 6.45207e-05 11.1729 0Z"
                                            />
                                        </svg>
                                    </template>
                                    <x-tabler-loader-2
                                        class="size-4 animate-spin"
                                        x-show="generatingImage"
                                        x-cloak
                                    />
                                </button>
                            </div>
                            <input
                                class="absolute inset-0 z-1 cursor-pointer opacity-0"
                                type="file"
                                multiple
                                accept="image/*"
                                x-ref="fileInput"
                                @change.prevent="handleFiles($event);"
                                :disabled="uploadingMedia"
                            >
                        </div>

                        @if (\App\Helpers\Classes\Helper::appIsDemo())
                            <x-button
                                class="mb-3 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to p-4 text-primary-foreground"
                                size="lg"
                                type="button"
                                onclick="toastr.error('{{ __('This action is disabled in the demo.') }}');"
                            >
                                @lang('Post Now')
                            </x-button>
                        @else
                            <x-button
                                class="mb-3 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to p-4 text-primary-foreground"
                                size="lg"
                                type="submit"
                            >
                                @lang('Post Now')
                            </x-button>
                        @endif

                        <x-modal
                            class:modal-head="hidden"
                            class:modal-body="pt-3"
                            class:modal-container="max-w-[540px] lg:w-[540px]"
                        >
                            <x-slot:trigger
                                class="w-full p-4"
                                variant="outline"
                                size="lg"
                                type="button"
                            >
                                <x-tabler-plus class="size-4" />
                                @lang('Schedule Post')
                            </x-slot:trigger>

                            <x-slot:modal>
                                <div
                                    class="space-y-7 [&_.air-datepicker]:w-full"
                                    x-data="{
                                        datepicker: null,
                                        selectedDate: null,
                                        init() {
                                            this.datepicker = new AirDatepicker('#social-media-schedule-calendar', {
                                                selectedDates: [new Date(this.formInputs.scheduled_at ?? Date.now())],
                                                inline: true,
                                                timepicker: true,
                                                timeFormat: 'HH:mm',
                                                isMobile: window.innerWidth <= 768,
                                                autoClose: window.innerWidth <= 768,
                                                locale: defaultLocale,
                                                onSelect: ({ date }) => {
                                                    this.formInputs.scheduled_at = date;
                                                }
                                            });
                                        },
                                    }"
                                >
                                    <input
                                        class="hidden"
                                        id="social-media-schedule-calendar"
                                        type="text"
                                    >

                                    <p class="mb-0 font-medium text-heading-foreground">
                                        @lang('Selected Date'):
                                        <span
                                            class="opacity-60"
                                            x-text="formInputs.scheduled_at"
                                            x-show="formInputs.scheduled_at"
                                        ></span>
                                        <span
                                            class="opacity-60"
                                            x-show="!formInputs.scheduled_at"
                                        >
                                            @lang('None')
                                        </span>
                                    </p>

                                    <x-button
                                        class="w-full text-2xs font-semibold"
                                        variant="primary"
                                        @click.prevent="modalOpen = false"
                                        type="button"
                                    >
                                        @lang('Done')
                                        <span
                                            class="inline-grid size-7 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                                            aria-hidden="true"
                                        >
                                            <x-tabler-chevron-right class="size-4" />
                                        </span>
                                    </x-button>

                                    <x-button
                                        class="!mt-2 w-full py-2.5 text-2xs font-semibold"
                                        @click.prevent="formInputs.scheduled_at = null"
                                        variant="outline"
                                        type="button"
                                        x-show="formInputs.scheduled_at"
                                    >

                                        <x-tabler-circle-minus class="size-4" />
                                        @lang('Clear Schedule')
                                    </x-button>
                                </div>
                            </x-slot:modal>
                        </x-modal>
                    </form>
                </div>

                <div class="w-full lg:ms-auto lg:w-4/12">
                    <div class="sticky top-8 flex grow flex-col rounded-[10px] border p-3.5 shadow-xl shadow-black/5 transition">
                        <div class="mb-2.5 flex items-center justify-between gap-1">
                            <div class="flex items-center gap-1 text-2xs text-heading-foreground">
                                <img
                                    class="dark:hidden"
                                    width="22"
                                    height="22"
                                    :src="getPlatformById(formInputs.platform_id)?.image"
                                >
                                <img
                                    class="hidden dark:block"
                                    width="22"
                                    height="22"
                                    :src="getPlatformById(formInputs.platform_id)?.image_dark_version"
                                >
                                <span x-text="getPlatformById(formInputs.platform_id)?.credentials?.username"></span>
                            </div>
                            <span class="text-3xs opacity-55">
                                @lang('Draft')
                            </span>
                        </div>

                        <template x-if="formInputs.media_urls.length">
                            <figure
                                class="relative z-1 mb-5 grid aspect-video w-full grid-cols-1 place-items-center overflow-hidden rounded-lg shadow-sm shadow-black/5"
                                x-data="{
                                    totalSlides: formInputs.media_urls?.length ?? 0,
                                    currentIndex: 0,
                                    prev() {
                                        this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
                                    },
                                    next() {
                                        this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
                                    }
                                }"
                            >
                                <template x-for="(media_url, index) in formInputs.media_urls">
                                    <img
                                        class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 size-full object-cover"
                                        x-show="currentIndex == index"
                                        x-cloak
                                        x-transition.opacity
                                        :src="media_url"
                                    >
                                </template>
                                <template x-if="formInputs.media_urls.length >= 2">
                                    <div>
                                        <button
                                            class="absolute start-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                            title="{{ __('Previous Slide') }}"
                                            @click.prevent="prev()"
                                        >
                                            <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                                        </button>
                                        <button
                                            class="absolute end-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                            title="{{ __('Next Slide') }}"
                                            @click.prevent="next()"
                                        >
                                            <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                                        </button>

                                        <div
                                            class="absolute bottom-5 left-1/2 z-2 inline-flex -translate-x-1/2 gap-1.5 rounded-full border border-background/10 bg-background/10 p-1 backdrop-blur">
                                            <template x-for="(media_url, index) in formInputs.media_urls">
                                                <button
                                                    class="relative inline-flex size-2 rounded-full bg-white/50 transition before:absolute before:-inset-x-1 before:-inset-y-1 hover:bg-white/80 active:scale-95 [&.active]:w-4 [&.active]:bg-white"
                                                    @click.prevent="currentIndex = index"
                                                    :class="{ active: currentIndex === index }"
                                                ></button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </figure>
                        </template>

                        <div class="flex items-center justify-between gap-4 text-heading-foreground/75">
                            <div class="flex items-center gap-4">
                                <svg
                                    width="23"
                                    height="20"
                                    viewBox="0 0 23 20"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M11.2375 19.2081C11.2375 19.2081 0.75 13.3351 0.75 6.20353C0.75 4.94281 1.1868 3.72103 1.98608 2.74606C2.78537 1.77109 3.89776 1.10316 5.134 0.855912C6.37024 0.608664 7.65396 0.797371 8.76674 1.38993C9.87953 1.98248 10.7526 2.94228 11.2375 4.10602V4.10602C11.7224 2.94228 12.5956 1.98248 13.7083 1.38993C14.8211 0.797371 16.1048 0.608664 17.3411 0.855912C18.5773 1.10316 19.6897 1.77109 20.489 2.74606C21.2883 3.72103 21.7251 4.94281 21.7251 6.20353C21.7251 13.3351 11.2375 19.2081 11.2375 19.2081Z"
                                    />
                                </svg>
                                <svg
                                    width="22"
                                    height="22"
                                    viewBox="0 0 22 22"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M2.15561 15.9357C0.906429 13.8281 0.469479 11.3371 0.926795 8.93019C1.38411 6.52329 2.70424 4.36609 4.63936 2.86355C6.57447 1.36101 8.99149 0.616466 11.4366 0.769704C13.8818 0.922943 16.187 1.96342 17.9193 3.6958C19.6517 5.42818 20.6922 7.73333 20.8454 10.1785C20.9987 12.6237 20.2541 15.0407 18.7516 16.9758C17.2491 18.9109 15.0918 20.231 12.6849 20.6883C10.2781 21.1457 7.78699 20.7087 5.67943 19.4595V19.4595L2.19756 20.4454C2.05491 20.4871 1.90366 20.4897 1.75966 20.4528C1.61567 20.416 1.48424 20.3411 1.37914 20.236C1.27404 20.1309 1.19915 19.9995 1.16232 19.8555C1.12548 19.7115 1.12806 19.5602 1.16979 19.4176L2.15561 15.9357Z"
                                    />
                                </svg>
                                <svg
                                    width="23"
                                    height="23"
                                    viewBox="0 0 23 23"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M10.4888 12.2356L15.2292 7.49525M20.9165 0.783314L1.36774 6.28927C1.20133 6.33477 1.05299 6.43046 0.942928 6.56331C0.832865 6.69615 0.766431 6.8597 0.752673 7.03167C0.738916 7.20363 0.778504 7.37566 0.866047 7.52432C0.953589 7.67297 1.08483 7.79103 1.24189 7.8624L10.2192 12.1099C10.3951 12.1913 10.5363 12.3325 10.6177 12.5084L14.8652 21.4857C14.9366 21.6428 15.0546 21.774 15.2033 21.8616C15.3519 21.9491 15.524 21.9887 15.6959 21.9749C15.8679 21.9612 16.0315 21.8947 16.1643 21.7847C16.2971 21.6746 16.3928 21.5263 16.4383 21.3599L21.9443 1.81109C21.986 1.66844 21.9886 1.51719 21.9518 1.37319C21.9149 1.2292 21.84 1.09776 21.7349 0.992667C21.6298 0.887569 21.4984 0.812678 21.3544 0.775845C21.2104 0.739011 21.0592 0.741591 20.9165 0.783314Z"
                                    />
                                </svg>
                            </div>

                            <svg
                                width="16"
                                height="23"
                                viewBox="0 0 16 23"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M15.1741 21.4846L7.96203 16.9771L0.75 21.4846V1.6515C0.75 1.41241 0.84498 1.18311 1.01404 1.01404C1.18311 0.84498 1.41241 0.75 1.6515 0.75H14.2725C14.5116 0.75 14.7409 0.84498 14.91 1.01404C15.0791 1.18311 15.1741 1.41241 15.1741 1.6515V21.4846Z"
                                />
                            </svg>
                        </div>

                        <p
                            class="mb-0 mt-5 text-2xs/[1.4em] opacity-65"
                            x-text="formInputs.content"
                            x-show="formInputs.content.trim()"
                        ></p>

                        <p
                            class="mb-0 mt-3 text-3xs opacity-55"
                            x-show="formInputs.scheduled_at"
                        >
                            <span
                                x-text="new Date(formInputs.scheduled_at ?? Date.now()).toLocaleString(navigator.languages.length ? navigator.languages[0] : 'en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).replace(',', '')"
                            ></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('socialMediaAgentCreatePostModal', () => ({
                _showModal: false,
                composeTitles: @json($compose_titles),
                platforms: @json($platforms),
                agents: @json($agent_options),
                uploadingMedia: false,
                creatingPost: false,
                generatingContent: false,
                generatingImage: false,
                publishingTypeLabels: {
                    'post': '{{ __('Post') }}',
                    'story': '{{ __('Story') }}',
                },
                formInputs: {
                    personalized_content: false,
                    platform_id: @json($default_platform_id),
                    agent_id: @json($default_agent_id),
                    publishing_type: 'post',
                    content: '',
                    media_urls: [],
                    scheduled_at: null,
                },

                get createPostModalShow() {
                    return this._showModal;
                },

                set createPostModalShow({
                    show,
                    platformId,
                    agentId,
                }) {
                    this._showModal = !!show;

                    document.body.style.overflow = this._showModal ? 'hidden' : '';

                    if (platformId != null) {
                        this.formInputs.platform_id = platformId;
                    }

                    if (agentId != null) {
                        this.formInputs.agent_id = agentId;
                    }

                    this.ensureAgentMatchesPlatform(this.formInputs.platform_id);
                },

                init() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const createPostFor = urlParams.get('create_post_for');

                    if (createPostFor) {
                        this.createPostModalShow = {
                            show: true,
                            platformId: parseInt(createPostFor, 10)
                        };
                    }

                    this.ensureAgentMatchesPlatform(this.formInputs.platform_id);
                },

                getPlatformById(platformId) {
                    return this.platforms.find(platform => platform.id === platformId);
                },

                getAgentById(agentId) {
                    if (agentId == null) {
                        return undefined;
                    }

                    const targetId = Number(agentId);
                    return this.agents.find(agent => Number(agent.id) === targetId);
                },

                agentSupportsPlatform(agent, platformId) {
                    if (!platformId) {
                        return true;
                    }

                    if (!agent || !Array.isArray(agent.platform_ids)) {
                        return false;
                    }

                    const targetPlatform = Number(platformId);
                    return agent.platform_ids.includes(targetPlatform);
                },

                findAgentIdForPlatform(platformId) {
                    if (!this.agents.length) {
                        return null;
                    }

                    if (!platformId) {
                        return this.agents[0]?.id ?? null;
                    }

                    const match = this.agents.find(agent => this.agentSupportsPlatform(agent, platformId));

                    return match ? match.id : null;
                },

                ensureAgentMatchesPlatform(platformId) {
                    if (!this.agents.length) {
                        this.formInputs.agent_id = null;
                        return;
                    }

                    if (!this.formInputs.agent_id) {
                        this.formInputs.agent_id = this.findAgentIdForPlatform(platformId);
                        return;
                    }

                    const selectedAgent = this.getAgentById(this.formInputs.agent_id);

                    if (!selectedAgent || !this.agentSupportsPlatform(selectedAgent, platformId)) {
                        this.formInputs.agent_id = this.findAgentIdForPlatform(platformId);
                    }
                },

                createPayload() {
                    const {
                        personalized_content,
                        platform_id,
                        publishing_type,
                        content,
                        media_urls,
                        scheduled_at,
                    } = this.formInputs;

                    let platformId = null;
                    let agentId = null;
                    let scheduledAt = null;

                    if (scheduled_at) {
                        const date = scheduled_at instanceof Date ? scheduled_at : new Date(scheduled_at);
                        if (!Number.isNaN(date.getTime())) {
                            scheduledAt = date.toISOString();
                        }
                    }

                    if (platform_id != null) {
                        platformId = Number(platform_id);
                    }

                    if (this.formInputs.agent_id != null) {
                        agentId = Number(this.formInputs.agent_id);
                    }

                    const postType = 'post';

                    return {
                        personalized_content,
                        platform_id: platformId,
                        agent_id: agentId,
                        post_type: postType,
                        publishing_type: publishing_type || 'post',
                        content,
                        media_urls: Array.isArray(media_urls) ? media_urls : [],
                        scheduled_at: scheduledAt,
                    };
                },

                async onCreatePostFormSubmit() {
                    if (this.creatingPost) {
                        return;
                    }

                    if (!this.formInputs.platform_id) {
                        return toastr.error('{{ __('Please select an account.') }}');
                    }

                    if (!this.formInputs.agent_id) {
                        return toastr.error('{{ __('Please select an agent.') }}');
                    }

                    if (!this.formInputs.content.trim()) {
                        return toastr.error('{{ __('Please fill in the post content.') }}');
                    }

                    this.creatingPost = true;

                    try {
                        const response = await fetch('{{ route('dashboard.user.social-media.agent.api.posts.store') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(this.createPayload()),
                        });

                        const data = await response.json();

                        if (!response.ok || data.success === false) {
                            throw new Error(data.message || '{{ __('Post could not be created.') }}');
                        }

                        toastr.success(data.message || '{{ __('Post created.') }}');
                        this.resetForm();
                        this.createPostModalShow = false;
                        window.dispatchEvent(new CustomEvent('social-media-agent:post-created'));
                    } catch (error) {
                        toastr.error(error.message || '{{ __('An unknown error occurred.') }}');
                    } finally {
                        this.creatingPost = false;
                    }
                },

                async generateAIImage() {
                    if (this.generatingImage) {
                        return;
                    }

                    if (!this.formInputs.content.trim()) {
                        return toastr.error('{{ __('Enter the content before generating an image.') }}');
                    }

                    if (!this.formInputs.agent_id) {
                        return toastr.error('{{ __('Select an agent first.') }}');
                    }

                    this.generatingImage = true;

                    try {
                        const response = await fetch('{{ route('dashboard.user.social-media.agent.api.posts.generate-image') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                content: this.formInputs.content,
                                platform_id: this.formInputs.platform_id,
                                agent_id: this.formInputs.agent_id,
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok || data.success === false) {
                            throw new Error(data.message || '{{ __('Image generation failed.') }}');
                        }

                        if (data.image_url) {
                            this.formInputs.media_urls = [data.image_url];
                            toastr.success('{{ __('Image ready!') }}');
                        } else {
                            toastr.info('{{ __('Image request queued. Check the Updates section shortly.') }}');
                        }
                    } catch (error) {
                        toastr.error(error.message || '{{ __('Image generation failed.') }}');
                    } finally {
                        this.generatingImage = false;
                    }
                },

                async generateAIContent() {
                    if (this.generatingContent) {
                        return;
                    }

                    if (!this.formInputs.platform_id) {
                        return toastr.error('{{ __('Select a target account first.') }}');
                    }

                    if (!this.formInputs.agent_id) {
                        return toastr.error('{{ __('Select an agent first.') }}');
                    }

                    this.generatingContent = true;

                    try {
                        const response = await fetch('{{ route('dashboard.user.social-media.agent.api.posts.generate-content') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                platform_id: this.formInputs.platform_id,
                                agent_id: this.formInputs.agent_id,
                                post_type: 'post',
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok || data.success === false) {
                            throw new Error(data.message || '{{ __('Content generation failed.') }}');
                        }

                        const text = data.full_text || data.content;
                        if (text) {
                            this.formInputs.content = text;
                            toastr.success('{{ __('Content ready!') }}');
                        }
                    } catch (error) {
                        toastr.error(error.message || '{{ __('Content generation failed.') }}');
                    } finally {
                        this.generatingContent = false;
                    }
                },

                resetForm() {
                    this.formInputs.content = '';
                    this.formInputs.media_urls = [];
                    this.formInputs.scheduled_at = null;
                    this.formInputs.personalized_content = false;
                    this.formInputs.publishing_type = 'post';
                },
            }))
        })
    </script>
@endPushOnce
