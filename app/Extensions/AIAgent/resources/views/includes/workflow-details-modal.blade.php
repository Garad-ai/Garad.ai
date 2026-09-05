<x-modal
    class="max-lg:w-full"
    class:modal-content="flex flex-col p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none max-h-[calc(100vh-4rem)] !min-w-0 w-[min(940px,100%)]"
    class:modal-head="px-0 shrink-0"
    class:modal-title="text-[12px] font-semibold"
    class:modal-body="p-0 flex flex-col grow"
    class:modal-container="flex flex-col grow w-full"
    class:close-btn="size-7"
    class:close-btn-icon="size-4"
    id="ai-agent-details-modal"
    title="{{ __('Agent Details') }}"
    @modal-toggle.window="if ( $event.detail.open && $event.detail.el.id === 'ai-agent-connectors-modal' ) { lockUnlockModal(true) } else { lockUnlockModal(false) }"
>
    <x-slot:trigger
        class="max-lg:w-full max-lg:justify-start max-lg:px-0 max-lg:text-start max-lg:outline-none"
        variant="outline"
        @click="mobile.menuOpen = false"
    >
        {{ __('Details') }}
    </x-slot:trigger>
    <x-slot:modal>
        {{-- Workflow Settings Panel --}}
        <div class="space-y-5 py-5">

            {{-- Avatar --}}
            <div>
                <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                    {{ __('Avatar') }}
                </label>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-9 lg:gap-10">
                    <div class="relative grid size-11 grid-cols-1 place-items-center rounded-full bg-foreground/5 transition hover:bg-primary hover:text-primary-foreground">
                        <div
                            class="col-start-1 col-end-2 row-start-1 row-end-2 contents size-full"
                            x-show="!workflowAvatarUrl || avatarPresets.some(p => p.url === workflowAvatarUrl)""
                        >
                            <template x-if="!avatarUploading">
                                <x-tabler-plus class="col-start-1 col-end-2 row-start-1 row-end-2 size-4" />
                            </template>
                            <template x-if="avatarUploading">
                                <x-tabler-loader-2 class="col-start-1 col-end-2 row-start-1 row-end-2 size-4 animate-spin" />
                            </template>

                            {{-- Custom upload button --}}
                            <label
                                class="absolute inset-0 cursor-pointer"
                                :class="{ 'opacity-60 pointer-events-none': avatarUploading }"
                                title="{{ __('Upload custom image') }}"
                            >
                                <input
                                    class="hidden"
                                    type="file"
                                    accept="image/*"
                                    @change="uploadAvatar($event)"
                                    ::disabled="avatarUploading"
                                >
                            </label>
                        </div>

                        {{-- Custom uploaded avatar (shown as first selected grid item when set) --}}
                        <template x-if="workflowAvatarUrl && !avatarPresets.some(p => p.url === workflowAvatarUrl)">
                            <div class="group/customav relative col-start-1 col-end-2 row-start-1 row-end-2 rounded-full ring-2 ring-primary ring-offset-2">
                                <img
                                    class="size-full rounded-full object-cover"
                                    :src="workflowAvatarUrl"
                                    alt=""
                                >
                                <button
                                    class="absolute -end-1 -top-1 hidden size-5 items-center justify-center rounded-full bg-red-500 text-white group-hover/customav:flex"
                                    type="button"
                                    @click="workflowAvatarUrl = null; workflowAvatar = null"
                                    title="{{ __('Remove') }}"
                                >
                                    <x-tabler-x class="size-2.5" />
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Preset avatars --}}
                    <template
                        x-for="preset in avatarPresets"
                        :key="'preset-' + preset.id"
                    >
                        <button
                            class="relative size-11 rounded-full ring-2 ring-offset-2 transition hover:scale-105"
                            :class="{ 'ring-primary': workflowAvatarUrl === preset.url, 'ring-transparent': workflowAvatarUrl !== preset.url }"
                            type="button"
                            @click="selectAvatarPreset(preset.url)"
                            ::disabled="avatarUploading"
                        >
                            <img
                                class="size-full rounded-full object-contain object-center"
                                :src="preset.url"
                                alt=""
                            >
                        </button>
                    </template>
                </div>
            </div>

            {{-- Name --}}
            <x-forms.input
                size="lg"
                type="text"
                label="{{ __('Agent Name') }}"
                placeholder="{{ __('Untitled Agent') }}"
                x-model="workflowName"
            />

            {{-- Description --}}
            <x-forms.input
                size="lg"
                type="textarea"
                rows="3"
                label="{{ __('Description') }}"
                placeholder="{{ __('Short description shown on agent cards and conversation details.') }}"
                x-model="workflowDescription"
                tooltip="{{ __('A brief summary of what this agent does. Displayed on agent cards and the messages contact-info panel.') }}"
            />

            {{-- Role --}}
            <x-forms.input
                size="lg"
                type="textarea"
                rows="5"
                label="{{ __('Agent Role') }}"
                placeholder="{{ __('Goal: Help users create high-quality marketing content for social media, blogs, and ads while maintaining a consistent brand voice. Approach: 	Analyze the user’s input, target audience, and preferred tone. Generate concise, engaging, and conversion-focused content with clear structure and modern copywriting techniques. Constraints Keep responses short and actionable Avoid repetitive or generic phrasing Do not generate misleading or false claims Maintain brand consistency across all outputs Prefer clean formatting and easy readability') }}"
                x-model="workflowRole"
                tooltip="{{ __('Describes what this agent does.') }}"
            />

            @if (class_exists(\App\Extensions\AIAgentGmail\System\AIAgentGmailServiceProvider::class))
                @include('ai-agent::includes.connectors-modal')
            @endif
        </div>
    </x-slot:modal>
</x-modal>
