<div class="grid w-full grid-cols-1 place-items-start">
    <div
        class="col-start-1 col-end-1 row-span-1 row-end-1 w-full"
        x-show="!selectedTemplate"
    >
        <div class="flex h-full gap-6">
            {{-- Sidebar --}}
            <div class="sticky top-0 flex w-full shrink-0 gap-x-4 gap-y-4 overflow-y-auto max-md:overflow-x-auto md:w-52 md:flex-col">
                <template
                    x-for="category in categories"
                    :key="category"
                >
                    <button
                        class="group text-start text-base font-medium text-heading-foreground transition-all max-md:whitespace-nowrap"
                        @click="selectedCategory = category"
                        :class="{ 'active': selectedCategory === category }"
                    >
                        <span
                            class="group-[&.active]:font-semibold group-[&.active]:underline"
                            x-text="category === 'all' ? '{{ __('All') }}' : category"
                        ></span>
                        <sup
                            class="ms-1 text-4xs no-underline"
                            x-show="selectedCategory === category"
                            x-text="getCategoryCount(category)"
                        ></sup>
                    </button>
                </template>
            </div>

            {{-- Templates Grid --}}
            <div class="flex-1">
                {{-- Loading Failed State --}}
                <div
                    class="flex items-center justify-center py-20"
                    x-show="loadingTemplatesFailed"
                    x-cloak
                >
                    <div class="text-center">
                        <div class="mx-auto mb-3 grid size-28 place-items-center rounded-full bg-foreground/5">
                            <x-tabler-alert-circle class="size-10 text-foreground" />
                        </div>
                        <p class="mb-1 text-lg font-medium text-heading-foreground">
                            {{ __('Failed to load templates') }}
                        </p>
                        <p class="mb-3 opacity-70">
                            {{ __('Please try again later') }}
                        </p>

                        <x-button @click.prevent="retryFetch()">
                            <x-tabler-refresh
                                class="size-4"
                                ::class="{ 'animate-spin': loading }"
                            />
                            {{ __('Retry') }}
                        </x-button>
                    </div>
                </div>

                {{-- Loading State --}}
                <div
                    class="flex items-center justify-center py-20"
                    x-show="!templatesList.length && !loadingTemplatesFailed"
                >
                    <p class="flex items-center gap-2">
                        <x-tabler-loader-2 class="size-5 animate-spin" />
                        {{ __('Loading Templates') }}
                    </p>
                </div>

                {{-- Templates Grid --}}
                <div
                    class="grid grid-cols-2 gap-4 md:grid-cols-3"
                    x-show="templatesList.length"
                    x-cloak
                >
                    <template
                        x-for="template in filteredTemplates"
                        :key="template.id"
                    >
                        <div
                            class="group/item relative block w-full"
                            @click="openTemplate(template)"
                        >
                            <div
                                class="relative mb-1.5 block w-full overflow-hidden rounded-lg shadow-md shadow-black/10 transition group-hover/item:scale-105 group-hover/item:shadow-lg group-hover/item:shadow-black/5">
                                <img
                                    class="aspect-[1/0.7176] scale-105 object-cover transition group-hover/item:scale-100"
                                    src="data:image/svg+xml;charset=utf-8,%3Csvg xmlns%3D'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg' viewBox%3D'0 0 100 100'%2F%3E"
                                    alt="{{ __('Template Preview') }}"
                                    x-intersect.once="$el.src = template.preview"
                                    loading="lazy"
                                >
                            </div>

                            <p
                                class="mb-0.5 block w-full truncate text-3xs font-medium opacity-60"
                                x-text="template.category"
                            ></p>
                            <p
                                class="mb-0 block text-2xs font-medium text-heading-foreground"
                                x-text="template.name"
                            ></p>
                        </div>
                    </template>
                </div>

                {{-- Empty State --}}
                <div
                    class="flex items-center justify-center py-20"
                    x-show="templatesList.length && !filteredTemplates.length"
                    x-cloak
                >
                    <p>
                        {{ __('No templates found in this category') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Selected Template Content --}}
    <div
        class="col-start-1 col-end-1 row-span-1 row-end-1 w-full"
        x-show="selectedTemplate"
        x-cloak
    >
        <div class="flex flex-col gap-10 md:flex-row md:gap-16">
            {{-- Left Side - Preview Image --}}
            <div class="hidden md:block md:w-[500px]">
                <img
                    class="h-full w-full rounded-md object-cover"
                    :src="selectedTemplate?.preview"
                    :alt="selectedTemplate?.name"
                >
            </div>

            {{-- Right Side - Form --}}
            <form
                class="flex flex-1 flex-col gap-5"
                @submit.prevent="handleSubmit($event)"
            >
                <template
                    x-for="input in selectedTemplate?.data.inputs"
                    :key="input.key"
                >
                    <div>
                        {{-- Label --}}
                        <label
                            class="mb-3.5 block text-xs font-medium text-heading-foreground"
                            :for="input.key"
                            x-show="input.type !== 'textarea'"
                            x-text="input.label"
                        ></label>

                        {{-- File Input with Drag & Drop --}}
                        <template x-if="input.type === 'file'">
                            <div
                                class="group/drop-area relative grid place-items-center overflow-y-auto rounded-[10px] border p-5 text-center transition [&.drag-over]:border-primary [&.drag-over]:bg-primary/10"
                                x-data="{
                                    dragOver: false,
                                    handleDragOver(e) {
                                        e.preventDefault();
                                        this.dragOver = true;
                                        $el.classList.add('drag-over');
                                    },
                                    handleDragLeave(e) {
                                        e.preventDefault();
                                        this.dragOver = false;
                                        $el.classList.remove('drag-over');
                                    },
                                    handleDrop(e, key) {
                                        e.preventDefault();
                                        this.dragOver = false;
                                        $el.classList.remove('drag-over');
                                        const file = e.dataTransfer.files[0];
                                        if (file) {
                                            handleFileUpload({ target: { files: [file] } }, key);
                                        }
                                    }
                                }"
                                @dragover.prevent="handleDragOver"
                                @dragleave.prevent="handleDragLeave"
                                @drop.prevent="handleDrop($event, input.key)"
                            >
                                <div x-show="!uploadedFiles[input.key]">
                                    <div class="mx-auto mb-2.5 inline-grid w-10 place-content-center">
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
                                    <p class="mb-2 text-sm font-medium">
                                        {{ __('Upload Image') }}
                                    </p>

                                    <p class="m-0 text-4xs font-medium opacity-60">
                                        {{ __('Max file size: 5MB.') }}
                                    </p>
                                </div>

                                {{-- Preview uploaded image --}}
                                <div
                                    class="flex w-full flex-col gap-2"
                                    x-show="uploadedFiles[input.key]"
                                >
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <div class="relative aspect-video h-auto w-full max-w-[200px]">
                                            <img
                                                class="h-full w-full rounded-lg object-cover object-center shadow-sm"
                                                x-show="uploadedFiles[input.key]"
                                                :src="uploadedFiles[input.key]?.preview"
                                                alt="Preview"
                                            >

                                            <button
                                                class="absolute -end-3 -top-3 z-10 inline-grid size-8 place-items-center rounded-full bg-background text-foreground shadow-lg shadow-black/5 transition hover:scale-105 hover:bg-red-500 hover:text-white"
                                                type="button"
                                                @click.prevent="removeFile(input.key)"
                                                title="{{ __('Remove') }}"
                                            >
                                                <x-tabler-x class="size-4" />
                                            </button>
                                        </div>
                                        <p
                                            class="m-0 text-4xs font-medium opacity-60"
                                            x-text="uploadedFiles[input.key]?.name"
                                        ></p>
                                    </div>
                                </div>

                                <input
                                    class="absolute inset-0 cursor-pointer opacity-0"
                                    data-exclude-media-manager="true"
                                    :id="input.key"
                                    :name="input.key"
                                    type="file"
                                    :accept="input.accept"
                                    :required="input.required"
                                    :x-ref="'file_' + input.key"
                                    @change="handleFileUpload($event, input.key)"
                                >
                            </div>
                        </template>

                        {{-- Textarea Input --}}
                        <template x-if="input.type === 'textarea'">
                            <div class="relative">
                                <label
                                    class="mb-3.5 flex items-center justify-between gap-1 text-xs font-medium text-heading-foreground"
                                    x-bind:for="input.key"
                                >
                                    <span x-text="input.label"></span>

                                    <div
                                        class="inline-grid size-7 place-items-center"
                                        x-show="input['ai-enhance'] === true"
                                        style="cursor: pointer;"
                                        @click="enhancePrompt(input.key, $event)"
                                    >
                                        <x-tabler-refresh
                                            class="lds-dual-ring2 hidden size-4 animate-spin"
                                            id="lds-dual-ring2"
                                        />
                                        {{-- blade-formatter-disable --}}
										<svg class="generate size-full" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" > <path fill-rule="evenodd" clip-rule="evenodd" d="M25.3483 15.0849L23.9529 15.3678C23.2806 15.5042 22.6635 15.8356 22.1785 16.3207C21.6934 16.8057 21.362 17.4229 21.2256 18.0951L20.9426 19.4905C20.9147 19.6301 20.8392 19.7556 20.7291 19.8458C20.619 19.936 20.4811 19.9853 20.3388 19.9853C20.1964 19.9853 20.0585 19.936 19.9484 19.8458C19.8383 19.7556 19.7629 19.6301 19.7349 19.4905L19.4519 18.0951C19.3156 17.4228 18.9843 16.8056 18.4992 16.3205C18.0142 15.8355 17.3969 15.5041 16.7246 15.3678L15.3292 15.0849C15.19 15.0564 15.0648 14.9807 14.9749 14.8706C14.885 14.7604 14.8359 14.6226 14.8359 14.4805C14.8359 14.3384 14.885 14.2006 14.9749 14.0905C15.0648 13.9803 15.19 13.9046 15.3292 13.8762L16.7246 13.5932C17.3969 13.4569 18.0142 13.1255 18.4992 12.6405C18.9843 12.1554 19.3156 11.5382 19.4519 10.8659L19.7349 9.47048C19.7629 9.33094 19.8383 9.20539 19.9484 9.11519C20.0585 9.025 20.1964 8.97571 20.3388 8.97571C20.4811 8.97571 20.619 9.025 20.7291 9.11519C20.8392 9.20539 20.9147 9.33094 20.9426 9.47048L21.2256 10.8659C21.362 11.5381 21.6934 12.1553 22.1785 12.6403C22.6635 13.1254 23.2806 13.4568 23.9529 13.5932L25.3483 13.8762C25.4876 13.9046 25.6127 13.9803 25.7026 14.0905C25.7925 14.2006 25.8416 14.3384 25.8416 14.4805C25.8416 14.6226 25.7925 14.7604 25.7026 14.8706C25.6127 14.9807 25.4876 15.0564 25.3483 15.0849ZM15.1421 22.1572L14.763 22.2342C14.2954 22.3291 13.8662 22.5595 13.5288 22.8969C13.1915 23.2342 12.961 23.6634 12.8662 24.131L12.7892 24.5102C12.7679 24.6164 12.7105 24.7119 12.6267 24.7806C12.5429 24.8492 12.438 24.8867 12.3297 24.8867C12.2214 24.8867 12.1164 24.8492 12.0326 24.7806C11.9488 24.7119 11.8914 24.6164 11.8701 24.5102L11.7931 24.131C11.6983 23.6634 11.4678 23.2342 11.1305 22.8969C10.7931 22.5595 10.3639 22.3291 9.89634 22.2342L9.51718 22.1572C9.41098 22.1359 9.31543 22.0785 9.24678 21.9947C9.17813 21.911 9.14062 21.806 9.14062 21.6977C9.14062 21.5894 9.17813 21.4844 9.24678 21.4006C9.31543 21.3169 9.41098 21.2594 9.51718 21.2382L9.89634 21.1612C10.3639 21.0663 10.7931 20.8358 11.1305 20.4985C11.4678 20.1611 11.6983 19.7319 11.7931 19.2644L11.8701 18.8852C11.8914 18.779 11.9488 18.6834 12.0326 18.6148C12.1164 18.5461 12.2214 18.5087 12.3297 18.5087C12.438 18.5087 12.5429 18.5461 12.6267 18.6148C12.7105 18.6834 12.7679 18.779 12.7892 18.8852L12.8662 19.2644C12.961 19.7319 13.1915 20.1611 13.5288 20.4985C13.8662 20.8358 14.2954 21.0663 14.763 21.1612L15.1421 21.2382C15.2483 21.2594 15.3439 21.3169 15.4125 21.4006C15.4812 21.4844 15.5187 21.5894 15.5187 21.6977C15.5187 21.806 15.4812 21.911 15.4125 21.9947C15.3439 22.0785 15.2483 22.1359 15.1421 22.1572Z" fill="url(#paint0_linear_2401_1456)" /> <defs> <linearGradient id="paint0_linear_2401_1456" x1="25.8416" y1="16.9312" x2="8.97735" y2="14.9877" gradientUnits="userSpaceOnUse" > <stop stop-color="#8D65E9" /> <stop offset="0.483" stop-color="#5391E4" /> <stop offset="1" stop-color="#6BCD94" /> </linearGradient> </defs> </svg>
										{{-- blade-formatter-enable --}}
                                    </div>
                                </label>

                                <x-forms.input
                                    type="textarea"
                                    x-bind:id="input.key"
                                    x-bind:name="input.key"
                                    x-bind:placeholder="input.placeholder"
                                    x-bind:required="input.required"
                                    x-bind:rows="input.rows || 5"
                                    x-model="formData[input.key]"
                                />
                            </div>
                        </template>

                        {{-- Select Input --}}
                        <template x-if="input.type === 'select'">
                            <x-forms.input
                                type="select"
                                x-bind:id="input.key"
                                x-bind:name="input.key"
                                x-bind:required="input.required"
                                x-model="formData[input.key]"
                            >
                                <template
                                    x-for="option in input.options"
                                    :key="option.value"
                                >
                                    <option
                                        :value="option.value"
                                        :selected="option.selected"
                                        x-text="option.label"
                                    ></option>
                                </template>
                            </x-forms.input>
                        </template>
                    </div>
                </template>

                {{-- Submit Button --}}
                <x-button
                    class="w-full"
                    size="xl"
                    type="submit"
                >
                    {{ __('Generate') }}
                </x-button>
            </form>
        </div>
    </div>
</div>
