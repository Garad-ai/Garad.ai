@php
    use App\Helpers\Classes\MarketplaceHelper;

    $isCreativeSuiteInstalled = MarketplaceHelper::isRegistered('creative-suite');
    $isAdvancedImageInstalled = MarketplaceHelper::isRegistered('advanced-image');
@endphp
@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_default_sidebar' => true,
    'disable_titlebar' => true,
    'disable_footer' => true,
])

@section('title', __('Media Library'))
@section('titlebar_actions', '')

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.remove("focus-mode");
            document.body.classList.add('navbar-shrinked');
            localStorage.setItem('lqdNavbarShrinked', true);
        })();
    </script>
@endpush

@push('css')
    <style>
        @media (min-width: 992px) {
            .lqd-header {
                display: none !important;
            }
        }
    </style>
@endpush

@section('content')
    <div
        class="lqd-adv-img-editor"
        x-data="imageProManager"
    >
        @include('ai-image-pro::home.top-navbar')

        <div
            class="pb-10 pt-[calc(30px+var(--header-height))]"
            x-data="mediaLibrary"
        >
            <h2 class="mb-5 text-[30px] font-medium">
                @lang('Media Library')
            </h2>

            <div class="mb-11 flex flex-col items-center justify-between gap-4 lg:flex-row">
                <div class="flex w-full grow flex-wrap items-center lg:w-auto">
                    <button
                        class="selected rounded-full px-6 py-3.5 text-base font-medium leading-none text-heading-foreground transition [&.selected]:bg-heading-foreground/5"
                        :class="{ 'selected': filter === 'assets' }"
                        @click="setFilter('assets')"
                    >
                        {{ __('My Assets') }}
                    </button>
                    <button
                        class="rounded-full px-6 py-3.5 text-base font-medium leading-none text-heading-foreground transition [&.selected]:bg-heading-foreground/5"
                        :class="{ 'selected': filter === 'bookmarks' }"
                        @click="setFilter('bookmarks')"
                    >
                        {{ __('Bookmarks') }}
                    </button>
                </div>

                <div class="flex w-full grow flex-wrap items-center gap-3 lg:w-auto lg:justify-end">
                    <div class="flex items-center gap-3 max-md:order-last">
                        <span class="whitespace-nowrap text-sm font-medium text-heading-foreground sm:hidden">
                            {{ __('Grid size') }}
                        </span>
                        <input
                            class="h-0.5 w-36 cursor-pointer appearance-none rounded-full bg-foreground/10 focus:outline-primary [&::-moz-range-thumb]:size-2.5 [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-none [&::-moz-range-thumb]:bg-primary active:[&::-moz-range-thumb]:scale-110 [&::-webkit-slider-thumb]:size-2.5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-none [&::-webkit-slider-thumb]:bg-primary active:[&::-webkit-slider-thumb]:scale-110"
                            type="range"
                            x-model.number="gridSize"
                            @change="saveGridSize()"
                            min="2"
                            max="6"
                            step="1"
                        />
                    </div>
                    <form
                        class="relative flex max-md:w-full md:w-[min(100%,180px)] 2xl:w-[min(100%,330px)]"
                        @submit.prevent="doSearch"
                    >
                        <x-tabler-search class="absolute start-5 top-1/2 size-5 -translate-y-1/2" />
                        <x-forms.input
                            class="h-auto w-full rounded-full border-none bg-foreground/5 py-4 ps-[52px] font-medium placeholder:text-foreground sm:text-[12px]"
                            container-class="grow"
                            placeholder="{{ __('Search') }}"
                            x-model="searchQuery"
                            @keyup.debounce.500ms="doSearch"
                        />
                    </form>

                    <button
                        class="h-[50px] rounded-full bg-heading-foreground/5 px-6 py-2.5 text-[12px] font-medium leading-none text-heading-foreground hover:bg-primary hover:text-primary-foreground"
                        @click.prevent="toggleSelectAll"
                        x-text="selectedItems.length === images.length && images.length ? '{{ __('Deselect all') }}' : '{{ __('Select all') }}'"
                    >
                        {{ __('Select all') }}
                    </button>

                    <x-dropdown.dropdown offsetY="10px">
                        <x-slot:trigger
                            class="h-[50px] rounded-full bg-heading-foreground/5 px-6 py-2.5 text-[12px] font-medium leading-none text-heading-foreground"
                        >
                            <span x-text="sortLabels[sort]">
                                {{ __('Sort by Date') }}
                            </span>
                            <x-tabler-chevron-down class="size-4" />
                        </x-slot:trigger>

                        <x-slot:dropdown
                            class="p-1"
                        >
                            <button
                                class="flex w-full items-center gap-2 rounded px-3 py-2 text-start text-xs font-medium hover:bg-foreground/5 [&.active]:bg-foreground/5"
                                :class="{ 'active': sort === 'date' }"
                                @click="setSort('date')"
                            >
                                {{ __('Date') }}
                                <x-tabler-caret-down
                                    class="size-4 fill-current transition-transform [&.flipped]:rotate-180"
                                    ::class="{ 'flipped': sortDirection === 'asc' }"
                                    x-show="sort === 'date'"
                                />
                            </button>
                            <button
                                class="flex w-full items-center gap-2 rounded px-3 py-2 text-start text-xs font-medium hover:bg-foreground/5 [&.active]:bg-foreground/5"
                                :class="{ 'active': sort === 'popularity' }"
                                @click="setSort('popularity')"
                            >
                                {{ __('Popularity') }}
                                <x-tabler-caret-down
                                    class="size-4 fill-current transition-transform [&.flipped]:rotate-180"
                                    ::class="{ 'flipped': sortDirection === 'asc' }"
                                    x-show="sort === 'popularity'"
                                />
                            </button>
                            {{-- <button
                                class="flex w-full items-center gap-2 rounded px-3 py-2 text-start text-xs font-medium hover:bg-foreground/5 [&.active]:bg-foreground/5"
                                :class="{ 'active': sort === 'variations' }"
                                @click="setSort('variations')"
                            >
                                {{ __('Variations') }}
                                <x-tabler-caret-down
                                    class="size-4 fill-current transition-transform [&.flipped]:rotate-180"
                                    ::class="{ 'flipped': sortDirection === 'asc' }"
                                    x-show="sort === 'variations'"
                                />
                            </button>
                            <button
                                class="flex w-full items-center gap-2 rounded px-3 py-2 text-start text-xs font-medium hover:bg-foreground/5 [&.active]:bg-foreground/5"
                                :class="{ 'active': sort === 'edits' }"
                                @click="setSort('edits')"
                            >
                                {{ __('Edits') }}
                                <x-tabler-caret-down
                                    class="size-4 fill-current transition-transform [&.flipped]:rotate-180"
                                    ::class="{ 'flipped': sortDirection === 'asc' }"
                                    x-show="sort === 'edits'"
                                />
                            </button> --}}
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                </div>
            </div>

            <div
                class="-mx-0.5 flex flex-wrap items-start"
                x-show="loading && !images.length"
            >
                @for ($i = 0; $i < 10; $i++)
                    <div
                        class="masonry-grid-item mb-0.5 w-1/4 px-px"
                        :style="{ width: `${100 / gridSize}%` }"
                    >
                        <div class="lqd-loading-skeleton lqd-is-loading relative aspect-square w-full overflow-hidden rounded">
                            <div
                                class="absolute size-full"
                                data-lqd-skeleton-el
                            ></div>
                        </div>
                    </div>
                @endfor
            </div>

            {{-- Empty States --}}
            <x-empty-state
                class="py-20"
                icon="tabler-photo-off"
                :title="__('No images found')"
                :description="__('You haven\'t bookmarked any images yet.')"
                show="!loading && !images.length && filter === 'bookmarks'"
                x-cloak
            />

            <x-empty-state
                class="py-20"
                icon="tabler-photo-off"
                :title="__('No images found')"
                :description="__('Try a different search term.')"
                show="!loading && !images.length && filter === 'assets' && searchQuery"
                x-cloak
            />

            <x-empty-state
                class="py-20"
                icon="tabler-photo-off"
                :title="__('No images found')"
                :description="__('Start generating images to see them here.')"
                show="!loading && !images.length && filter === 'assets' && !searchQuery"
                x-cloak
            />

            {{-- Image Grid --}}
            <div
                x-ref="imageGrid"
                x-show="images.length"
            >
                {{-- Date Group Headers and Images --}}
                <template
                    x-for="(group, groupIndex) in groupedImages"
                    :key="group.label"
                >
                    <div class="mb-6 w-full">
                        {{-- Date Header --}}
                        <h3
                            class="mb-6 mt-0 text-xs font-medium"
                            x-text="group.label"
                        ></h3>

                        {{-- Images Grid --}}
                        <div
                            class="grid gap-0.5"
                            :style="`grid-template-columns: repeat(${gridSize}, minmax(0, 1fr))`"
                        >
                            <template
                                x-for="(image, index) in group.images"
                                :key="image.id"
                            >
                                <div
                                    class="image-result group relative aspect-square cursor-pointer overflow-hidden rounded-[3px] bg-foreground/[2%]"
                                    data-id-prefix="media-library"
                                    x-data="{ inView: false }"
                                    x-intersect:enter.margin.250px.once="inView = true"
                                    :data-id="image.id"
                                    :data-payload="JSON.stringify(image)"
                                    @click="setActiveModal(image, 'media-library')"
                                >
                                    <img
                                        class="h-full w-full rounded-[3px] object-cover"
                                        :src="inView ? `${(image.thumbnail || image.url).startsWith('upload') ? '/' : ''}${image.thumbnail || image.url}` :
                                            `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E`"
                                        :alt="image.prompt"
                                    >

                                    {{-- Selection Checkbox --}}
                                    <div
                                        class="absolute end-3 top-4 z-10"
                                        @click.stop
                                    >
                                        <label
                                            class="flex size-5 cursor-pointer items-center justify-center rounded border bg-white/80 opacity-0 transition group-hover:opacity-100 max-md:opacity-100 [&.selected]:border-primary [&.selected]:bg-primary [&.selected]:text-primary-foreground [&.selected]:opacity-100"
                                            :class="{ 'selected': isSelected(image.id) }"
                                        >
                                            <input
                                                class="sr-only"
                                                type="checkbox"
                                                :checked="isSelected(image.id)"
                                                @change="toggleSelect(image.id)"
                                            >
                                            <x-tabler-check
                                                class="size-4"
                                                x-show="isSelected(image.id)"
                                            />
                                        </label>
                                    </div>

                                    {{-- Edits Badge --}}
                                    <template x-if="image.edits">
                                        <span
                                            class="absolute start-1 top-1 inline-block rounded-full bg-background px-3 py-1.5 text-4xs font-medium text-heading-foreground md:start-3 md:top-3 md:text-2xs"
                                            x-text="`${image.edits} {{ __('edits') }}`"
                                        ></span>
                                    </template>

                                    {{-- Variations Badge --}}
                                    <template x-if="image.variations && !image.edits">
                                        <span
                                            class="absolute start-1 top-1 inline-block rounded-full bg-background px-3 py-1.5 text-4xs font-medium text-heading-foreground md:start-3 md:top-3 md:text-2xs"
                                            x-text="`${image.variations} {{ __('variations') }}`"
                                        ></span>
                                    </template>

                                    {{-- Hover Overlay --}}
                                    <div class="absolute inset-x-0 bottom-0 flex w-full items-center justify-between px-4 py-5">
                                        <x-progressive-blur
                                            class="absolute inset-x-0 -top-3 bottom-0 z-0 opacity-0 group-hover:opacity-100 group-has-[.lqd-is-active]:translate-y-0 group-has-[.lqd-is-active]:opacity-100"
                                        />

                                        <div
                                            class="relative z-1 flex min-w-0 flex-1 translate-y-1 items-center justify-between gap-2 opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100 group-has-[.lqd-is-active]:translate-y-0 group-has-[.lqd-is-active]:opacity-100">
                                            <div class="grow overflow-hidden">
                                                <p
                                                    class="m-0 truncate text-3xs font-medium text-white"
                                                    x-text="image.prompt"
                                                    :title="image.prompt"
                                                ></p>
                                            </div>

                                            <div
                                                class="relative z-1 flex translate-y-1 justify-end gap-2 opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100 group-has-[.lqd-is-active]:translate-y-0 group-has-[.lqd-is-active]:opacity-100"
                                                @click.stop
                                            >
                                                <x-dropdown.dropdown
                                                    anchor="end"
                                                    offsetY="5px"
                                                >
                                                    <x-slot:trigger
                                                        class="size-7 p-0 text-white group-[&.lqd-is-active]/dropdown:scale-110"
                                                        variant="ghost"
                                                    >
                                                        <x-tabler-dots-circle-horizontal class="size-5" />
                                                    </x-slot:trigger>

                                                    <x-slot:dropdown
                                                        class="min-w-56 p-1"
                                                    >
                                                        <x-button
                                                            class="w-full justify-start !rounded-md text-start text-2xs font-medium hover:transform-none hover:shadow-none"
                                                            variant="ghost"
                                                            href="#"
                                                            @click.prevent="editWithAssistant(image)"
                                                        >
                                                            <x-tabler-message-circle class="size-4 shrink-0" />
                                                            {{ __('Open with Assistant') }}
                                                        </x-button>
                                                        <x-button
                                                            class="w-full justify-start !rounded-md text-start text-2xs font-medium hover:transform-none hover:shadow-none"
                                                            variant="ghost"
                                                            href="#"
                                                            @click.prevent="editWithEditor(image)"
                                                        >
                                                            <x-tabler-scissors class="size-4 shrink-0" />
                                                            {{ __('Open with Editor') }}
                                                        </x-button>
                                                        <x-button
                                                            class="w-full justify-start !rounded-md text-start text-2xs font-medium hover:transform-none hover:shadow-none"
                                                            variant="ghost"
                                                            href="#"
                                                            @click.prevent="openWithCreativeSuite(image)"
                                                            x-show="isCreativeSuiteInstalled"
                                                        >
                                                            <x-tabler-brush class="size-4 shrink-0" />
                                                            {{ __('Open with Creative Suite') }}
                                                        </x-button>

                                                        @if ($app_is_demo)
                                                            <x-button
                                                                class="w-full justify-start !rounded-md text-start text-2xs font-medium text-red-500/60 hover:transform-none hover:bg-red-500/10 hover:shadow-none"
                                                                variant="ghost"
                                                                href="#"
                                                                @click.prevent="toastr.info('{{ __('This feature is disabled in demo mode.') }}')"
                                                            >
                                                                <x-tabler-trash class="size-4 shrink-0" />
                                                                {{ __('Delete Media') }}
                                                            </x-button>
                                                        @else
                                                            <x-button
                                                                class="w-full justify-start !rounded-md text-start text-2xs font-medium text-red-500 hover:transform-none hover:bg-red-500/10 hover:shadow-none"
                                                                variant="ghost"
                                                                href="#"
                                                                @click.prevent="deleteImage(image.id)"
                                                            >
                                                                <x-tabler-trash class="size-4 shrink-0" />
                                                                {{ __('Delete Media') }}
                                                            </x-button>
                                                        @endif
                                                    </x-slot:dropdown>
                                                </x-dropdown.dropdown>
                                            </div>
                                        </div>
                                    </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Load More (auto-triggered on scroll) --}}
            <div
                class="mt-8 flex justify-center py-4"
                x-show="hasMore && images.length"
                x-cloak
                x-intersect:enter.margin.200px="loadMore"
            >
                <div
                    class="flex items-center gap-2 text-sm text-foreground/60"
                    x-show="loadingMore"
                >
                    <x-tabler-loader-2 class="size-5 animate-spin" />
                    {{ __('Loading more...') }}
                </div>
            </div>

            {{-- Bulk Actions Bar --}}
            <div
                class="pointer-events-none fixed bottom-20 end-0 start-0 z-50 transition-all lg:bottom-8 lg:start-[--navbar-width]"
                x-cloak
                x-show="selectedItems.length"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-4"
            >
                <div class="container">
                    <form
                        class="pointer-events-auto flex flex-col justify-between gap-3 rounded-lg border border-foreground/5 bg-background px-6 py-4 shadow-xl shadow-black/5 md:flex-row md:items-center md:rounded-full md:py-2 md:pe-2"
                        @submit.prevent="applyBulkAction"
                    >
                        <p class="m-0 text-sm font-medium">
                            <span x-text="selectedItems.length"></span>
                            <span x-show="selectedItems.length === 1">{{ __('item') }}</span>
                            <span x-show="selectedItems.length !== 1">{{ __('items') }}</span>
                            {{ __('selected') }}
                        </p>
                        <div class="flex items-center gap-3">
                            <x-forms.input
                                class="w-full rounded-full md:w-auto md:pe-12"
                                type="select"
                                size="md"
                                x-model="bulkAction"
                            >
                                <option value="delete">
                                    {{ __('Move to Trash') }}
                                </option>
                            </x-forms.input>

                            <x-button
                                class="rounded-full"
                                type="submit"
                                size="md"
                                ::disabled="bulkLoading || isDemo"
                            >
                                <span x-show="!bulkLoading">{{ __('Apply') }}</span>
                                <x-tabler-loader-2
                                    class="size-4 animate-spin"
                                    x-show="bulkLoading"
                                />
                            </x-button>

                            <button
                                class="ms-auto flex size-10 items-center justify-center rounded-full text-foreground/70 transition hover:bg-foreground/5 hover:text-foreground"
                                type="button"
                                @click="clearSelection"
                            >
                                <x-tabler-x class="size-5" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Image Modal Component --}}
        @include('ai-image-pro::home.shared-components.image-modal')
    </div>
@endsection

@include('ai-image-pro::includes.scripts.image-pro-manager-script')
@include('ai-image-pro::includes.scripts.media-library-script')
