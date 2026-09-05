@php
    $default_platform = $currentPlatform->value;
    $default_platform_id = 0;
    $current_platform = $default_platform;
    $tones = [
        'default' => 'Default',
        'informative' => 'Informative',
        'humorous' => 'Humorous',
        'emphatic' => 'Emphatic',
        'engaging' => 'Engaging',
        'promotional' => 'Promotional',
        'educational' => 'Educational',
        'celebratory' => 'Celebratory',
        'urgent' => 'Urgent/Time-Sensitive',
        'professional' => 'Professional',
        'excited' => 'Excited',
    ];
    $company_id = $editingPost['company_id'] ?? '';
    $product_id = $editingPost['product_id'] ?? '';
    $campaign_id = $editingPost['campaign_id'] ?? '';
    $is_personalized_content = $editingPost['is_personalized_content'] ?: '';
    $tone = $editingPost['tone'] ?? 'default';
    $content = $editingPost['content'] ?? '';
    $link = $editingPost['link'] ?? '';
    $postImage = $editingPost['image'] ?? '';
    $video = $editingPost['video'] ?? '';
    $is_repeated = $editingPost['is_repeated'] ?? false;
    $repeat_period = $editingPost->repeat_period;
    $repeat_start_date = $editingPost->repeat_start_date?->format('d/m/Y') ?? '';
    $repeat_time = $editingPost['repeat_time'] ?? '';
    $scheduled_at = $editingPost['scheduled_at'];
    $social_media_platform_id = $editingPost['social_media_platform_id'];
    $companies_list = $companies->pluck('name', 'id')->toArray();
    $campaigns_list = $campaigns->pluck('name', 'id')->toArray();

    $credentials = $currentPlatform->platform()?->credentials;

    $platformUsername = $credentials['name'] ?? '';
    $platformPicture = $credentials['picture'] ?? '';

    $all_platforms = \App\Extensions\SocialMedia\System\Enums\PlatformEnum::cases();
    $imageLimits = collect(config('social-media'))->mapWithKeys(fn($v, $k) => [$k => data_get($v, 'requirements.images.limit', 1)])->toArray();
@endphp

@extends('panel.layout.app', ['disable_tblr' => true, 'disable_titlebar' => true, 'layout_wide' => true])
@section('title', __('Edit Post'))

@push('css')
    <link
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
        rel="stylesheet"
    />
    <style>
        .lqd-social-media-post-create-datepicker .air-datepicker {
            --adp-background-color: hsl(var(--background));
            --adp-day-name-color: hsl(var(--heading-foreground) / 50%);
            --adp-color-other-month: hsl(var(--heading-foreground) / 50%);
            --adp-color: hsl(var(--heading-foreground));
            --adp-accent-color: hsl(var(--primary));
            --adp-border-color: hsl(var(--border));
            --adp-border-color-inner: hsl(var(--border));
            --adp-cell-background-color-selected-hover: hsl(var(--primary));
            --adp-cell-background-color-selected: hsl(var(--primary));
            --adp-color-current-date: #4eb5e6;
            --adp-cell-background-color-hover: hsl(var(--heading-foreground) / 15%);
            --adp-background-color-hover: hsl(var(--heading-foreground) / 15%);
            width: 100%;
            border-radius: 0.625rem;
            border-color: hsl(var(--input-border));
        }

        @media(min-width: 992px) {
            .lqd-header {
                display: none !important;
            }
        }
    </style>
@endpush

