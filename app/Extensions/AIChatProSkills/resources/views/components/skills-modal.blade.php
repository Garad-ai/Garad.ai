@php
    $skillCreator = \App\Extensions\AIChatProSkills\System\Models\Skill::query()->where('source', 'seeded')->where('slug', 'skill-creator')->active()->first();
@endphp

<div x-data="skillsModal()">
    <template x-teleport="body">
        <div
            class="lqd-modal lqd-modal-skills"
            :class="{ 'modal-open': open }"
        >
            <div
                class="lqd-modal-modal fixed inset-0 z-[990] flex items-center justify-center overflow-y-auto overscroll-contain px-4"
                x-cloak
                x-show="open"
                @keydown.escape.window="!uploadModalOpen && !detailModalOpen && close()"
            >
                {{-- Backdrop --}}
                <div
                    class="lqd-modal-backdrop fixed inset-0 bg-black/5 backdrop-blur-sm"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="close()"
                ></div>

                {{-- Content --}}
                <div
                    class="lqd-modal-content relative z-[100] h-[min(715px,95vh)] w-[min(100%,1000px)] overflow-y-auto overscroll-contain rounded-xl bg-surface-background px-5 py-5 shadow-2xl shadow-black/10 md:px-10"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-[0.98] translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-[0.98] translate-y-1"
                    @click.outside="!uploadModalOpen && !detailModalOpen && close()"
                >
                    {{-- Header --}}
                    <div class="lqd-modal-head flex items-center gap-3 border-b border-foreground/5 pb-3">
                        <div class="grow">
                            <h4 class="lqd-modal-title m-0 text-base font-semibold">
                                {{ __('Skills') }}
                            </h4>
                            <p class="m-0 text-2xs text-foreground/60">
                                {{ __('Instructions that extend the capabilities of AI') }}
                            </p>
                        </div>

                        <button
                            class="lqd-modal-close ms-auto inline-flex size-8 items-center justify-center rounded-lg transition-all hover:bg-foreground/10"
                            @click="close()"
                            type="button"
                        >
                            <x-tabler-x class="size-4" />
                        </button>
                    </div>

                    {{-- Tabs + Actions --}}
                    <div class="flex items-center justify-between gap-3 py-2 max-md:flex-wrap">
                        <div class="flex grow items-center gap-4 overflow-x-auto max-md:w-full">
                            <template
                                x-for="tab in tabs"
                                :key="tab.key"
                            >
                                <button
                                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-1.5 text-2xs font-medium leading-none text-heading-foreground transition [&.active]:bg-secondary [&.active]:text-secondary-foreground"
                                    :class="{ 'active': activeTab === tab.key }"
                                    @click="switchTab(tab.key)"
                                    x-show="!isGuest || tab.key !== 'personal'"
                                    type="button"
                                >
                                    <span x-text="tab.label"></span>
                                    <span
                                        class="opacity-35"
                                        x-text="tab.count"
                                    ></span>
                                </button>
                            </template>
                        </div>

                        <div class="flex items-center gap-3 max-md:w-full max-md:flex-wrap">
                            <x-button
                                class="bg-transparent text-xs font-medium"
                                type="button"
                                x-show="!isGuest"
                                variant="ghost-shadow"
                                @click="showUploadModal()"
                            >
                                {{ __('Upload Skill') }}
                            </x-button>

                            @if ($skillCreator)
                                <x-button
                                    class="bg-transparent text-xs font-medium"
                                    variant="ghost-shadow"
                                    type="button"
                                    x-show="!isGuest"
                                    @click="useSkillCreator()"
                                >
                                    {{ __('Add Skill') }}
                                </x-button>
                            @endif

                            <div class="relative grow max-md:w-full">
                                <x-tabler-search class="pointer-events-none absolute start-4 top-1/2 size-4 -translate-y-1/2 text-foreground" />
                                <input
                                    class="h-[38px] w-full rounded-full border-none bg-clay ps-12 font-medium placeholder:text-foreground sm:text-2xs"
                                    type="text"
                                    placeholder="{{ __('Search') }}"
                                    x-model="searchQuery"
                                    @input.debounce.300ms="fetchSkills"
                                />
                            </div>
                        </div>
                    </div>

                    {{-- Skills List --}}
                    <div class="lqd-modal-body">
                        <template x-if="loading">
                            <div class="flex items-center justify-center py-8">
                                <x-tabler-loader-2 class="size-5 animate-spin text-primary" />
                            </div>
                        </template>

                        <template x-if="!loading && skills.length === 0">
                            <div class="py-8 text-center">
                                <x-tabler-tool class="mx-auto mb-2 size-8 text-foreground/30" />
                                <p class="text-xs text-foreground/50">{{ __('No skills found') }}</p>
                            </div>
                        </template>

                        <template x-if="!loading && skills.length > 0">
                            <div>
                                <template
                                    x-for="skill in skills"
                                    :key="skill.id"
                                >
                                    <div
                                        class="flex gap-3 border-b border-foreground/5"
                                        x-data="{ menuOpen: false }"
                                    >
                                        <div
                                            class="group min-w-0 flex-1 cursor-pointer py-5"
                                            @click="viewSkill(skill)"
                                        >
                                            <p
                                                class="mb-2 mt-0 flex items-center gap-1.5 truncate text-2xs font-medium text-heading-foreground group-hover:underline group-hover:underline-offset-2">
                                                <x-tabler-clipboard class="size-4 shrink-0 opacity-70" />
                                                <span x-text="skill.name"></span>
                                            </p>
                                            <p
                                                class="m-0 truncate text-xs"
                                                x-text="skill.description?.length > 80 ? skill.description.substring(0, 80) + '...' : skill.description"
                                            ></p>
                                        </div>

                                        {{-- Actions --}}
                                        <div class="flex shrink-0 items-center gap-2">
                                            <div
                                                class="relative"
                                                @click.outside="menuOpen = false"
                                            >
                                                <button
                                                    class="group/actions-toggle inline-flex items-center rounded-full border border-foreground/5 text-3xs font-semibold leading-tight transition [&.is-added]:text-heading-foreground"
                                                    type="button"
                                                    :class="{ 'is-added': skill.is_added }"
                                                    @click.prevent="!skill.is_added && addSkill(skill)"
                                                >
                                                    <template x-if="!skill.is_added">
                                                        <span class="px-3 py-2">
                                                            {{ __('+ Add') }}
                                                        </span>
                                                    </template>
                                                    <template x-if="skill.is_added">
                                                        <span class="inline-flex">
                                                            <span class="inline-flex items-center py-2 pe-1.5 ps-3 group-hover/actions-toggle:hidden">
                                                                {{ __('Added') }}
                                                            </span>
                                                            <span
                                                                class="hidden items-center gap-1 rounded-s-full py-2 pe-1.5 ps-3 text-red-500 transition hover:bg-red-500 hover:text-white group-hover/actions-toggle:inline-flex"
                                                                @click.prevent="removeSkill(skill); menuOpen = false"
                                                            >
                                                                <x-tabler-x class="size-3.5" />
                                                                {{ __('Remove') }}
                                                            </span>
                                                            <span
                                                                class="inline-flex items-center rounded-e-full py-2 pe-3 ps-1.5 transition hover:bg-heading-foreground hover:text-background"
                                                                @click.prevent="menuOpen = !menuOpen"
                                                            >
                                                                <x-tabler-dots class="size-4" />
                                                            </span>
                                                        </span>
                                                    </template>
                                                </button>

                                                <template x-if="skill.is_added">
                                                    <div
                                                        class="absolute end-0 top-full z-10 mt-1.5 min-w-[200px] rounded-dropdown bg-dropdown-background px-5 py-1 shadow-xl shadow-black/5"
                                                        x-show="menuOpen"
                                                        x-transition
                                                        x-cloak
                                                    >
                                                        <button
                                                            class="flex w-full items-center justify-start gap-2 whitespace-nowrap py-5 text-start text-2xs font-medium"
                                                            @click="toggleAutoUse(skill);"
                                                            type="button"
                                                        >
                                                            {{ __('Auto Use in Chat') }}
                                                            <span
                                                                class="ms-auto flex h-5 w-[37px] shrink-0 items-center rounded-full px-0.5 transition-colors"
                                                                :class="skill.auto_use ? 'bg-primary' : 'bg-foreground/20'"
                                                            >
                                                                <span
                                                                    class="size-4 rounded-full bg-white shadow transition-transform"
                                                                    :class="skill.auto_use ? 'translate-x-full' : 'translate-x-0'"
                                                                ></span>
                                                            </span>
                                                        </button>
                                                        <template x-if="skill.is_owner">
                                                            <button
                                                                class="flex w-full items-center gap-2 whitespace-nowrap border-t border-foreground/5 py-5 text-2xs font-medium text-red-500 hover:underline hover:underline-offset-2"
                                                                @click="deleteSkill(skill); menuOpen = false"
                                                                type="button"
                                                            >
                                                                <x-tabler-trash class="size-4" />
                                                                {{ __('Delete Skill') }}
                                                            </button>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Skill View Detail Modal --}}
    <template x-teleport="body">
        <div
            class="lqd-modal lqd-modal-skill-detail"
            :class="{ 'modal-open': detailModalOpen }"
            @click.stop
        >
            <div
                class="lqd-modal-modal fixed inset-0 z-[1000] flex items-center justify-center overflow-y-auto overscroll-contain px-4"
                x-cloak
                x-show="detailModalOpen"
                @keydown.escape.window="detailModalOpen && (detailModalOpen = false)"
            >
                {{-- Backdrop --}}
                <div
                    class="lqd-modal-backdrop fixed inset-0 bg-black/5 backdrop-blur-sm"
                    x-show="detailModalOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="detailModalOpen = false"
                ></div>

                {{-- Content --}}
                <div
                    class="lqd-modal-content relative z-[100] flex h-[min(715px,95vh)] w-[min(100%,1000px)] flex-col rounded-xl bg-surface-background px-5 py-5 shadow-2xl shadow-black/10 max-md:overflow-y-auto md:px-10"
                    x-show="detailModalOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-[0.98] translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-[0.98] translate-y-1"
                    @click.outside="detailModalOpen = false"
                >
                    {{-- Header --}}
                    <div class="lqd-modal-head flex items-center gap-3 border-b border-foreground/5 pb-3 max-md:flex-wrap">
                        <div class="flex items-center gap-2.5 max-md:w-full max-md:flex-wrap">
                            <h4
                                class="lqd-modal-title m-0 text-base font-semibold"
                                x-text="detailSkill?.name ?? detailSkill?.slug"
                            ></h4>
                            <span
                                class="whitespace-nowrap text-[12px] opacity-70"
                                x-text="detailSkill?.is_public ? '{{ __('Public Skill') }}' : '{{ __('Personal Skill') }}'"
                            ></span>
                        </div>
                        <div class="ms-auto flex items-center gap-2 max-md:order-first max-md:w-full max-md:justify-end">
                            <template x-if="detailSkill && !detailSkill.is_added">
                                <x-button
                                    class="px-3 py-2 text-2xs font-medium leading-tight"
                                    href="javascript:void(0)"
                                    variant="outline"
                                    @click="addSkill(detailSkill)"
                                >
                                    {{ __('+ Add Skill') }}
                                </x-button>
                            </template>
                            <template x-if="detailSkill && detailSkill.is_added">
                                <x-button
                                    class="px-3 py-2 text-2xs font-medium leading-tight"
                                    href="javascript:void(0)"
                                    variant="danger"
                                    @click="removeSkill(detailSkill)"
                                >
                                    <x-tabler-x class="size-4" />
                                    {{ __('Remove') }}
                                </x-button>
                            </template>
                            <button
                                class="lqd-modal-close inline-flex size-8 items-center justify-center rounded-lg transition-all hover:bg-foreground/10"
                                @click="detailModalOpen = false"
                                type="button"
                            >
                                <x-tabler-x class="size-4" />
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="flex min-h-0 flex-1 pt-3">
                        {{-- Loading --}}
                        <template x-if="detailLoading">
                            <div class="flex flex-1 items-center justify-center">
                                <x-tabler-loader-2 class="size-6 animate-spin text-primary" />
                            </div>
                        </template>

                        <template x-if="!detailLoading && detailSkill">
                            <div class="flex min-h-0 flex-1 gap-3 max-md:flex-wrap">
                                {{-- File Tree Sidebar --}}
                                <div class="w-52 shrink-0 overflow-y-auto px-4 py-2 max-md:max-h-36 max-md:w-full">
                                    <template
                                        x-for="item in detailFileTree"
                                        :key="item.name"
                                    >
                                        <div>
                                            {{-- File --}}
                                            <template x-if="item.type === 'file'">
                                                <button
                                                    class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-[12px] transition-colors hover:bg-foreground/5 [&.active]:bg-foreground/5 [&.active]:font-medium"
                                                    :class="{ 'active': detailActiveFile === item.name }"
                                                    @click="selectDetailFile(item.path || item.name, item.name)"
                                                    type="button"
                                                >
                                                    <x-tabler-clipboard class="size-4 shrink-0 text-foreground/40" />
                                                    <span x-text="item.name"></span>
                                                </button>
                                            </template>

                                            {{-- Folder --}}
                                            <template x-if="item.type === 'folder'">
                                                <div>
                                                    <button
                                                        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-[12px] transition-colors hover:bg-foreground/5"
                                                        @click="item.open = !item.open"
                                                        type="button"
                                                    >
                                                        <x-tabler-chevron-down
                                                            class="size-3 shrink-0 text-foreground/40 transition-transform"
                                                            ::class="{ '-rotate-90': !item.open }"
                                                        />
                                                        <x-tabler-folder class="size-4 shrink-0 text-foreground/40" />
                                                        <span x-text="item.name"></span>
                                                    </button>
                                                    <div
                                                        class="ms-3"
                                                        x-show="item.open"
                                                        x-collapse
                                                    >
                                                        <template
                                                            x-for="child in (item.children || [])"
                                                            :key="child.name"
                                                        >
                                                            <button
                                                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-xs transition-colors hover:bg-foreground/5 [&.active]:bg-foreground/5 [&.active]:font-medium"
                                                                :class="{ 'active': detailActiveFile === child.name }"
                                                                @click="selectDetailFile(child.path || child.name, child.name)"
                                                                type="button"
                                                            >
                                                                <x-tabler-file class="size-4 shrink-0 text-foreground/40" />
                                                                <span x-text="child.name"></span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                {{-- Content Pane --}}
                                <div
                                    class="flex-1 rounded-[20px] border border-foreground/5 p-6 md:overflow-y-auto md:px-7 md:py-5.5 [&_pre]:whitespace-pre-wrap [&_pre_code]:whitespace-pre-wrap">
                                    <div>
                                        <h2
                                            class="m-0 text-lg font-bold text-heading-foreground"
                                            x-text="detailSkill.name"
                                        ></h2>
                                        <p
                                            class="m-0 mt-1 text-sm text-foreground/60"
                                            x-text="detailSkill.description"
                                        ></p>
                                    </div>

                                    <div class="mt-4 rounded-lg bg-foreground/[3%] px-3 py-2">
                                        <code
                                            class="text-xs text-foreground/70"
                                            x-text="'/skills/' + (detailSkill.slug || 'skill') + '/SKILL.md'"
                                        ></code>
                                    </div>

                                    <div
                                        class="prose prose-sm mt-6 max-w-none text-foreground [&_h1]:text-lg [&_h1]:font-bold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:text-sm [&_h3]:font-semibold [&_li]:text-sm [&_p]:text-sm [&_strong]:font-semibold"
                                        x-html="detailRenderedHtml"
                                    ></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Upload Skill Modal (User) --}}
    <template x-teleport="body">
        <div
            class="lqd-modal lqd-modal-upload-skill"
            :class="{ 'modal-open': uploadModalOpen }"
            @click.stop
        >
            <div
                class="lqd-modal-modal fixed inset-0 z-[995] flex items-center justify-center px-4"
                x-cloak
                x-show="uploadModalOpen"
                @keydown.escape.window="uploadModalOpen && (uploadModalOpen = false)"
            >
                {{-- Backdrop --}}
                <div
                    class="lqd-modal-backdrop fixed inset-0 bg-black/50 backdrop-blur-sm"
                    x-show="uploadModalOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="uploadModalOpen = false"
                ></div>

                {{-- Content --}}
                <div
                    class="lqd-modal-content relative z-[100] w-[min(100%,760px)]"
                    x-show="uploadModalOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-[0.98] translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-[0.98] translate-y-1"
                >
                    {{-- Header --}}
                    <div class="lqd-modal-head flex">
                        <button
                            class="lqd-modal-close ms-auto inline-grid size-9 place-items-center rounded-full bg-surface-background md:absolute md:-end-14 md:top-0"
                            @click="uploadModalOpen = false"
                            type="button"
                        >
                            <x-tabler-x class="size-4" />
                        </button>
                    </div>

                    <div
                        class="lqd-modal-body max-h-[min(580px,95vh)] overflow-y-auto overscroll-contain rounded-xl bg-surface-background px-5 py-5 shadow-2xl shadow-black/10 md:px-10 md:pb-10 md:pt-6">
                        <div class="mb-5 flex gap-5 border-b border-foreground/5">
                            <button
                                class="py-3 text-[12px] font-semibold leading-tight text-foreground/50 transition-colors [&.active]:text-primary"
                                :class="{ 'active': uploadTab === 'file' }"
                                @click="uploadTab = 'file'"
                                type="button"
                            >{{ __('Upload Your Skill') }}</button>
                            <button
                                class="py-3 text-[12px] font-semibold leading-tight text-foreground/50 transition-colors [&.active]:text-primary"
                                :class="{ 'active': uploadTab === 'github' }"
                                @click="uploadTab = 'github'"
                                type="button"
                            >{{ __('Import from GitHub') }}</button>
                        </div>

                        <div x-show="uploadTab === 'file'">
                            <div
                                class="flex flex-col items-center rounded-[20px] border border-dashed p-6 text-center transition-colors"
                                :class="uploadDragover ? 'border-primary bg-primary/5' : 'border-border'"
                                @dragover.prevent="uploadDragover = true"
                                @dragleave.prevent="uploadDragover = false"
                                @drop.prevent="uploadDragover = false; uploadFile = $event.dataTransfer?.files?.[0]; uploadFileName = uploadFile?.name || ''"
                            >
                                {{-- blade-formatter-disable --}}
                                <svg class="mb-4" width="108" height="91" viewBox="0 0 108 91" fill="none" xmlns="http://www.w3.org/2000/svg"> <g filter="url(#filter0_d_1550_1109)"> <path d="M41.6052 24.9001C43.8943 24.6174 45.0389 24.4761 46.1303 24.7526C47.2217 25.029 48.1609 25.6983 50.0393 27.0367L52.8666 29.0513L55.1952 30.7104C57.3131 32.2195 58.372 32.974 59.0176 34.0577C59.6632 35.1413 59.8226 36.4318 60.1413 39.0127L62.8033 60.5711C63.4966 66.1853 63.8432 68.9924 62.3144 70.9519C60.7857 72.9114 57.9786 73.258 52.3644 73.9513L35.1562 76.0761C29.542 76.7694 26.7349 77.116 24.7754 75.5873C22.8159 74.0585 22.4693 71.2514 21.776 65.6372L18.5897 39.8332C17.8965 34.219 17.5499 31.4119 19.0786 29.4524C20.6074 27.4929 23.4145 27.1463 29.0287 26.453L41.6052 24.9001Z" fill="white"/> </g> <rect x="29.0049" y="42.5775" width="20" height="4" rx="2" transform="rotate(-7.0393 29.0049 42.5775)" fill="#E0E0E0"/> <rect x="30.6631" y="58.716" width="17" height="4" rx="2" transform="rotate(-7.0393 30.6631 58.716)" fill="#E0E0E0"/> <rect x="29.9854" y="50.5172" width="13" height="4" rx="2" transform="rotate(-7.0393 29.9854 50.5172)" fill="#E0E0E0"/> <g filter="url(#filter1_d_1550_1109)"> <path d="M75.2221 27.1248C77.4938 27.5238 78.6297 27.7232 79.5926 28.3067C80.5554 28.8902 81.258 29.8048 82.663 31.6339L84.7778 34.3871L86.5196 36.6545C88.1037 38.7168 88.8958 39.748 89.1964 40.973C89.497 42.1981 89.2721 43.4788 88.8223 46.0401L85.0652 67.4348C84.0868 73.0064 83.5976 75.7922 81.5628 77.2192C79.5279 78.6461 76.7421 78.1569 71.1705 77.1785L54.093 74.1795C48.5214 73.2011 45.7356 72.7119 44.3087 70.6771C42.8818 68.6422 43.371 65.8564 44.3494 60.2848L48.8463 34.6767C49.8248 29.1051 50.314 26.3193 52.3488 24.8924C54.3836 23.4655 57.1694 23.9547 62.741 24.9331L75.2221 27.1248Z" fill="white"/> </g> <rect x="58.0039" y="40.346" width="20" height="4" rx="2" transform="rotate(9.96 58.0039 40.346)" fill="#E0E0E0"/> <rect x="54.8721" y="56.2641" width="14.3984" height="4" rx="2" transform="rotate(9.96 54.8721 56.2641)" fill="#E0E0E0"/> <rect x="56.6201" y="48.2254" width="13" height="4" rx="2" transform="rotate(9.96 56.6201 48.2254)" fill="#E0E0E0"/> <defs> <filter id="filter0_d_1550_1109" x="0" y="0.603195" width="81.3926" height="88.0626" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB"> <feFlood flood-opacity="0" result="BackgroundImageFix"/> <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/> <feOffset dy="-6"/> <feGaussianBlur stdDeviation="9"/> <feComposite in2="hardAlpha" operator="out"/> <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.05 0"/> <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_1550_1109"/> <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_1550_1109" result="shape"/> </filter> <filter id="filter1_d_1550_1109" x="25.416" y="0" width="81.917" height="90.1115" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB"> <feFlood flood-opacity="0" result="BackgroundImageFix"/> <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/> <feOffset dy="-6"/> <feGaussianBlur stdDeviation="9"/> <feComposite in2="hardAlpha" operator="out"/> <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.05 0"/> <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_1550_1109"/> <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_1550_1109" result="shape"/> </filter> </defs> </svg>
								{{-- blade-formatter-enable --}}
                                <p
                                    class="mb-5 text-base font-semibold"
                                    x-show="!uploadFileName"
                                >
                                    {{ __('Drag and drop .skill, .zip, or .md file here') }}
                                </p>
                                <p
                                    class="mb-5 text-base font-semibold"
                                    x-show="uploadFileName"
                                    x-text="uploadFileName"
                                    x-cloak
                                ></p>

                                <div class="mb-5 flex w-[min(100%,300px)] items-center gap-8">
                                    <span class="h-px flex-1 bg-foreground/5"></span>
                                    <span class="text-2xs">{{ __('or') }}</span>
                                    <span class="h-px flex-1 bg-foreground/5"></span>
                                </div>

                                <x-button
                                    class="mb-4.5 px-5.5 py-3 text-sm font-medium"
                                    href="javascript:void(0)"
                                    variant="outline"
                                    hover-variant="primary"
                                    type="button"
                                    @click="$refs.userFileInput.click()"
                                >
                                    {{ __('Browse Files') }}
                                </x-button>

                                <ul class="list-inside list-disc space-y-0.5 text-[12px] opacity-60">
                                    <li>
                                        {{ __('Upload a .skill or .zip file containing a SKILL.md, or upload a .md file directly.') }}
                                    </li>
                                    <li>
                                        {{ __('The SKILL.md must include name and description in YAML frontmatter.') }}
                                    </li>
                                </ul>

                                <input
                                    class="hidden"
                                    type="file"
                                    accept=".skill,.zip,.md"
                                    x-ref="userFileInput"
                                    @change="uploadFile = $event.target.files?.[0]; uploadFileName = uploadFile?.name || ''"
                                />
                            </div>

                            <div class="mt-5">
                                <x-button
                                    class="w-full p-3"
                                    href="javascript:void(0)"
                                    variant="secondary"
                                    @click="submitUserUpload()"
                                    ::disabled="!uploadFile || uploadLoading"
                                >
                                    <span x-show="!uploadLoading">
                                        {{ __('Add Skill') }}
                                    </span>

                                    <span
                                        x-show="uploadLoading"
                                        x-cloak
                                    >
                                        {{ __('Uploading...') }}
                                    </span>

                                    <span class="inline-grid size-7 place-items-center rounded-full bg-surface-background">
                                        <x-tabler-chevron-right
                                            class="size-4"
                                            x-show="!uploadLoading"
                                        />
                                    </span>
                                </x-button>
                            </div>
                        </div>

                        <div
                            x-show="uploadTab === 'github'"
                            x-cloak
                        >
                            <div x-show="!uploadShowFileList">
                                <h5 class="relative mb-2.5 text-2xs font-medium">
                                    {{ __('GitHub URL') }}

                                    <x-info-tooltip text="{{ __('Paste a repo URL to scan for all skill files, or a direct link to a specific .md file.') }}" />
                                </h5>
                                <input
                                    class="h-12 w-full rounded-xl border border-foreground/5 px-4"
                                    type="text"
                                    placeholder="https://github.com/owner/repo or .../blob/main/path/SKILL.md"
                                    x-model="uploadGithubUrl"
                                />

                                <div class="mt-5">
                                    <x-button
                                        class="w-full p-3"
                                        href="javascript:void(0)"
                                        variant="secondary"
                                        @click="scanUserGithub()"
                                        ::disabled="!uploadGithubUrl || uploadLoading"
                                    >
                                        <span x-show="!uploadLoading">
                                            {{ __('Scan Repository') }}
                                        </span>
                                        <span
                                            x-show="uploadLoading"
                                            x-cloak
                                        >
                                            {{ __('Scanning...') }}
                                        </span>
                                        <span class="inline-grid size-7 place-items-center rounded-full bg-surface-background">
                                            <x-tabler-chevron-right
                                                class="size-4"
                                                x-show="!uploadLoading"
                                            />
                                        </span>
                                    </x-button>
                                </div>
                            </div>

                            {{-- File selection list --}}
                            <div
                                x-show="uploadShowFileList"
                                x-cloak
                            >
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="m-0 text-xs font-medium text-heading-foreground">
                                        {{ __('Skills found') }}
                                        (<span x-text="uploadSelectedCount"></span>/<span x-text="uploadScannedFiles.length"></span> {{ __('selected') }})
                                    </p>
                                    <button
                                        class="text-2xs text-primary hover:underline"
                                        type="button"
                                        @click="uploadShowFileList = false; uploadScannedFiles = []"
                                    >
                                        {{ __('Back') }}
                                    </button>
                                </div>

                                <div class="mb-3 flex items-center gap-2 border-b pb-2">
                                    <input
                                        class="size-4 rounded border-input-border text-primary accent-primary"
                                        type="checkbox"
                                        checked
                                        @change="uploadToggleAll($event.target.checked)"
                                    />
                                    <span class="text-2xs font-medium text-foreground/60">{{ __('Select / Deselect All') }}</span>
                                </div>

                                <div class="max-h-[250px] overflow-y-auto">
                                    <template
                                        x-for="(file, index) in uploadScannedFiles"
                                        :key="file.path"
                                    >
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-2 transition-colors hover:bg-foreground/[3%]">
                                            <input
                                                class="mt-0.5 size-4 rounded border-input-border text-primary accent-primary"
                                                type="checkbox"
                                                x-model="file.selected"
                                            />
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="m-0 truncate text-xs font-medium text-heading-foreground"
                                                    x-text="file.name"
                                                ></p>
                                                <p class="m-0 truncate text-2xs text-foreground/40">
                                                    <span x-text="file.folder"></span>
                                                    <template x-if="file.file_count > 0">
                                                        <span
                                                            class="ms-1 text-foreground/30"
                                                            x-text="'(' + file.file_count + ' bundled files)'"
                                                        ></span>
                                                    </template>
                                                </p>
                                            </div>
                                        </label>
                                    </template>
                                </div>

                                <div class="mt-4">
                                    <x-button
                                        class="w-full"
                                        href="javascript:void(0)"
                                        variant="secondary"
                                        size="sm"
                                        @click="importUserSelected()"
                                        ::disabled="uploadSelectedCount === 0 || uploadLoading"
                                    >
                                        <span x-show="!uploadLoading">{{ __('Import Selected') }} (<span x-text="uploadSelectedCount"></span>)</span>
                                        <span
                                            x-show="uploadLoading"
                                            x-cloak
                                        >{{ __('Importing...') }}</span>
                                        <x-tabler-chevron-right
                                            class="size-4"
                                            x-show="!uploadLoading"
                                        />
                                    </x-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('skillsModal', () => ({
                open: false,
                loading: false,
                skills: [],
                activeTab: 'all',
                searchQuery: '',
                tabs: [{
                        key: 'all',
                        label: '{{ __('All') }}',
                        count: 0
                    },
                    {
                        key: 'public',
                        label: '{{ __('Public') }}',
                        count: 0
                    },
                    {
                        key: 'personal',
                        label: '{{ __('Personal') }}',
                        count: 0
                    },
                ],

                // Detail sub-modal
                detailModalOpen: false,
                detailSkill: null,
                detailLoading: false,
                detailRenderedHtml: '',
                detailFileTree: [],
                detailActiveFile: 'SKILL.md',
                detailFiles: {},

                // Skill Creator
                skillCreatorId: {{ $skillCreator?->id ?? 'null' }},

                // Upload sub-modal
                uploadModalOpen: false,
                uploadTab: 'file',
                uploadFile: null,
                uploadFileName: '',
                uploadGithubUrl: '',
                uploadDragover: false,
                uploadLoading: false,
                uploadScannedFiles: [],
                uploadShowFileList: false,
                skillIdMap: {},

                async init() {
                    await this.fetchSkills();

                    const chatsV2 = Alpine.store('chatsV2');

                    window.__registerSkillIdMapping = (name, id) => {
                        this.skillIdMap[name] = String(id);
                    };

                    window.addEventListener('open-skills-modal', () => {
                        this.open = true;
                    });

                    window.addEventListener('open-skill-preview', (e) => {
                        const data = e.detail;
                        if (!data) return;

                        this.detailSkill = {
                            name: data.name || 'Untitled Skill',
                            description: data.description || '',
                            slug: (data.name || 'skill').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-+$/, ''),
                            is_public: false,
                            _fromPreview: true,
                            _files: data.files || [],
                        };

                        // Build a file content map from parsed files
                        this.detailFiles = {};
                        if (data.files && Array.isArray(data.files)) {
                            data.files.forEach(f => {
                                this.detailFiles[f.path] = f.content || '';
                            });
                        }
                        // Always ensure SKILL.md is present
                        if (!this.detailFiles['SKILL.md'] && data.instructions) {
                            this.detailFiles['SKILL.md'] = data.instructions;
                        }

                        this.detailRenderedHtml = this.renderMarkdown(data.instructions || '');
                        this.detailFileTree = this.buildFileTree(data.bundled_resources);
                        this.detailActiveFile = 'SKILL.md';
                        this.detailLoading = false;
                        this.detailModalOpen = true;
                    });

                    if (chatsV2) {
                        chatsV2.$watch('selectedTools', (newVal) => {
                            const skillIds = newVal
                                .filter(t => t.startsWith('skill-'))
                                .map(t => {
                                    const skillName = t.replace('skill-', '');
                                    if (this.skillIdMap[skillName]) {
                                        return this.skillIdMap[skillName];
                                    }
                                    const skill = this.skills.find(s => s.name === skillName);
                                    return skill ? String(skill.id) : null;
                                }).filter(Boolean);

                            const input = document.getElementById('selected_skill_ids');
                            if (input) {
                                input.value = skillIds.join(',');
                            }
                        });
                    }
                },

                close() {
                    this.open = false;
                },

                escapeHtml(str) {
                    const div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                },

                requireAuth() {
                    if (this.isGuest) {
                        toastr.info('{{ __('Please login to use skills.') }}');
                        return true;
                    }
                    if (!this.canUseSkills) {
                        toastr.info('{{ __('Your current plan does not include Skills. Please upgrade your plan.') }}');
                        return true;
                    }
                    return false;
                },

                useSkillCreator() {
                    if (this.requireAuth()) return;
                    if (document.getElementById('chatbot_council_mode')?.value === '1') {
                        toastr.warning('{{ __('Skills are not supported in Council Mode. Please disable Council Mode to use skills.') }}');
                        return;
                    }
                    if (!this.skillCreatorId) return;

                    const creatorSkill = this.skills.find(s => s.id === this.skillCreatorId);
                    if (creatorSkill && !creatorSkill.is_added) {
                        this.addSkill(creatorSkill);
                    }

                    // Select the skill creator
                    const input = document.getElementById('selected_skill_ids');
                    if (input) {
                        let ids = input.value ? input.value.split(',').filter(Boolean) : [];
                        const strId = String(this.skillCreatorId);
                        if (!ids.includes(strId)) {
                            ids.push(strId);
                        }
                        input.value = ids.join(',');
                        this.updateSkillBadges(ids);
                    }

                    this.close();

                    // Focus the chat input
                    this.$nextTick(() => {
                        const textarea = document.getElementById('prompt') || document.getElementById('chat_message');
                        if (textarea) {
                            textarea.focus();
                        }
                    });
                },

                switchTab(tab) {
                    this.activeTab = tab;
                    this.fetchSkills();
                },

                isGuest: {{ auth()->check() ? 'false' : 'true' }},
                canUseSkills: {{ auth()->check() && (auth()->user()->isAdmin() || (auth()->user()->relationPlan && auth()->user()->relationPlan->checkOpenAiItem('ai_chat_pro_skills'))) ? 'true' : 'false' }},
                loginUrl: '{{ route('login') }}',

                async fetchSkills() {
                    this.loading = true;
                    try {
                        const params = new URLSearchParams({
                            tab: this.activeTab,
                            search: this.searchQuery
                        });
                        const url = this.isGuest ? `/dashboard/user/skills/public-list?${params}` : `/dashboard/user/skills?${params}`;
                        const res = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        this.skills = data.skills;
                        this.skills.forEach(s => this.skillIdMap[s.name] = String(s.id));
                        data.counts && this.tabs.forEach(t => t.count = data.counts[t.key] || 0);
                    } catch (err) {
                        console.error('Failed to fetch skills:', err);
                    } finally {
                        this.loading = false;
                    }
                },

                async addSkill(skill) {
                    if (this.requireAuth()) return;

                    // If skill came from chat preview (no ID yet), create it first
                    if (skill._fromPreview && skill._files?.length) {
                        try {
                            const res = await fetch('/dashboard/user/skills/create-from-content', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({
                                    files: skill._files
                                }),
                            });
                            const data = await res.json();
                            if (res.ok) {
                                skill.is_added = true;
                                skill.id = data.skill?.id;
                                skill._fromPreview = false;
                                toastr.success(data.message || '{{ __('Skill added.') }}');
                                this.fetchSkills();
                            } else {
                                toastr.error(data.message || '{{ __('Failed to add skill.') }}');
                            }
                        } catch (err) {
                            toastr.error('{{ __('Failed to add skill.') }}');
                        }
                        return;
                    }

                    try {
                        const res = await fetch(`/dashboard/user/skills/add/${skill.id}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            const skillToUpdate = this.skills.find(s => s.id === skill.id);
                            if (skillToUpdate) {
                                skillToUpdate.is_added = true;
                            }

                            if (this.detailSkill && this.detailSkill.id === skill.id) {
                                this.detailSkill.is_added = true;
                            }

                            toastr.success('{{ __('Skill added.') }}');
                        }
                    } catch (err) {
                        toastr.error('{{ __('Failed to add skill.') }}');
                    }
                },

                async deleteSkill(skill) {
                    if (!confirm("{{ __('Are you sure you want to permanently delete this skill?') }}")) return;
                    try {
                        const res = await fetch(`/dashboard/user/skills/delete/${skill.id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (res.ok) {
                            skill.is_added = false;
                            this.skills = this.skills.filter(s => s.id !== skill.id);
                            this.updateSelectedSkills();
                            toastr.success(data.message || '{{ __('Skill deleted.') }}');
                        } else {
                            toastr.error(data.message || "{{ __('Failed to delete skill.') }}");
                        }
                    } catch (err) {
                        toastr.error("{{ __('Failed to delete skill.') }}");
                    }
                },

                async removeSkill(skill) {
                    try {
                        const res = await fetch(`/dashboard/user/skills/remove/${skill.id}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            const skillToUpdate = this.skills.find(s => s.id === skill.id);
                            if (skillToUpdate) {
                                skillToUpdate.is_added = false;
                                skillToUpdate.auto_use = false;
                                this.updateSelectedSkills();
                            }

                            if (this.detailSkill && this.detailSkill.id === skill.id) {
                                this.detailSkill.is_added = false;
                                this.detailSkill.auto_use = false;
                            }

                            toastr.success('{{ __('Skill removed.') }}');
                        }
                    } catch (err) {
                        toastr.error('{{ __('Failed to remove skill.') }}');
                    }
                },

                async toggleAutoUse(skill) {
                    try {
                        const res = await fetch(`/dashboard/user/skills/toggle-auto/${skill.id}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (res.ok) {
                            const skillToUpdate = this.skills.find(s => s.id === skill.id);
                            if (skillToUpdate) {
                                skillToUpdate.auto_use = data.auto_use;
                            }

                            toastr.success(data.message);
                        }
                    } catch (err) {
                        toastr.error('{{ __('Failed to toggle auto-use.') }}');
                    }
                },

                selectSkill(skill) {
                    if (this.requireAuth()) return;
                    const input = document.getElementById('selected_skill_ids');
                    if (!input) return;

                    this.skillIdMap[skill.name] = String(skill.id);

                    let ids = input.value ? input.value.split(',').filter(Boolean) : [];
                    const strId = String(skill.id);

                    if (ids.includes(strId)) {
                        ids = ids.filter(id => id !== strId);
                    } else {
                        ids.push(strId);
                    }

                    input.value = ids.join(',');
                    this.updateSkillBadges(ids);
                },

                updateSelectedSkills() {
                    const input = document.getElementById('selected_skill_ids');
                    if (!input) return;

                    const currentIds = input.value ? input.value.split(',').filter(Boolean) : [];
                    const addedIds = this.skills.filter(s => s.is_added).map(s => String(s.id));
                    const validIds = currentIds.filter(id => addedIds.includes(id));
                    input.value = validIds.join(',');
                    this.updateSkillBadges(validIds);
                },

                updateSkillBadges(ids) {
                    const chatsV2 = Alpine.store('chatsV2');

                    if (!chatsV2) return;

                    ids.forEach(id => {
                        const skill = this.skills.find(s => String(s.id) === id);

                        if (!skill) return;

                        chatsV2.toggleToolSelection(`skill-${skill.name}`);
                    });
                },

                async viewSkill(skill) {
                    this.detailSkill = skill;
                    this.detailModalOpen = true;
                    this.detailLoading = true;
                    this.detailRenderedHtml = '';
                    this.detailFileTree = [];
                    this.detailActiveFile = 'SKILL.md';

                    try {
                        const showUrl = this.isGuest ? `/dashboard/user/skills/public-show/${skill.id}` : `/dashboard/user/skills/${skill.id}`;
                        const res = await fetch(showUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (data.skill) {
                            this.detailSkill = {
                                ...skill,
                                ...data.skill
                            };
                            this.detailRenderedHtml = this.renderMarkdown(data.skill.instructions || '');
                            this.detailFileTree = this.buildFileTree(data.skill.bundled_resources);
                        }
                    } catch (err) {
                        console.error('Failed to load skill detail:', err);
                    } finally {
                        this.detailLoading = false;
                    }
                },

                renderMarkdown(md) {
                    if (typeof markdownit !== 'undefined') {
                        return markdownit({
                            html: false
                        }).render(md);
                    }
                    // Basic fallback: escape HTML, convert headers/lists/bold
                    let html = md
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/^### (.+)$/gm, '<h3 class="mb-1 mt-4 text-sm font-semibold">$1</h3>')
                        .replace(/^## (.+)$/gm, '<h2 class="mb-1.5 mt-5 text-base font-semibold">$1</h2>')
                        .replace(/^# (.+)$/gm, '<h1 class="mb-2 mt-6 text-lg font-bold">$1</h1>')
                        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                        .replace(/^- (.+)$/gm, '<li class="ms-4 list-disc text-sm">$1</li>')
                        .replace(/^\d+\. (.+)$/gm, '<li class="ms-4 list-decimal text-sm">$1</li>')
                        .replace(/\n{2,}/g, '<br><br>')
                        .replace(/\n/g, '<br>');
                    return html;
                },

                buildFileTree(resources) {
                    let tree = [{
                        name: 'SKILL.md',
                        path: 'SKILL.md',
                        type: 'file',
                        active: true
                    }];
                    if (!resources || typeof resources !== 'object') return tree;

                    // Build tree from bundled_resources keys (e.g. scripts/, references/, assets/)
                    for (const [folder, files] of Object.entries(resources)) {
                        let children = [];
                        if (Array.isArray(files)) {
                            children = files.map(f => {
                                const name = typeof f === 'string' ? f : (f.name || f);
                                const path = typeof f === 'object' && f.path ? f.path : folder + '/' + name;
                                return {
                                    name,
                                    path,
                                    type: 'file'
                                };
                            });
                        }
                        tree.push({
                            name: folder,
                            type: 'folder',
                            open: true,
                            children
                        });
                    }
                    return tree;
                },

                async selectDetailFile(filePath, fileName) {
                    this.detailActiveFile = fileName;

                    // For SKILL.md, always use the instructions from detailSkill
                    if (filePath.toUpperCase() === 'SKILL.MD' || fileName.toUpperCase() === 'SKILL.MD') {
                        const instructions = this.detailSkill?.instructions || '';
                        this.detailRenderedHtml = this.renderMarkdown(instructions);
                        return;
                    }

                    // Check if we have file content cached from preview event
                    if (this.detailFiles && (this.detailFiles[filePath] || this.detailFiles[fileName])) {
                        const content = this.detailFiles[filePath] || this.detailFiles[fileName];
                        this.renderFileContent(filePath, content);
                        return;
                    }

                    // Fetch file content from the server
                    if (this.detailSkill?.id) {
                        this.detailRenderedHtml =
                            '<div class="flex items-center justify-center py-8"><svg class="size-5 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></div>';
                        try {
                            const res = await fetch(`/dashboard/user/skills/file-content/${this.detailSkill.id}?path=${encodeURIComponent(filePath)}`, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            const data = await res.json();
                            const content = data.content || '';

                            // Cache for future clicks
                            if (!this.detailFiles) this.detailFiles = {};
                            this.detailFiles[filePath] = content;

                            this.renderFileContent(filePath, content);
                        } catch (err) {
                            this.detailRenderedHtml = '<p class="text-sm text-foreground/50">{{ __('Failed to load file content.') }}</p>';
                        }
                    } else {
                        this.detailRenderedHtml = '<p class="text-sm text-foreground/50">{{ __('File content not available.') }}</p>';
                    }
                },

                renderFileContent(filePath, content) {
                    const ext = filePath.split('.').pop().toLowerCase();
                    const codeExts = ['py', 'js', 'sh', 'bash', 'json', 'yaml', 'yml', 'xml', 'css', 'html', 'php', 'rb', 'go', 'rs', 'ts', 'sql', 'txt'];
                    if (codeExts.includes(ext)) {
                        this.detailRenderedHtml = '<pre class="!whitespace-pre-wrap rounded [direction:ltr] text-sm"><code>' + this.escapeHtml(content) +
                            '</code></pre>';
                    } else {
                        this.detailRenderedHtml = this.renderMarkdown(content);
                    }
                },

                isDemo: {{ $app_is_demo ? 'true' : 'false' }},

                showUploadModal() {
                    if (this.isDemo) {
                        toastr.info('{{ __('This feature is disabled in Demo version.') }}');
                        return;
                    }
                    this.uploadModalOpen = true;
                },

                async submitUserUpload() {
                    if (!this.uploadFile) return;
                    this.uploadLoading = true;
                    const formData = new FormData();
                    formData.append('file', this.uploadFile);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content);

                    try {
                        const res = await fetch('/dashboard/user/skills/upload', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (res.ok) {
                            toastr.success(data.message);
                            this.uploadModalOpen = false;
                            this.uploadFile = null;
                            this.uploadFileName = '';
                            this.fetchSkills();
                        } else {
                            const errMsg = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                            toastr.error(errMsg || 'Upload failed.');
                        }
                    } catch (err) {
                        toastr.error('Upload failed.');
                    } finally {
                        this.uploadLoading = false;
                    }
                },

                async scanUserGithub() {
                    if (this.isDemo) {
                        toastr.info('{{ __('This feature is disabled in Demo version.') }}');
                        return;
                    }
                    if (!this.uploadGithubUrl) return;
                    this.uploadLoading = true;
                    this.uploadScannedFiles = [];
                    this.uploadShowFileList = false;
                    try {
                        const res = await fetch('/dashboard/user/skills/scan-github', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                url: this.uploadGithubUrl
                            })
                        });
                        const data = await res.json();
                        if (res.ok && data.files?.length) {
                            this.uploadScannedFiles = data.files.map(f => ({
                                ...f,
                                selected: true
                            }));
                            this.uploadShowFileList = true;
                        } else {
                            toastr.error(data.message || 'No skill files found.');
                        }
                    } catch (err) {
                        toastr.error('An error occurred.');
                    } finally {
                        this.uploadLoading = false;
                    }
                },

                get uploadSelectedCount() {
                    return this.uploadScannedFiles.filter(f => f.selected).length;
                },

                uploadToggleAll(checked) {
                    this.uploadScannedFiles.forEach(f => f.selected = checked);
                },

                async importUserSelected() {
                    const selected = this.uploadScannedFiles.filter(f => f.selected).map(f => f.path);
                    if (!selected.length) return;
                    this.uploadLoading = true;
                    try {
                        const res = await fetch('/dashboard/user/skills/import-github-bulk', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                url: this.uploadGithubUrl,
                                files: selected
                            })
                        });
                        const data = await res.json();
                        if (res.ok) {
                            toastr.success(data.message);
                            this.uploadModalOpen = false;
                            this.uploadGithubUrl = '';
                            this.uploadScannedFiles = [];
                            this.uploadShowFileList = false;
                            this.fetchSkills();
                        } else {
                            toastr.error(data.message || 'Import failed.');
                        }
                    } catch (err) {
                        toastr.error('Import failed.');
                    } finally {
                        this.uploadLoading = false;
                    }
                }
            }));
        })
    </script>
@endPushOnce
