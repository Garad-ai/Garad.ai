@php
    $upload_terms = [__('Clearly visible face'), __('Ensure good lighting'), __('PNG or JPG (Max: :size MB)', ['size' => $maxUploadMb])];
@endphp

<x-modal
    class:modal-modal="overflow-hidden"
    class:modal-head="lg:p-0 lg:border-0"
    class:modal-content="min-w-0 h-auto w-[min(100%,765px)] lg:overflow-visible"
    class:modal-body="lg:overflow-y-auto lg:max-h-[calc(100vh-4rem)] p-5 lg:px-9 lg:pb-8 lg:pt-6"
    class:close-btn="lg:inline-grid lg:place-items-center lg:size-9 lg:rounded-full lg:bg-background lg:absolute lg:top-0 lg:-end-14 hover:bg-background hover:text-foreground"
    id="ugc-factory-create-modal"
>
    <x-slot:modal>
        <div class="mb-5 flex items-center gap-5.5 overflow-x-auto border-b">
            <button
                class="whitespace-nowrap py-2.5 text-[12px] font-semibold text-heading-foreground opacity-50 transition hover:opacity-85 [&.active]:opacity-100"
                :class="{ 'active': createModal.activeTab === 'upload' }"
                type="button"
                @click.prevent="toggleCreateModal(true, 'upload')"
            >
                {{ __('Upload Your Actor') }}
            </button>
            <button
                class="whitespace-nowrap py-2.5 text-[12px] font-semibold text-heading-foreground opacity-50 transition hover:opacity-85 [&.active]:opacity-100"
                :class="{ 'active': createModal.activeTab === 'create' }"
                type="button"
                @click.prevent="toggleCreateModal(true, 'create')"
            >
                {{ __('Create New Actor') }}
            </button>
        </div>

        <div x-show="createModal.activeTab === 'upload'">
            <form
                class="flex flex-col gap-5"
                @submit.prevent="submitActorUpload"
            >
                <x-forms.droparea
                    style="extended"
                    name="ugc_factory_actor_file"
                    accept="image/*"
                    :max-size-mb="$maxUploadMb ?? null"
                    :terms="$upload_terms"
                    @change="captureUploadFile($event)"
                >
                    <x-slot:decorator>
                        <figure
                            class="mx-auto mb-4.5 inline-flex w-52"
                            aria-hidden="true"
                        >
                            <img
                                class="drop-shadow-xl"
                                src="{{ custom_theme_url('/vendor/ugc-factory/images/create-modal-decorator.webp') }}"
                                alt="{{ __('Decorator') }}"
                                aria-hidden="true"
                            >
                        </figure>
                    </x-slot:decorator>
                </x-forms.droparea>

                <x-forms.input
                    size="lg"
                    name="ugc_factory_actor_name"
                    label="{{ __('Name') }}"
                    placeholder="{{ __('e.g. Sanchez') }}"
                    x-model="createModal.uploadName"
                />

                <p
                    class="m-0 text-3xs text-red-500"
                    x-show="createModal.modalError"
                    x-text="createModal.modalError"
                    x-cloak
                ></p>

                <x-button
                    class="w-full"
                    type="submit"
                    variant="secondary"
                    size="lg"
                    ::disabled="createModal.submittingUpload || isDemo"
                >
                    <span x-show="!createModal.submittingUpload">{{ __('Create Model') }}</span>
                    <span
                        x-show="createModal.submittingUpload"
                        x-cloak
                    >{{ __('Uploading…') }}</span>
                    <span
                        class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground"
                        x-show="!createModal.submittingUpload"
                    >
                        <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                    </span>
                </x-button>
            </form>
        </div>

        <div
            x-show="createModal.activeTab === 'create'"
            x-cloak
        >
            <form
                class="flex flex-col gap-5"
                @submit.prevent="submitActorGenerate"
            >
                <x-forms.input
                    size="lg"
                    name="ugc_factory_actor_generate_name"
                    label="{{ __('Name') }}"
                    placeholder="{{ __('e.g. Sanchez') }}"
                    x-model="createModal.generateName"
                />

                <x-forms.input
                    type="textarea"
                    rows="8"
                    size="lg"
                    name="ugc_factory_actor_generate_prompt"
                    label="{{ __('Appearance and Setting') }}"
                    placeholder="{{ __('A fashionable woman with a chic short blonde haircut is seated at a café, gazing directly at the camera as the sun sets behind her.') }}"
                    x-model="createModal.generatePrompt"
                />

                <p
                    class="m-0 text-3xs text-red-500"
                    x-show="createModal.modalError"
                    x-text="createModal.modalError"
                    x-cloak
                ></p>

                <x-button
                    class="w-full"
                    type="submit"
                    variant="secondary"
                    size="lg"
                    ::disabled="createModal.submittingGenerate || isDemo"
                >
                    <span x-show="!createModal.submittingGenerate">{{ __('Create Model') }}</span>
                    <span
                        x-show="createModal.submittingGenerate"
                        x-cloak
                    >{{ __('Submitting…') }}</span>
                    <span
                        class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground"
                        x-show="!createModal.submittingGenerate"
                    >
                        <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                    </span>
                </x-button>
            </form>
        </div>
    </x-slot:modal>
</x-modal>
