@php
    $defaultTemplateId = $templates[0]['id'] ?? '';
    $defaultTemplateName = $templates[0]['name'] ?? '';
    $defaultTemplatePreview = $templates[0]['preview_text'] ?? __('did you hear about');
    $defaultTemplatePreviewUrl = $templates[0]['preview_url'] ?? '';
@endphp

<p class="mb-5 border-b py-2.5 text-[12px] font-semibold transition">
    {{ __('Video') }}
</p>

<x-card
    class:body="p-5"
    class="bg-transparent"
>
    <form
        class="flex flex-col gap-5"
        id="ai-captions-form"
        method="post"
        action="{{ route('dashboard.user.ai-captions.store') }}"
        enctype="multipart/form-data"
        x-data="aiCaptionsForm({
            templates: {{ Illuminate\Support\Js::from($templates) }},
            defaultTemplateId: {{ Illuminate\Support\Js::from($defaultTemplateId) }},
            defaultTemplateName: {{ Illuminate\Support\Js::from($defaultTemplateName) }},
            defaultTemplatePreview: {{ Illuminate\Support\Js::from($defaultTemplatePreview) }},
            defaultTemplatePreviewUrl: {{ Illuminate\Support\Js::from($defaultTemplatePreviewUrl) }},
            isDemo: {{ Illuminate\Support\Js::from($isDemo) }},
        })"
        @submit.prevent="handleSubmit($event)"
    >
        @csrf

        {{-- Select Video --}}
        <div>
            <label
                class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-foreground transition"
                for="ai-captions-video-file"
            >
                <span>{{ __('Select Video') }}</span>
                <span class="lqd-tooltip-container group relative inline-flex cursor-default before:absolute before:-start-1.5 before:-top-1.5 before:h-7 before:w-7 [&:hover>.lqd-tooltip-content]:visible [&:hover>.lqd-tooltip-content]:translate-y-0 [&:hover>.lqd-tooltip-content]:opacity-100">
                    <span class="lqd-tooltip-icon opacity-40">
                        <x-tabler-info-circle-filled class="size-4" />
                    </span>
                    <span class="lqd-tooltip-content invisible absolute bottom-full start-1/2 z-50 mb-3 min-w-64 -translate-x-1/2 translate-y-1 rounded-xl bg-background/80 px-4 py-3 text-center text-xs leading-normal text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm backdrop-saturate-150 transition-all before:absolute before:inset-x-0 before:-top-3 before:h-3">
                        {{ __('Upload a portrait 9:16 video up to 50MB and 5 minutes long.') }}
                    </span>
                </span>
            </label>

            <label
                class="lqd-filepicker-label block cursor-pointer rounded-input border border-dashed px-4 py-5 text-center transition hover:border-primary/50 hover:bg-primary/[3%]"
                for="ai-captions-video-file"
                @drop="dropHandler($event)"
                @dragover.prevent
            >
                <x-tabler-circle-arrow-up class="mx-auto mb-2.5 size-6 opacity-25" />
                <p class="mb-2 text-xs font-medium">
                    <span x-show="!fileName">{{ __('Upload Video') }}</span>
                    <span
                        class="text-primary"
                        x-show="fileName"
                        x-text="fileName"
                        x-cloak
                    ></span>
                </p>
                <p class="m-0 text-4xs leading-relaxed opacity-50">
                    {{ __('Max File Size: 50mb') }}<br>
                    {{ __('Max Duration: 5m') }}<br>
                    {{ __('Aspect Ratio: 9:16 (Portrait)') }}
                </p>
                <input
                    class="hidden"
                    id="ai-captions-video-file"
                    type="file"
                    name="video_file"
                    accept="video/mp4,video/quicktime"
                    @change="onFileChange($event)"
                />
            </label>
        </div>

        {{-- Select Caption Style --}}
        <div>
            <label class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-foreground transition">
                <span>{{ __('Select Caption Style') }}</span>
                <span class="lqd-tooltip-container group relative inline-flex cursor-default before:absolute before:-start-1.5 before:-top-1.5 before:h-7 before:w-7 [&:hover>.lqd-tooltip-content]:visible [&:hover>.lqd-tooltip-content]:translate-y-0 [&:hover>.lqd-tooltip-content]:opacity-100">
                    <span class="lqd-tooltip-icon opacity-40">
                        <x-tabler-info-circle-filled class="size-4" />
                    </span>
                    <span class="lqd-tooltip-content invisible absolute bottom-full start-1/2 z-50 mb-3 min-w-64 -translate-x-1/2 translate-y-1 rounded-xl bg-background/80 px-4 py-3 text-center text-xs leading-normal text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm backdrop-saturate-150 transition-all before:absolute before:inset-x-0 before:-top-3 before:h-3">
                        {{ __('Pick the animated caption style applied to your video.') }}
                    </span>
                </span>
            </label>

            <input
                type="hidden"
                name="template_id"
                x-model="templateId"
            />

            <button
                class="lqd-caption-style-trigger group block w-full overflow-hidden rounded-input transition hover:opacity-90"
                type="button"
                @click.prevent="openTemplatePicker()"
            >
                <span class="relative flex h-24 items-center justify-center overflow-hidden bg-neutral-900 px-4">
                    <video
                        class="pointer-events-none absolute inset-0 size-full object-cover"
                        x-show="!!templatePreviewUrl"
                        :src="templatePreviewUrl || ''"
                        x-ref="previewVideo"
                        muted
                        loop
                        autoplay
                        playsinline
                        preload="auto"
                    ></video>
                    <span
                        class="relative inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-2xs font-semibold text-white backdrop-blur-sm"
                        x-show="!templatePreviewUrl"
                        x-text="templatePreview"
                    ></span>
                </span>
            </button>

            <p
                class="mt-2 text-2xs font-medium text-foreground"
                x-text="templateName"
            ></p>
        </div>

        @if (\App\Helpers\Classes\Helper::appIsDemo())
            <x-button
                class="w-full py-[18px]"
                onclick="toastr.info('{{ __('This feature is disabled in the demo version.') }}')"
                size="lg"
                type="button"
            >
                {{ __('Generate') }}
            </x-button>
        @else
            <x-button
                class="w-full py-[18px]"
                size="lg"
                type="submit"
                ::disabled="submitting || !templateId"
            >
                <span x-show="!submitting">{{ __('Generate') }}</span>
                <span
                    class="inline-flex items-center gap-2"
                    x-show="submitting"
                    x-cloak
                >
                    <span class="inline-block size-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                    {{ __('Generating...') }}
                </span>
            </x-button>
        @endif

        <div x-init="$nextTick(() => $dispatch('generator-changed', { generator: 'ai-captions', quantity: 1 }))">
            <x-cost-preview class="w-full justify-end" />
        </div>
    </form>
