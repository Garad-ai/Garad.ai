{{-- Hero Banner --}}
<div class="mb-8 overflow-hidden rounded-2xl py-12 px-20 bg-[linear-gradient(90deg,_#f6f6f6_0%,_#f6f6f6_60%,_rgba(145,222,252,0.35)_75%,_#D5C0F8_100%)] dark:bg-[linear-gradient(90deg,_rgba(145,222,252,0)_13.75%,_#D5C0F8_96.16%)]">
	<div class="flex items-center justify-between gap-6">
        <div>
            <h2 class="mb-8 text-xl font-medium max-w-md">
                @lang('Create AI agents that answer real phone calls and talk just like a human.')
            </h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-button
                    href="#"
                    @click.prevent="setActiveAgent('new_agent', 1, true);"
                    x-data="{}"
                >
                    <x-tabler-plus class="size-4" />
                    @lang('Create New Agent')
                </x-button>
                <x-button
                    href="#"
                    @click.prevent="$store.phoneCallAgentHistory.setOpen(true)"
                    x-data="{}"
					variant="ghost-shadow"
                >
                    @lang('Call History')
                </x-button>
            </div>
        </div>
        <img
            class="hidden w-40 shrink-0 object-contain md:block"
            src="{{ asset('vendor/phone-call-agent/images/robot.png') }}"
            alt="{{ __('Phone Call Agent') }}"
            width="160"
            height="160"
        />
    </div>
</div>

{{-- Agents Grid --}}
<div class="py-6">
    <h2 class="mb-6">
        @lang('My Phone Call Agents')
    </h2>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        <template
            x-for="agent in agents?.data.filter(a => a.id !== 'new_agent')"
            :key="agent.id"
        >
            <x-card
                class="cursor-pointer transition-shadow hover:shadow-md"
                size="md"
            >
                <x-slot:head
                    class="flex items-center justify-between gap-4 border-none px-5 py-[18px]"
                >
                    <figure class="flex size-10 items-center justify-center rounded-full">
						 <img
							src="{{ asset('vendor/phone-call-agent/images/phone-icon.png') }}"
							alt="{{ __('Avatar') }}"
							width="40"
							height="40"
						/>
					</figure>

                    <x-dropdown.dropdown
                        class:dropdown-dropdown="max-lg:end-0 max-lg:start-auto"
                        anchor="end"
                    >
                        <x-slot:trigger class="size-10">
                            <svg
                                width="3"
                                height="13"
                                viewBox="0 0 3 13"
                                fill="currentColor"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path d="M3 11.5C3 12.3 2.3 13 1.5 13C0.7 13 0 12.3 0 11.5C0 10.7 0.7 10 1.5 10C2.3 10 3 10.7 3 11.5ZM3 6.5C3 7.3 2.3 8 1.5 8C0.7 8 0 7.3 0 6.5C0 5.7 0.7 5 1.5 5C2.3 5 3 5.7 3 6.5ZM3 1.5C3 2.3 2.3 3 1.5 3C0.7 3 0 2.3 0 1.5C0 0.7 0.7 0 1.5 0C2.3 0 3 0.7 3 1.5Z" />
                            </svg>
                            <span class="sr-only">@lang('Agent Options')</span>
                        </x-slot:trigger>
                        <x-slot:dropdown class="min-w-[170px]">
                            @php
                                $dropdown_items = [
                                    ['label' => __('Configure'), 'link' => '#', 'attrs' => ['@click.prevent' => 'setActiveAgent(agent.id, 1, true);']],
                                    ['label' => __('Train'), 'link' => '#', 'attrs' => ['@click.prevent' => 'setActiveAgent(agent.id, 2);']],
                                    ['label' => __('Phone Numbers'), 'link' => '#', 'attrs' => ['@click.prevent' => 'setActiveAgent(agent.id, 3, true);']],
                                ];
                            @endphp
                            <ul class="py-1 text-xs font-medium">
                                @foreach ($dropdown_items as $dropdown_item)
                                    <li>
                                        <a
                                            class="flex px-5 py-2 text-heading-foreground transition-colors hover:bg-heading-foreground/[3%]"
                                            href="{{ $dropdown_item['link'] }}"
                                            @foreach ($dropdown_item['attrs'] as $attr => $value)
                                                {{ $attr }}="{{ $value }}"
                                            @endforeach
                                        >
                                            @lang($dropdown_item['label'])
                                        </a>
                                    </li>
                                @endforeach
                                <li :class="{ 'opacity-50': submittingData, 'pointer-events-none': submittingData }">
                                    <x-forms.input
                                        class="h-[18px] w-[34px] [background-size:0.625rem]"
                                        class:label="py-2 px-5 flex-row-reverse justify-between text-xs font-medium text-heading-foreground hover:bg-heading-foreground/[3%]"
                                        label="{{ __('Activate') }}"
                                        type="checkbox"
                                        switcher
                                        ::id="`active-agent-${agent.id}`"
                                        ::checked="agent.active"
                                        @change="toggleAgentActivation(agent.id);"
                                        x-model="agent.active"
                                        x-init="$el.closest('label').setAttribute('for', `active-agent-${agent.id}`)"
                                    />
                                </li>
                                <li :class="{ 'opacity-50': submittingData, 'pointer-events-none': submittingData }">
                                    <form
                                        action="{{ route('dashboard.phone-call-agent.delete') }}"
                                        @submit.prevent="deleteAgent"
                                    >
                                        <input
                                            type="hidden"
                                            :value="agent.id"
                                            name="id"
                                        >
                                        <x-button
                                            class="w-full justify-between rounded-none px-5 py-2 text-start text-xs font-medium text-heading-foreground hover:translate-y-0"
                                            variant="ghost"
                                            hover-variant="danger"
                                            type="submit"
                                        >
                                            @lang('Delete')
                                            <x-tabler-trash class="size-4" aria-hidden="true" />
                                        </x-button>
                                    </form>
                                </li>
                            </ul>
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                </x-slot:head>

                <div @click="setActiveAgent(agent.id, 1, true)">
                    <h3
                        class="mb-2.5"
                        x-text="agent.title"
                    ></h3>
                    <p
                        class="mb-2.5 text-sm font-medium text-heading-foreground/50"
                        x-text="agent.today_calls_count > 0 ? agent.today_calls_count + ' {{ __('Calls Today') }}' : '{{ __('No Calls Today') }}'"
                    ></p>

                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border px-1.5 py-1 text-[12px] font-medium leading-none transition-all [&.lqd-active]:text-green-500 [&.lqd-passive]:bg-heading-foreground/5 [&.lqd-passive]:text-heading-foreground"
                        :class="{
                            'lqd-active': agent.active,
                            'lqd-passive': !agent.active
                        }"
                    >
                        <x-tabler-check class="size-4" ::class="{ hidden: !agent.active }" />
                        <span class="inline-flex min-h-4 items-center" :class="{ hidden: !agent.active }">@lang('Active')</span>
                        <span class="inline-flex min-h-4 items-center" :class="{ hidden: agent.active }">@lang('Inactive')</span>
                    </div>
                </div>
            </x-card>
        </template>

        {{-- Empty state --}}
        <template x-if="agents?.data.filter(a => a.id !== 'new_agent').length === 0">
            <div class="col-span-full">
                <x-empty-state
                    icon="tabler-phone-off"
                    :title="__('No agents yet')"
                    :description="__('Create your first phone call agent to get started.')"
                />
            </div>
        </template>
    </div>
</div>
