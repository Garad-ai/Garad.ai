<template x-if="selectedStep.type === 'social_media_agent'">
    <div class="space-y-4">
        {{-- Action Event --}}
        <div>
            <x-forms.input
                type="select"
                label="{{ __('Action Event') }}"
                x-model="selectedStep.config.action_event"
                tooltip="{{ __('Choose which social media operation to perform.') }}"
            >
                <option value="">{{ __('Select an action event...') }}</option>
                <option value="list_social_media_agents">{{ __('List Social Media Agents') }}</option>
                <option value="list_social_posts">{{ __('List Social Posts') }}</option>
                <option value="create_social_post">{{ __('Create Social Post') }}</option>
                <option value="get_social_post_analytics">{{ __('Get Post Analytics') }}</option>
                <option value="approve_social_post">{{ __('Approve Social Post') }}</option>
                <option value="create_social_media_agent">{{ __('Create Social Media Agent') }}</option>
            </x-forms.input>
        </div>

        {{-- list_social_media_agents fields --}}
        <template x-if="selectedStep.config.action_event === 'list_social_media_agents'">
            <div class="space-y-3">
            </div>
        </template>

        {{-- list_social_posts fields --}}
        <template x-if="selectedStep.config.action_event === 'list_social_posts'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Agent') }}</label>
                    <template x-if="socialMediaAgents.length > 0">
                        <x-forms.input
                            type="select"
                            x-model.number="selectedStep.config.agent_id"
                        >
                            <option value="">{{ __('All agents') }}</option>
                            <template
                                x-for="agent in socialMediaAgents"
                                :key="agent.id"
                            >
                                <option
                                    :value="agent.id"
                                    x-text="agent.name"
                                ></option>
                            </template>
                        </x-forms.input>
                    </template>
                    <template x-if="socialMediaAgentsLoaded && socialMediaAgents.length === 0">
                        <div class="flex items-center gap-2 rounded-lg border border-dashed border-border px-3 py-2.5 text-[11px] text-foreground/50">
                            <x-tabler-social class="size-3.5 shrink-0" />
                            <span>{{ __('No agents found.') }}</span>
                            @if(\Illuminate\Support\Facades\Route::has('dashboard.user.social-media.agent.create'))
                            <a
                                class="ms-auto text-primary hover:underline"
                                href="{{ route('dashboard.user.social-media.agent.create') }}"
                                target="_blank"
                            >{{ __('Create agent') }}</a>
                            @endif
                        </div>
                    </template>
                    <template x-if="!socialMediaAgentsLoaded">
                        <div class="rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-foreground/40">{{ __('Loading...') }}</div>
                    </template>
                </div>
                <x-forms.input
                    type="select"
                    label="{{ __('Status') }}"
                    x-model="selectedStep.config.status"
                >
                    <option value="">{{ __('Any status') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="pending_approval">{{ __('Pending Approval') }}</option>
                    <option value="approved">{{ __('Approved') }}</option>
                    <option value="scheduled">{{ __('Scheduled') }}</option>
                    <option value="published">{{ __('Published') }}</option>
                    <option value="failed">{{ __('Failed') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="number"
                    min="1"
                    max="50"
                    label="{{ __('Limit') }}"
                    placeholder="10"
                    x-model.number="selectedStep.config.limit"
                />
            </div>
        </template>

        {{-- create_social_post fields --}}
        <template x-if="selectedStep.config.action_event === 'create_social_post'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Agent') }} *</label>
                    <template x-if="socialMediaAgents.length > 0">
                        <x-forms.input
                            type="select"
                            x-model.number="selectedStep.config.agent_id"
                            required
                        >
                            <option value="">{{ __('Select agent...') }}</option>
                            <template
                                x-for="agent in socialMediaAgents"
                                :key="agent.id"
                            >
                                <option
                                    :value="agent.id"
                                    x-text="agent.name"
                                ></option>
                            </template>
                        </x-forms.input>
                    </template>
                    <template x-if="socialMediaAgentsLoaded && socialMediaAgents.length === 0">
                        <div class="flex items-center gap-2 rounded-lg border border-dashed border-border px-3 py-2.5 text-[11px] text-foreground/50">
                            <x-tabler-social class="size-3.5 shrink-0" />
                            <span>{{ __('No agents found.') }}</span>
                            @if(\Illuminate\Support\Facades\Route::has('dashboard.user.social-media.agent.create'))
                            <a
                                class="ms-auto text-primary hover:underline"
                                href="{{ route('dashboard.user.social-media.agent.create') }}"
                                target="_blank"
                            >{{ __('Create agent') }}</a>
                            @endif
                        </div>
                    </template>
                    <template x-if="!socialMediaAgentsLoaded">
                        <div class="rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-foreground/40">{{ __('Loading...') }}</div>
                    </template>
                </div>
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Content') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <div
                        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
                        x-data="variableTagInput({ configKey: 'content', stepRef: selectedStep, multiline: true })"
                    >
                        <div
                            class="px-3 py-2 pe-8 text-sm outline-none"
                            data-placeholder="{{ __('Use {ai_response} for dynamic content') }}"
                            x-ref="editor"
                            contenteditable="true"
                            @input.debounce.50ms="syncToModel()"
                            @keydown="handleKeydown($event)"
                            @paste="handlePaste($event)"
                            @click="handleClick($event)"
                            :class="multiline ? 'min-h-[4.5rem] whitespace-pre-wrap break-words' : 'min-h-[2.25rem] whitespace-nowrap overflow-x-auto'"
                        ></div>
                        <button
                            class="absolute end-2 top-2 rounded p-0.5 text-foreground/40 hover:bg-foreground/5 hover:text-foreground/70"
                            type="button"
                            @click.stop="menuOpen = !menuOpen"
                        ><x-tabler-braces class="size-3.5" /></button>
                        <div
                            class="absolute end-0 top-full z-50 mt-1 min-w-[180px] rounded-lg border border-border bg-background p-1.5 shadow-lg"
                            x-show="menuOpen"
                            x-cloak
                            @click.outside="menuOpen = false"
                        >
                            <template
                                x-for="v in availableVariables"
                                :key="v.name"
                            >
                                <button
                                    class="block w-full rounded px-2 py-1 text-start hover:bg-foreground/5"
                                    type="button"
                                    @click="insertVariable(v)"
                                >
                                    <span
                                        class="block font-mono text-[10px] text-heading-foreground"
                                        x-text="v.name"
                                    ></span>
                                    <span
                                        class="block text-[9px] text-foreground/40"
                                        x-text="v.description"
                                    ></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                <x-forms.input
                    type="select"
                    label="{{ __('Post Type') }}"
                    x-model="selectedStep.config.post_type"
                >
                    <option value="text">{{ __('Text') }}</option>
                    <option value="single_image">{{ __('Single Image') }}</option>
                    <option value="carousel">{{ __('Carousel') }}</option>
                    <option value="video">{{ __('Video') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="datetime-local"
                    label="{{ __('Schedule At') }}"
                    x-model="selectedStep.config.scheduled_at"
                    tooltip="{{ __('Leave blank to save as draft.') }}"
                />
            </div>
        </template>

        {{-- get_social_post_analytics fields --}}
        <template x-if="selectedStep.config.action_event === 'get_social_post_analytics'">
            <div class="space-y-3">
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">{{ __('Agent') }} *</label>
                    <template x-if="socialMediaAgents.length > 0">
                        <x-forms.input
                            type="select"
                            x-model.number="selectedStep.config.agent_id"
                            required
                        >
                            <option value="">{{ __('Select agent...') }}</option>
                            <template
                                x-for="agent in socialMediaAgents"
                                :key="agent.id"
                            >
                                <option
                                    :value="agent.id"
                                    x-text="agent.name"
                                ></option>
                            </template>
                        </x-forms.input>
                    </template>
                    <template x-if="socialMediaAgentsLoaded && socialMediaAgents.length === 0">
                        <div class="flex items-center gap-2 rounded-lg border border-dashed border-border px-3 py-2.5 text-[11px] text-foreground/50">
                            <x-tabler-social class="size-3.5 shrink-0" />
                            <span>{{ __('No agents found.') }}</span>
                            @if(\Illuminate\Support\Facades\Route::has('dashboard.user.social-media.agent.create'))
                            <a
                                class="ms-auto text-primary hover:underline"
                                href="{{ route('dashboard.user.social-media.agent.create') }}"
                                target="_blank"
                            >{{ __('Create agent') }}</a>
                            @endif
                        </div>
                    </template>
                    <template x-if="!socialMediaAgentsLoaded">
                        <div class="rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-foreground/40">{{ __('Loading...') }}</div>
                    </template>
                </div>
                <x-forms.input
                    type="number"
                    min="1"
                    label="{{ __('Lookback Days') }}"
                    placeholder="14"
                    x-model.number="selectedStep.config.lookback_days"
                    tooltip="{{ __('Days of post history to include in analytics.') }}"
                />
            </div>
        </template>

        {{-- approve_social_post fields --}}
        <template x-if="selectedStep.config.action_event === 'approve_social_post'">
            <div class="space-y-3">
                <x-forms.input
                    type="number"
                    min="1"
                    label="{{ __('Post ID') }} *"
                    placeholder="{{ __('Post ID') }}"
                    x-model.number="selectedStep.config.post_id"
                    required
                    tooltip="{{ __('Post must be in pending_approval status.') }}"
                />
            </div>
        </template>

        {{-- create_social_media_agent fields --}}
        <template x-if="selectedStep.config.action_event === 'create_social_media_agent'">
            <div class="space-y-3">
                <x-forms.input
                    type="text"
                    label="{{ __('Agent Name') }} *"
                    placeholder="{{ __('e.g. Brand Twitter Bot') }}"
                    x-model="selectedStep.config.name"
                    required
                />

                {{-- Social Media Accounts --}}
                <div>
                    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
                        {{ __('Social Media Accounts') }}
                        <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
                    </label>
                    <template x-if="socialMediaPlatforms.length > 0">
                        <div class="flex flex-col gap-1">
                            <template
                                x-for="platform in socialMediaPlatforms"
                                :key="platform.id"
                            >
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-2xs">
                                    <span
                                        class="span inline-grid size-5 place-items-center rounded border transition has-[input:checked]:border-primary has-[input:checked]:bg-primary has-[input:checked]:text-primary-foreground"
                                    >
                                        <input
                                            class="peer hidden"
                                            type="checkbox"
                                            :checked="(selectedStep.config.platform_ids ?? []).includes(platform.id)"
                                            @change="toggleSocialMediaPlatform(selectedStep, platform.id)"
                                        />
                                        <x-tabler-check class="size-4 scale-90 opacity-0 transition peer-checked:scale-100 peer-checked:opacity-100" />
                                    </span>
                                    <span
                                        class="flex-1 truncate"
                                        x-text="platform.name + ' (' + platform.platform + ')'"
                                    ></span>
                                </label>
                            </template>
                        </div>
                    </template>
                    <template x-if="socialMediaPlatformsLoaded && socialMediaPlatforms.length === 0">
                        <div class="flex items-center gap-2 rounded-lg border border-dashed border-border px-3 py-2.5 text-[11px] text-foreground/50">
                            <x-tabler-social class="size-3.5 shrink-0" />
                            <span>{{ __('No connected accounts.') }}</span>
                            @if(\Illuminate\Support\Facades\Route::has('dashboard.user.social-media.platforms'))
                            <a
                                class="ms-auto text-primary hover:underline"
                                href="{{ route('dashboard.user.social-media.platforms') }}"
                                target="_blank"
                            >{{ __('Connect account') }}</a>
                            @endif
                        </div>
                    </template>
                    <template x-if="!socialMediaPlatformsLoaded">
                        <div class="rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-foreground/40">{{ __('Loading...') }}</div>
                    </template>
                </div>

                <x-forms.input
                    type="select"
                    label="{{ __('Tone') }} *"
                    x-model="selectedStep.config.tone"
                    required
                >
                    <option value="">{{ __('Select tone...') }}</option>
                    <option value="professional">{{ __('Professional') }}</option>
                    <option value="casual">{{ __('Casual') }}</option>
                    <option value="humorous">{{ __('Humorous') }}</option>
                    <option value="inspirational">{{ __('Inspirational') }}</option>
                    <option value="educational">{{ __('Educational') }}</option>
                    <option value="promotional">{{ __('Promotional') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="select"
                    label="{{ __('Language') }} *"
                    x-model="selectedStep.config.language"
                    required
                >
                    <option value="">{{ __('Select language...') }}</option>
                    <option value="en">{{ __('English') }}</option>
                    <option value="tr">{{ __('Turkish') }}</option>
                    <option value="de">{{ __('German') }}</option>
                    <option value="fr">{{ __('French') }}</option>
                    <option value="es">{{ __('Spanish') }}</option>
                    <option value="ar">{{ __('Arabic') }}</option>
                    <option value="pt">{{ __('Portuguese') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="select"
                    label="{{ __('Plan Type') }}"
                    x-model="selectedStep.config.plan_type"
                >
                    <option value="daily">{{ __('Daily') }}</option>
                    <option value="weekly">{{ __('Weekly') }}</option>
                    <option value="monthly">{{ __('Monthly') }}</option>
                </x-forms.input>
                <x-forms.input
                    type="number"
                    min="1"
                    max="10"
                    label="{{ __('Posts per Day') }}"
                    placeholder="1"
                    x-model.number="selectedStep.config.daily_post_count"
                />
                <x-forms.input
                    type="number"
                    min="0"
                    max="30"
                    label="{{ __('Hashtag Count') }}"
                    placeholder="5"
                    x-model.number="selectedStep.config.hashtag_count"
                />
                <x-forms.input
                    type="url"
                    label="{{ __('Site URL') }}"
                    placeholder="https://example.com"
                    x-model="selectedStep.config.site_url"
                />
                <x-forms.input
                    type="textarea"
                    rows="2"
                    label="{{ __('Brand Description') }}"
                    placeholder="{{ __('Brief description of the brand or product...') }}"
                    x-model="selectedStep.config.site_description"
                />
            </div>
        </template>
    </div>
</template>
