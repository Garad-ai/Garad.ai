{{-- Phone Simulator Panel --}}
<div class="sticky top-24 flex flex-col items-center gap-6 w-full">

    {{-- Phone mockup card --}}
    <div class="flex w-full flex-col items-center gap-6 rounded-2xl border border-dashed border-heading-foreground/15 p-6 lg:py-10">

        {{-- iPhone frame --}}
        <div
            class="relative overflow-hidden rounded-[2.8rem] bg-[#1c1c1e] shadow-2xl"
            style="width: 240px; height: 480px; box-shadow: 0 0 0 8px #3a3a3c, 0 0 0 10px #2c2c2e, 0 20px 60px rgba(0,0,0,0.5);"
        >
            {{-- Notch --}}
            <div class="absolute start-1/2 top-3 z-10 h-[18px] w-[80px] -translate-x-1/2 rounded-full bg-black"></div>

            {{-- Screen --}}
            <div class="absolute inset-0 bg-black">

                {{-- Idle state: incoming call notification --}}
                <template x-if="simulation.status === 'idle' || simulation.status === 'error' || simulation.status === 'ended'">
                    <div class="flex h-full flex-col">
                        {{-- Call notification bar --}}
                        <div class="mx-3 mt-12 flex items-center justify-between rounded-2xl bg-[#1c1c1e]/95 px-4 py-3 backdrop-blur">
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-sm font-semibold text-white"
                                    x-text="activeAgent.title || '{{ __('Agent') }}'"
                                ></span>
                                <span class="text-xs text-white/50">@lang('Test call')</span>
                            </div>
                            <button
                                class="flex size-10 items-center justify-center rounded-full bg-green-500 shadow-lg transition-all hover:bg-green-400 active:scale-95"
                                type="button"
                                @click.prevent="startSimulation()"
                                :disabled="simulation.status === 'error'"
                                title="{{ __('Start simulation') }}"
                            >
                                <svg
                                    class="size-5 text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                >
                                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
                                </svg>
                            </button>
                        </div>

                        {{-- Error message --}}
                        <template x-if="simulation.status === 'error'">
                            <div class="mx-3 mt-2 rounded-xl bg-red-500/20 px-3 py-2">
                                <p
                                    class="text-center text-xs text-red-400"
                                    x-text="simulation.error || '{{ __('Simulation failed') }}'"
                                ></p>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Loading state --}}
                <template x-if="simulation.status === 'loading'">
                    <div class="flex h-full flex-col items-center justify-center gap-4">
                        <div class="size-14 animate-spin rounded-full border-4 border-white/10 border-t-green-500"></div>
                        <p class="text-xs text-white/50">@lang('Connecting...')</p>
                    </div>
                </template>

                {{-- Active call state --}}
                <template x-if="simulation.status === 'active'">
                    <div class="flex h-full flex-col items-center justify-between pb-8 pt-12">
                        {{-- Call info --}}
                        <div class="flex flex-col items-center gap-3">
                            <div class="relative flex size-20 items-center justify-center rounded-full bg-white/10">
                                <svg
                                    class="size-8 text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                >
                                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
                                </svg>
                                {{-- Pulse ring --}}
                                <span class="absolute inset-0 animate-ping rounded-full bg-green-500/30"></span>
                            </div>
                            <span
                                class="text-base font-semibold text-white"
                                x-text="activeAgent.title || '{{ __('Agent') }}'"
                            ></span>
                            <span class="text-sm text-green-400">@lang('Connected')</span>
                        </div>

                        {{-- Hang up button --}}
                        <button
                            class="flex size-14 items-center justify-center rounded-full bg-red-500 shadow-lg transition-all hover:bg-red-400 active:scale-95"
                            type="button"
                            @click.prevent="endSimulation()"
                            title="{{ __('End call') }}"
                        >
                            <svg
                                class="size-6 rotate-[135deg] text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
                            </svg>
                        </button>
                    </div>
                </template>

            </div>

            {{-- Side buttons --}}
            <div
                class="absolute end-[-10px] top-[100px] h-[60px] w-[4px] rounded-e-sm bg-[#3a3a3c]"
                aria-hidden="true"
            ></div>
            <div
                class="absolute start-[-10px] top-[80px] h-[36px] w-[4px] rounded-s-sm bg-[#3a3a3c]"
                aria-hidden="true"
            ></div>
            <div
                class="absolute start-[-10px] top-[124px] h-[36px] w-[4px] rounded-s-sm bg-[#3a3a3c]"
                aria-hidden="true"
            ></div>
            <div
                class="absolute start-[-10px] top-[170px] h-[60px] w-[4px] rounded-s-sm bg-[#3a3a3c]"
                aria-hidden="true"
            ></div>

        </div>

        {{-- Info text --}}
        <p class="text-center text-xs/5 opacity-50">
            @lang('This is a web simulation. The actual phone call feature will function properly in real-world scenarios once the')
            <button
                class="underline underline-offset-2"
                type="button"
                @click.prevent="setEditingStep(1)"
            >@lang('configuration')</button>
            @lang('is complete.')
        </p>

        {{-- Configure button: hidden on step 1, navigates to step 1 --}}
        <button
            class="rounded-lg border border-heading-foreground/15 px-5 py-2 text-xs font-medium text-heading-foreground/70 transition-colors hover:border-heading-foreground/30 hover:text-heading-foreground"
            type="button"
            x-show="editingStep !== 1"
            @click.prevent="setEditingStep(1)"
        >
            @lang('Configure')
        </button>

    </div>
</div>
