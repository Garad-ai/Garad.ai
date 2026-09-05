{{-- Step 1 - Configure --}}
<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 transition-all"
    data-step="1"
    x-show="editingStep === 1"
    x-transition:enter-start="opacity-0 -translate-x-3"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-3"
>
    <h2 class="mb-3.5">@lang('Configure')</h2>
    <p class="text-xs/5 opacity-60 lg:max-w-[360px]">
        @lang('Set up your phone call agent with a title, welcome message, and instructions.')
    </p>

    <div class="flex flex-col gap-7 pt-9">
        <div>
            <x-forms.input
                class:label="text-heading-foreground"
                label="{{ __('Agent Title') }}"
                placeholder="{{ __('Support Agent') }}"
                name="title"
                size="lg"
                x-model="activeAgent.title"
            />
            <template
                x-for="(error, index) in formErrors.title"
                :key="'error-' + index"
            >
                <div class="mt-2 text-2xs/5 font-medium text-red-500">
                    <p x-text="error"></p>
                </div>
            </template>
        </div>

        <div>
            <x-forms.input
                class:label="text-heading-foreground"
                label="{{ __('Welcome Message') }}"
                placeholder="{{ __('Hello, how can I help you today?') }}"
                name="welcome_message"
                size="lg"
                x-model="activeAgent.welcome_message"
            />
            <template
                x-for="(error, index) in formErrors.welcome_message"
                :key="'error-' + index"
            >
                <div class="mt-2 text-2xs/5 font-medium text-red-500">
                    <p x-text="error"></p>
                </div>
            </template>
        </div>

        <div>
            <x-forms.input
                class:label="text-heading-foreground"
                label="{{ __('Agent Instructions') }}"
                placeholder="{{ __('Describe the agent role and behavior') }}"
                name="instructions"
                size="lg"
                type="textarea"
                rows="5"
                x-model="activeAgent.instructions"
            />
            <template
                x-for="(error, index) in formErrors.instructions"
                :key="'error-' + index"
            >
                <div class="mt-2 text-2xs/5 font-medium text-red-500">
                    <p x-text="error"></p>
                </div>
            </template>
        </div>

        <div>
            <x-forms.input
                class:label="text-heading-foreground"
                label="{{ __('Language') }}"
                name="language"
                size="lg"
                type="select"
                x-model="activeAgent.language"
            >
                <option value="en" selected>@lang('Auto')</option>
                @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                    @if (in_array($localeCode, explode(',', $settings_two->languages), true))
                        <option value="{{ $localeCode }}">{{ $properties['name'] }}</option>
                    @endif
                @endforeach
            </x-forms.input>
        </div>

        <div>
            <x-forms.input
                class:label="text-heading-foreground"
                label="{{ __('Max Call Duration (seconds)') }}"
                name="max_call_duration"
                size="lg"
                type="number"
                min="30"
                max="3600"
                x-model="activeAgent.max_call_duration"
            />
        </div>

        {{-- ElevenLabs Voice Selection --}}
        <template x-if="activeAgent.provider !== 'twilio'">
            <div>
                <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-heading-foreground">
                    <span class="lqd-input-label-txt">{{ __('Voice') }}</span>
                </label>
                <x-dropdown.dropdown
                    class="w-full"
                    class:dropdown="w-[min(calc(100vw-30px),400px)] max-h-[min(75vh,500px)] overflow-y-auto bg-dropdown-background/90 px-2 pb-4 pt-0 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
                    triggerType="click"
                    offsetY="0.25rem"
                >
                    <x-slot:trigger
                        class="h-11 min-h-10 w-full justify-between overflow-hidden !rounded-input bg-background text-start font-normal capitalize hover:shadow-none sm:text-2xs"
                        variant="outline"
                        type="button"
                    >
                        <span
                            class="w-full grow truncate"
                            x-text="(() => { const v = elevenLabsVoices.find(v => v.voice_id === activeAgent.voice_id); return v ? (v.name + (v.labels?.language ? ' (' + v.labels.language.toUpperCase() + ')' : '')) : '{{ __('Select a voice') }}'; })()"
                        ></span>
                        <x-tabler-chevron-down class="size-4 shrink-0" />
                    </x-slot:trigger>

                    <x-slot:dropdown>
                        <div class="sticky top-0 z-10 -mx-2 mb-2 mt-1 px-2 pt-2 backdrop-blur-lg">
                            <x-forms.input
                                class="mb-3 shadow-md shadow-black/5"
                                type="search"
                                placeholder="{{ __('Search voices...') }}"
                                x-model="voiceQuery"
                            />
                        </div>

                        <div class="space-y-0.5">
                            <template x-for="voice in filteredElevenLabsVoices" :key="voice.voice_id">
                                <button
                                    class="group/voice-row flex w-full items-center justify-normal gap-2.5 overflow-hidden rounded-lg px-4 py-2.5 text-start text-2xs font-medium capitalize text-heading-foreground transition hover:bg-foreground/5"
                                    :class="{ 'bg-foreground/5': activeAgent.voice_id === voice.voice_id }"
                                    type="button"
                                    @click.prevent="activeAgent.voice_id = voice.voice_id"
                                >
                                    <span
                                        class="inline-grid size-6 shrink-0 place-items-center rounded-lg border bg-background transition hover:bg-primary hover:text-primary-foreground"
                                        :class="{ 'hidden': !voice.preview_url }"
                                        title="{{ __('Preview') }}"
                                        @click.prevent.stop="previewVoice(voice.voice_id)"
                                    >
                                        <x-tabler-player-play
                                            class="size-3.5 fill-current"
                                            x-show="previewingVoice !== voice.voice_id"
                                        />
                                        <x-tabler-volume
                                            class="size-3.5"
                                            x-cloak
                                            x-show="previewingVoice === voice.voice_id"
                                        />
                                    </span>

                                    <div class="flex w-full flex-col gap-0.5">
                                        <span class="flex items-center gap-1.5">
                                            <span class="truncate" x-text="voice.name"></span>
                                            <template x-if="voice.labels?.language">
                                                <span
                                                    class="shrink-0 rounded bg-foreground/10 px-1 py-px text-3xs font-semibold uppercase opacity-70"
                                                    x-text="voice.labels.language"
                                                ></span>
                                            </template>
                                        </span>
                                        <template x-if="voice.category">
                                            <span class="truncate text-3xs opacity-50" x-text="voice.category"></span>
                                        </template>
                                    </div>

                                    <template x-if="activeAgent.voice_id === voice.voice_id">
                                        <x-tabler-check class="ms-auto size-5 shrink-0" />
                                    </template>
                                </button>
                            </template>

                            <p
                                class="m-0 px-4 py-6 text-center text-2xs opacity-60"
                                x-show="filteredElevenLabsVoices.length === 0"
                                x-cloak
                            >
                                {{ __('No voices match your search.') }}
                            </p>
                        </div>
                    </x-slot:dropdown>
                </x-dropdown.dropdown>
            </div>
        </template>

        {{-- Booking --}}
        <div class="flex w-full flex-col gap-5 overflow-hidden rounded-xl border p-5 max-w-[550px]">
			<div>
				<x-forms.input
					class="h-[18px] w-[34px] [background-size:0.625rem]"
					class:label="text-heading-foreground flex-row-reverse justify-between"
					label="{{ __('Booking Assistant') }}"
					name="booking_enabled"
					size="lg"
					type="checkbox"
					switcher
					x-model.boolean="activeAgent.booking_enabled"
				/>

				<template
					x-for="(error, index) in formErrors.booking_enabled"
					:key="'error-' + index"
				>
					<div class="mt-2 text-2xs/5 font-medium text-red-500">
						<p x-text="error"></p>
					</div>
				</template>
			</div>

            <div
                x-show="activeAgent.booking_enabled"
                x-effect="if (activeAgent.booking_enabled && ! activeAgent.booking_provider) activeAgent.booking_provider = 'cal_com'"
                x-cloak
                class="flex flex-col gap-5"
            >
                <div>
                    <x-forms.input
                        class:label="text-heading-foreground"
                        label="{{ __('Booking Provider') }}"
                        name="booking_provider"
                        size="lg"
                        type="select"
                        x-model="activeAgent.booking_provider"
                    >
                        <option value="cal_com">Cal.com</option>
                        <option value="calendly">Calendly</option>
                    </x-forms.input>
                </div>

                {{-- Calendly paid plan notice --}}
                <div
                    x-show="activeAgent.booking_provider === 'calendly'"
                    x-cloak
                    class="flex gap-2.5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-2xs text-amber-800 dark:border-amber-800/40 dark:bg-amber-900/20 dark:text-amber-300"
                >
                    <x-tabler-info-circle class="mt-px size-3.5 shrink-0" />
                    <span>{{ __('Booking creation and rescheduling require a paid Calendly plan. Availability checks and cancellations work on all plans.') }}</span>
                </div>

                {{-- Cal.com: plain numeric ID --}}
                <div x-show="activeAgent.booking_provider !== 'calendly'">
                    <x-forms.input
                        class:label="text-heading-foreground"
                        label="{{ __('Event Type ID') }}"
                        placeholder="{{ __('e.g. 12345') }}"
                        name="booking_event_type_id"
                        size="lg"
                        x-model="activeAgent.booking_event_type_id"
                    />
                </div>

                {{-- Calendly: fetch event types from API --}}
                <div
                    x-show="activeAgent.booking_provider === 'calendly'"
                    x-cloak
                    x-data="{
                        eventTypes: [],
                        fetching: false,
                        fetchError: '',
                        async fetchEventTypes(apiKey) {
                            if (!apiKey) {
                                this.fetchError = '{{ __('Enter API key first.') }}';
                                return;
                            }
                            this.fetching = true;
                            this.fetchError = '';
                            try {
                                const res = await fetch('{{ route('dashboard.phone-call-agent.booking.calendly-event-types') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                    },
                                    body: JSON.stringify({ api_key: apiKey }),
                                });
                                const data = await res.json();
                                if (!res.ok) {
                                    this.fetchError = data.message || '{{ __('Failed to fetch event types.') }}';
                                } else {
                                    this.eventTypes = data.event_types;
                                }
                            } catch (e) {
                                this.fetchError = '{{ __('Request failed.') }}';
                            } finally {
                                this.fetching = false;
                            }
                        }
                    }"
                    class="flex flex-col gap-2"
                >
                    <label class="text-2xs font-medium text-heading-foreground">{{ __('Event Type') }}</label>
                    <div class="flex items-center gap-2">
                        <select
                            class="grow rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
                            x-show="eventTypes.length > 0"
                            x-cloak
                            x-model="activeAgent.booking_event_type_id"
                        >
                            <template x-for="et in eventTypes" :key="et.uri">
                                <option :value="et.uri" x-text="et.name"></option>
                            </template>
                        </select>
                        <input
                            type="text"
                            class="grow rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
                            x-show="eventTypes.length === 0"
                            placeholder="https://api.calendly.com/event_types/…"
                            x-model="activeAgent.booking_event_type_id"
                        />
                        <x-button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="fetchEventTypes(activeAgent.booking_api_key)"
                            ::disabled="fetching"
                        >
                            <x-tabler-refresh class="size-4" ::class="fetching && 'animate-spin'" />
                            <span x-text="fetching ? '{{ __('Fetching…') }}' : (eventTypes.length > 0 ? '{{ __('Refresh') }}' : '{{ __('Fetch') }}')"></span>
                        </x-button>
                    </div>
                    <p x-show="fetchError" class="text-2xs font-medium text-red-500" x-text="fetchError"></p>
                </div>

                <div>
                    <x-forms.input
                        class:label="text-heading-foreground"
                        label="{{ __('API Key') }}"
                        placeholder="{{ __('Provider API key') }}"
                        name="booking_api_key"
                        type="password"
                        size="lg"
                        x-model="activeAgent.booking_api_key"
                    />
                </div>

            </div>
        </div>

        <template x-if="activeAgent.provider === 'twilio'">
            <div class="flex flex-col gap-7">
                <div>
                    <x-forms.input
                        class:label="text-heading-foreground"
                        label="{{ __('Voice (TTS)') }}"
                        name="voice_id"
                        size="lg"
                        type="select"
                        x-model="activeAgent.voice_id"
                    >
                        <optgroup label="Amazon Polly (Neural)">
                            <option value="Polly.Joanna-Neural">Joanna (US English, Female)</option>
                            <option value="Polly.Matthew-Neural">Matthew (US English, Male)</option>
                            <option value="Polly.Salli-Neural">Salli (US English, Female)</option>
                            <option value="Polly.Amy-Neural">Amy (British English, Female)</option>
                            <option value="Polly.Brian-Neural">Brian (British English, Male)</option>
                            <option value="Polly.Emma-Neural">Emma (British English, Female)</option>
                            <option value="Polly.Lucia-Neural">Lucia (Spanish, Female)</option>
                            <option value="Polly.Lupe-Neural">Lupe (Spanish US, Female)</option>
                            <option value="Polly.Lea-Neural">Léa (French, Female)</option>
                            <option value="Polly.Vicki-Neural">Vicki (German, Female)</option>
                            <option value="Polly.Bianca-Neural">Bianca (Italian, Female)</option>
                        </optgroup>
                        <optgroup label="Google Cloud TTS">
                            <option value="Google.en-US-Standard-B">Google English US (Male)</option>
                            <option value="Google.en-US-Standard-C">Google English US (Female)</option>
                            <option value="Google.en-GB-Standard-A">Google English GB (Female)</option>
                            <option value="Google.fr-FR-Standard-A">Google French (Female)</option>
                            <option value="Google.de-DE-Standard-A">Google German (Female)</option>
                            <option value="Google.es-ES-Standard-A">Google Spanish (Female)</option>
                        </optgroup>
                    </x-forms.input>
                </div>
            </div>
        </template>
    </div>
</div>
