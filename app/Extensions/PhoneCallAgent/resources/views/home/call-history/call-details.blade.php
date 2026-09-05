<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full px-4 py-6 text-center"
    x-show="contactInfo.activeTab === 'details'"
    x-transition.opacity
>
    <div class="mx-auto mb-3 size-[90px]">
        <figure class="grid size-full place-items-center rounded-full text-white">
            <svg
                class="size-full"
                viewBox="0 0 32 31"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M16 0C7.44048 0 0.5 6.93943 0.5 15.5C0.5 24.0606 7.4398 31 16 31C24.5609 31 31.5 24.0606 31.5 15.5C31.5 6.93943 24.5609 0 16 0ZM16 4.63468C18.8323 4.63468 21.1274 6.93057 21.1274 9.76163C21.1274 12.5934 18.8323 14.8886 16 14.8886C13.1691 14.8886 10.874 12.5934 10.874 9.76163C10.874 6.93057 13.1691 4.63468 16 4.63468ZM15.9966 26.9475C13.1718 26.9475 10.5846 25.9187 8.58906 24.2158C8.10294 23.8012 7.82243 23.1931 7.82243 22.5552C7.82243 19.6839 10.1461 17.386 13.0179 17.386H18.9834C21.8559 17.386 24.1708 19.6839 24.1708 22.5552C24.1708 23.1938 23.8916 23.8005 23.4048 24.2151C21.41 25.9187 18.8221 26.9475 15.9966 26.9475Z"
                    :fill="getCallerColor(callsList.find(c => c.id == activeCall)?.caller_number)"
                />
            </svg>
        </figure>
    </div>

    <h3
        class="mb-2.5"
        x-text="callsList.find(c => c.id == activeCall)?.caller_number || '{{ __('Unknown Caller') }}'"
    ></h3>

    <div class="mb-2.5 flex items-center justify-center gap-3 font-medium opacity-70">
        {{ __('Channel') }}
        <span class="inline-block size-0.5 rounded-full bg-current"></span>
        {{ __('Phone Call') }}
    </div>

    <p class="mb-7 flex items-center justify-center gap-1 font-medium text-primary">
        <x-tabler-phone class="size-4" />
        <span x-text="callsList.find(c => c.id == activeCall)?.called_number || '---'"></span>
    </p>

    {{-- Tags --}}
    <div class="relative mb-5 flex justify-center">
        <div
            class="flex max-w-full items-center justify-center gap-2"
            @click.outside="if (!$event.target.closest('.cp_dialog')) callTagModal.show = false"
        >
            <template x-if="callsList.find(c => c.id == activeCall)?.tags?.length">
                <div class="no-scrollbar flex gap-2 overflow-x-auto whitespace-nowrap">
                    <template
                        x-for="tag in callsList.find(c => c.id == activeCall)?.tags ?? []"
                        :key="tag.id"
                    >
                        <div
                            class="relative z-1 inline-flex shrink-0 cursor-default items-center gap-1 overflow-hidden rounded-md px-2.5 py-1 text-[12px] font-medium text-[--color] before:absolute before:inset-0 before:-z-1 before:bg-[--color] before:opacity-15"
                            :style="{ '--color': tag.tag_color ?? '#6366f1' }"
                            @click.prevent="toggleCallTagModal"
                            x-text="tag.tag"
                        ></div>
                    </template>
                </div>
            </template>

            <span
                class="inline-flex rounded-md bg-foreground/5 px-2.5 py-1 text-[12px] font-medium"
                x-show="!callsList.find(c => c.id == activeCall)?.tags?.length"
                @click.prevent="toggleCallTagModal"
            >
                {{ __('No tags assigned yet') }}
            </span>

            <x-button
                class="size-[26px] shrink-0 rounded-md bg-foreground/5 p-0"
                size="none"
                variant="ghost-shadow"
                title="{{ __('Add or edit tags') }}"
                @click.prevent="toggleCallTagModal"
            >
                <x-tabler-plus class="size-4" />
            </x-button>

            <div
                class="absolute inset-x-0 top-full z-10 mt-2.5 max-h-72 overflow-y-auto overscroll-contain rounded-dropdown border border-dropdown-border bg-dropdown-background px-4 py-6 shadow-xl shadow-black/5"
                x-cloak
                x-show="callTagModal.show"
                x-transition.origin.top
                x-trap="callTagModal.show"
            >
                <form
                    class="relative mb-1"
                    @submit.prevent="createCallTag"
                >
                    <div
                        class="absolute start-4 top-1/2 z-2 flex -translate-y-1/2 items-center before:absolute before:-inset-2.5 before:left-1/2 before:top-1/2 before:-translate-x-1/2 before:-translate-y-1/2"
                        x-data="liquidColorPicker({ colorVal: callTagModal.form.tag_color })"
                    >
                        <span
                            class="lqd-input-color-wrap !size-3.5 shrink-0 rounded-full !border-[3px] !border-background shadow-md shadow-black/10"
                            x-ref="colorInputWrap"
                            :style="{ backgroundColor: colorVal }"
                        ></span>
                        <input
                            class="invisible w-0 border-none bg-transparent p-0 text-3xs font-medium focus:outline-none"
                            type="text"
                            value="#ffffff"
                            x-ref="colorInput"
                            :value="callTagModal.form.tag_color"
                            @input="callTagModal.form.tag_color = $event.target.value"
                            @change="picker.setColor($event.target.value)"
                            @keydown.enter.prevent="picker?.setColor($event.target.value);"
                            @focus="picker.open(); $el.select();"
                        />
                    </div>
                    <x-forms.input
                        class="min-h-11 w-full rounded-lg bg-foreground/5 px-10 py-3.5"
                        type="text"
                        placeholder="{{ __('Add New Tag') }}"
                        x-model="callTagModal.form.tag"
                    />
                    <x-button
                        class="absolute end-4 top-1/2 size-5 -translate-y-1/2 p-0 hover:-translate-y-1/2 hover:scale-105"
                        size="none"
                        variant="none"
                        type="submit"
                        ::disabled="callTagModal.creating"
                        @click.prevent="createCallTag"
                    >
                        <x-tabler-plus
                            class="size-4"
                            x-show="!callTagModal.creating && !callTagModal.form.tag.trim()"
                        />
                        <x-tabler-check
                            class="size-4"
                            x-show="!callTagModal.creating && callTagModal.form.tag.trim()"
                        />
                        <x-tabler-loader-2
                            class="size-4 animate-spin"
                            x-show="callTagModal.creating"
                        />
                    </x-button>
                </form>

                <p
                    class="mb-0 mt-5 text-2xs font-medium"
                    x-show="callTagModal.loading"
                >
                    {{ __('Loading tags...') }}
                </p>

                <div
                    x-show="!callTagModal.loading"
                    x-cloak
                >
                    <template
                        x-for="tag in callTagModal.items"
                        :key="tag.id"
                    >
                        <button
                            class="mb-0.5 flex w-full items-center justify-between gap-1 rounded-lg p-3 transition last:mb-0 hover:bg-foreground/[3%] [&.active]:bg-foreground/10"
                            type="button"
                            :class="{ 'active': callTagModal.selected.includes(tag.id) }"
                            @click.prevent="toggleCallTagSelection(tag.id)"
                            :title="tag.tag"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="size-2 shrink-0 rounded-sm"
                                    :style="{ backgroundColor: tag.tag_color }"
                                ></span>
                                <span
                                    class="w-full grow truncate"
                                    x-text="tag.tag"
                                ></span>
                            </span>
                            <x-tabler-check
                                class="size-4"
                                x-show="callTagModal.selected.includes(tag.id)"
                            />
                        </button>
                    </template>

                    <p
                        class="mb-0 mt-5 text-2xs font-medium"
                        x-show="!callTagModal.items.length"
                        x-cloak
                    >
                        {{ __('No tags yet. Use the form above to create one.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <p class="-mx-4 mb-5 flex items-center justify-center gap-10">
        <span class="inline-block h-px grow bg-current opacity-5"></span>
        {{ __('Details') }}
        <span class="inline-block h-px grow bg-current opacity-5"></span>
    </p>

    <div class="rounded-lg border px-4 xl:mx-4">
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('ID') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end text-foreground"
                x-text="callsList.find(c => c.id == activeCall)?.id ?? '---'"
            ></span>
        </p>
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('Status') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end capitalize text-foreground"
                x-text="callsList.find(c => c.id == activeCall)?.status ?? '---'"
            ></span>
        </p>
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('Agent') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end text-foreground"
                x-text="callsList.find(c => c.id == activeCall)?.agent?.title ?? '---'"
            ></span>
        </p>
        <p class="mb-0 flex items-center justify-between gap-1 border-b py-4 text-foreground/60">
            {{ __('Created') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end text-foreground"
                x-text="callsList.find(c => c.id == activeCall)?.started_at ? getDiffHumanTime(callsList.find(c => c.id == activeCall).started_at) : '---'"
            ></span>
        </p>
        <p class="mb-0 flex items-center justify-between gap-1 py-4 text-foreground/60">
            {{ __('Duration') }}
            <span
                class="max-w-[65%] overflow-hidden text-ellipsis whitespace-nowrap text-end text-foreground"
                x-text="formatDuration(callsList.find(c => c.id == activeCall)?.duration || 0)"
            ></span>
        </p>
    </div>
</div>
