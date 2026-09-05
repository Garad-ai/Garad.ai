<x-modal
    class:modal-content="w-[min(760px,100%)] lg:h-auto lg:overflow-visible"
    class:modal-head="lg:p-0 lg:border-0"
    class:modal-body="p-8 lg:overflow-y-auto lg:max-h-[95vh]"
    class:close-btn="lg:absolute lg:inline-grid lg:size-9 lg:place-items-center lg:rounded-full lg:bg-background lg:shadow-lg lg:-end-12 lg:top-0 lg:hover:bg-background lg:hover:text-foreground lg:hover:scale-110"
    id="ai-persona-upload-modal"
>
    <x-slot:modal>
        <div class="mb-5 flex gap-4 border-b">
            <button
                class="-mb-px border-b border-transparent py-2.5 text-[12px] font-semibold opacity-50 transition hover:opacity-100 [&.active]:border-b-current [&.active]:opacity-100"
                type="button"
                :class="{ 'active': uploadModalActiveTab === 'upload' }"
                @click.prevent="uploadModalActiveTab = 'upload'"
            >
                {{ __('Upload Your Avatar') }}
            </button>
            <button
                class="-mb-px border-b border-transparent py-2.5 text-[12px] font-semibold opacity-50 transition hover:opacity-100 [&.active]:border-b-current [&.active]:opacity-100"
                type="button"
                :class="{ 'active': uploadModalActiveTab === 'create' }"
                @click.prevent="uploadModalActiveTab = 'create'"
            >
                {{ __('Create Your Avatar') }}
            </button>
        </div>

        <form
            class="m-0 space-y-5"
            @submit.prevent="handleAvatarFormSubmit"
            enctype="multipart/form-data"
        >
            @csrf

            <div x-show="uploadModalActiveTab === 'upload'">
                <x-forms.input
                    id="upload_avatar_name"
                    name="upload_avatar_name"
                    size="lg"
                    label="{{ __('Avatar Name') }}"
                    placeholder="{{ __('e.g. My Spokesperson') }}"
                    x-model="uploadForm.name"
                />

                <input
                    class="hidden"
                    type="file"
                    x-ref="fileInput"
                    accept="image/png,image/jpeg"
                    @change="handleFileSelect($event)"
                >

                <div
                    class="mt-4 cursor-pointer rounded-2xl border-2 border-dashed p-8 text-center transition-colors [&.drag-over]:border-primary/50 [&.drag-over]:bg-primary/5"
                    @click="$refs.fileInput.click()"
                    @dragover.prevent="dragOverUploadForm = true"
                    @dragleave.prevent="dragOverUploadForm = false"
                    @drop.prevent="handleFileDrop($event)"
                    :class="{ 'drag-over': dragOverUploadForm }"
                >
                    <template x-if="!uploadPreview">
                        <div>
                            <x-tabler-cloud-upload class="mx-auto mb-4 size-10 text-foreground opacity-25" />

                            <h3 class="mb-0">
                                {{ __('Drag and Drop Image') }}
                            </h3>

                            <div class="mx-auto my-5 flex w-[min(100%,300px)] items-center gap-8 text-heading-foreground">
                                <span class="inline-flex h-px grow bg-current opacity-5"></span>
                                {{ __('or') }}
                                <span class="inline-flex h-px grow bg-current opacity-5"></span>
                            </div>

                            <x-button
                                class="mb-4 text-sm"
                                type="button"
                                variant="outline"
                                @click.stop="$refs.fileInput.click()"
                                size="xl"
                            >
                                {{ __('Browse Files') }}
                            </x-button>

                            <div class="space-y-1 text-[12px] opacity-50">
                                <ul class="list-inside list-disc">
                                    <li>{{ __('Clearly visible face and hair') }}</li>
                                    <li>{{ __('Ensure good lighting without harsh shadows') }}</li>
                                    <li>{{ __('PNG or JPG (Max: 10Mb)') }}</li>
                                </ul>
                            </div>
                        </div>
                    </template>

                    <template x-if="uploadPreview">
                        <div class="text-center">
                            <img
                                class="mx-auto mb-2 max-h-48 rounded-lg"
                                :src="uploadPreview"
                            >

                            <p
                                class="text-2xs font-medium opacity-60"
                                x-text="uploadFileName"
                            ></p>

                            <x-button
                                variant="link"
                                type="button"
                                @click.stop="resetUpload"
                            >
                                <x-tabler-refresh class="size-4" />
                                {{ __('Upload Another') }}
                            </x-button>
                        </div>
                    </template>
                </div>
            </div>

            <div
                class="space-y-5"
                x-show="uploadModalActiveTab === 'create'"
            >
                <x-forms.input
                    id="ai_avatar_name"
                    name="ai_avatar_name"
                    size="lg"
                    label="{{ __('Avatar Name') }}"
                    placeholder="{{ __('e.g. My Spokesperson') }}"
                    x-model="uploadForm.name"
                />

                <div class="grid grid-cols-2 gap-4">
                    <x-forms.input
                        id="ai_avatar_age"
                        name="ai_avatar_age"
                        type="select"
                        size="lg"
                        label="{{ __('Age') }}"
                        x-model="uploadForm.age"
                    >
                        <option value="Young Adult">{{ __('Young Adult') }}</option>
                        <option value="Early Middle Age">{{ __('Early Middle Age') }}</option>
                        <option value="Late Middle Age">{{ __('Late Middle Age') }}</option>
                        <option value="Senior">{{ __('Senior') }}</option>
                        <option value="Unspecified">{{ __('Unspecified') }}</option>
                    </x-forms.input>

                    <x-forms.input
                        id="ai_avatar_gender"
                        name="ai_avatar_gender"
                        type="select"
                        size="lg"
                        label="{{ __('Gender') }}"
                        x-model="uploadForm.gender"
                    >
                        <option value="Woman">{{ __('Woman') }}</option>
                        <option value="Man">{{ __('Man') }}</option>
                        <option value="Unspecified">{{ __('Unspecified') }}</option>
                    </x-forms.input>
                </div>

                <x-forms.input
                    id="ai_avatar_ethnicity"
                    name="ai_avatar_ethnicity"
                    type="select"
                    size="lg"
                    label="{{ __('Ethnicity') }}"
                    x-model="uploadForm.ethnicity"
                >
                    <option value="White">{{ __('White') }}</option>
                    <option value="Black">{{ __('Black') }}</option>
                    <option value="Asian American">{{ __('Asian American') }}</option>
                    <option value="East Asian">{{ __('East Asian') }}</option>
                    <option value="South East Asian">{{ __('South East Asian') }}</option>
                    <option value="South Asian">{{ __('South Asian') }}</option>
                    <option value="Middle Eastern">{{ __('Middle Eastern') }}</option>
                    <option value="Pacific">{{ __('Pacific') }}</option>
                    <option value="Hispanic">{{ __('Hispanic') }}</option>
                    <option value="Unspecified">{{ __('Unspecified') }}</option>
                </x-forms.input>

                <div class="grid grid-cols-3 gap-4">
                    <x-forms.input
                        id="ai_avatar_orientation"
                        name="ai_avatar_orientation"
                        type="select"
                        size="lg"
                        label="{{ __('Orientation') }}"
                        x-model="uploadForm.orientation"
                    >
                        <option value="square">{{ __('Square') }}</option>
                        <option value="horizontal">{{ __('Horizontal') }}</option>
                        <option value="vertical">{{ __('Vertical') }}</option>
                    </x-forms.input>

                    <x-forms.input
                        id="ai_avatar_pose"
                        name="ai_avatar_pose"
                        type="select"
                        size="lg"
                        label="{{ __('Pose') }}"
                        x-model="uploadForm.pose"
                    >
                        <option value="half_body">{{ __('Half body') }}</option>
                        <option value="close_up">{{ __('Close up') }}</option>
                        <option value="full_body">{{ __('Full body') }}</option>
                    </x-forms.input>

                    <x-forms.input
                        id="ai_avatar_style"
                        name="ai_avatar_style"
                        type="select"
                        size="lg"
                        label="{{ __('Style') }}"
                        x-model="uploadForm.style"
                    >
                        <option value="Realistic">{{ __('Realistic') }}</option>
                        <option value="Pixar">{{ __('Pixar') }}</option>
                        <option value="Cinematic">{{ __('Cinematic') }}</option>
                        <option value="Vintage">{{ __('Vintage') }}</option>
                        <option value="Noir">{{ __('Noir') }}</option>
                        <option value="Cyberpunk">{{ __('Cyberpunk') }}</option>
                        <option value="Unspecified">{{ __('Unspecified') }}</option>
                    </x-forms.input>
                </div>

                <x-forms.input
                    id="ai_avatar_appearance"
                    name="ai_avatar_appearance"
                    size="lg"
                    rows="6"
                    label="{{ __('Describe Your Avatar') }}"
                    type="textarea"
                    x-model="uploadForm.appearance"
                    placeholder="{{ __('The avatar\'s appearance — clothing, mood, lighting, background, etc.') }}"
                    maxlength="1000"
                />
            </div>

            @if ($app_is_demo)
                <x-button
                    class="w-full text-xs"
                    type="button"
                    size="lg"
                    variant="secondary"
                    @click.prevent="toastr.info('{{ __('This feature is disabled in Demo version.') }}');"
                >
                    <span x-text="uploadModalActiveTab === 'upload' ? '{{ __('Upload Avatar') }}' : '{{ __('Create Your Avatar') }}'"></span>
                    <span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                        <x-tabler-chevron-right class="size-4" />
                    </span>
                </x-button>
            @else
                <x-button
                    class="w-full text-xs"
                    type="submit"
                    size="lg"
                    variant="secondary"
                    ::disabled="creatingAvatar"
                >
                    <x-tabler-refresh
                        class="me-2 size-4 animate-spin"
                        x-show="creatingAvatar"
                        x-cloak
                    />
                    <span
                        x-text="creatingAvatar
                        ? '{{ __('Submitting...') }}'
                        : (uploadModalActiveTab === 'upload' ? '{{ __('Upload Avatar') }}' : '{{ __('Create Your Avatar') }}')"
                    ></span>
                    <span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                        <x-tabler-chevron-right class="size-4" />
                    </span>
                </x-button>
            @endif
        </form>
    </x-slot:modal>
</x-modal>
