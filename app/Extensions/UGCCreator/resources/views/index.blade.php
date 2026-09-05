@php
    use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;

    $productAssets = $productAssets ?? [];
    $characterAssets = $characterAssets ?? [];
    $isDemo = $isDemo ?? false;
    $cameraPresets = $cameraPresets ?? [];
    $models = $models ?? [];
    $defaultModel = $defaultModel ?? UGCCreatorRegistry::DEFAULT_MODEL;
    $maxUploadMb = $maxUploadMb ?? UGCCreatorRegistry::DEFAULT_MAX_UPLOAD_SIZE_MB;
    $showModelSelect = count($models) > 1;

    $ugcCreatorInitial = [
        'isDemo' => (bool) $isDemo,
        'csrf' => csrf_token(),
        'cameraPresets' => $cameraPresets,
        'models' => $models,
        'defaultModel' => $defaultModel,
        'maxUploadMb' => $maxUploadMb,
        'productAssets' => $productAssets,
        'characterAssets' => $characterAssets,
        'endpoints' => [
            'videosStore' => route('dashboard.user.ugc-creator.videos.store'),
            'ugcStudio' => route('dashboard.user.ugc-studio.index'),
            'aiScript' => url('/dashboard/user/openai/update-writing'),
        ],
        'i18n' => [
            'demoDisabled' => __('This feature is disabled in demo mode.'),
            'uploadFailed' => __('Could not upload the image. Please try again.'),
            'generateFailed' => __('Could not start the video generation. Please try again.'),
            'detailsRequired' => __('Please describe the video before generating.'),
            'referenceRequired' => __('Please upload at least one product or character image, or pick a camera preset.'),
            'detailsSeedRequired' => __('Type a short description first so the AI can improve it.'),
            'detailsImproveFailed' => __('Could not improve the description. Please try again.'),
        ],
    ];
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('UGC Creator'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Freely describe and generate fully customized UGC-style videos with AI.'))

@section('titlebar_actions')
    <x-button variant="ghost-shadow" href="{{ route('dashboard.user.ugc-studio.index') }}">
        {{ __('View All Videos') }}
    </x-button>

	@if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('video-editor'))
		<x-button
			href="{{ route('dashboard.user.video-editor.index') }}"
			size="md"
		>
			<x-tabler-plus class="size-4" />
			{{ __('Open AI Video Editor') }}
		</x-button>
	@endif
@endsection

@push('before-head-close')
    <style>
        @media (min-width: 992px) {
            .lqd-page-content-wrap { overflow: visible; }
        }
    </style>
@endpush

