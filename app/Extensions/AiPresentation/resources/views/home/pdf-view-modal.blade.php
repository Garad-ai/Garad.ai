<!-- PDF Viewer Modal -->
<div
    class="fixed inset-0 z-10 bg-[#f5f5f5] dark:bg-background"
    x-data="pdfViewer"
    @open-pdf.window="openPdfViewer($event.detail.url, $event.detail.title, $event.detail.pages)"
    @keyup.escape.window="showPdfModal && closePdfViewer()"
    x-show="showPdfModal"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    style="background-image: radial-gradient(hsl(var(--foreground) / 20%) 0.75px, transparent 0px); background-size: 12px 12px; background-position: center;"
>
    <!-- Modal Container -->
    <div class="flex h-full w-full flex-col">
        <!-- Top Header Bar -->
        <div class="fixed inset-x-0 top-0">
            <div class="flex items-center justify-between p-4">
                <div>
                    <x-header-logo />
                </div>
                <div class="flex h-12 items-center gap-3 rounded-full bg-background px-4 py-1.5 shadow-xl shadow-black/[3%] lg:pe-3 lg:ps-5">
                    <x-button
                        variant="link"
                        ::href="currentPdfUrl"
                        ::download="`${currentPdfTitle}.pdf`"
                    >
                        {{ __('Download') }}
                        <span class="inline-grid size-[34px] place-items-center rounded-full border">
                            <x-tabler-circle-chevron-down
                                class="size-6"
                                stroke-width="1.5"
                            />
                        </span>
                    </x-button>

                    <x-button
                        class="inline-grid size-[34px] place-items-center rounded-full border lg:hidden"
                        variant="link"
                        @click="$refs.thumbsWrap.classList.toggle('active')"
                    >
                        <x-tabler-slideshow class="size-4" />
                    </x-button>

                    <x-button
                        class="inline-grid size-[34px] place-items-center rounded-full border"
                        variant="link"
                        @click="closePdfViewer()"
                    >
                        <x-tabler-x
                            class="size-4"
                            stroke-width="2.5"
                        />
                    </x-button>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar: Thumbnails -->
            <div
                class="overflow-hidden max-lg:fixed max-lg:inset-x-4 max-lg:bottom-20 max-lg:top-20 max-lg:h-0 max-lg:shadow-lg lg:pb-5 lg:ps-9 lg:pt-24 max-lg:[&.active]:h-auto"
                x-ref="thumbsWrap"
            >
                <div class="h-full overflow-y-auto rounded-xl bg-background p-5 lg:w-56">
                    <div
                        class="flex items-center justify-center py-12"
                        x-show="!pdfLoaded"
                    >
                        <svg
                            class="size-8 animate-spin text-gray-400"
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                        </svg>
                    </div>

                    <ol
                        class="flex list-inside list-decimal flex-col gap-3 text-3xs font-semibold"
                        x-cloak
                        x-show="pdfLoaded"
                    >
                        <template x-for="(thumbPreview, index) in thumbPreviews">
                            <li>
                                <a
                                    class="mt-2 block rounded border p-1.5 transition hover:scale-105"
                                    :href="`#pdf-page-${index + 1}`"
                                    @click.prevent="document.querySelector($el.getAttribute('href'))?.scrollIntoView({ behavior: 'smooth' }); $refs.thumbsWrap.classList.remove('active')"
                                >
                                    <img :src="thumbPreview">
                                </a>
                            </li>
                        </template>
                    </ol>
                </div>
            </div>

            <!-- Main PDF Viewer -->
            <div class="flex flex-1 flex-col overflow-y-auto pb-16 pt-24">
                <div class="container flex grow flex-col">
                    <div
                        class="flex h-full items-center justify-center text-center"
                        x-show="!pdfLoaded"
                    >
                        <p class="flex items-center gap-1 text-2xs font-medium text-heading-foreground">
                            <x-tabler-loader-2 class="size-4 animate-spin" />
                            {{ __('Loading...') }}
                        </p>
                    </div>
                    <div x-show="pdfLoaded">
                        <div class="mb-3.5 flex flex-wrap items-center justify-between gap-3">
                            <input
                                class="grow border-none bg-transparent bg-none text-2xs font-medium text-heading-foreground disabled:opacity-100"
                                placeholder="{{ __('PDF Title') }}"
                                :value="currentPdfTitle"
                                :disabled="!changingTitle"
                                x-ref="pdfTitle"
                                @blur="if (!changingTitle) return"
                                @keyup.enter.prevent="saveTitle()"
                            />
                            <div class="flex shrink-0 items-center gap-2">
                                <x-button
                                    class="text-2xs font-medium text-heading-foreground underline underline-offset-4"
                                    variant="link"
                                    x-show="!changingTitle"
                                    @click.prevent="startRenaming()"
                                >
                                    {{ __('Rename') }}
                                </x-button>
                                <x-button
                                    class="inline-flex items-center gap-1 text-2xs font-medium text-green-600 hover:text-green-700"
                                    variant="link"
                                    x-show="changingTitle"
                                    @click.prevent="saveTitle()"
                                >
                                    <svg
                                        class="size-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    {{ __('Save') }}
                                </x-button>
                            </div>
                        </div>
                        <div
                            class="flex flex-col gap-8"
                            x-ref="pagesContainer"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('pdfViewer', () => ({
                showPdfModal: false,
                currentPdfUrl: '',
                currentPdfTitle: '',
                originalPdfTitle: '',
                currentPage: 1,
                totalPages: 1,
                pdfLoaded: false,
                thumbPreviews: [],
                changingTitle: false,

                async openPdfViewer(url, title, pages = null) {
                    if ('pdfjsLib' in window) {
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/5.4.149/pdf.worker.min.mjs';
                    } else {
                        return toastr.error('{{ __('PDF viewer not loaded. Please Try Again.') }}')
                    }

                    // Reset state
                    this.currentPdfUrl = url;
                    this.currentPdfTitle = title || 'Presentation Name';
                    this.originalPdfTitle = this.currentPdfTitle;
                    this.currentPage = 1;
                    this.pdfLoaded = false;
                    this.thumbPreviews = [];
                    this.changingTitle = false;
                    document.body.style.overflow = 'hidden';

                    this.$refs.pagesContainer.innerHTML = '';

                    this.showPdfModal = true;

                    try {
                        const pdf = await pdfjsLib.getDocument(url).promise;
                        this.totalPages = pdf.numPages;

                        for (let pageNumber = 1; pageNumber <= this.totalPages; pageNumber++) {
                            await this.renderPage(pdf, pageNumber);
                        }

                        this.pdfLoaded = true;
                    } catch (err) {
                        toastr.error('{{ __('Could not load the PDF.') }}')
                        console.log('{{ __('Could not load the PDF.') }}: ', err);
                    }
                },

                async renderPage(pdf, pageNumber) {
                    try {
                        const page = await pdf.getPage(pageNumber);
                        const scale = 2;
                        const viewport = page.getViewport({
                            scale: scale
                        });

                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');

                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };

                        await page.render(renderContext).promise;

                        canvas.classList.add('max-w-full', 'h-auto', 'rounded', 'scroll-mt-4');
                        canvas.id = `pdf-page-${pageNumber}`;

                        this.$refs.pagesContainer.appendChild(canvas);

                        const thumbPreview = canvas.toDataURL();
                        this.thumbPreviews.push(thumbPreview);
                    } catch (err) {
                        toastr.error(`{{ __('Failed to load a pdf page. Page') }} ${pageNumber}`);
                        console.error(`Error rendering page ${pageNumber}:`, err);
                    }
                },

                closePdfViewer() {
                    this.showPdfModal = false;
                    this.currentPdfUrl = '';
                    this.currentPdfTitle = '';
                    this.originalPdfTitle = '';
                    this.currentPage = 1;
                    this.totalPages = 1;
                    this.pdfLoaded = false;
                    this.thumbPreviews = [];
                    this.changingTitle = false;
                    document.body.style.overflow = '';
                },

                startRenaming() {
                    this.changingTitle = true;
                    this.originalPdfTitle = this.currentPdfTitle;
                    this.$nextTick(() => {
                        this.$refs.pdfTitle.focus();
                        this.$refs.pdfTitle.select();
                    });
                },

                saveTitle() {
                    const newTitle = this.$refs.pdfTitle.value?.trim();

                    if (!newTitle) {
                        toastr.error('{{ __('Title cannot be empty') }}');
                        this.currentPdfTitle = this.originalPdfTitle;
                        this.$refs.pdfTitle.value = this.originalPdfTitle;
                        this.changingTitle = false;
                        return;
                    }

                    if (newTitle === this.originalPdfTitle) {
                        this.changingTitle = false;
                        return;
                    }

                    const self = this;

                    $.ajax({
                        type: 'post',
                        url: '/dashboard/user/ai-presentation/rename-pdf',
                        data: JSON.stringify({
                            url: this.currentPdfUrl,
                            title: newTitle
                        }),
                        contentType: 'application/json',
                        processData: false,
                        success: function(data) {
                            self.currentPdfTitle = newTitle;
                            self.originalPdfTitle = newTitle;
                            self.changingTitle = false;
                            toastr.success('{{ __('Title updated successfully') }}');
                        },
                        error: function(data) {
                            console.error('Error saving title:', data);
                            toastr.error(magicai_localize?.something_wrong || 'Something went wrong. Please reload the page and try it again');
                            self.currentPdfTitle = self.originalPdfTitle;
                            self.$refs.pdfTitle.value = self.originalPdfTitle;
                            self.changingTitle = false;
                        }
                    });
                }
            }));
        });
    </script>
@endpush
