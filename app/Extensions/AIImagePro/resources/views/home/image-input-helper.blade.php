{{-- Image Input Helper Component - UPDATED --}}
@php
    $contentManagerEnabled = \App\Helpers\Classes\MarketplaceHelper::isRegistered('content-manager') && setting('content_manager_enabled', '1') === '1';
@endphp
<div
    class="flex"
    x-data="aiImageProImageInputHelper"
    @keydown.escape.window="pickerModalOpen = false"
>
    {{-- Trigger Button & Image Thumbnails Container --}}
    <div class="inline-flex items-center gap-1.5">
        {{-- Image Thumbnails (shown outside modal) --}}
        <template x-for="(image, index) in uploadingImages">
            <div class="group relative inline-block size-10 shrink-0 max-sm:mt-2">
                <img
                    class="size-full rounded-full border border-border object-cover shadow-sm"
                    :src="image.src"
                    :alt="image.name"
                />
                {{-- Remove button on hover --}}
                <button
                    class="absolute -end-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-red-500 text-xs text-white transition-opacity hover:bg-red-600 sm:opacity-0 sm:group-hover:opacity-100"
                    type="button"
                    @click.prevent="removeImage(index)"
                    title="Remove image"
                >
                    <x-tabler-x class="size-3" />
                </button>
            </div>
        </template>

        {{-- Trigger Button --}}
        <x-button
            class="size-[38px] shrink-0 p-0 outline-2 outline-foreground/5 hover:bg-primary hover:text-primary-foreground hover:outline-primary"
            variant="outline"
            type="button"
            @click.prevent="{{ $contentManagerEnabled ? '$refs.formFileInput.click()' : 'pickerModalOpen = true' }}"
        >
            <x-tabler-plus class="size-4" />
        </x-button>
    </div>

    <template x-teleport="body">
        {{-- Modal --}}
        <div
            class="fixed inset-0 z-[999] flex flex-col items-center justify-center px-5"
            x-cloak
            x-show="pickerModalOpen"
            x-transition.opacity
        >
            {{-- Backdrop --}}
            <div
                class="absolute inset-0 z-0 bg-black/25 backdrop-blur"
                @click="pickerModalOpen = false"
            ></div>

            <div class="relative w-[min(735px,100%)]">
                {{-- Close Button --}}
                <x-button
                    class="absolute -end-4 -top-4 z-10 size-[34px] bg-background/90 backdrop-blur-md lg:-end-14 lg:top-0"
                    variant="outline"
                    hover-variant="danger"
                    size="none"
                    @click.prevent="pickerModalOpen = false"
                >
                    <x-tabler-x class="size-4" />
                </x-button>

                {{-- Modal Content --}}
                <div
                    class="group/drop-area relative z-2 grid h-[min(570px,90vh)] w-full place-items-center overflow-y-auto overscroll-contain rounded-[20px] bg-background/90 px-5 py-5 backdrop-blur-xl"
                    x-cloak
                    x-show="pickerModalOpen"
                    x-transition.scale
                    @dragover.prevent="handleDragOver"
                    @dragleave.prevent="handleDragLeave"
                    @drop.prevent="handleFileChange"
                    x-ref="dropArea"
                >

                    {{-- Content --}}
                    <div class="mx-auto flex w-[min(100%,400px)] flex-col items-center justify-center gap-4 text-center">
                        {{-- Empty State --}}
                        <div
                            class="w-full"
                            x-show="!uploadingImages.length"
                        >
                            <div class="grid place-content-center">
                                <div class="col-start-1 col-end-1 row-start-1 row-end-1">
                                    <div class="mx-auto mb-4 inline-grid w-12 place-content-center">
                                        {{-- Upload Icon --}}
                                        <svg
                                            class="col-start-1 col-end-1 row-start-1 row-end-1 h-auto w-full text-heading-foreground/20 transition-all group-[&.drag-over]/drop-area:scale-50 group-[&.drag-over]/drop-area:opacity-0"
                                            width="48"
                                            height="49"
                                            viewBox="0 0 48 49"
                                            fill="currentColor"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <path
                                                d="M40.9355 41.3123C36.2903 45.9574 30.6452 48.28 24 48.28C17.3548 48.28 11.6774 45.9574 6.96774 41.3123C2.32258 36.6026 0 30.9252 0 24.28C0 17.6348 2.32258 11.9897 6.96774 7.34451C11.6774 2.63484 17.3548 0.279999 24 0.279999C30.6452 0.279999 36.2903 2.63484 40.9355 7.34451C45.6452 11.9897 48 17.6348 48 24.28C48 30.9252 45.6452 36.6026 40.9355 41.3123ZM37.6452 10.6348C33.9032 6.82839 29.3548 4.92516 24 4.92516C18.6452 4.92516 14.0645 6.82839 10.2581 10.6348C6.51613 14.3768 4.64516 18.9252 4.64516 24.28C4.64516 29.6348 6.51613 34.2155 10.2581 38.0219C14.0645 41.7639 18.6452 43.6348 24 43.6348C29.3548 43.6348 33.9032 41.7639 37.6452 38.0219C41.4516 34.2155 43.3548 29.6348 43.3548 24.28C43.3548 18.9252 41.4516 14.3768 37.6452 10.6348ZM25.9355 36.6671H22.0645C21.2903 36.6671 20.9032 36.28 20.9032 35.5058V27.28C20.9032 25.6231 19.5601 24.28 17.9032 24.28H14.4194C13.9032 24.28 13.5484 24.0542 13.3548 23.6026C13.1613 23.0865 13.2258 22.6671 13.5484 22.3445L23.2258 12.6671C23.7419 12.151 24.2581 12.151 24.7742 12.6671L34.4516 22.3445C34.7742 22.6671 34.8387 23.0865 34.6452 23.6026C34.4516 24.0542 34.0968 24.28 33.5806 24.28H30.0968C28.4399 24.28 27.0968 25.6231 27.0968 27.28V35.5058C27.0968 36.28 26.7097 36.6671 25.9355 36.6671Z"
                                            />
                                        </svg>
                                        {{-- Drag Over Icon --}}
                                        <svg
                                            class="col-start-1 col-end-1 row-start-1 row-end-1 h-auto w-full scale-50 text-heading-foreground opacity-0 transition-all group-[&.drag-over]/drop-area:scale-100 group-[&.drag-over]/drop-area:opacity-100"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            width="24"
                                            height="24"
                                            stroke-width="1.5"
                                        >
                                            <path d="M19 11v-2a2 2 0 0 0 -2 -2h-8a2 2 0 0 0 -2 2v8a2 2 0 0 0 2 2h2"></path>
                                            <path d="M13 13l9 3l-4 2l-2 4l-3 -9"></path>
                                            <path d="M3 3l0 .01"></path>
                                            <path d="M7 3l0 .01"></path>
                                            <path d="M11 3l0 .01"></path>
                                            <path d="M15 3l0 .01"></path>
                                            <path d="M3 7l0 .01"></path>
                                            <path d="M3 11l0 .01"></path>
                                            <path d="M3 15l0 .01"></path>
                                        </svg>
                                    </div>
                                    <h4 class="mb-5 text-base">
                                        @lang('Drag and Drop Image')
                                        <template x-if="input.multiple">
                                            <span>@lang('(s)')</span>
                                        </template>
                                    </h4>
                                </div>
                            </div>
                            <div class="mx-auto flex w-3/4 items-center gap-7 text-2xs font-medium text-heading-foreground">
                                <span class="inline-flex h-px grow bg-heading-foreground/5"></span>
                                @lang('or')
                                <span class="inline-flex h-px grow bg-heading-foreground/5"></span>
                            </div>
                        </div>

                        {{-- Preview Grid --}}
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2"
                            x-show="uploadingImages.length"
                        >
                            <template x-for="(image, index) in uploadingImages">
                                <div class="group relative flex flex-col items-center justify-center gap-2 only-of-type:col-span-full">
                                    <img
                                        class="aspect-square h-auto w-full rounded-lg object-cover object-center shadow-sm"
                                        :src="image.src"
                                        :alt="image.name"
                                    >
                                    {{-- Remove button for modal preview --}}
                                    <button
                                        class="absolute end-2 top-2 flex size-6 items-center justify-center rounded-full bg-red-500 text-white transition-opacity hover:bg-red-600 sm:opacity-0 sm:group-hover:opacity-100"
                                        type="button"
                                        @click.prevent="removeImage(index)"
                                        title="Remove image"
                                    >
                                        <x-tabler-x class="size-4" />
                                    </button>
                                    <p
                                        class="m-0 w-full truncate text-3xs font-medium opacity-60"
                                        x-text="image.name"
                                    ></p>
                                </div>
                            </template>
                        </div>

                        {{-- Action Buttons --}}
                        <div
                            class="grid w-full grid-cols-3 place-items-center gap-2"
                            :class="{ 'grid-cols-1': !uploadingImages.length, 'grid-cols-3': uploadingImages.length }"
                        >
                            <x-button
                                class="relative z-3 text-heading-foreground outline-heading-foreground/5 transition-all hover:scale-105 hover:bg-heading-foreground hover:text-heading-background"
                                variant="outline"
                                type="button"
                                @click.prevent="$refs.formFileInput.click()"
                                ::class="{ 'w-fit px-4': !uploadingImages.length, 'w-full': uploadingImages.length }"
                            >
                                <span x-show="!uploadingImages.length">
                                    @lang('Browse Files')
                                </span>
                                <span x-show="uploadingImages.length">
                                    @lang('Add More')
                                </span>
                            </x-button>
                            <x-button
                                class="relative z-3 w-full px-1.5 transition-all hover:scale-105"
                                variant="danger"
                                type="button"
                                x-show="uploadingImages.length"
                                @click.prevent="clearImageInputs"
                            >
                                @lang('Clear All')
                            </x-button>
                            <x-button
                                class="relative z-3 w-full px-1.5 transition-all hover:scale-105"
                                variant="primary"
                                type="button"
                                x-show="uploadingImages.length"
                                @click.prevent="pickerModalOpen = false"
                            >
                                @lang('Done')
                            </x-button>
                        </div>

                        {{-- File Info --}}
                        <p class="text-3xs font-medium opacity-60">
                            <span
                                x-text="uploadingImages.length > 0 ? uploadingImages.length + ' image(s) selected' : ''"
                                x-show="uploadingImages.length"
                                x-cloak
                            ></span>
                            <span x-show="!uploadingImages.length">
                                <template x-if="input.accept && input.accept.includes('image')">
                                    <span>{{ __('PNG or JPG (Max: 25MB)') }}</span>
                                </template>
                                <template x-if="input.accept && input.accept.includes('video')">
                                    <span>{{ __('Video files accepted') }}</span>
                                </template>
                                <template x-if="!input.accept">
                                    <span>{{ __('PNG or JPG (Max: 25MB)') }}</span>
                                </template>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @unless ($contentManagerEnabled)
        {{-- Hidden File Input (inside modal when Content Manager is disabled) --}}
        <input
            class="absolute inset-0 z-2 size-0 cursor-pointer opacity-0"
            data-exclude-media-manager="true"
            :id="input.name.replace(/[\[\]]/g, '_')"
            :name="input.name + (input.multiple ? '[]' : '')"
            type="file"
            :accept="input.accept || 'image/*'"
            x-ref="formFileInput"
            @input="handleFileChange"
            :multiple="input.multiple || false"
            :required="input.required || false"
        >
    @endunless

    @if ($contentManagerEnabled)
        {{-- Hidden File Input (outside modal when Content Manager is enabled) --}}
        <input
            class="hidden"
            :id="input.name.replace(/[\[\]]/g, '_')"
            :name="input.name + (input.multiple ? '[]' : '')"
            type="file"
            :accept="input.accept || 'image/*'"
            x-ref="formFileInput"
            @input="handleFileChange"
            :multiple="input.multiple || false"
            :required="input.required || false"
            @if (!auth()->check()) data-exclude-media-manager="true" @endif
        >
    @endif
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiImageProImageInputHelper', () => ({
                pickerModalOpen: false,
                uploadingImages: [],
                storedFiles: [], // Store actual File objects in a regular array

                init() {
                    // Listen for external image load events
                    window.addEventListener('load-image-from-url', (event) => {
                        this.loadImageFromUrl(event.detail.url, event.detail.name);
                    });

                    // Listen for clear image inputs event (after form submission)
                    window.addEventListener('clear-image-inputs', () => {
                        this.clearImageInputs();
                    });

                    // Clipboard image paste: scoped to the nearest form so we don't
                    // hijack pastes on unrelated parts of the page.
                    const form = this.$el.closest('form');
                    if (form) {
                        form.addEventListener('paste', (event) => {
                            // If another image-input-helper in the same form already
                            // handled this paste, skip.
                            if (event.defaultPrevented) return;

                            const items = event.clipboardData?.items;
                            if (!items?.length) return;

                            const imageFiles = [];
                            for (const item of items) {
                                if (item.kind === 'file' && item.type.startsWith('image/')) {
                                    const file = item.getAsFile();
                                    if (file) imageFiles.push(file);
                                }
                            }

                            if (!imageFiles.length) return;

                            event.preventDefault();

                            // Only keep the first image when the input is single-file.
                            const acceptedFiles = (this.input?.multiple ? imageFiles : imageFiles.slice(0, 1));

                            const dataTransfer = new DataTransfer();
                            acceptedFiles.forEach((file, idx) => {
                                const ext = file.type.split('/')[1] || 'png';
                                const name = file.name && file.name !== 'image.png'
                                    ? file.name
                                    : `pasted-image-${Date.now()}-${idx}.${ext}`;
                                dataTransfer.items.add(new File([file], name, { type: file.type }));
                            });

                            this.handleUploadingMultiImages(dataTransfer.files);
                        });
                    }
                },

                async loadImageFromUrl(url, name = 'image.png') {
                    try {
                        // Fetch the image as blob
                        const response = await fetch(url);
                        if (!response.ok) {
                            throw new Error('Failed to fetch image');
                        }

                        const blob = await response.blob();

                        // Create a File object from the blob
                        const file = new File([blob], name, {
                            type: blob.type || 'image/png'
                        });

                        // Clear existing images first
                        this.clearImageInputs();

                        // Add the file using existing method
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);

                        this.$refs.formFileInput.files = dataTransfer.files;
                        this.storedFiles = [file];

                        this.uploadingImages = [{
                            src: URL.createObjectURL(file),
                            name: name
                        }];
                    } catch (error) {
                        console.error('Failed to load image from URL:', error);
                        if (window.toastr) {
                            window.toastr.error('{{ __('Failed to load image') }}');
                        }
                    }
                },

                handleDragOver(event) {
                    this.$refs.dropArea.classList.add('drag-over');
                },

                handleDragLeave(event) {
                    this.$refs.dropArea.classList.remove('drag-over');
                },

                handleUploadingMultiImages(files) {
                    const newFilesArray = Array.from(files);
                    const dataTransfer = new DataTransfer();

                    // Add existing stored files first
                    this.storedFiles.forEach(file => {
                        dataTransfer.items.add(file);
                    });

                    // Add new files (if not duplicates)
                    newFilesArray.forEach(file => {
                        if (!file.type.startsWith('image/')) {
                            toastr.error('Please upload a valid image file.');
                            this.clearImageInputs();
                            return;
                        }

                        // Check file size (25MB = 26214400 bytes)
                        const maxSize = 26214400;
                        if (file.size > maxSize) {
                            toastr.error('File size must not exceed 25MB: ' + file.name);
                            return;
                        }

                        // Check for duplicates by name and size
                        const isDuplicate = this.storedFiles.some(
                            existingFile => existingFile.name === file.name && existingFile.size === file.size
                        );

                        if (!isDuplicate) {
                            dataTransfer.items.add(file);
                        }
                    });

                    // Update the file input
                    this.$refs.formFileInput.files = dataTransfer.files;

                    // Store the actual File objects in our array (not the FileList reference)
                    this.storedFiles = Array.from(dataTransfer.files);

                    // Update preview images
                    this.uploadingImages = this.storedFiles.map(file => ({
                        src: URL.createObjectURL(file),
                        name: file.name
                    }));

                    return dataTransfer.files;
                },

                handleFileChange(event) {
                    let files = event.dataTransfer ? event.dataTransfer.files : event.target?.files;

                    this.$refs.dropArea.classList.remove('drag-over');

                    if (!files?.length) return;

                    if (event.dataTransfer) {
                        this.$refs.formFileInput.files = files;
                    }

                    files = this.handleUploadingMultiImages(files);

                    // REMOVED: pickerModalOpen = false - This was closing the modal
                    // Modal will stay open so users can see the preview
                },

                removeImage(index) {
                    // Remove from stored files array
                    this.storedFiles.splice(index, 1);

                    // Rebuild the DataTransfer with remaining files
                    const dataTransfer = new DataTransfer();
                    this.storedFiles.forEach(file => {
                        dataTransfer.items.add(file);
                    });

                    this.$refs.formFileInput.files = dataTransfer.files;
                    this.uploadingImages.splice(index, 1);
                },

                clearImageInputs() {
                    this.uploadingImages = [];
                    this.storedFiles = [];
                    if (this.$refs.formFileInput) {
                        this.$refs.formFileInput.value = '';
                        // Also clear by setting empty FileList
                        const dataTransfer = new DataTransfer();
                        this.$refs.formFileInput.files = dataTransfer.files;
                    }
                }
            }));
        });
    </script>
@endpush
