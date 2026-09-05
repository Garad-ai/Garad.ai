<x-sidedrawer id="social-media-agent-sidedrawer">
    <template x-if="editingPost">
        <div class="max-h-[calc(100vh-4rem)] min-h-full overflow-y-auto">
            <div class="flex items-center justify-between gap-1 border-b px-6 py-5">
                <span class="text-[18px] font-medium text-heading-foreground">
					 <span
						 class="capitalize"
						 x-text="editingPost.publishing_type"
					 >
                        @lang('Post')
                    </span>
                    <span
                        class="block font-heading text-[28px] font-semibold leading-none"
                        x-show="editingPostPagination.currentPage"
                        x-text="`#${editingPostPagination.currentPage}`"
                    ></span>
                </span>

                <div class="flex select-none items-center gap-2">
                    <a
                        class="inline-grid size-8 place-items-center rounded-full text-heading-foreground transition hover:bg-primary hover:text-primary-foreground"
                        href="#"
                        @click.prevent="openEditSidedrawer({url: editingPostPagination.prevPageUrl})"
                        title="{{ __('Previous Post') }}"
                        :class="{ 'opacity-50 pointer-events-none': !editingPostPagination.prevPageUrl, 'animate-pulse pointer-events-none': currentTasks.has('fetchingPost') }"
                    >
                        <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                    </a>
                    <a
                        class="inline-grid size-8 place-items-center rounded-full text-heading-foreground transition hover:bg-primary hover:text-primary-foreground"
                        href="#"
                        @click.prevent="openEditSidedrawer({url: editingPostPagination.nextPageUrl})"
                        title="{{ __('Next Post') }}"
                        :class="{ 'opacity-50 pointer-events-none': !editingPostPagination.nextPageUrl, 'animate-pulse pointer-events-none': currentTasks.has('fetchingPost') }"
                    >
                        <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                    </a>
                </div>
            </div>

            <div
                class="px-6 py-4"
                :class="{ 'animate-pulse pointer-events-none': currentTasks.has('fetchingPost') }"
                x-data="{
                    videoExtensions: ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'],
                    isVideo(url) {
                        if (typeof url !== 'string') return false;
                        const clean = url.split('?')[0].split('#')[0].toLowerCase();
                        return this.videoExtensions.some(ext => clean.endsWith('.' + ext));
                    },
                    get videoStatus() {
                        return editingPost?.video_status ?? 'none';
                    },
                    get videoPreviews() {
                        const direct = Array.isArray(editingPost?.video_urls) ? editingPost.video_urls.filter(Boolean) : [];
                        if (direct.length) {
                            return direct;
                        }
                        const mediaVideos = Array.isArray(editingPost?.media_urls)
                            ? editingPost.media_urls.filter(url => this.isVideo(url))
                            : [];

                        return mediaVideos;
                    },
                    get imagePreviews() {
                        const media = Array.isArray(editingPost?.media_urls) ? editingPost.media_urls.filter(Boolean) : [];

                        if (this.videoPreviews.length) {
                            return media.filter(url => !this.isVideo(url));
                        }

                        return media;
                    },
                }"
            >
                <template x-if="videoPreviews.length">
                    <figure
                        class="relative z-1 mb-5 grid w-full grid-cols-1 place-items-center overflow-hidden rounded-lg shadow-sm shadow-black/5"
                        x-data="{
                            get totalSlides() {
                                return videoPreviews?.length ?? 0;
                            },
                            currentIndex: 0,
                            prev() {
                                this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
                            },
                            next() {
                                this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
                            }
                        }"
                    >
                        <template x-for="(video, index) in videoPreviews">
                            <video
                                class="col-start-1 col-end-1 row-start-1 row-end-1 h-auto w-full"
                                x-transition.opacity
                                x-show="currentIndex == index"
                                controls
                                playsinline
                                preload="metadata"
                                :src="video"
                            ></video>
                        </template>
                        <template x-if="videoPreviews.length >= 2">
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
                                    <template x-for="(video_url, index) in videoPreviews">
                                        <button
                                            class="relative inline-flex size-2 rounded-full bg-white/50 transition before:absolute before:-inset-x-1 before:-inset-y-1 hover:bg-white/80 active:scale-95 [&.active]:w-4 [&.active]:bg-white"
                                            @click.prevent="currentIndex = index"
                                            :class="{ active: currentIndex === index }"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="absolute top-4 left-4 inline-flex items-center gap-2 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">
                            <x-tabler-device-tv class="size-4" />
                            <span>@lang('Video')</span>
                        </div>
                    </figure>
                </template>

                <template x-if="!videoPreviews.length && ['pending', 'generating'].includes(videoStatus)">
                    @include('social-media-agent::components.video-generation-loader', ['class' => 'mb-5'])
                </template>

                <template x-if="!videoPreviews.length && videoStatus === 'failed'">
                    <div class="mb-4 flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-2xs text-destructive">
                        <x-tabler-alert-triangle class="size-4 shrink-0" />
                        <div>
                            <p class="m-0 font-semibold">@lang('Video generation failed')</p>
                            <p class="m-0 text-3xs opacity-80">@lang('Try regenerating from the chat or upload a video manually.')</p>
                        </div>
                    </div>
                </template>

                <template x-if="imagePreviews.length">
                    <figure
                        class="relative z-1 mb-5 grid w-full grid-cols-1 place-items-center overflow-hidden rounded-lg shadow-sm shadow-black/5"
                        x-data="{
                            get totalSlides() {
                                return imagePreviews?.length ?? 0
                            },
                            currentIndex: 0,
                            prev() {
                                this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
                            },
                            next() {
                                this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
                            }
                        }"
                    >
                        <template x-for="(media, index) in imagePreviews">
                            <img
                                class="col-start-1 col-end-1 row-start-1 row-end-1 h-auto w-full"
                                x-transition.opacity
                                x-show="currentIndex == index"
                                :src="media"
                            >
                        </template>
                        <template x-if="imagePreviews.length >= 2">
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
                                    <template x-for="(media_url, index) in imagePreviews">
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

                <div class="space-y-2">
                    <template x-if="!isVideoPost(editingPost)">
                        <div
                            class="relative flex flex-col items-center gap-1 rounded-xl bg-foreground/5 p-8"
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
                                        editingPost.media_urls = data.items;
                                    } else {
                                        console.error('Upload failed:', data.message);
                                    }
                                } catch (error) {
                                    console.error('Upload error:', error);
                                } finally {
                                    this.uploading = false;
                                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                                }
                            },
                            init() {
                                this.$watch('editingPost.id', () => {
                                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                                });
                            }
                        }"
                        >
                            <div
                                class="flex w-full gap-7"
                                :class="{ 'opacity-50': uploading }"
                            >
                                <x-tabler-loader-2
                                    class="size-6 shrink-0 animate-spin"
                                    x-show="uploading"
                                    x-cloak
                                />
                                <svg
                                    class="shrink-0"
                                    width="27"
                                    height="22"
                                    viewBox="0 0 27 22"
                                    fill="currentColor"
                                    xmlns="http://www.w3.org/2000/svg"
                                    x-show="!uploading"
                                >
                                    <path
                                        d="M17.3082 12.422C17.3082 14.7497 15.4147 16.6433 13.087 16.6433C10.7593 16.6433 8.86648 14.7497 8.86648 12.422C8.86648 10.0944 10.7593 8.20081 13.087 8.20081C15.4147 8.20081 17.3082 10.0951 17.3082 12.422ZM26.174 6.42809V18.4175C26.174 20.0158 24.8781 21.3117 23.2798 21.3117H2.89423C1.29589 21.3117 0 20.0158 0 18.4175V6.42809C0 4.82975 1.29589 3.53386 2.89423 3.53386H6.45414V2.53245C6.45414 1.13382 7.58723 0 8.9866 0H17.1874C18.5868 0 19.7199 1.13382 19.7199 2.53245V3.53314H23.2798C24.8781 3.53386 26.174 4.82975 26.174 6.42809ZM19.4789 12.422C19.4789 8.8976 16.6115 6.03014 13.087 6.03014C9.56327 6.03014 6.69581 8.8976 6.69581 12.422C6.69581 15.9465 9.56327 18.814 13.087 18.814C16.6115 18.814 19.4789 15.9465 19.4789 12.422Z"
                                    />
                                </svg>
                                <span
                                    class="text-sm font-medium"
                                    x-text="uploading ? '{{ __('Uploading...') }}' : '{{ __('Select Image') }}'"
                                ></span>
                            </div>
                            <input
                                class="absolute inset-0 z-1 cursor-pointer opacity-0"
                                type="file"
                                multiple
                                accept="image/*"
                                x-ref="fileInput"
                                @change.prevent="handleFiles($event);"
                                :disabled="uploading"
                            >
                        </div>
                    </template>
                    <template x-if="isVideoPost(editingPost)">
                        <div class="rounded-xl border border-dashed border-foreground/10 bg-foreground/5 px-4 py-3 text-3xs text-heading-foreground/70">
                            @lang('Video posts manage their media automatically. Uploads are disabled while the clip is rendering or attached.')
                        </div>
                    </template>

                    <div
                        class="relative select-none"
                        x-data="{ dropdownOpen: false }"
                        @click.outside="dropdownOpen = false"
                    >
                        <div
                            class="flex min-h-[72px] flex-col gap-2.5 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-3 transition-all"
                            :class="{ 'rounded-b-none border-foreground/5': dropdownOpen }"
                            @click.prevent="dropdownOpen = !dropdownOpen"
                        >
                            <p class="m-0 w-full text-2xs font-medium opacity-50">
                                @lang('Account')
                            </p>
                            <p
                                class="m-0"
                                x-show="!editingPost.platform_id"
                            >
                                @lang('Choose an Account')
                            </p>
                            <template x-if="editingPost.platform_id">
                                <div class="flex items-center gap-2 self-start rounded-full border bg-background px-2.5 py-[9px] text-2xs font-medium transition">
                                    <figure class="w-5 transition-all group-hover:scale-125">
                                        <img
                                            class="h-auto w-full"
                                            :class="{ 'dark:hidden': getPlatformById(editingPost.platform_id).image_dark_version }"
                                            :src="getPlatformById(editingPost.platform_id).image"
                                            :alt="getPlatformById(editingPost.platform_id).name"
                                        />
                                        <template x-if="getPlatformById(editingPost.platform_id).image_dark_version">
                                            <img
                                                class="hidden h-auto w-full dark:block"
                                                :src="getPlatformById(editingPost.platform_id).image_dark_version"
                                                :alt="getPlatformById(editingPost.platform_id).name"
                                            />
                                        </template>
                                    </figure>
                                    <span x-text="getPlatformById(editingPost.platform_id).credentials?.username ?? '{{ __('Unknown') }}'"></span>
                                    <button
                                        class="inline-grid size-[17px] place-items-center rounded-full border p-0 transition hover:scale-110 hover:border-red-500 hover:bg-red-500 hover:text-white"
                                        @click.prevent.stop="editingPost.platform_id = ''"
                                        x-show="!isVideoPost(editingPost)"
                                        x-cloak
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
                                @php
                                    $image = 'vendor/social-media/icons/' . $platform->platform . '.svg';
                                    $image_dark_version = 'vendor/social-media/icons/' . $platform->platform . '-light.svg';
                                    $darkImageExists = file_exists(public_path($image_dark_version));
                                @endphp

                                <div
                                    class="flex cursor-pointer items-center gap-2 rounded-full bg-background px-3.5 py-[9px] text-2xs font-medium transition hover:-translate-y-0.5 hover:scale-105 hover:shadow-lg hover:shadow-black/5"
                                    @click.prevent="editingPost.platform_id = {{ $platform->id }}; dropdownOpen = false;"
                                    x-show="canUsePlatform(editingPost, '{{ $platform->platform }}') && editingPost.platform_id != {{ $platform->id }}"
                                    x-cloak
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
                            <template x-if="isVideoPost(editingPost)">
                                <p class="m-0 w-full text-[11px] text-heading-foreground/60">
                                    @lang('Video posts can only publish to YouTube or YouTube Shorts accounts.')
                                </p>
                            </template>
                        </div>
                    </div>

                    <div
                        class="relative select-none [&.active_.air-datepicker]:block [&_.air-datepicker]:absolute [&_.air-datepicker]:start-0 [&_.air-datepicker]:top-full [&_.air-datepicker]:z-10 [&_.air-datepicker]:hidden [&_.air-datepicker]:w-full [&_.air-datepicker]:rounded-t-none [&_.air-datepicker]:border-foreground/5"
                        @click.outside="pickerOpen = false"
                        :class="{ active: pickerOpen }"
                        x-data="{
                            pickerOpen: false,
                            timepicker: null,

                            init() {
                                this.$watch('editingPost.id', () => {
                                    this.timepicker?.selectDate([this.getDatepickerTime()], { updateTime: true });
                                });

                                this.timepicker = new AirDatepicker(this.$refs.timePicker, {
                                    locale: defaultLocale,
                                    selectedDates: [this.getDatepickerTime()],
                                    timepicker: true,
                                    inline: true,
                                    dateFormat: 'yyyy-MM-dd',
                                    timeFormat: 'HH:mm',
                                    onSelect: ({ formattedDate }) => {
                                        this.editingPost.scheduled_at = formattedDate;
                                    }
                                });
                            },

                            getDatepickerTime() {
                                const time = this.editingPost.published_at ?? this.editingPost.scheduled_at ?? new Date();

                                return (new Date(new Date(time).toUTCString()).getTime() + new Date().getTimezoneOffset() * 60000);
                            },
                        }"
                    >
                        <div
                            class="flex min-h-[72px] flex-col gap-2.5 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-3 transition-all"
                            :class="{ 'rounded-b-none border-foreground/5': pickerOpen }"
                        >
                            <p
                                class="m-0 flex w-full items-center gap-1 text-2xs font-medium opacity-50"
                                @click="pickerOpen = !pickerOpen"
                            >
                                @lang('Date and Time')
                                <span
                                    class="lqd-tooltip-container group relative inline-flex cursor-default items-center before:hidden [&:hover>.lqd-tooltip-content]:visible [&:hover>.lqd-tooltip-content]:translate-y-0 [&:hover>.lqd-tooltip-content]:opacity-100"
                                    @click.stop
                                >
                                    <span class="lqd-tooltip-icon opacity-60">
                                        <x-tabler-clock class="size-4" />
                                    </span>
                                    <span
                                        class="lqd-tooltip-content invisible bottom-full start-1/2 z-50 mb-2 -translate-x-1/2 translate-y-1 rounded-xl bg-background/80 px-3 py-2 text-center text-[11px] leading-normal text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm backdrop-saturate-150 transition-all before:absolute before:inset-x-0 before:-top-3 before:h-3"
                                    >
                                        {{ __('Timezone: :tz', ['tz' => config('app.timezone')]) }}
                                    </span>
                                </span>
                            </p>
                            <input
                                class="m-0 border-none bg-transparent bg-none p-0 focus-visible:outline-none"
                                type="text"
                                :value="'{{ __('Choose a Time') }}'"
                                x-ref="timePicker"
                                readonly
                                @click="pickerOpen = !pickerOpen"
                            ></input>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="flex min-h-[72px] flex-col gap-2.5 rounded-[10px] border border-transparent bg-foreground/5 px-4 py-3 transition-all">
                            <p class="m-0 w-full text-2xs font-medium opacity-50">
                                @lang('Post Description')
                            </p>
                            <textarea
                                class="m-0 border-none bg-transparent bg-none text-foreground focus-visible:outline-none"
                                rows="7"
                                x-model="editingPost.content"
                            ></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-2 pt-3.5 md:grid-cols-2">
                        <x-button
                            class="w-full"
                            variant="outline"
                            type="button"
                            @click.prevent="rejectPost(editingPost.id)"
                            ::disabled="currentTasks.has('updatePost') || currentTasks.has('rejectPost')"
                        >
                            <x-tabler-loader-2
                                class="size-4 animate-spin"
                                x-cloak
                                x-show="currentTasks.has('rejectPost')"
                            />
                            @lang('Reject')
                        </x-button>
                        <x-button
                            class="w-full"
                            variant="primary"
                            type="button"
                            @click.prevent="await updatePost(editingPost.id); autoNavigateOnPostUpdates && openEditSidedrawer({query: 'status=draft'})"
                            ::disabled="currentTasks.has('updatePost') || currentTasks.has('rejectPost')"
                        >
                            <x-tabler-loader-2
                                class="size-4 animate-spin"
                                x-cloak
                                x-show="currentTasks.has('updatePost')"
                            />
                            @lang('Accept')
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</x-sidedrawer>
