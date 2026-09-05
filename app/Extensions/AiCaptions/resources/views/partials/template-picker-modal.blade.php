<template x-teleport="body">
    <div
        class="lqd-captions-template-picker group/modal invisible fixed start-0 top-0 z-[999] grid h-screen w-screen place-items-center px-5 opacity-0 [&.is-active]:visible [&.is-active]:opacity-100"
        id="ai-captions-template-picker"
        x-data="aiCaptionsTemplatePicker({ templates: {{ Illuminate\Support\Js::from($templates) }} })"
        :class="{ 'is-active': open }"
        @keyup.escape.window="open = false"
    >
        <div
            class="absolute start-0 top-0 z-0 h-screen w-screen bg-black/50 opacity-0 backdrop-blur transition-opacity group-[&.is-active]/modal:opacity-100"
            @click="open = false"
        ></div>

        <div class="relative z-10 w-[min(1100px,100%)]">
            <a
                class="absolute -end-4 -top-4 z-10 flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-sm transition-all hover:bg-red-500 hover:text-white"
                @click.prevent="open = false"
                href="#"
            >
                <x-tabler-x class="size-4" />
            </a>

            <div class="relative max-h-[calc(100vh-80px)] overflow-y-auto rounded-xl bg-background p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="m-0 text-base font-semibold">{{ __('Pick a caption template') }}</h3>
                    <p class="m-0 text-xs opacity-60">{{ __('Previews play automatically.') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <template
                        x-for="template in templates"
                        :key="template.id"
                    >
                        <button
                            class="group/template relative overflow-hidden rounded-md border border-card-border bg-card-background text-start transition hover:border-primary [&.active]:border-primary [&.active]:ring-2 [&.active]:ring-primary"
                            type="button"
                            :class="{ 'active': selectedId === template.id }"
                            @click="select(template)"
                        >
                            <div class="relative aspect-video overflow-hidden bg-neutral-900">
                                <video
                                    class="pointer-events-none size-full object-contain"
                                    x-show="!!template.preview_url"
                                    :data-src="template.preview_url || ''"
                                    x-intersect:enter="loadAndPlay($el)"
                                    x-intersect:leave="pause($el)"
                                    muted
                                    loop
                                    playsinline
                                    preload="none"
                                ></video>
                                <div
                                    class="pointer-events-none absolute inset-0 flex items-center justify-center px-3"
                                    x-show="!template.preview_url"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-2xs font-semibold text-white backdrop-blur-sm"
                                        x-text="template.preview_text || template.name"
                                    ></span>
                                </div>
                                <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3 text-white">
                                    <p
                                        class="m-0 text-xs font-semibold"
                                        x-text="template.name"
                                    ></p>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiCaptionsTemplatePicker', (initial) => ({
                templates: initial.templates || [],
                open: false,
                selectedId: '',

                loadAndPlay(v) {
                    if (!v) return;
                    const src = v.dataset.src;
                    if (!src) return;
                    if (v.getAttribute('src') !== src) {
                        v.setAttribute('src', src);
                        v.load();
                    }
                    v._playPromise = v.play().catch((err) => {
                        if (err && err.name !== 'AbortError') {
                            console.warn('caption preview play failed', err);
                        }
                    });
                },

                async pause(v) {
                    if (!v) return;
                    if (v._playPromise) {
                        try { await v._playPromise; } catch (e) {}
                    }
                    v.pause();
                },

                select(template) {
                    this.selectedId = template.id;

                    const form = document.getElementById('ai-captions-form');
                    if (form) {
                        const formData = Alpine.$data(form);
                        if (formData && typeof formData.applyTemplate === 'function') {
                            formData.applyTemplate(template);
                        }
                    }

                    this.open = false;
                },
            }));
        });
    </script>
@endpush
