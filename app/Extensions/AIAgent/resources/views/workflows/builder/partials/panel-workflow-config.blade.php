{{-- Workflow Settings Panel --}}
<div class="space-y-5">

    {{-- Avatar --}}
    <div>
        <label class="mb-2 block text-xs font-medium text-label">{{ __('Avatar') }}</label>

        <div class="grid grid-cols-5 gap-2">
            {{-- Custom uploaded avatar (shown as first selected grid item when set) --}}
            <template x-if="workflowAvatarUrl && !avatarPresets.some(p => p.url === workflowAvatarUrl)">
                <div class="group/customav relative">
                    <div class="relative flex items-center justify-center rounded-full border-2 border-primary p-0.5 shadow-md shadow-primary/20">
                        <img
                            class="size-10 rounded-full object-cover"
                            :src="workflowAvatarUrl"
                            alt=""
                        >
                        <span class="pointer-events-none absolute end-0 top-0 grid size-4 -translate-y-1 translate-x-1 scale-100 place-items-center rounded-full bg-primary text-white">
                            <x-tabler-check class="size-2.5" />
                        </span>
                    </div>
                    <button
                        class="absolute -end-1 -top-1 hidden size-4 items-center justify-center rounded-full bg-red-500 text-white group-hover/customav:flex"
                        type="button"
                        @click="workflowAvatarUrl = null; workflowAvatar = null"
                        title="{{ __('Remove') }}"
                    >
                        <x-tabler-x class="size-2.5" />
                    </button>
                </div>
            </template>

            {{-- Preset avatars --}}
            <template x-for="preset in avatarPresets" :key="'preset-' + preset.id">
                <button
                    class="relative flex items-center justify-center rounded-full border-2 p-0.5 transition-all hover:scale-105"
                    :class="workflowAvatarUrl === preset.url
                        ? 'border-primary shadow-md shadow-primary/20'
                        : 'border-transparent hover:border-border'"
                    type="button"
                    @click="selectAvatarPreset(preset.url)"
                    ::disabled="avatarUploading"
                >
                    <img
                        class="size-10 rounded-full object-cover"
                        :src="preset.url"
                        alt=""
                    >
                    <span
                        class="pointer-events-none absolute end-0 top-0 grid size-4 -translate-y-1 translate-x-1 scale-75 place-items-center rounded-full bg-primary text-white opacity-0 transition-all"
                        :class="workflowAvatarUrl === preset.url && 'opacity-100 scale-100'"
                    >
                        <x-tabler-check class="size-2.5" />
                    </span>
                </button>
            </template>

            {{-- Custom upload button --}}
            <label
                class="flex size-[52px] cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-border text-foreground/40 transition-all hover:scale-105 hover:border-primary hover:text-primary"
                :class="avatarUploading && 'opacity-60 pointer-events-none'"
                title="{{ __('Upload custom image') }}"
            >
                <template x-if="!avatarUploading">
                    <x-tabler-plus class="size-5" />
                </template>
                <template x-if="avatarUploading">
                    <x-tabler-loader-2 class="size-5 animate-spin" />
                </template>
                <input
                    class="hidden"
                    type="file"
                    accept="image/*"
                    @change="uploadAvatar($event)"
                    ::disabled="avatarUploading"
                >
            </label>
        </div>
    </div>

    {{-- Name --}}
    <div>
        <label class="mb-1.5 block text-xs font-medium text-label">{{ __('Agent Name') }}</label>
        <input
            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary"
            type="text"
            x-model="workflowName"
            placeholder="{{ __('Untitled Agent') }}"
        >
    </div>

    {{-- Role --}}
    <div>
        <label class="mb-1.5 block text-xs font-medium text-label">{{ __('Agent Role') }}</label>
        <input
            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary"
            type="text"
            x-model="workflowRole"
            placeholder="{{ __('e.g. Customer Support Agent') }}"
        >
        <p class="mt-1 text-[10px] text-foreground/40">{{ __('Describes what this agent does.') }}</p>
    </div>

    @if(class_exists(\App\Extensions\AIAgentGmail\System\AIAgentGmailServiceProvider::class))
    {{-- Connectors --}}
    <div>
        <label class="mb-1.5 block text-xs font-medium text-label">{{ __('Connectors') }}</label>
        <button
            class="flex w-full items-center gap-2 rounded-xl border border-border px-3 py-2.5 text-sm text-foreground/60 transition-colors hover:border-primary/30 hover:bg-primary/5 hover:text-foreground"
            type="button"
            @click="connectorsModal.open = true; connectorsModal.tab = 'list'"
        >
            <x-tabler-plug class="size-4 text-foreground/40" />
            <span>{{ __('Manage Connectors') }}</span>
            <span
                class="ms-auto inline-flex items-center justify-center rounded-full bg-foreground/10 px-1.5 py-0.5 text-[10px] font-semibold leading-none"
                x-show="connectors.length > 0"
                x-text="connectors.length"
            ></span>
            <x-tabler-chevron-right class="size-3.5 text-foreground/30" x-show="connectors.length === 0" />
        </button>
        <p class="mt-1 text-[10px] text-foreground/40">{{ __('Gmail, Outlook, and other service integrations.') }}</p>
    </div>
    @endif

</div>
