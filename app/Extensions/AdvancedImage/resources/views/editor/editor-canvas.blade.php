<div
    class="lqd-adv-img-editor-canvas-wrap mt-[--header-h] flex w-full grow overflow-y-auto px-5 transition-all md:pe-[--sidebar-w] md:ps-28 md:group-[&.sidebar-collapsed]/editor:pe-28"
    x-ref="editorCanvasWrap"
>
    <div
        class="lqd-adv-img-editor-canvas relative m-auto grid max-w-[720px] grow translate-y-[--zoom-offset] scale-[--zoom-level] py-6 motion-duration-150 [--zoom-offset:0px] group-[&.active]/editor:motion-scale-in-[0.975] group-[&.active]/editor:motion-opacity-in-0 group-[&.active]/editor:motion-delay-100"
        x-ref="editorCanvas"
    >
        <figure
            class="lqd-adv-img-editor-canvas-fig col-start-1 col-end-1 row-start-1 row-end-1 w-full grow overflow-hidden rounded-xl"
            x-show="editingImage?.output && selectedTool !== 'sketch_to_image' && !uploadingImages.length"
            x-cloak
            x-transition
        >
            <img
                class="h-auto w-full"
                x-show="editingImage?.output"
                x-transition
                :src="editingImage?.output"
                x-ref="editorImagePreview"
                @load="makeCanvasEditable"
            >
        </figure>

        <div
            class="group/drop-area relative col-start-1 col-end-1 row-start-1 row-end-1 grid h-[min(570px,90vh)] w-full grow place-items-center overflow-y-auto rounded-3xl border-2 border-dashed py-5 transition-colors [&.drag-over]:border-heading-foreground"
            x-show="uploadingImages.length || (!editingImage?.output && !busy && selectedTool !== 'sketch_to_image')"
            x-ref="dropArea"
            @dragover.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleFileChange"
        >
            <div class="mx-auto flex w-[min(100%,400px)] flex-col items-center justify-center gap-4 text-center">
                <div x-show="!uploadingImages.length">
                    <div class="mx-auto mb-4 inline-grid w-12 place-content-center">
                        {{-- blade-formatter-disable --}}
                        <svg class="col-start-1 col-end-1 row-start-1 row-end-1 h-auto w-full text-heading-foreground/20 transition-all group-[&.drag-over]/drop-area:scale-50 group-[&.drag-over]/drop-area:opacity-0" width="48" height="49" viewBox="0 0 48 49" fill="currentColor" xmlns="http://www.w3.org/2000/svg" > <path d="M40.9355 41.3123C36.2903 45.9574 30.6452 48.28 24 48.28C17.3548 48.28 11.6774 45.9574 6.96774 41.3123C2.32258 36.6026 0 30.9252 0 24.28C0 17.6348 2.32258 11.9897 6.96774 7.34451C11.6774 2.63484 17.3548 0.279999 24 0.279999C30.6452 0.279999 36.2903 2.63484 40.9355 7.34451C45.6452 11.9897 48 17.6348 48 24.28C48 30.9252 45.6452 36.6026 40.9355 41.3123ZM37.6452 10.6348C33.9032 6.82839 29.3548 4.92516 24 4.92516C18.6452 4.92516 14.0645 6.82839 10.2581 10.6348C6.51613 14.3768 4.64516 18.9252 4.64516 24.28C4.64516 29.6348 6.51613 34.2155 10.2581 38.0219C14.0645 41.7639 18.6452 43.6348 24 43.6348C29.3548 43.6348 33.9032 41.7639 37.6452 38.0219C41.4516 34.2155 43.3548 29.6348 43.3548 24.28C43.3548 18.9252 41.4516 14.3768 37.6452 10.6348ZM25.9355 36.6671H22.0645C21.2903 36.6671 20.9032 36.28 20.9032 35.5058V27.28C20.9032 25.6231 19.5601 24.28 17.9032 24.28H14.4194C13.9032 24.28 13.5484 24.0542 13.3548 23.6026C13.1613 23.0865 13.2258 22.6671 13.5484 22.3445L23.2258 12.6671C23.7419 12.151 24.2581 12.151 24.7742 12.6671L34.4516 22.3445C34.7742 22.6671 34.8387 23.0865 34.6452 23.6026C34.4516 24.0542 34.0968 24.28 33.5806 24.28H30.0968C28.4399 24.28 27.0968 25.6231 27.0968 27.28V35.5058C27.0968 36.28 26.7097 36.6671 25.9355 36.6671Z" /> </svg> <svg class="col-start-1 col-end-1 row-start-1 row-end-1 h-auto w-full scale-50 text-heading-foreground opacity-0 transition-all group-[&.drag-over]/drop-area:scale-100 group-[&.drag-over]/drop-area:opacity-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="24" height="24" stroke-width="1.5" > <path d="M19 11v-2a2 2 0 0 0 -2 -2h-8a2 2 0 0 0 -2 2v8a2 2 0 0 0 2 2h2"></path> <path d="M13 13l9 3l-4 2l-2 4l-3 -9"></path> <path d="M3 3l0 .01"></path> <path d="M7 3l0 .01"></path> <path d="M11 3l0 .01"></path> <path d="M15 3l0 .01"></path> <path d="M3 7l0 .01"></path> <path d="M3 11l0 .01"></path> <path d="M3 15l0 .01"></path> </svg>
						{{-- blade-formatter-enable --}}
                    </div>
                    <h4 class="text-base">
                        <span x-show="!selectedToolSupportMultiImagesUpload()">
                            @lang('Drag and Drop an Image')
                        </span>
                        <span x-show="selectedToolSupportMultiImagesUpload()">
                            @lang('Drag and Drop Multiple Images')
                        </span>
                    </h4>

                    <div class="mx-auto flex w-3/4 items-center gap-7 text-2xs font-medium text-heading-foreground">
                        <span class="inline-flex h-px grow bg-heading-foreground/5"></span>
                        @lang('or')
                        <span class="inline-flex h-px grow bg-heading-foreground/5"></span>
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                    x-show="uploadingImages.length"
                >
                    <template x-for="image in uploadingImages">
                        <div class="flex flex-col items-center justify-center gap-2 only-of-type:col-span-full">
                            <img
                                class="aspect-video h-auto w-full rounded-lg object-cover object-center shadow-sm"
                                :src="image.src"
                            >
                            <p
                                class="m-0 text-3xs font-medium opacity-60"
                                x-text="image.name"
                            ></p>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-2">
                    <x-button
                        class="relative z-3 px-8 py-3 text-heading-foreground outline-heading-foreground/5 transition-all hover:scale-105 hover:bg-heading-foreground hover:text-heading-background"
                        variant="outline"
                        type="button"
                        @click.prevent="$refs.editorFileInput.click()"
                    >
                        <span x-show="!uploadingImages.length">
                            @lang('Browse Files')
                        </span>
                        <span x-show="uploadingImages.length">
                            @lang('Add More')
                        </span>
                    </x-button>
                    <x-button
                        class="relative z-3 px-8 py-3 transition-all hover:scale-105"
                        variant="danger"
                        type="button"
                        x-show="uploadingImages.length"
                        @click.prevent="clearImageInputs"
                    >
                        @lang('Clear Files')
                    </x-button>
                </div>

                <p class="m-0 text-3xs font-medium opacity-60">
                    <span
                        x-text="uploadingImages.map(img => img.name ?? '').join(', ')"
                        x-show="uploadingImages.length"
                        x-cloak
                    ></span>
                    <span x-show="!uploadingImages.length">
                        {{ __('PNG or JPG') }}
                    </span>
                </p>

                <input
                    class="absolute inset-0 z-2 opacity-0"
                    type="file"
                    accept="image/png, image/jpeg"
                    @change="handleFileChange"
                    :multiple="selectedToolSupportMultiImagesUpload()"
                    x-ref="editorFileInput"
                >
            </div>
        </div>

        <div
            class="lqd-adv-img-editor-busy-screen relative z-2 col-start-1 col-end-1 row-start-1 row-end-1 flex w-full grow flex-col items-center justify-center rounded-xl bg-background/90 py-5 text-center text-heading-foreground backdrop-blur-xl backdrop-saturate-[120%]"
            x-show="busy"
            x-cloak
            x-transition
        >
            <h4 class="mb-5 flex flex-col">
                @lang('Action in progress...')
                <small>This process might take a little while.</small>
            </h4>
            <x-tabler-loader-2 class="size-11 animate-spin" />
        </div>

        <div
            class="lqd-adv-img-editor-img-details relative z-2 col-start-1 col-end-1 row-start-1 row-end-1 flex w-full grow flex-col items-center justify-center rounded-xl bg-background/90 px-5 py-10 text-heading-foreground backdrop-blur-xl backdrop-saturate-[120%]"
            x-show="editingImage?.output && showImageDetails"
            x-cloak
            x-transition
        >
            <x-button
                class="absolute end-6 top-6 z-10 size-[34px] border-heading-foreground/10"
                variant="outline"
                hover-variant="danger"
                size="none"
                @click.prevent="showImageDetails = false"
            >
                <x-tabler-x class="size-4" />
            </x-button>

            <div class="mx-auto w-[min(100%,380px)]">
                <template>
                    <h4
                        class="mb-4"
                        x-text="editingImage?.input"
                    ></h4>
                    <button
                        class="inline-flex items-center gap-3 rounded bg-background px-2.5 py-1 text-3xs font-semibold transition-all hover:bg-heading-foreground hover:text-heading-background"
                        @click.prevent="navigator.clipboard.writeText(editingImage?.input || ''); toastr.success('{{ __('Copied to clipboard!') }}')"
                    >
                        <x-tabler-copy class="size-4" />
                        @lang('Copy Prompt')
                    </button>
                </template>

                <div class="flex flex-col gap-5 pt-12 text-2xs font-medium">
                    <div class="flex justify-between gap-2">
                        <span>
                            @lang('Date'):
                        </span>
                        <span
                            class="opacity-30"
                            x-text="new Date(editingImage?.created_at || Date.now()).toLocaleString()"
                        ></span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span>
                            @lang('AI Model'):
                        </span>
                        <span
                            class="opacity-30"
                            x-text="editingImage?.payload?.image_generator || editingImage?.response"
                        ></span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span>
                            @lang('Art Style'):
                        </span>
                        <span
                            class="opacity-30"
                            x-text="editingImage?.payload?.image_style || '{{ __('None') }}'"
                        ></span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span>
                            @lang('Credit'):
                        </span>
                        <span
                            class="opacity-30"
                            x-text="editingImage?.credits"
                        ></span>
                    </div>
                </div>
            </div>
        </div>
        <canvas
            class="lqd-adv-img-editor-img-mask-canvas col-start-1 col-end-1 row-start-1 row-end-1 h-auto min-w-full max-w-full select-none rounded-xl mix-blend-lighten"
            :class="{ 'opacity-80': selectedTool !== 'sketch_to_image', 'border shadow': selectedTool === 'sketch_to_image' }"
            x-ref="editorMaskCanvas"
            :width="editingImageDimensions.width"
            :height="editingImageDimensions.height"
            @mousedown.prevent="startPainting"
            @mouseup.window.prevent="stopPainting"
            @mousemove.prevent="paint"
            @touchstart.prevent="startPainting"
            @touchend.window.passive="stopPainting"
            @touchmove.prevent="paint"
            x-cloak
            x-show="(((selectedTool === 'cleanup' || selectedTool === 'inpainting') && editingImage?.output)  || selectedTool === 'sketch_to_image') && ! ['nano-banana/edit', 'nano-banana-pro/edit', 'nano-banana-2/edit', 'xai/grok-imagine-image/edit'].includes(aiModel)"
        ></canvas>
    </div>
</div>