@section('content')
    <div
        class="lqd-social-media-post-create"
        x-data="socialMediaPostCreate"
    >
        <div class="lqd-social-media-post-create-header max-w-[100vw] border-b">
            <div class="container">
                @php
                    $grid_cols = count($all_platforms);
                    $grid_cols_classname = 'grid-cols-' . $grid_cols;
                @endphp

                <div @class([
                    'lqd-social-media-post-platforms no-scrollbar flex w-full gap-2 overflow-x-auto py-5 lg:grid',
                    $grid_cols_classname,
                ])>
                    @foreach ($all_platforms as $platform)
                        @php
                            $is_connected = $platform->platform()?->isConnected() && $currentPlatform === $platform;
                        @endphp

                        <x-button
                            @class([
                                'text-sm font-medium [&.active]:bg-secondary [&.active]:text-secondary-foreground [&.active]:outline-secondary',
                                'active' => $is_connected && $current_platform === $platform->value,
                            ])
                            type="button"
                            variant="outline"
                            ::class="{ active: currentPlatform === '{{ $platform->value }}' && {{ $is_connected ? 1 : 0 }} }"
                            @click.prevent="currentPlatform = '{{ $platform->value }}'; socialMediaPlatformId = '{{ $platform->platform()?->id }}';"
                            :disabled="!$is_connected"
                        >
                            @php
                                $image = 'vendor/social-media/icons/' . $platform->value . '.svg';
                                $image_dark_version = 'vendor/social-media/icons/' . $platform->value . '-mono-light.svg';
                                $darkImageExists = file_exists(public_path($image_dark_version));
                            @endphp
                            <img
                                @class([
                                    'w-7 h-auto max-h-7 shrink-0',
                                    'dark:hidden' => $darkImageExists,
                                ])
                                src="{{ asset($image) }}"
                                alt="{{ $platform->label() }}"
                            />
                            @if ($darkImageExists)
                                <img
                                    class="hidden h-auto max-h-7 w-7 shrink-0 dark:block"
                                    src="{{ asset($image_dark_version) }}"
                                    alt="{{ $platform->label() }}"
                                />
                            @endif
                            <span class="truncate whitespace-nowrap">
                                {{ $platform->label() }}
                            </span>
                            <span
                                @class([
                                    'ms-2 inline-grid size-6 place-items-center rounded-full bg-background text-heading-foreground shadow-xl shrink-0',
                                    'hidden' => $current_platform !== $platform->value,
                                ])
                                :class="{ hidden: currentPlatform !== '{{ $platform->value }}' }"
                            >
                                <x-tabler-check class="size-4" />
                            </span>
                        </x-button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="lqd-social-media-post-create-content-wrap py-8">
            <div class="container">
                <div class="flex flex-wrap justify-between gap-y-5">
                    <div class="w-full lg:w-5/12">
                        <h2 class="mb-3.5">
                            @lang('Edit Post')
                            {{ $editingPost->status === \App\Extensions\SocialMedia\System\Enums\StatusEnum::draft ? '(' . __('Draft') . ')' : '' }}
                        </h2>
                        <p class="mb-8 text-xs/5 font-medium opacity-60 lg:w-2/3">
                            @lang('Instantly edit engaging, tailored posts to captivate your audience and save time.')
                        </p>

                        <form
                            class="space-y-7"
                            method="post"
                            action="{{ route('dashboard.user.social-media.post.store') }}"
                            x-ref="form"
                        >
                            <input
                                name="company_id"
                                type="hidden"
                                x-model="selectedCompany"
                            />
                            <input
                                name="product_id"
                                type="hidden"
                                x-model="selectedProduct"
                            />
                            <input
                                type="hidden"
                                name="campaign_id"
                                x-model="selectedCampaign"
                            >
                            <input
                                type="hidden"
                                name="scheduled_at"
                                x-model="scheduledAt"
                            >
                            <input
                                type="hidden"
                                name="is_repeated"
                                x-model="isRepeated"
                            >
                            <input
                                type="hidden"
                                name="repeat_period"
                                x-model="repeatPeriod"
                            >
                            <input
                                type="hidden"
                                name="repeat_start_date"
                                x-model="repeatStartDate"
                            >
                            <input
                                type="hidden"
                                name="repeat_time"
                                x-model="repeatTime"
                            >
                            <input
                                type="hidden"
                                name="social_media_platform_id"
                                x-model="socialMediaPlatformId"
                            >
                            <input
                                type="hidden"
                                name="image"
                                x-model="image"
                            >
                            <input
                                type="hidden"
                                name="images"
                                :value="JSON.stringify(images)"
                            >

                            <input
                                type="hidden"
                                name="video"
                                x-model="video"
                            >

                            {{-- Personalized content checkbox and modals --}}
                            <div class="space-y-5">
                                <x-forms.input
                                    class:label="text-heading-foreground flex-row-reverse justify-between"
                                    type="checkbox"
                                    name="is_personalized_content"
                                    label="{{ __('Personalized Content') }}"
                                    switcher
                                    ::checked="personalizedContent"
                                    x-model="personalizedContent"
                                    :checked="filled($is_personalized_content)"
                                />

                                <div
                                    class="grid grid-cols-1 gap-5 md:grid-cols-2"
                                    @if (!$is_personalized_content) x-cloak @endif
                                    x-show="personalizedContent"
                                    x-transition
                                >
                                    {{-- Company Modal --}}
                                    <x-modal
                                        class:modal-head="border-b-0"
                                        class:modal-body="pt-3"
                                        class:modal-container="max-w-[600px]"
                                    >
                                        <x-slot:trigger
                                            class="w-full flex-wrap rounded-xl"
                                            ::class="{
                                                'bg-primary text-primary-foreground outline-primary': selectedCompany &&
                                                    selectedProduct
                                            }"
                                            variant="outline"
                                            size="lg"
                                            type="button"
                                        >
                                            @lang('Company')
                                            <span
                                                class="ms-[-0.5ch] opacity-70"
                                                @if (!filled($company_id) || !filled($product_id)) x-cloak @endif
                                                x-show="selectedCompany && selectedProduct"
                                                x-text="': ' + companies[selectedCompany]"
                                            >
                                                @if (isset($companies_list[$company_id]) && filled($company_id) && filled($product_id))
                                                    : {{ $companies_list[$company_id] }}
                                                @endif
                                            </span>
                                            <x-tabler-chevron-right
                                                @class([
                                                    'size-4',
                                                    'hidden' => filled($company_id) && filled($product_id),
                                                ])
                                                ::class="{ hidden: selectedCompany && selectedProduct }"
                                            />
                                            <span
                                                @class([
                                                    'size-5 place-items-center rounded-full bg-background text-heading-foreground shadow-xl shrink-0',
                                                    'hidden' => !filled($company_id) || !filled($product_id),
                                                    'inline-grid' => filled($company_id) && filled($product_id),
                                                ])
                                                :class="{
                                                    hidden: !selectedCompany || !
                                                        selectedProduct,
                                                    'inline-grid': selectedCompany && selectedProduct
                                                }"
                                                aria-hidden="true"
                                            >
                                                <x-tabler-check class="size-4" />
                                            </span>
                                        </x-slot:trigger>

                                        <x-slot:modal>
                                            <h3 class="mb-3.5">
                                                @lang('Company Info')
                                            </h3>
                                            <p class="mb-7 text-heading-foreground/60">
                                                @lang('Start by selecting a company or create a new one at BrandCenter in a few clicks.')
                                            </p>

                                            <div class="flex flex-col gap-y-7">
                                                <x-forms.input
                                                    class:label="text-heading-foreground"
                                                    size="lg"
                                                    type="select"
                                                    label="{{ __('Select a Company') }}"
                                                    x-model="selectedCompany"
                                                    @change="selectedProduct = null"
                                                >
                                                    <option value="">
                                                        {{ __('None') }}
                                                    </option>
                                                    @foreach ($companies as $company)
                                                        <option value="{{ $company['id'] }}">
                                                            {{ $company['name'] }}
                                                        </option>
                                                    @endforeach
                                                </x-forms.input>

                                                <div
                                                    class="grid place-items-center"
                                                    x-show="selectedCompany"
                                                >
                                                    @foreach ($companies as $company)
                                                        <div
                                                            class="col-start-1 col-end-1 row-start-1 row-end-1 w-full"
                                                            x-show="selectedCompany == '{{ $company['id'] }}'"
                                                            x-transition
                                                        >
                                                            <x-forms.input
                                                                class:label="text-heading-foreground"
                                                                size="lg"
                                                                type="select"
                                                                label="{{ __('Select a Product') }}"
                                                                x-model="selectedProduct"
                                                            >
                                                                <option value="">
                                                                    {{ __('None') }}
                                                                </option>
                                                                @foreach ($company->products as $product)
                                                                    <option value="{{ $product['id'] }}">
                                                                        {{ $product['name'] }}
                                                                    </option>
                                                                @endforeach
                                                            </x-forms.input>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <x-button
                                                    class="w-full text-2xs font-semibold"
                                                    variant="secondary"
                                                    type="button"
                                                    @click.prevent="modalOpen = false"
                                                >
                                                    @lang('Next')
                                                    <span
                                                        class="inline-grid size-7 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                                                        aria-hidden="true"
                                                    >
                                                        <x-tabler-chevron-right class="size-4" />
                                                    </span>
                                                </x-button>
                                            </div>
                                        </x-slot:modal>
                                    </x-modal>

                                    {{-- Campaign Modal --}}
                                    <x-modal
                                        class:modal-head="border-b-0"
                                        class:modal-body="pt-3"
                                        class:modal-container="max-w-[600px]"
                                    >
                                        <x-slot:trigger
                                            class="w-full flex-wrap rounded-xl"
                                            ::class="{ 'bg-primary text-primary-foreground outline-primary': selectedCampaign }"
                                            variant="outline"
                                            size="lg"
                                            type="button"
                                        >
                                            @lang('Campaign')
                                            <span
                                                class="ms-[-0.5ch] opacity-70"
                                                @if (!filled($campaign_id)) x-cloak @endif
                                                x-show="selectedCampaign"
                                                x-text="': ' + campaigns[selectedCampaign]"
                                            >
                                                @if (isset($campaigns_list[$company_id]) && filled($campaign_id))
                                                    : {{ $campaigns_list[$campaign_id] }}
                                                @endif
                                            </span>
                                            <x-tabler-chevron-right
                                                class="size-4"
                                                ::class="{ hidden: selectedCampaign }"
                                            />
                                            <span
                                                class="hidden size-5 shrink-0 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                                                :class="{ hidden: !selectedCampaign, 'inline-grid': selectedCampaign }"
                                                aria-hidden="true"
                                            >
                                                <x-tabler-check class="size-4" />
                                            </span>
                                        </x-slot:trigger>

                                        <x-slot:modal>
                                            <h3 class="mb-3.5">
                                                @lang('Company Info')
                                            </h3>
                                            <p class="mb-7 text-heading-foreground/60">
                                                @lang('Start by selecting a company or create a new one at BrandCenter in a few clicks.')
                                            </p>

                                            <div class="flex flex-col gap-y-7">
                                                <x-forms.input
                                                    class:label="text-heading-foreground"
                                                    size="lg"
                                                    type="select"
                                                    label="{{ __('Select a Company') }}"
                                                    x-model="selectedCampaign"
                                                >
                                                    <option value="">
                                                        {{ __('None') }}
                                                    </option>
                                                    @foreach ($campaigns as $campaign)
                                                        <option value="{{ $campaign['id'] }}">
                                                            {{ $campaign['name'] }}
                                                        </option>
                                                    @endforeach
                                                </x-forms.input>

                                                <div
                                                    x-show="selectedCampaign"
                                                    x-transition
                                                >
                                                    <p class="text-2xs font-semibold text-heading-foreground">
                                                        @lang('Target Audience')
                                                    </p>
                                                    @foreach ($campaigns as $campaign)
                                                        <p
                                                            class="m-0 rounded-input border border-input-border p-4"
                                                            x-show="selectedCampaign == '{{ $campaign['id'] }}'"
                                                        >
                                                            {!! $campaign['target_audience'] !!}
                                                        </p>
                                                    @endforeach
                                                </div>

                                                <x-button
                                                    class="w-full text-2xs font-semibold"
                                                    variant="secondary"
                                                    type="button"
                                                    @click.prevent="modalOpen = false"
                                                >
                                                    @lang('Next')
                                                    <span
                                                        class="inline-grid size-7 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                                                        aria-hidden="true"
                                                    >
                                                        <x-tabler-chevron-right class="size-4" />
                                                    </span>
                                                </x-button>
                                            </div>
                                        </x-slot:modal>
                                    </x-modal>
                                </div>
                            </div>

                            <x-forms.input
                                class:label="text-heading-foreground"
                                type="select"
                                size="lg"
                                name="socialMediaPlatformId"
                                label="{{ __('Select Account') }}"
                                x-model="socialMediaPlatformId"
                            >
                                @foreach ($userPlatforms as $userPlatform)
                                    <option
                                        {{ $userPlatform->id == $social_media_platform_id ? 'selected' : '' }}
                                        value="{{ $userPlatform->id }}"
                                    >
                                        {{ data_get($userPlatform, 'credentials.name') }}
                                    </option>
                                @endforeach

                            </x-forms.input>

                            {{-- Tone dropdown --}}
                            <x-forms.input
                                class:label="text-heading-foreground"
                                type="select"
                                size="lg"
                                name="tone"
                                label="{{ __('Tone') }}"
                                x-model="tone"
                            >
                                @foreach ($tones as $value => $label)
                                    <option value="{{ $value }}">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-forms.input>

                            {{-- Content textarea --}}
                            <x-forms.input
                                class:label="text-heading-foreground"
                                type="textarea"
                                name="content"
                                label="{{ __('Post Content') }}"
                                placeholder="{!! __('Now’s the perfect time to grab your favorites! 💥 Buy 2, Get 1 Free! 💥 #futureishere') !!}"
                                rows="5"
                                size="lg"
                                x-model="content"
                            >
                                <x-slot:label-extra>
                                    <x-button
                                        class="text-2xs"
                                        type="button"
                                        variant="link"
                                        @click.prevent="generateContent"
                                    >
                                        <span class="me-1 inline-grid place-items-center">
                                            {{-- blade-formatter-disable --}}
											<svg class="col-start-1 col-end-1 row-start-1 row-end-1" :class="{hidden: generatingContent}" width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z" fill="url(#paint0_linear_8906_3722)"/> <defs> <linearGradient id="paint0_linear_8906_3722" x1="17.0017" y1="8.19589" x2="0.137511" y2="6.25241" gradientUnits="userSpaceOnUse"> <stop stop-color="#8D65E9"/> <stop offset="0.483" stop-color="#5391E4"/> <stop offset="1" stop-color="#6BCD94"/> </linearGradient> </defs> </svg>
											{{-- blade-formatter-enable --}}
                                            <x-tabler-refresh
                                                class="col-start-1 col-end-1 row-start-1 row-end-1 hidden size-4 animate-spin"
                                                x-show="generatingContent"
                                                ::class="{ hidden: !generatingContent }"
                                            />
                                        </span>
                                        @lang('Enhance with AI')
                                    </x-button>
                                </x-slot:label-extra>
                            </x-forms.input>

                            {{-- Image input - Multi-image --}}
                            <div x-show="!['tiktok', 'youtube', 'youtube-shorts'].includes(currentPlatform)">
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="flex items-center gap-1.5 text-sm font-medium text-heading-foreground">
                                        @lang('Select Image(s)')
                                        <x-info-tooltip text="{{ __('Upload or generate images for your post. Supported platforms have different image limits.') }}" />
                                    </label>
                                    <x-button
                                        class="text-2xs"
                                        type="button"
                                        variant="link"
                                        @click.prevent="openGenerateModal"
                                    >
                                        <span class="me-1 inline-grid place-items-center">
                                            {{-- blade-formatter-disable --}}
											<svg class="col-start-1 col-end-1 row-start-1 row-end-1" :class="{hidden: generatingImage}" width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z" fill="url(#paint0_linear_8906_3722)"/> <defs> <linearGradient id="paint0_linear_8906_3722" x1="17.0017" y1="8.19589" x2="0.137511" y2="6.25241" gradientUnits="userSpaceOnUse"> <stop stop-color="#8D65E9"/> <stop offset="0.483" stop-color="#5391E4"/> <stop offset="1" stop-color="#6BCD94"/> </linearGradient> </defs> </svg>
											{{-- blade-formatter-enable --}}
                                            <x-tabler-refresh
                                                class="col-start-1 col-end-1 row-start-1 row-end-1 hidden size-4 animate-spin"
                                                x-show="generatingImage"
                                                ::class="{ hidden: !generatingImage }"
                                            />
                                        </span>
                                        @lang('Generate with AI')
                                    </x-button>
                                </div>

                                <div
                                    class="rounded-[10px] border border-dashed border-foreground/10 transition"
                                    @dragover.prevent="$el.classList.add('border-primary', 'bg-primary/10')"
                                    @dragleave.prevent="$el.classList.remove('border-primary', 'bg-primary/10')"
                                    @drop.prevent="$el.classList.remove('border-primary', 'bg-primary/10'); Object.defineProperty($refs.uploadImage, 'files', { value: $event.dataTransfer.files, writable: true }); uploadImage({ target: $refs.uploadImage })"
                                >
                                    {{-- Empty state: Drag & Drop / File Input Zone --}}
                                    <label
                                        class="block cursor-pointer p-6 text-center transition hover:bg-primary/5 sm:p-8"
                                        x-show="images.length === 0"
                                    >
                                        <div class="mx-auto mb-2.5 flex size-12 items-center justify-center rounded-full border border-foreground/10">
                                            <x-tabler-upload class="size-5 opacity-40" />
                                        </div>
                                        <p class="mb-2 text-sm font-medium">
                                            @lang('Drag and drop or click to browse')
                                        </p>
                                        <p class="m-0 text-4xs font-medium opacity-50">
                                            @lang('Max File Size: 5mb')
                                        </p>
                                        <input
                                            class="hidden"
                                            type="file"
                                            accept="image/*"
                                            multiple
                                            x-ref="uploadImage"
                                            @change="uploadImage"
                                        >
                                    </label>

                                    {{-- Filled state: Thumbnail Grid --}}
                                    <div
                                        class="grid grid-cols-3 gap-3 p-4"
                                        x-ref="imageGrid"
                                        x-show="images.length > 0"
                                    >
                                        <template
                                            x-for="(img, index) in images"
                                            :key="index"
                                        >
                                            <div
                                                class="relative cursor-move overflow-visible rounded-xl border-2 transition-colors"
                                                :class="index === carouselIndex ? 'border-primary' : 'border-transparent'"
                                                @click="carouselIndex = index"
                                            >
                                                <img
                                                    class="aspect-square w-full rounded-lg object-cover"
                                                    :src="img"
                                                    alt=""
                                                >
                                                <button
                                                    class="absolute -end-2 -top-2 z-10 inline-grid size-6 place-items-center rounded-full bg-background text-foreground shadow-lg shadow-black/5 transition hover:bg-red-500 hover:text-white"
                                                    type="button"
                                                    x-show="index === carouselIndex"
                                                    x-transition
                                                    @click.stop="removeImage(index)"
                                                >
                                                    <x-tabler-x class="size-3.5" />
                                                </button>
                                                <div
                                                    class="absolute bottom-1 end-1 z-10 flex size-5 items-center justify-center rounded-full bg-primary text-white"
                                                    x-show="index === carouselIndex && requiresSingleImage()"
                                                    x-transition
                                                >
                                                    <x-tabler-check class="size-3" />
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Add more images button --}}
                                        <label
                                            class="flex aspect-square cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-foreground/10 transition hover:border-primary hover:bg-primary/5"
                                            x-show="images.length < getImageLimit()"
                                        >
                                            <x-tabler-plus class="size-6 opacity-30" />
                                            <input
                                                class="hidden"
                                                type="file"
                                                accept="image/*"
                                                multiple
                                                @change="uploadImage"
                                            >
                                        </label>
                                    </div>

                                    {{-- Single image warning banner --}}
                                    <div
                                        class="flex items-center gap-2 border-t border-foreground/10 px-4 py-2.5 text-xs text-amber-600 dark:text-amber-400"
                                        x-show="requiresSingleImage()"
                                        x-cloak
                                        x-transition
                                    >
                                        <x-tabler-info-circle class="size-4 shrink-0" />
                                        <span>@lang('Only the selected image will be published. Click an image to select it.')</span>
                                    </div>
                                </div>

                                {{-- Generate with AI Modal --}}
                                <div
                                    class="fixed inset-0 z-[99] flex items-center justify-center bg-black/50"
                                    x-show="showAiImageModal"
                                    x-cloak
                                    x-transition.opacity
                                    @click.self="showAiImageModal = false"
                                >
                                    <div
                                        class="w-full max-w-md rounded-2xl bg-background p-6 shadow-2xl"
                                        @click.stop
                                    >
                                        <h3 class="mb-1 text-lg font-semibold">@lang('Generate Images')</h3>
                                        <p class="mb-5 text-sm text-foreground/70">
                                            @lang('Select the number of images you\'d like the AI to generate and provide descriptions for your images if you want to override the default post content.')
                                        </p>

                                        <div class="space-y-4">
                                            <div>
                                                <label class="mb-1 flex items-center gap-1 text-sm font-medium text-heading-foreground">
                                                    @lang('Describe Your Images')
                                                    <x-info-tooltip text="{{ __('Leave empty to use post content as the image prompt.') }}" />
                                                </label>
                                                <textarea
                                                    class="w-full rounded-lg border border-input-border bg-background px-3 py-2 text-sm"
                                                    rows="4"
                                                    x-model="aiImagePrompt"
                                                    placeholder="{{ __('What would you like to create?') }}"
                                                ></textarea>
                                            </div>

                                            <div>
                                                <label class="mb-1 flex items-center gap-1 text-sm font-medium text-heading-foreground">
                                                    @lang('Number of Images')
                                                    <x-info-tooltip text="{{ __('Maximum number depends on the selected platform.') }}" />
                                                </label>
                                                <select
                                                    class="w-full rounded-lg border border-input-border bg-background px-3 py-2 text-sm"
                                                    x-model.number="aiImageCount"
                                                >
                                                    <template
                                                        x-for="n in Math.max(1, getImageLimit() - images.length)"
                                                        :key="n"
                                                    >
                                                        <option
                                                            :value="n"
                                                            x-text="n"
                                                        ></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mt-6">
                                            <x-button
                                                class="w-full"
                                                variant="primary"
                                                type="button"
                                                @click="generateImage"
                                            >
                                                @lang('Generate')
                                                <x-tabler-chevron-right class="size-4" />
                                            </x-button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <x-forms.input
                                class:label="text-heading-foreground"
                                label="{{ __('Select Custom Video') }}"
                                size="lg"
                                name="upload_video"
                                type="file"
                                accept="video/*"
                                x-ref="uploadVideo"
                                @change="uploadVideo"
                                x-show="['tiktok', 'youtube', 'youtube-shorts'].includes(currentPlatform)"
                            >
                                <x-slot:label-extra>
                                    <x-button
                                        class="text-2xs"
                                        type="button"
                                        variant="link"
                                        @click.prevent="generateVideo"
                                    >
                                        <span class="me-1 inline-grid place-items-center">
                                            <svg
                                                class="col-start-1 col-end-1 row-start-1 row-end-1"
                                                :class="{ hidden: generatingVideo }"
                                                width="17"
                                                height="17"
                                                viewBox="0 0 17 17"
                                                fill="none"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    clip-rule="evenodd"
                                                    d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z"
                                                    fill="url(#paint0_linear_8906_3722)"
                                                />
                                                <defs>
                                                    <linearGradient
                                                        id="paint0_linear_8906_3722"
                                                        x1="17.0017"
                                                        y1="8.19589"
                                                        x2="0.137511"
                                                        y2="6.25241"
                                                        gradientUnits="userSpaceOnUse"
                                                    >
                                                        <stop stop-color="#8D65E9" />
                                                        <stop
                                                            offset="0.483"
                                                            stop-color="#5391E4"
                                                        />
                                                        <stop
                                                            offset="1"
                                                            stop-color="#6BCD94"
                                                        />
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                            <x-tabler-refresh
                                                class="col-start-1 col-end-1 row-start-1 row-end-1 hidden size-4 animate-spin"
                                                x-show="generatingVideo"
                                                ::class="{ hidden: !generatingVideo }"
                                            />
                                        </span>
                                        @lang('Generate with AI')
                                    </x-button>
                                </x-slot:label-extra>
                            </x-forms.input>

                            <p
                                class="mt-2 text-2xs font-semibold text-red-500"
                                x-show="videoGenerationError"
                                x-text="videoGenerationError"
                                x-cloak
                            ></p>

                            {{-- Submit & Schedule modal --}}
                            <div class="space-y-4">
                                <x-button
                                    class="w-full text-2xs font-semibold"
                                    @click.prevent="postNow"
                                    variant="secondary"
                                    type="button"
                                    ::disabled="isPosting"
                                >
                                    @lang('Post Now')
                                    <span
                                        class="inline-grid size-7 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                                        aria-hidden="true"
                                    >
                                        <x-tabler-chevron-right class="size-4" />
                                    </span>
                                </x-button>

                                {{-- Schedule Modal --}}
                                <x-modal
                                    class:modal-head="border-b-0"
                                    class:modal-body="pt-3"
                                    class:modal-container="max-w-[540px] lg:w-[540px]"
                                >
                                    <x-slot:trigger
                                        class="w-full text-2xs font-semibold disabled:opacity-50 disabled:pointer-events-none"
                                        variant="outline"
                                        type="button"
                                        size="lg"
                                        ::disabled="isPosting"
                                    >
                                        @lang('Schedule')
                                    </x-slot:trigger>

                                    <x-slot:modal>
                                        <div
                                            class="lqd-social-media-post-create-datepicker space-y-7"
                                            x-data="{
                                                datepicker: null,
                                                selectedDate: null,
                                                selectedTime: null,
                                                init() {
                                                    this.datepicker = new AirDatepicker('#social-media-schedule-calendar', {
                                                        selectedDates: [new Date(scheduledAt)],
                                                        inline: true,
                                                        timepicker: true,
                                                        timeFormat: 'HH:mm',
                                                        isMobile: window.innerWidth <= 768,
                                                        autoClose: window.innerWidth <= 768,
                                                        locale: defaultLocale,
                                                        onSelect: ({ formattedDate }) => {
                                                            const dateTime = formattedDate.split(' ');
                                                            const date = dateTime[0];
                                                            const time = dateTime[1];

                                                            this.selectedDate = date;
                                                            this.selectedTime = time;
                                                            this.scheduledAt = date;
                                                            this.repeatStartDate = date;
                                                            this.repeatTime = time;
                                                        }
                                                    });
                                                },
                                            }"
                                        >
                                            <div class="flex items-center justify-between gap-3">
                                                <x-forms.input
                                                    class:label="text-heading-foreground"
                                                    containerClass="grow"
                                                    type="checkbox"
                                                    size="sm"
                                                    switcher
                                                    label="{{ __('Repeat?') }}"
                                                    x-model="isRepeated"
                                                    ::checked="isRepeated"
                                                    @change="if(!$event.target.checked) repeatPeriod = null"
                                                />

                                                <x-forms.input
                                                    class:label="text-heading-foreground"
                                                    containerClass="grow"
                                                    type="select"
                                                    x-show="isRepeated"
                                                    x-model="repeatPeriod"
                                                >
                                                    <option value="">
                                                        @lang('None')
                                                    </option>
                                                    <option value="day">
                                                        @lang('Every Day')
                                                    </option>
                                                    <option value="week">
                                                        @lang('Every Week')
                                                    </option>
                                                    <option value="month">
                                                        @lang('Every Month')
                                                    </option>
                                                </x-forms.input>
                                            </div>

                                            <input
                                                class="hidden"
                                                id="social-media-schedule-calendar"
                                                type="text"
                                            >

                                            <p class="mb-0 font-medium text-heading-foreground">
                                                @lang('Selected Date'):
                                                <span
                                                    class="opacity-60"
                                                    x-text="selectedDate + ' ' + selectedTime"
                                                    x-show="selectedDate"
                                                ></span>
                                                <span
                                                    class="opacity-60"
                                                    x-show="!selectedDate"
                                                >
                                                    @lang('None')
                                                </span>
                                            </p>

                                            <x-button
                                                class="w-full text-2xs font-semibold"
                                                variant="primary"
                                                @click="schedulePost"
                                                type="button"
                                            >
                                                @lang('Schedule Post')
                                                <span
                                                    class="inline-grid size-7 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                                                    aria-hidden="true"
                                                >
                                                    <x-tabler-chevron-right class="size-4" />
                                                </span>
                                            </x-button>
                                        </div>
                                    </x-slot:modal>
                                </x-modal>
                            </div>
                        </form>
                    </div>

                    <div class="hidden w-full rounded-[20px] bg-heading-foreground/5 py-9 lg:block lg:w-6/12">
                        <div class="sticky top-7 mx-auto w-11/12 2xl:w-4/5">
                            @include('social-media::components.post.social-media-card', [
                                'current_platform' => $current_platform,
                                'image' => $image,
                                'video' => $video,
                                'content' => $content,
                                'link' => $link,
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/js/format-string.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('socialMediaPostCreate', () => ({
                    userPlatforms: @json($userPlatforms),
                    platformUsername: "{{ $platformUsername ?: 'Jhon Doe' }}",
                    platformPicture: "{!! $platformPicture ?: custom_theme_url('/assets/img/avatars/avatar-1.jpg') !!}",
                    currentPlatform: '{{ $current_platform }}',
                    personalizedContent: '{{ $is_personalized_content }}',
                    selectedCompany: '{{ $company_id }}',
                    selectedProduct: '{{ $product_id }}',
                    selectedCampaign: '{{ $campaign_id }}',
                    scheduledAt: '{{ $scheduled_at }}',
                    isRepeated: '{{ $is_repeated }}',
                    repeatPeriod: '{{ $repeat_period }}',
                    repeatStartDate: '{{ $repeat_start_date }}',
                    repeatTime: null,
                    content: `{!! $content !!}`,
                    image: '{{ $postImage }}',
                    images: @json($editingPost->images ?? ($postImage ? [$postImage] : [])),
                    video: '{{ $video }}',
                    link: '{{ $link }}',
                    companies: @json($companies_list),
                    campaigns: @json($campaigns_list),
                    tone: '{{ $tone }}',
                    socialMediaPlatformId: '{{ $social_media_platform_id }}',
                    isStory: false,
                    generatingImage: false,
                    generatingVideo: false,
                    generatingContent: false,
                    videoGenerationError: '',
                    previewVideoPaused: true,
                    carouselIndex: 0,
                    aiImageCount: 1,
                    aiImagePrompt: '',
                    showAiImageModal: false,
                    imageLimits: @json($imageLimits),
                    pendingImagePolls: 0,
                    loadingImageRequests: new Map(), // Track loading placeholders by requestId
                    isPosting: false,
                    isDemo: {{ $app_is_demo ? 1 : 0 }},
                    demoImageGenerated: false,
                    init() {
                        this.onPlatformChange = this.onPlatformChange.bind(this);
                        this.onImageChange = this.onImageChange.bind(this);
                        this.onVideoChange = this.onVideoChange.bind(this);

                        const pageContentWrap = document.querySelector('.lqd-page-content-wrap');

                        if (pageContentWrap) {
                            pageContentWrap.style.overflow = 'visible';
                        }

                        this.$watch('currentPlatform', (value) => {
                            this.onPlatformChange(value);
                            const limit = this.getImageLimit();
                            if (this.images.length > limit) {
                                toastr.warning(
                                    `{{ __('This platform supports a maximum of') }} ${limit} {{ __('images. Extra images have been removed.') }}`
                                );
                                this.images = this.images.slice(0, limit);
                                this.syncImageFromImages();
                            }
                        });
                        this.$watch('isStory', (val) => {
                            if (val && this.images.length > 1) {
                                toastr.info('{{ __('Story mode supports only one image. Click an image to select which one to publish.') }}');
                            }
                        });

                        this.$nextTick(() => {
                            const container = this.$refs.imageGrid;
                            if (container && window.Sortable) {
                                Sortable.create(container, {
                                    animation: 150,
                                    ghostClass: 'opacity-40',
                                    onEnd: (evt) => {
                                        const moved = this.images.splice(evt.oldIndex, 1)[0];
                                        this.images.splice(evt.newIndex, 0, moved);
                                        this.syncImageFromImages();
                                    }
                                });
                            }
                        });
                    },

                    getImageLimit() {
                        return this.imageLimits[this.currentPlatform] || 10;
                    },

                    isCarouselSupported() {
                        return ['facebook', 'instagram', 'linkedin'].includes(this.currentPlatform) && !this.isStory;
                    },

                    requiresSingleImage() {
                        return !this.isCarouselSupported() && this.images.length > 1;
                    },

                    syncImageFromImages() {
                        this.image = this.images.length ? this.images[0] : '';
                        this.carouselIndex = Math.min(this.carouselIndex, Math.max(0, this.images.length - 1));
                    },

                    removeImage(index) {
                        this.images.splice(index, 1);
                        this.syncImageFromImages();
                    },

                    reloadPreviewVideo() {
                        this.$nextTick(() => {
                            if (this.$refs.previewVideo) {
                                this.$refs.previewVideo.load();
                            }
                        });
                    },

                    isStorySupported() {
                        return ['facebook', 'instagram', 'tiktok'].includes(this.currentPlatform);
                    },

                    isImageSupported() {
                        return ['facebook', 'x', 'instagram', 'linkedin'].includes(this.currentPlatform);
                    },

                    isOnlyImageSupported() {
                        return ['facebook', 'x', 'instagram', 'linkedin'].includes(this.currentPlatform);
                    },

                    isVideoSupported() {
                        return ['tiktok', 'youtube', 'youtube-shorts'].includes(this.currentPlatform);
                    },

                    isOnlyVideoSupported() {
                        return ['tiktok', 'youtube', 'youtube-shorts'].includes(this.currentPlatform);
                    },

                    onImageChange(value) {
                        if (value) {
                            this.video = null;
                            this.$refs.uploadVideo.value = null;
                        }
                    },
                    onVideoChange(value) {
                        if (value) {
                            this.image = null;
                            this.$refs.uploadImage.value = null;
                        }
                    },

                    onPlatformChange(value) {
                        this.currentPlatform = value;
                        window.history.replaceState(null, null, `?platform=${value}`);

                        if (!this.isStorySupported()) {
                            this.isStory = false;
                        }

                        if (this.isOnlyImageSupported()) {
                            this.video = null;
                            this.$refs.uploadVideo.value = null;
                        }

                        if (this.isOnlyVideoSupported()) {
                            this.image = null;
                            this.$refs.uploadImage.value = null;
                        }
                    },

                    async postNow() {
                        if (this.isPosting) return;

                        this.isPosting = true;

                        let form = this.$refs.form;
                        let formData = new FormData(form);
                        formData.append('post_now', 1);
                        if (this.requiresSingleImage()) {
                            const selectedImage = this.images[this.carouselIndex];
                            formData.set('images', JSON.stringify([selectedImage]));
                            formData.set('image', selectedImage);
                        }
                        try {
                            let response = await fetch(
                                "{{ route('dashboard.user.social-media.post.update', $editingPost->id) }}", {
                                    method: "POST",
                                    headers: {
                                        "X-CSRF-TOKEN": document.querySelector(
                                            'meta[name="csrf-token"]').getAttribute(
                                            'content'),
                                        "Accept": "application/json"
                                    },
                                    body: formData
                                });

                            let result = await response.json();

                            if (result.status === 'success') {
                                toastr.success(result.message);
                            } else {
                                toastr.error(result.message);
                            }
                        } catch (error) {
                            console.error("Error:", error);
                        } finally {
                            this.isPosting = false;
                        }
                    },
                    async schedulePost() {
                        if (this.isPosting) return;

                        this.isPosting = true;

                        let form = this.$refs.form;
                        let formData = new FormData(form);
                        formData.append('post_now', 0);
                        if (this.requiresSingleImage()) {
                            const selectedImage = this.images[this.carouselIndex];
                            formData.set('images', JSON.stringify([selectedImage]));
                            formData.set('image', selectedImage);
                        }
                        try {
                            let response = await fetch(
                                "{{ route('dashboard.user.social-media.post.update', $editingPost->id) }}", {
                                    method: "POST",
                                    headers: {
                                        "X-CSRF-TOKEN": document.querySelector(
                                            'meta[name="csrf-token"]').getAttribute(
                                            'content'),
                                        "Accept": "application/json"
                                    },
                                    body: formData
                                });

                            let result = await response.json();

                            if (result.status === 'success') {
                                toastr.success(result.message);
                            } else {
                                toastr.error(result.message);
                            }
                        } catch (error) {
                            console.error("Error:", error);
                        } finally {
                            this.isPosting = false;
                        }
                    },
                    async uploadVideo(event) {
                        const input = event.target;
                        const file = input.files[0];

                        if (!file) return;

                        let formData = new FormData();
                        formData.append('upload_video', file);
                        formData.append('_token', '{{ csrf_token() }}');

                        try {
                            const response = await fetch(
                                '{{ route('dashboard.user.social-media.upload.video') }}', {
                                    method: 'POST',
                                    body: formData,
                                });
                            const data = await response.json();

                            if (data && data.video_path) {
                                this.video = data.video_path;
                                this.videoGenerationError = '';
                                this.reloadPreviewVideo();
                            } else {
                                console.error('Expected data not returned from server', data);
                                const message = data?.message ?? '{{ __('Expected data not returned from server') }}';
                                this.videoGenerationError = message;
                                toastr.error(message);
                            }
                        } catch (error) {
                            console.error('Error occurred while uploading the image', error);
                            const message = error?.message ?? '{{ __('Error occurred while uploading the image') }}';
                            this.videoGenerationError = message;
                            toastr.error(message);
                        }
                    },
                    async uploadImage(event) {
                        const input = event.target;
                        const files = Array.from(input.files);

                        if (!files.length) return;

                        const limit = this.getImageLimit();
                        const remaining = limit - this.images.length;

                        if (remaining <= 0) {
                            toastr.error(`{{ __('Maximum image limit reached.') }}`);
                            return;
                        }

                        const filesToUpload = files.slice(0, remaining);

                        for (const file of filesToUpload) {
                            let formData = new FormData();
                            formData.append('upload_image', file);
                            formData.append('_token', '{{ csrf_token() }}');

                            try {
                                const response = await fetch(
                                    '{{ route('dashboard.user.social-media.upload.image') }}', {
                                        method: 'POST',
                                        body: formData,
                                    });
                                const data = await response.json();

                                if (data && data.image_path) {
                                    this.images.push(data.image_path);
                                    this.syncImageFromImages();
                                } else {
                                    toastr.error(data.message ?? '{{ __('Expected data not returned from server') }}');
                                }
                            } catch (error) {
                                toastr.error('{{ __('Error occurred while uploading the image') }}');
                            }
                        }

                        input.value = '';
                    },
                    async generateVideo() {
                        this.videoGenerationError = '';
                        if (!this.content || !this.content.trim().length) {
                            const message = '{{ __('Please enter some content before generating an video.') }}';
                            this.videoGenerationError = message;
                            return toastr.error(message);
                        }

                        const formData = new FormData();

                        const prompt =
                            `{{ __('Create a short, visually captivating vertical video (9:16 format) optimized for TikTok. The video should align with the message: "${this.content}" and reflect the tone, style, and core message to maximize viewer engagement. Make it dynamic, aesthetic, and platform-native — include smooth transitions, energetic or emotional visuals (depending on tone), and scenes that match the storytelling flow. The video should not include any on-screen text, as captions or overlays will be added later. Focus on mood, movement, and storytelling through visuals only.') }}`;
                        formData.append('prompt', prompt);

                        try {
                            this.generatingVideo = true;

                            const response = await fetch(
                                '{{ route('dashboard.user.social-media.video.generate') }}', {
                                    method: 'POST',
                                    body: formData,
                                });
                            let data = null;

                            try {
                                data = await response.json();
                            } catch (jsonError) {
                                console.error('Video generation JSON parse error', jsonError);
                            }

                            if (!response.ok) {
                                const message = response.status === 422 ?
                                    (data?.message ?? '{{ __('Video generation could not be completed. Please review your content and try again.') }}') :
                                    (data?.message ?? '{{ __('Video generation failed. Please try again later.') }}');

                                toastr.error(message);
                                this.videoGenerationError = message;
                                this.generatingVideo = false;
                                return;
                            }

                            if (data?.status === 'success') {
                                this.videoGenerationError = '';
                                this.getVideoStatus();
                                return;
                            }

                            const failMessage = data?.message ?? '{{ __('Video generation failed. Please try again later.') }}';
                            this.videoGenerationError = failMessage;
                            toastr.error(failMessage);
                            this.generatingVideo = false;
                        } catch (e) {
                            const message = e?.message ?? '{{ __('Video generation failed. Please try again later.') }}';
                            this.videoGenerationError = message;
                            toastr.error(message);
                            this.generatingVideo = false;
                        }

                    },
                    async getVideoStatus() {
                        fetch('{{ route('dashboard.user.social-media.video.status') }}', {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        }).then(response => {
                            if (!response.ok) {
                                return response.json().then(errorData => {
                                    throw new Error(errorData.message ||
                                        'An unknown error occurred');
                                });
                            }
                            return response.json();
                        }).then(data => {
                            if (data.status === 'error') {
                                throw new Error(data.message);
                            }

                            if (data.status === 'COMPLETED') {
                                this.generatingVideo = false;

                                this.video = data.video_path;
                                this.videoGenerationError = '';
                                this.reloadPreviewVideo();

                            } else {
                                setTimeout(() => {
                                    this.getVideoStatus();
                                }, 1000);
                            }
                        }).catch(error => {
                            const message = error?.message || error || '{{ __('Video generation failed. Please try again later.') }}';
                            toastr.error(message);
                            this.videoGenerationError = message;
                            this.generatingVideo = false;
                        });
                    },
                    openGenerateModal() {
                        const limit = this.getImageLimit();
                        const remaining = limit - this.images.length;
                        if (remaining <= 0) {
                            return toastr.error('{{ __('Maximum image limit reached.') }}');
                        }
                        // Keep current aiImageCount but ensure it doesn't exceed remaining slots
                        this.aiImageCount = Math.min(this.aiImageCount || 1, remaining);
                        this.aiImagePrompt = '';
                        this.showAiImageModal = true;
                    },
                    async generateImage() {
                        if (this.isDemo && this.demoImageGenerated) {
                            return toastr.error('{{ __('Demo mode is limited to 1 image generation per 24 hours.') }}');
                        }

                        if (this.isDemo && this.aiImageCount > 1) {
                            return toastr.error('{{ __('Demo mode is limited to 1 image generation per 24 hours.') }}');
                        }

                        this.showAiImageModal = false;

                        if ((!this.content || !this.content.trim().length) && (!this.aiImagePrompt || !this.aiImagePrompt.trim().length)) {
                            return toastr.error(
                                '{{ __('Please enter post content or describe your images.') }}'
                            );
                        }

                        const limit = this.getImageLimit();
                        const remaining = limit - this.images.length;
                        if (remaining <= 0) {
                            return toastr.error('{{ __('Maximum image limit reached.') }}');
                        }
                        const imageCount = this.isDemo ? 1 : Math.min(this.aiImageCount, remaining);

                        const customPrompt = this.aiImagePrompt?.trim();
                        const storyPrompt =
                            `Generate a visually engaging vertical image (9:16 aspect ratio, 1080x1920) for a social media story on ${this.currentPlatform}. The image should align with the following post content: ${this.content}, while being eye-catching and optimized for story dimensions. Do not include any text in the image.`;
                        const postPrompt =
                            `Generate a visually engaging image for a social media post on ${this.currentPlatform}. The image should align with the following post content: ${this.content}, while being eye-catching, relevant, and optimized for the platform's recommended dimensions. The image should reflect the tone, style, and message to drive engagement. Do not include any text in the image.`;
                        const prompt = customPrompt || (this.isStory ? storyPrompt : postPrompt);

                        this.generatingImage = true;

                        // Generate each image individually
                        for (let i = 0; i < imageCount; i++) {
                            try {
                                @include('social-media::post.includes.image-script')

                                const response = await fetch('/dashboard/user/openai/generate', {
                                    method: 'POST',
                                    body: formData,
                                });
                                const data = await response.json();

                                if (data.status === 'success') {
                                    if (this.isDemo) {
                                        this.demoImageGenerated = true;
                                    }

                                    console.log(`[Image ${i + 1}] Response received for requestId:`, data.requestId);

                                    // Add loading placeholder immediately
                                    if (data.requestId && this.images.length < limit) {
                                        const placeholderUrl = '/themes/default/assets/img/loading.svg';
                                        const imageIndex = this.images.length;

                                        this.images.push(placeholderUrl);
                                        this.loadingImageRequests.set(data.requestId, imageIndex);
                                        this.syncImageFromImages();

                                        console.log(`[Image ${i + 1}] Added placeholder at index ${imageIndex}`);

                                        // Start polling to get real image
                                        this.pendingImagePolls++;
                                        console.log(`[Polling] Active polls:`, this.pendingImagePolls);
                                        this.getImageStatus(data.requestId);
                                    }

                                    console.log(`[Image ${i + 1}] Current images array:`, this.images.length);
                                } else {
                                    toastr.error(data.message || `{{ __('Failed to generate image') }} ${i + 1}`);
                                }
                            } catch (error) {
                                toastr.error(error.message || `{{ __('Failed to generate image') }} ${i + 1}`);
                            }
                        }

                        // Don't hide loader yet - wait for all polling to complete
                        if (this.pendingImagePolls === 0) {
                            this.generatingImage = false;
                        }
                        console.log(`[Generation] Loop finished. Pending polls:`, this.pendingImagePolls);
                    },
                    async getImageStatus(requestId) {
                        const limit = this.getImageLimit();
                        console.log(`[Status Check] Polling for requestId:`, requestId);
                        fetch('{{ route('dashboard.user.social-media.image.get.status') }}?request_id=' +
                            requestId, {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json'
                                }
                            }).then(response => {
                            if (!response.ok) {
                                return response.json().then(errorData => {
                                    throw new Error(errorData.message ||
                                        'An unknown error occurred');
                                });
                            }
                            return response.json();
                        }).then(data => {
                            console.log(`[Status Check] Response for ${requestId}:`, data.status);
                            if (data.status === 'success') {
                                const image = data.data;
                                const output = image.output;

                                // Replace loading placeholder with real image
                                if (output && this.loadingImageRequests.has(requestId)) {
                                    const imageIndex = this.loadingImageRequests.get(requestId);
                                    console.log(`[Status Check] Replacing placeholder at index ${imageIndex} with real image:`, output);

                                    this.images[imageIndex] = output;
                                    this.loadingImageRequests.delete(requestId);
                                    this.syncImageFromImages();
                                } else if (output && !this.images.includes(output) && this.images.length < limit) {
                                    // Fallback: if no placeholder tracked, add normally
                                    console.log(`[Status Check] Adding image (no placeholder found):`, output);
                                    this.images.push(output);
                                    this.syncImageFromImages();
                                } else if (output && this.images.includes(output)) {
                                    console.log(`[Status Check] Image already exists, skipping:`, output);
                                }

                                // Polling completed for this request
                                this.pendingImagePolls--;
                                console.log(`[Polling] Completed. Remaining polls:`, this.pendingImagePolls);

                                // Hide loader when all polls are done
                                if (this.pendingImagePolls === 0) {
                                    this.generatingImage = false;
                                    console.log(`[Polling] All polls completed. Loader hidden.`);
                                }
                            } else {
                                // Still processing, continue polling
                                console.log(`[Status Check] Still pending, retrying in 1s...`);
                                setTimeout(() => {
                                    this.getImageStatus(requestId);
                                }, 1000);
                            }
                        }).catch(error => {
                            console.error('[Status Check] Error:', error);
                            // Polling failed, remove placeholder and decrement counter
                            if (this.loadingImageRequests.has(requestId)) {
                                const imageIndex = this.loadingImageRequests.get(requestId);
                                console.log(`[Polling] Failed. Removing placeholder at index ${imageIndex}`);
                                this.images.splice(imageIndex, 1);
                                this.loadingImageRequests.delete(requestId);
                                this.syncImageFromImages();
                            }

                            this.pendingImagePolls--;
                            console.log(`[Polling] Failed. Remaining polls:`, this.pendingImagePolls);

                            if (this.pendingImagePolls === 0) {
                                this.generatingImage = false;
                            }
                        });
                    },
                    async generateContent() {

                        {{-- if(this.personalizedContent) { --}}
                        {{--	if(!this.selectedCompany) { --}}
                        {{--		return toastr.error('{{ __('Please select a company first.') }}'); --}}
                        {{--	} --}}

                        {{--	if(!this.selectedProduct) { --}}
                        {{--		return toastr.error('{{ __('Please select a product first.') }}'); --}}
                        {{--	} --}}

                        {{--	if(!this.selectedCampaign) { --}}
                        {{--		return toastr.error('{{ __('Please select a campaign first.') }}'); --}}
                        {{--	} --}}
                        {{-- } --}}

                        if (!this.content || !this.content.trim().length) {
                            return toastr.error(
                                '{{ __('Please enter some content first.') }}');
                        }

                        const formData = new FormData();
                        formData.append('campaign_id', this.selectedCampaign);
                        formData.append('is_personalized_content', (this.personalizedContent ?
                            1 : 0));
                        formData.append('selected_company', this.selectedCompany);
                        formData.append('selected_product', this.selectedProduct);
                        formData.append('social_media_platform_id', this.socialMediaPlatformId);
                        formData.append('content', this.content);
                        formData.append('tone', this.tone);
                        formData.append('platform',
                            '{{ $editingPost['social_media_platform'] }}');

                        try {
                            this.generatingContent = true;

                            const response = await fetch(
                                '{{ route('dashboard.user.social-media.campaign.generate') }}', {
                                    method: 'POST',
                                    body: formData,
                                });
                            const data = await response.json();

                            if (data.result) {
                                this.content = data.result;
                            } else {
                                if (data.message) {
                                    toastr.error(data.message);
                                    return;
                                }
                                toastr.error('{{ __('Failed to generate content.') }}');
                            }
                        } catch (error) {
                            toastr.error(error.message);
                        } finally {
                            this.generatingContent = false;
                        }
                    },
                }));
            });
        })();
    </script>
@endpush