</x-card>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiCaptionsForm', (initial) => ({
                templates: initial.templates || [],
                templateId: initial.defaultTemplateId || '',
                templateName: initial.defaultTemplateName || '',
                templatePreview: initial.defaultTemplatePreview || '{{ __('did you hear about') }}',
                templatePreviewUrl: initial.defaultTemplatePreviewUrl || '',
                fileName: '',
                submitting: false,
                isDemo: initial.isDemo,
                videoDurationSeconds: 0,

                async onFileChange(event) {
                    const input = event.target;
                    const file = input.files && input.files[0];
                    if (!file) {
                        this.fileName = '';
                        return;
                    }
                    if (!(await this.validateVideoFile(file))) {
                        input.value = '';
                        this.fileName = '';
                        return;
                    }
                    this.fileName = file.name;
                },

                async dropHandler(event) {
                    event.preventDefault();
                    const file = event.dataTransfer.files && event.dataTransfer.files[0];
                    if (!file) return;
                    if (!(await this.validateVideoFile(file))) {
                        return;
                    }
                    const input = document.getElementById('ai-captions-video-file');
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    this.fileName = file.name;
                },

                validateVideoFile(file) {
                    return new Promise((resolve) => {
                        const allowedTypes = ['video/mp4', 'video/quicktime'];
                        const allowedExt = /\.(mp4|mov)$/i;
                        if (file.type ? !allowedTypes.includes(file.type) : !allowedExt.test(file.name)) {
                            toastr.error('{{ __('Video must be an MP4 or MOV file.') }}');
                            resolve(false);
                            return;
                        }
                        if (file.size > 50 * 1024 * 1024) {
                            toastr.error('{{ __('The video file must not exceed 50MB.') }}');
                            resolve(false);
                            return;
                        }

                        const url = URL.createObjectURL(file);
                        const video = document.createElement('video');
                        video.preload = 'metadata';
                        video.muted = true;
                        video.playsInline = true;

                        let settled = false;
                        const finish = (result) => {
                            if (settled) return;
                            settled = true;
                            try { URL.revokeObjectURL(url); } catch (e) {}
                            video.onloadedmetadata = null;
                            video.onerror = null;
                            video.src = '';
                            resolve(result);
                        };

                        video.onloadedmetadata = () => {
                            const duration = video.duration;
                            const w = video.videoWidth;
                            const h = video.videoHeight;

                            if (isFinite(duration) && duration > 300) {
                                toastr.error('{{ __('Video must be 5 minutes or shorter.') }}');
                                finish(false);
                                return;
                            }
                            if (w && h) {
                                const ratio = w / h;
                                const target = 9 / 16;
                                if (Math.abs(ratio - target) > 0.05) {
                                    toastr.error('{{ __('Video must be portrait 9:16 aspect ratio.') }}');
                                    finish(false);
                                    return;
                                }
                            }
                            if (isFinite(duration)) {
                                this.videoDurationSeconds = Math.round(duration);
                                const minutes = Math.max(1, Math.ceil(duration / 60));
                                this.$dispatch('generator-changed', { generator: 'ai-captions', quantity: minutes, _force: Date.now() });
                            }
                            finish(true);
                        };

                        // Some valid MP4/MOV files (e.g. HEVC) cannot be previewed
                        // by the browser. Let the server validate in that case
                        // rather than blocking the upload.
                        video.onerror = () => {
                            finish(true);
                        };

                        // Safety net in case neither event fires.
                        setTimeout(() => finish(true), 4000);

                        video.src = url;
                    });
                },

                openTemplatePicker() {
                    const modal = document.getElementById('ai-captions-template-picker');
                    if (modal && typeof Alpine !== 'undefined') {
                        const data = Alpine.$data(modal);
                        if (data) {
                            data.open = true;
                        }
                    }
                },

                applyTemplate(template) {
                    this.templateId = template.id;
                    this.templateName = template.name;
                    this.templatePreview = template.preview_text || this.templatePreview;
                    this.templatePreviewUrl = template.preview_url || '';
                    this.$nextTick(() => {
                        const v = this.$refs.previewVideo;
                        if (v && this.templatePreviewUrl) {
                            v.load();
                            const p = v.play();
                            if (p && typeof p.catch === 'function') {
                                p.catch(() => {});
                            }
                        }
                    });
                },

                async handleSubmit(event) {
                    if (this.isDemo) {
                        toastr.error('{{ __('This feature is disabled in demo mode.') }}');
                        return;
                    }

                    if (!this.templateId) {
                        toastr.error('{{ __('Please pick a caption style.') }}');
                        return;
                    }

                    if (!this.fileName) {
                        toastr.error('{{ __('Please upload a video.') }}');
                        return;
                    }

                    const form = event.target;
                    const formData = new FormData(form);

                    this.submitting = true;

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: formData,
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            toastr.success(data.message);
                            window.dispatchEvent(new CustomEvent('caption-generated', {
                                detail: { video_id: data.video_id },
                            }));
                            form.reset();
                            this.fileName = '';
                        } else {
                            toastr.error(data.message || '{{ __('Submission failed.') }}');
                        }
                    } catch (err) {
                        toastr.error('{{ __('An error occurred.') }}');
                    } finally {
                        this.submitting = false;
                    }
                },
            }));
        });
    </script>
@endpush
