<ul class="lqd-ext-phone-history-list flex flex-col">
    <template
        x-for="(callItem, index) in callsList"
        x-show="callsList.length"
    >
        <li
            class="lqd-ext-phone-history-list-item group/call-item relative transition-colors hover:bg-heading-foreground/5 [&.lqd-active]:bg-heading-foreground/5"
            :class="{ 'lqd-active': activeCall ? activeCall == callItem.id : index === 0 }"
        >
            <div class="flex gap-3 px-5 py-4">
                <figure class="flex size-[52px] shrink-0 items-center justify-center rounded-full text-white">
                    <svg
                        width="52"
                        height="52"
                        viewBox="0 0 32 31"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M16 0C7.44048 0 0.5 6.93943 0.5 15.5C0.5 24.0606 7.4398 31 16 31C24.5609 31 31.5 24.0606 31.5 15.5C31.5 6.93943 24.5609 0 16 0ZM16 4.63468C18.8323 4.63468 21.1274 6.93057 21.1274 9.76163C21.1274 12.5934 18.8323 14.8886 16 14.8886C13.1691 14.8886 10.874 12.5934 10.874 9.76163C10.874 6.93057 13.1691 4.63468 16 4.63468ZM15.9966 26.9475C13.1718 26.9475 10.5846 25.9187 8.58906 24.2158C8.10294 23.8012 7.82243 23.1931 7.82243 22.5552C7.82243 19.6839 10.1461 17.386 13.0179 17.386H18.9834C21.8559 17.386 24.1708 19.6839 24.1708 22.5552C24.1708 23.1938 23.8916 23.8005 23.4048 24.2151C21.41 25.9187 18.8221 26.9475 15.9966 26.9475Z"
                            :fill="getCallerColor(callItem.caller_number)"
                        />
                    </svg>
                </figure>

                <div class="flex w-10/12 grow gap-1">
                    <div class="max-w-full grow overflow-hidden text-start">
                        <h4 class="mb-0 flex max-w-full items-center gap-2 text-xs font-medium">
                            <span
                                class="inline-block grow truncate"
                                x-text="callItem.caller_number || '{{ __('Unknown Caller') }}'"
                            ></span>
                            <x-tabler-pinned
                                class="size-3 shrink-0 fill-current text-primary"
                                x-cloak
                                x-show="callItem.pinned > 0"
                            />
                        </h4>
                        <p
                            class="mb-0 text-xs opacity-50"
                            x-text="callItem.called_number || '---'"
                        ></p>
                        <p
                            class="mb-0 truncate text-xs"
                            x-text="callItem.transcripts?.at(-1)?.message || ''"
                        ></p>
                    </div>
                    <div class="shrink-0">
                        <p class="mb-0.5 text-[12px] opacity-40">
                            <span x-text="getShortDiffHumanTime(Math.floor((new Date() - new Date(callItem.started_at || callItem.created_at)) / 1000))"></span>
                        </p>
                    </div>
                </div>
            </div>

            <a
                class="absolute start-0 top-0 inline-block h-full w-full"
                href="#"
                title="{{ __('View Call Details') }}"
                @click.prevent="setActiveCall(callItem.id)"
            ></a>
        </li>
    </template>

    <template x-if="!callsList.length && !fetching">
        <p class="mb-0.5 px-5 py-4 font-semibold text-heading-foreground">
            {{ __('No call history found.') }}
        </p>
    </template>
</ul>
