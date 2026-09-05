<x-modal
    class:modal-content="flex flex-col p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none h-[min(670px,calc(100vh-4rem))] !min-w-0 w-[min(940px,100%)]"
    class:modal-head="p-0 shrink-0"
    class:modal-title="text-[12px] font-semibold"
    class:modal-body="p-0 flex flex-col grow"
    class:modal-container="flex flex-col grow w-full"
    class:close-btn="size-7"
    class:close-btn-icon="size-4"
    id="ai-agent-knowledge-base-modal"
>
    <x-slot:trigger
        class="flex flex-col items-start gap-3 border border-dashed p-6 text-start text-2xs/tight [--button-rounded:10px] hover:border-primary hover:bg-primary hover:text-primary-foreground"
        variant="none"
    >
        <x-tabler-plus class="size-5" />
        <span class="font-medium">
            {{ __('Add Knowledge Source') }}
        </span>
        <span class="opacity-60">
            {{ __('Personalize your agent') }}
        </span>
    </x-slot:trigger>
    <x-slot:head-content>
        <div class="flex gap-5">
            <button
                class="active py-2 text-[12px] font-semibold text-heading-foreground opacity-50 transition [&.active]:opacity-100"
                @click.prevent="knowledgeForm.type = 'file'"
                :class="{ 'active': knowledgeForm.type === 'file' }"
            >
                {{ __('Upload File') }}
            </button>
            <button
                class="active py-2 text-[12px] font-semibold text-heading-foreground opacity-50 transition [&.active]:opacity-100"
                @click.prevent="knowledgeForm.type = 'text'"
                :class="{ 'active': knowledgeForm.type === 'text' }"
            >
                {{ __('Add Plain Text') }}
            </button>
        </div>
    </x-slot:head-content>

    <x-slot:modal>
        <div class="flex grow flex-col gap-5 py-5">
            <x-forms.input
                size="lg"
                type="text"
                label="{{ __('Name') }}"
                placeholder="{{ __('Product FAQ') }}"
                x-model="knowledgeForm.name"
            />

            <template x-if="knowledgeForm.type === 'text'">
                <div class="contents">
                    <x-forms.input
                        size="lg"
                        type="textarea"
                        rows="10"
                        label="{{ __('Content') }}"
                        placeholder="{{ __('Paste your knowledge text here...') }}"
                        x-model="knowledgeForm.content"
                        @keydown.enter="if (!$event.shiftKey && !$event.metaKey && !$event.ctrlKey) { $event.preventDefault(); saveKnowledgeSource(); }"
                    />

                    <x-button
                        class="w-full"
                        size="lg"
                        @click.prevent="saveKnowledgeSource"
                        ::disabled="knowledgeForm.loading"
                    >
                        <x-tabler-plus class="size-4" />
                        {{ __('Add') }}
                    </x-button>
                </div>
            </template>

            <template x-if="knowledgeForm.type === 'file'">
                <div
                    class="relative flex w-full flex-col items-center justify-center gap-5 rounded-[22px] border-2 border-dashed px-4 py-5 text-center transition lg:py-8 [&.is-dragging]:border-primary [&.is-dragging]:bg-primary/5"
                    :class="{ 'is-dragging': knowledgeForm.dragging }"
                    @dragover.prevent="knowledgeForm.dragging = true"
                    @dragenter.prevent="knowledgeForm.dragging = true"
                    @dragleave.prevent="if ($event.target === $el) knowledgeForm.dragging = false"
                    @drop.prevent="handleKnowledgeDrop($event)"
                >
                    <svg
                        class="pointer-events-none fill-foreground/50"
                        width="38"
                        height="38"
                        viewBox="0 0 38 38"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M32.4073 32.4839C28.7298 36.1613 24.2608 38 19 38C13.7392 38 9.24462 36.1613 5.51613 32.4839C1.83871 28.7554 0 24.2608 0 19C0 13.7392 1.83871 9.27016 5.51613 5.59274C9.24462 1.86425 13.7392 0 19 0C24.2608 0 28.7298 1.86425 32.4073 5.59274C36.1358 9.27016 38 13.7392 38 19C38 24.2608 36.1358 28.7554 32.4073 32.4839ZM29.8024 8.19758C26.8401 5.18414 23.2392 3.67742 19 3.67742C14.7608 3.67742 11.1344 5.18414 8.12097 8.19758C5.1586 11.1599 3.67742 14.7608 3.67742 19C3.67742 23.2392 5.1586 26.8656 8.12097 29.879C11.1344 32.8414 14.7608 34.3226 19 34.3226C23.2392 34.3226 26.8401 32.8414 29.8024 29.879C32.8159 26.8656 34.3226 23.2392 34.3226 19C34.3226 14.7608 32.8159 11.1599 29.8024 8.19758ZM20.5323 28.8065H17.4677C16.8548 28.8065 16.5484 28.5 16.5484 27.8871V22C16.5484 20.3431 15.2052 19 13.5484 19H11.4153C11.0067 19 10.7258 18.8212 10.5726 18.4637C10.4194 18.0551 10.4704 17.7231 10.7258 17.4677L18.3871 9.80645C18.7957 9.39785 19.2043 9.39785 19.6129 9.80645L27.2742 17.4677C27.5296 17.7231 27.5806 18.0551 27.4274 18.4637C27.2742 18.8212 26.9933 19 26.5847 19H24.4516C22.7948 19 21.4516 20.3431 21.4516 22V27.8871C21.4516 28.5 21.1452 28.8065 20.5323 28.8065Z"
                        />
                    </svg>
                    <p class="pointer-events-none m-0 text-base/tight font-medium">
                        <span x-show="!knowledgeForm.dragging">{{ __('Drag and Drop Files') }}</span>
                        <span
                            x-show="knowledgeForm.dragging"
                            x-cloak
                        >{{ __('Drop file to upload') }}</span>
                    </p>
                    <div class="pointer-events-none flex w-[min(305px,100%)] items-center gap-6">
                        <span class="inline-block h-px grow bg-foreground/5"></span>
                        {{ __('or') }}
                        <span class="inline-block h-px grow bg-foreground/5"></span>
                    </div>
                    <x-button
                        class="relative z-3"
                        variant="outline"
                        size="lg"
                        @click.prevent="if (!knowledgeForm.name.trim()) { return toastr.error('{{ __('Enter a name first') }}') } else { $refs.knowledgeBaseFileInput.click()}"
                    >
                        {{ __('Browse Files') }}
                    </x-button>
                    <ul class="pointer-events-none list-inside list-disc text-[12px] text-foreground/50">
                        <li>
                            {{ __('PDF or .doc') }}
                        </li>
                    </ul>
                    <a
                        class="absolute inset-0 z-1"
                        href="#"
                        @click.prevent="if (!knowledgeForm.name.trim()) { return toastr.error('{{ __('Enter a name first') }}') } else { $refs.knowledgeBaseFileInput.click()}"
                    ></a>
                    <input
                        class="hidden"
                        type="file"
                        x-ref="knowledgeBaseFileInput"
                        accept=".txt,.md,.csv,.json,.pdf"
                        @change="knowledgeForm.file = $event.target.files[0]; saveKnowledgeSource()"
                    />
                </div>
            </template>

            {{-- Attached sources --}}
            <template x-if="(selectedStep.config.knowledge_source_ids ?? []).length > 0">
                <div class="space-y-1.5">
                    <template
                        x-for="sid in (selectedStep.config.knowledge_source_ids ?? [])"
                        :key="sid"
                    >
                        <div class="flex items-center gap-3 rounded-full border px-6 py-2.5">
                            <x-tabler-file-text class="size-5 shrink-0 opacity-65" />
                            <span
                                class="truncate text-sm"
                                x-text="knowledgeSourceName(sid)"
                            ></span>

                            <x-button
                                class="ms-auto size-7 shrink-0"
                                size="none"
                                variant="ghost"
                                type="button"
                                @click.prevent="deleteKnowledgeSource(sid)"
                                title="{{ __('Delete') }}"
                            >
                                <x-tabler-x class="size-4" />
                            </x-button>
                        </div>
                    </template>
                </div>
            </template>

            <x-button
                class="mt-auto w-full"
                variant="secondary"
                type="button"
                @click.prevent="toggleModal(false)"
                ::disabled="knowledgeForm.loading"
            >
                {{ __('Done') }}
                <span class="inline-grid size-[30px] place-items-center rounded-full bg-background text-foreground">
                    <x-tabler-chevron-right
                        class="size-4 rtl:rotate-180"
                        x-show="!knowledgeForm.loading"
                    />
                    <x-tabler-loader-2
                        class="size-4 animate-spin"
                        x-show="knowledgeForm.loading"
                        x-cloak
                    />
                </span>
            </x-button>
        </div>
    </x-slot:modal>
</x-modal>