@section('content')
    <div class="py-10">
        <div
            class="lqd-ugc-creator flex flex-wrap items-start gap-10 lg:flex-nowrap"
            x-data="ugcCreator({{ json_encode($ugcCreatorInitial) }})"
            x-init="$nextTick(() => { const m = models.find(m => m.value === defaultModel); if (m?.entity) $dispatch('generator-changed', { generator: m.entity, quantity: 1 }); })"
        >
            {{-- Left column — form --}}
            <div class="w-full lg:sticky lg:top-1 lg:w-[35%] lg:shrink-0">
                <p class="mb-5 border-b py-2.5 text-[12px] font-semibold text-heading-foreground transition">
                    {{ __('Video') }}
                </p>

                <x-card class:body="p-5">
                    <form class="flex flex-col gap-5" @submit.prevent="submitVideo()">
                        {{-- Product images upload (multi) --}}
                        <div>
                            <div class="mb-2 flex items-center gap-1">
                                <label class="text-[12px] font-semibold text-heading-foreground">
                                    {{ __('Product Images (Optional)') }}
                                    <span
                                        class="ms-1 text-2xs font-normal opacity-60"
                                        x-text="`(${productAssets.length}/${currentModelMaxRefs})`"
                                    ></span>
                                </label>
                                <x-info-tooltip text="{{ __('Upload one or more product images to guide the generation. The selected model determines how many references are accepted.') }}" />
                            </div>

                            <div
                                class="group/upload relative min-h-[8rem] cursor-pointer rounded-[10px] border border-dashed border-foreground/15 p-4 text-center transition hover:border-primary/60"
                                :class="{ 'border-primary bg-primary/5': dragOverProduct }"
                                @click="$refs.productInput.click()"
                                @dragover.prevent="dragOverProduct = true"
                                @dragleave.prevent="dragOverProduct = false"
                                @drop.prevent="dragOverProduct = false; selectAsset('product', { target: { files: $event.dataTransfer.files } })"
                            >
                                <input
                                    class="hidden"
                                    type="file"
                                    x-ref="productInput"
                                    accept="image/png,image/jpeg,image/webp"
                                    multiple
                                    @change="selectAsset('product', $event)"
                                >

                                {{-- Centered preview grid --}}
                                <div
                                    class="flex flex-wrap items-center justify-center gap-2"
                                    x-show="productAssets.length > 0 && !uploadingProduct"
                                    x-cloak
                                >
                                    <template
                                        x-for="asset in productAssets"
                                        :key="asset.id"
                                    >
                                        <div class="relative size-20 overflow-hidden rounded-md border border-card-border bg-card-background">
                                            <img
                                                class="size-full object-cover"
                                                :src="asset.image_url"
                                                alt=""
                                            >
                                            <button
                                                class="absolute end-1 top-1 inline-grid size-5 place-items-center rounded-full bg-background/90 text-red-500 shadow-sm"
                                                type="button"
                                                @click.stop.prevent="removeAsset('product', asset.id)"
                                            >
                                                <x-tabler-trash class="size-3" />
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <p
                                    class="mt-3 mb-0 text-2xs opacity-60"
                                    x-show="productAssets.length > 0 && !uploadingProduct"
                                    x-cloak
                                >
                                    {{ __('Click or drag to add more') }}
                                </p>

                                {{-- Uploading state --}}
                                <div
                                    class="grid min-h-[6rem] place-items-center"
                                    x-show="uploadingProduct"
                                    x-cloak
                                >
                                    <x-shimmer-text class="text-2xs">
                                        {{ __('Uploading…') }}
                                    </x-shimmer-text>
                                </div>

                                {{-- Empty state --}}
                                <div
                                    class="grid min-h-[6rem] place-items-center"
                                    x-show="productAssets.length === 0 && !uploadingProduct"
                                >
                                    <div>
                                        <svg class="mx-auto mb-2 size-7 opacity-30 transition group-hover/upload:-translate-y-1 group-hover/upload:opacity-60" viewBox="0 0 38 38" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M32.4073 32.4839C28.7298 36.1613 24.2608 38 19 38C13.7392 38 9.24462 36.1613 5.51613 32.4839C1.83871 28.7554 0 24.2608 0 19C0 13.7392 1.83871 9.27016 5.51613 5.59274C9.24462 1.86425 13.7392 0 19 0C24.2608 0 28.7298 1.86425 32.4073 5.59274C36.1358 9.27016 38 13.7392 38 19C38 24.2608 36.1358 28.7554 32.4073 32.4839ZM29.8024 8.19758C26.8401 5.18414 23.2392 3.67742 19 3.67742C14.7608 3.67742 11.1344 5.18414 8.12097 8.19758C5.1586 11.1599 3.67742 14.7608 3.67742 19C3.67742 23.2392 5.1586 26.8656 8.12097 29.879C11.1344 32.8414 14.7608 34.3226 19 34.3226C23.2392 34.3226 26.8401 32.8414 29.8024 29.879C32.8159 26.8656 34.3226 23.2392 34.3226 19C34.3226 14.7608 32.8159 11.1599 29.8024 8.19758ZM20.5323 28.8065H17.4677C16.8548 28.8065 16.5484 28.5 16.5484 27.8871V22C16.5484 20.3431 15.2052 19 13.5484 19H11.4153C11.0067 19 10.7258 18.8212 10.5726 18.4637C10.4194 18.0551 10.4704 17.7231 10.7258 17.4677L18.3871 9.80645C18.7957 9.39785 19.2043 9.39785 19.6129 9.80645L27.2742 17.4677C27.5296 17.7231 27.5806 18.0551 27.4274 18.4637C27.2742 18.8212 26.9933 19 26.5847 19H24.4516C22.7948 19 21.4516 20.3431 21.4516 22V27.8871C21.4516 28.5 21.1452 28.8065 20.5323 28.8065Z" />
                                        </svg>
                                        <p class="mb-1 text-xs font-medium">{{ __('Drag and drop or click to browse') }}</p>
                                        <p class="m-0 text-4xs opacity-50">{{ __('Max File Size: :size MB', ['size' => $maxUploadMb]) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Character image upload --}}
                        <div>
                            <div class="mb-2 flex items-center gap-1">
                                <label class="text-[12px] font-semibold text-heading-foreground">
                                    {{ __('Character Image (Optional)') }}
                                </label>
                                <x-info-tooltip text="{{ __('Upload a character / person reference image. The AI will match their look and identity.') }}" />
                            </div>

                            <div
                                class="group/upload relative min-h-[8rem] cursor-pointer overflow-hidden rounded-[10px] border border-dashed border-foreground/15 p-4 text-center transition hover:border-primary/60"
                                :class="{ 'border-primary bg-primary/5': dragOverCharacter }"
                                @click="characterAsset ? null : $refs.characterInput.click()"
                                @dragover.prevent="dragOverCharacter = true"
                                @dragleave.prevent="dragOverCharacter = false"
                                @drop.prevent="dragOverCharacter = false; selectAsset('character', { target: { files: $event.dataTransfer.files } })"
                            >
                                <input
                                    class="hidden"
                                    type="file"
                                    x-ref="characterInput"
                                    accept="image/png,image/jpeg,image/webp"
                                    @change="selectAsset('character', $event)"
                                >

                                {{-- Centered character preview --}}
                                <div
                                    class="relative mx-auto grid place-items-center"
                                    x-show="characterAsset && !uploadingCharacter"
                                    x-cloak
                                >
                                    <div class="relative size-32 overflow-hidden rounded-md border border-card-border bg-card-background">
                                        <img
                                            class="size-full object-cover"
                                            :src="characterAsset?.image_url"
                                            alt=""
                                        >
                                        <button
                                            class="absolute end-1 top-1 inline-grid size-6 place-items-center rounded-full bg-background/90 text-red-500 shadow-sm"
                                            type="button"
                                            @click.stop.prevent="removeAsset('character')"
                                        >
                                            <x-tabler-trash class="size-3.5" />
                                        </button>
                                    </div>
                                </div>

                                {{-- Uploading state --}}
                                <div
                                    class="grid min-h-[6rem] place-items-center"
                                    x-show="uploadingCharacter"
                                    x-cloak
                                >
                                    <x-shimmer-text class="text-2xs">
                                        {{ __('Uploading…') }}
                                    </x-shimmer-text>
                                </div>

                                {{-- Empty state --}}
                                <div
                                    class="grid min-h-[6rem] place-items-center"
                                    x-show="!characterAsset && !uploadingCharacter"
                                >
                                    <div>
                                        <svg class="mx-auto mb-2 size-7 opacity-30 transition group-hover/upload:-translate-y-1 group-hover/upload:opacity-60" viewBox="0 0 38 38" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M32.4073 32.4839C28.7298 36.1613 24.2608 38 19 38C13.7392 38 9.24462 36.1613 5.51613 32.4839C1.83871 28.7554 0 24.2608 0 19C0 13.7392 1.83871 9.27016 5.51613 5.59274C9.24462 1.86425 13.7392 0 19 0C24.2608 0 28.7298 1.86425 32.4073 5.59274C36.1358 9.27016 38 13.7392 38 19C38 24.2608 36.1358 28.7554 32.4073 32.4839ZM29.8024 8.19758C26.8401 5.18414 23.2392 3.67742 19 3.67742C14.7608 3.67742 11.1344 5.18414 8.12097 8.19758C5.1586 11.1599 3.67742 14.7608 3.67742 19C3.67742 23.2392 5.1586 26.8656 8.12097 29.879C11.1344 32.8414 14.7608 34.3226 19 34.3226C23.2392 34.3226 26.8401 32.8414 29.8024 29.879C32.8159 26.8656 34.3226 23.2392 34.3226 19C34.3226 14.7608 32.8159 11.1599 29.8024 8.19758ZM20.5323 28.8065H17.4677C16.8548 28.8065 16.5484 28.5 16.5484 27.8871V22C16.5484 20.3431 15.2052 19 13.5484 19H11.4153C11.0067 19 10.7258 18.8212 10.5726 18.4637C10.4194 18.0551 10.4704 17.7231 10.7258 17.4677L18.3871 9.80645C18.7957 9.39785 19.2043 9.39785 19.6129 9.80645L27.2742 17.4677C27.5296 17.7231 27.5806 18.0551 27.4274 18.4637C27.2742 18.8212 26.9933 19 26.5847 19H24.4516C22.7948 19 21.4516 20.3431 21.4516 22V27.8871C21.4516 28.5 21.1452 28.8065 20.5323 28.8065Z" />
                                        </svg>
                                        <p class="mb-1 text-xs font-medium">{{ __('Drag and drop or click to browse') }}</p>
                                        <p class="m-0 text-4xs opacity-50">{{ __('Max File Size: :size MB', ['size' => $maxUploadMb]) }}</p>
                                    </div>
                                </div>
                            </div>

                        </div>

                        {{-- Video Details (script) with sparkle AI button --}}
                        <x-forms.input
                            class:label-extra="order-3 ms-auto -my-1.5"
                            type="textarea"
                            rows="5"
                            name="video_details"
                            label="{{ __('Video Details') }}"
                            tooltip="{{ __('Describe what the character should say, do, and how the camera should move — dialogue, voiceover lines, actions, expressions, and camera notes all go here. Use the sparkle button to let AI rewrite or improve your description.') }}"
                            x-model="form.videoDetails"
                        >
                            <x-slot:label-extra>
                                <x-button
                                    class="inline-grid size-7 place-items-center text-2xs"
                                    type="button"
                                    size="none"
                                    variant="ghost"
                                    hover-variant="primary"
                                    ::disabled="detailsImproving"
                                    @click.prevent="improveDetails()"
                                >
                                    <svg
                                        class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 group-hover:fill-current"
                                        :class="{ hidden: detailsImproving }"
                                        width="17" height="17" viewBox="0 0 17 17"
                                        fill="url(#paint0_linear_ugc_creator)"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            clip-rule="evenodd"
                                            d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z"
                                        />
                                        <defs>
                                            <linearGradient id="paint0_linear_ugc_creator" x1="17.0017" y1="8.19589" x2="0.137511" y2="6.25241" gradientUnits="userSpaceOnUse">
                                                <stop stop-color="#8D65E9" />
                                                <stop offset="0.483" stop-color="#5391E4" />
                                                <stop offset="1" stop-color="#6BCD94" />
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                    <x-tabler-refresh
                                        class="col-start-1 col-end-1 row-start-1 row-end-1 hidden size-4 animate-spin"
                                        x-show="detailsImproving"
                                        ::class="{ hidden: !detailsImproving }"
                                    />
                                </x-button>
                            </x-slot:label-extra>
                        </x-forms.input>

                        @if ($showModelSelect)
                            <x-forms.selectbox
                                name="model"
                                label="{{ __('AI Model') }}"
                                :value="$defaultModel"
                                x-modelable="selected"
                                x-model="form.model"
                                @change="() => { const m = models.find(m => m.value === form.model); if (m?.entity) $dispatch('generator-changed', { generator: m.entity, quantity: 1, _force: Date.now() }); }"
                            >
                                @foreach ($models as $model)
                                    <x-forms.selectbox-option
                                        value="{{ $model['value'] }}"
                                        label="{{ $model['label'] }}"
                                    >
                                        <span class="grow truncate">{{ $model['label'] }}</span>
                                    </x-forms.selectbox-option>
                                @endforeach
                            </x-forms.selectbox>
                        @endif

                        {{-- Audio toggle (disabled for models that don't emit native audio) --}}
                        <div>
                            <div class="mb-2 flex items-center gap-1">
                                <label class="text-[12px] font-semibold text-heading-foreground">
                                    {{ __('Audio') }}
                                </label>
                                <x-info-tooltip text="{{ __('Enable synced audio in the generated video. Only models that support native audio will emit sound.') }}" />
                            </div>
                            <button
                                class="flex w-full items-center justify-center gap-2 rounded-card border border-card-border bg-card-background px-3 py-3 text-sm font-medium transition hover:border-primary/60 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-card-border"
                                type="button"
                                :disabled="!currentModelSupportsAudio"
                                @click.prevent="if (currentModelSupportsAudio) form.audioEnabled = !form.audioEnabled"
                            >
                                <x-tabler-volume class="size-4" x-show="currentModelSupportsAudio && form.audioEnabled" />
                                <x-tabler-volume-off class="size-4" x-show="!currentModelSupportsAudio || !form.audioEnabled" x-cloak />
                                <span x-text="(currentModelSupportsAudio && form.audioEnabled) ? '{{ __('On') }}' : '{{ __('Off') }}'"></span>
                            </button>
                        </div>

                        <x-button
                            class="w-full py-4"
                            type="submit"
                            size="lg"
                            ::disabled="submitting || isDemo"
                        >
                            <span x-show="!submitting">{{ __('Generate') }}</span>
                            <span x-show="submitting" x-cloak>{{ __('Submitting…') }}</span>
                        </x-button>

                        <x-cost-preview class="w-full justify-end" />

                        <p
                            class="m-0 text-center text-3xs text-label"
                            x-show="isDemo"
                            x-cloak
                        >
                            {{ __('This feature is disabled in demo mode.') }}
                        </p>
                    </form>
                </x-card>
            </div>

            {{-- Right column — camera presets (cards mirror UGCFactory actor grid) --}}
            <div class="w-full grow">
                <p class="mb-5 border-b py-2.5 text-[12px] font-semibold text-heading-foreground transition">
                    {{ __('Camera Presets') }}
                </p>

                <div class="grid grid-cols-2 gap-1.5 md:grid-cols-3">
                    @foreach ($cameraPresets as $preset)
                        <div
                            class="group/ugc-item relative aspect-[1/1.78] scale-100 cursor-pointer overflow-hidden rounded-2xl border-[5px] border-transparent transition hover:z-1 hover:scale-[1.03] hover:shadow-xl hover:shadow-black/10 active:scale-100 [&.selected]:border-primary"
                            :class="{ 'selected': form.cameraPreset === '{{ $preset['key'] }}' }"
                            @click.prevent="form.cameraPreset = '{{ $preset['key'] }}'"
                        >
                            <img
                                class="size-full scale-[1.03] object-cover transition group-hover/ugc-item:scale-100 group-active/ugc-item:scale-100 {{ empty($preset['image']) ? 'hidden' : '' }}"
                                src="{{ $preset['image'] ?? '' }}"
                                alt="{{ $preset['label'] }}"
                            >
                            <div class="{{ empty($preset['image']) ? 'grid' : 'hidden' }} size-full place-items-center bg-surface text-label">
                                <x-tabler-camera class="size-10 opacity-60" />
                            </div>

                            <div
                                class="absolute inset-x-0 bottom-0 top-2/3 z-1 overflow-hidden bg-gradient-to-t from-black/60 to-transparent">
                            </div>

                            <div class="absolute inset-0 flex flex-col justify-between gap-2 overflow-hidden p-3.5">
                                <div class="flex items-center justify-between gap-1">
                                    <span
                                        class="inline-grid size-9 place-items-center rounded-full bg-background text-foreground shadow-lg shadow-black/5"
                                        x-cloak
                                        x-show="form.cameraPreset === '{{ $preset['key'] }}'"
                                    >
                                        <x-tabler-check class="size-5" />
                                    </span>
                                </div>

                                <div class="relative z-2 px-2 pb-2 text-center">
                                    <p class="truncate text-sm font-medium text-white">
                                        {{ $preset['label'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@pushOnce('script-before')
    @vite('app/Extensions/UGCCreator/resources/js/ugcCreator.js')
@endPushOnce
