@php
    $total_steps = 7;
    $platforms_with_image = collect($platforms)
        ->map(function ($platform) {
            $timestampKeys = ['created_at', 'updated_at', 'deleted_at', 'connected_at', 'expires_at'];
            $isArray = is_array($platform);

            $name = $isArray ? $platform['platform'] ?? null : $platform->platform ?? null;

            $image = asset('vendor/social-media/icons/' . $name . '.svg');
            $image_dark_version = asset('vendor/social-media/icons/' . $name . '-light.svg');
            $darkImageExists = file_exists(public_path($image_dark_version));

            if ($isArray) {
                $platform = \Illuminate\Support\Arr::except($platform, $timestampKeys);
                $platform['image'] = $image;
                $platform['image_dark_version'] = $darkImageExists ? $image_dark_version : null;

                return $platform;
            }

            foreach ($timestampKeys as $key) {
                unset($platform->{$key});
            }

            $platform->image = $image;
            $platform->image_dark_version = $darkImageExists ? $image_dark_version : null;

            return $platform;
        })
        ->values()
        ->all();

    $days_of_week = collect(range(0, 6))
        ->mapWithKeys(function ($index) {
            $day = \Carbon\Carbon::now()->startOfWeek()->addDays($index);

            return [
                $index => [
                    'number' => $day->dayOfWeekIso,
                    'name' => $day->englishDayOfWeek,
                    'label' => $day->translatedFormat('l'),
                    'shorthand' => $day->translatedFormat('D'),
                ],
            ];
        })
        ->toArray();

    $time_slots = [
        [
            'key' => 'morning',
            'label' => 'Morning',
            'start' => '08:00',
            'end' => '12:00',
        ],
        [
            'key' => 'noon',
            'label' => 'Noon',
            'start' => '12:00',
            'end' => '16:00',
        ],
        [
            'key' => 'evening',
            'label' => 'Evening',
            'start' => '17:00',
            'end' => '22:00',
        ],
    ];
@endphp

@extends('panel.layout.app', ['disable_tblr' => true, 'disable_titlebar' => true])
@section('title', __('Create Social Media Agent'))
@section('titlebar_subtitle', __('Automate your social media posting with AI-powered agents'))

@push('css')
    <style>
        @media (min-width: 992px) {

            .lqd-navbar-expander,
            .lqd-navbar,
            .lqd-header,
            .lqd-page-footer {
                display: none !important;
            }

            .lqd-page-wrapper {
                padding-inline: 0 !important;
            }
        }
    </style>
@endpush

@section('content')
    <div
        class="flex min-h-screen flex-col items-center justify-center py-10 lg:py-28"
        x-data="agentWizard"
        style="--current-step: 0; --total-steps: {{ $total_steps }}"
        :style="{ '--current-step': currentStep, '--total-steps': totalSteps }"
    >
        <div class="absolute inset-x-0 top-[--header-height] z-5 flex items-center p-4 lg:fixed lg:top-0">
            <div class="flex basis-1/3">
                <div
                    x-cloak
                    x-show="currentStep > 0"
                    x-transition
                >
                    <x-button
                        variant="link"
                        @click.prevent="prevStep()"
                    >
                        <x-tabler-arrow-left class="size-4" />
                        @lang('Back')
                    </x-button>
                </div>
            </div>

            <div class="flex basis-1/3 justify-center">
                <div class="relative h-[5px] w-40 overflow-hidden rounded-full bg-foreground/5 backdrop-blur-lg">
                    <div
                        class="absolute inset-y-0 start-0 w-full origin-left scale-x-[calc(var(--current-step)/var(--total-steps))] rounded-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to transition rtl:origin-right">
                    </div>
                </div>
            </div>

            <div class="flex basis-1/3 items-center justify-end gap-9">
                <p
                    class="m-0 text-xs font-medium"
                    x-show="currentStep > 0 && currentStep <= 7"
                    x-transition
                    x-cloak
                >
                    @lang('Step')
                    <span x-text="Math.max(1,currentStep)">1</span>
                    @lang('of')
                    {{ $total_steps }}
                </p>
                <x-button
                    class="hidden size-9 place-items-center rounded-full bg-background text-foreground shadow-xs lg:inline-grid"
                    variant="link"
                    title="{{ __('Close') }}"
                    href="{{ route('dashboard.user.social-media.agent.index') }}"
                >
                    <x-tabler-x class="size-4" />
                </x-button>
            </div>
        </div>

        <div class="container px-0">
            <div class="mx-auto lg:max-w-[500px]">
                <form
                    class="grid w-full grid-cols-1 place-items-center"
                    @submit.prevent="submitForm"
                    @keydown.enter.prevent
                    novalidate
                >
                    <template
                        x-for="(platformId, index) in formData.platform_ids"
                        :key="'hidden-platform-' + platformId"
                    >
                        <input
                            type="hidden"
                            name="platform_ids[]"
                            :value="platformId"
                        >
                    </template>
                    @include('social-media-agent::create.create-step-0')
                    @include('social-media-agent::create.create-step-1')
                    @include('social-media-agent::create.create-step-2')
                    @include('social-media-agent::create.create-step-3')
                    @include('social-media-agent::create.create-step-4')
                    @include('social-media-agent::create.create-step-5')
                    @include('social-media-agent::create.create-step-6', ['days_of_week' => $days_of_week])
                    @include('social-media-agent::create.create-step-7', ['time_slots' => $time_slots])
                    @include('social-media-agent::create.create-step-8')
                    @include('social-media-agent::create.create-step-9')
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('agentWizard', () => ({
                platforms: @json($platforms_with_image),
                totalSteps: {{ $total_steps }},
                currentStep: 0,
                submitting: false,
                scraping: false,
                scraped: false,
                lastScrapedUrl: null,
                generatingTargets: false,
                targetsGenerated: false,
                availableTargets: [],
                stepsErrors: new Map(),
                daysOfWeek: @json($days_of_week),
                timeSlots: @json($time_slots),

                stepTitles: [
                    '{{ __('Select Platforms') }}',
                    '{{ __('Website Info') }}',
                    '{{ __('Target Audience') }}',
                    '{{ __('Post Types') }}',
                    '{{ __('Settings') }}',
                    '{{ __('Schedule') }}',
                    '{{ __('Review') }}'
                ],

                formData: {
                    name: '',
                    platform_ids: [],
                    site_url: '',
                    site_description: '',
                    scraped_content: null,
                    scraped_content_cache_key: null,
                    target_audience: [],
                    ai_target_audience: false,
                    post_types: [],
                    language: '{{ array_key_exists('en-US', config('openai_languages', [])) ? 'en-US' : array_key_first(config('openai_languages', [])) ?? 'en-US' }}',
                    plan_type: 'weekly',
                    tone: 'professional',
                    include_hashtags: false,
                    hashtag_count: 5,
                    include_emoji: false,
                    has_image: false,
                    publishing_type: 'post',
                    ai_schedule: false,
                    schedule_days: [],
                    schedule_times: [],
                    daily_post_count: 1
                },

                init() {
                    this.buildStepsErrors();

                    // Set defaults
                    const daysSource = Array.isArray(this.daysOfWeek) ? this.daysOfWeek : Object.values(this.daysOfWeek);
                    this.formData.schedule_days = daysSource
                        .slice(0, 5)
                        .map(day => {
                            if (day && typeof day === 'object' && 'number' in day) {
                                return String(day.number);
                            }

                            const numericValue = Number(day);

                            return Number.isNaN(numericValue) ? null : String(numericValue);
                        })
                        .filter(day => day !== null);
                    this.formData.schedule_times = [this.timeSlots.find(t => t.key === 'noon')];
                },

                buildStepsErrors() {
                    for (let i = 0; i <= this.totalSteps; i++) {
                        this.stepsErrors.set(i, []);
                    }
                },

                emptyStepsErrors() {
                    this.buildStepsErrors();
                },

                checkCurrentStepErrors() {
                    this.stepsErrors.set(this.currentStep, []);

                    switch (this.currentStep) {
                        case 1:
                            if (!this.formData.platform_ids.length) {
                                this.stepsErrors.get(this.currentStep).push('{{ __('Please select a platform.') }}');
                            }

                            break;
                        case 2:
                            const hasUrl = Boolean(this.formData.site_url && this.formData.site_url.trim());
                            const hasDescription = Boolean(this.formData.site_description && this.formData.site_description.trim());
                            const hasScrape = this.hasScrapedDetails();

                            if (!hasUrl && !hasDescription) {
                                this.stepsErrors.get(this.currentStep).push('{{ __('Please provide a website URL or describe your brand.') }}');
                            }

                            if (hasUrl && !hasScrape && !hasDescription) {
                                this.stepsErrors.get(this.currentStep).push(
                                    '{{ __('We could not scrape your website. Please fill the brand description manually.') }}');
                            }

                            break;
                        case 3:
                            if (!this.formData.target_audience.length && !this.formData.ai_target_audience) {
                                this.stepsErrors.get(this.currentStep).push('{{ __('Please select target audiences.') }}');
                            }

                            break;
                        case 4:
                            if (!this.formData.post_types.length) {
                                this.stepsErrors.get(this.currentStep).push('{{ __('Please select post types.') }}');
                            }

                            break;
                        case 6:
                            if (!this.formData.schedule_days.length || !this.formData.schedule_times.length) {
                                this.stepsErrors.get(this.currentStep).push('{{ __('Please set a schedule.') }}');
                            }

                            break;
                        case 7:
                            if (!this.formData.name.trim()) {
                                this.stepsErrors.get(this.currentStep).push('{{ __('Please fill the agent name.') }}');
                            }

                            break;
                    }
                },

                canProceed(errorsArray) {
                    return !this.stepsErrors.get(this.currentStep).length
                },

                hasMeaningfulScrapeContent(content) {
                    if (!content || typeof content !== 'object') {
                        return false;
                    }

                    const hasSummary = Boolean(content.summary && content.summary.trim && content.summary.trim().length);
                    const pagesCount = Number(content.pages_count || (Array.isArray(content.pages) ? content.pages.length : 0));

                    return hasSummary || pagesCount > 0;
                },

                hasScrapedDetails() {
                    const cacheKey = this.formData.scraped_content_cache_key;
                    const content = this.formData.scraped_content;

                    return Boolean(cacheKey) || this.hasMeaningfulScrapeContent(content);
                },

                shouldForceManualDescription() {
                    const hasUrl = Boolean(this.formData.site_url && this.formData.site_url.trim());
                    const hasDescription = Boolean(this.formData.site_description && this.formData.site_description.trim());

                    return hasUrl && !hasDescription && !this.hasScrapedDetails();
                },

                async ensureScrapeIfNeeded() {
                    const hasUrl = Boolean(this.formData.site_url && this.formData.site_url.trim());

                    if (!hasUrl || this.hasScrapedDetails() || this.scraping) {
                        return;
                    }

                    await this.scrapeWebsite();
                },

                async nextStep(errorsArray) {
                    if (this.currentStep === 2) {
                        await this.ensureScrapeIfNeeded();
                    }

                    this.checkCurrentStepErrors();

                    if (this.currentStep === 2 && this.shouldForceManualDescription()) {
                        window.dispatchEvent(new CustomEvent('social-media-agent-force-description'));
                    }

                    if (this.canProceed() && this.currentStep < 7) {
                        this.currentStep++;
                    }
                },

                prevStep() {
                    if (this.currentStep > 0) {
                        this.currentStep--;
                    }
                },

                resetScrapedContent() {
                    this.scraped = false;
                    this.formData.scraped_content = null;
                    this.formData.scraped_content_cache_key = null;
                    this.lastScrapedUrl = null;
                },

                handleSiteUrlInput(value) {
                    const sanitized = typeof value === 'string' ? value.trim() : '';

                    if (!sanitized || sanitized !== this.lastScrapedUrl) {
                        this.resetScrapedContent();
                    }
                },

                async scrapeWebsite() {
                    const url = (this.formData.site_url || '').trim();
                    this.formData.site_url = url;
                    if (!url) {
                        this.resetScrapedContent();
                        return false;
                    }

                    this.scraping = true;
                    let scrapedSuccessfully = false;
                    try {
                        const response = await fetch('{{ route('dashboard.user.social-media.agent.scrape-website') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                url
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            const sanitizedContent = this.sanitizeScrapedContent(data.scraped_content || data);
                            if (this.hasMeaningfulScrapeContent(sanitizedContent)) {
                                this.formData.scraped_content = sanitizedContent;
                                this.formData.scraped_content_cache_key = data.cache_key || null;
                                this.scraped = true;
                                this.lastScrapedUrl = url;
                                scrapedSuccessfully = true;
                            } else {
                                this.resetScrapedContent();
                            }
                        } else {
                            this.resetScrapedContent();
                        }
                    } catch (error) {
                        console.error('Scraping error:', error);
                        alert('Failed to scrape website. Please try again.');
                        this.resetScrapedContent();
                    } finally {
                        this.scraping = false;
                    }

                    return scrapedSuccessfully;
                },

                async generateTargets() {
                    this.generatingTargets = true;
                    try {
                        const payload = {
                            site_description: this.formData.site_description,
                            existing_targets: this.availableTargets.map(target => target.name)
                        };

                        if (this.formData.scraped_content_cache_key) {
                            payload.scraped_content_cache_key = this.formData.scraped_content_cache_key;
                        } else if (this.formData.scraped_content) {
                            payload.scraped_content = this.sanitizeScrapedContent(this.formData.scraped_content);
                        }

                        const response = await fetch('{{ route('dashboard.user.social-media.agent.generate-targets') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.availableTargets = data.targets;
                            this.targetsGenerated = true;
                        }
                    } catch (error) {
                        console.error('Target generation error:', error);
                        alert('Failed to generate targets. Please try again.');
                    } finally {
                        this.generatingTargets = false;
                    }
                },

                toggleTarget(target) {
                    const index = this.formData.target_audience.findIndex(t => t.id === target.id);
                    if (index > -1) {
                        this.formData.target_audience.splice(index, 1);
                    } else {
                        this.formData.target_audience.push(target);
                    }
                },

                isTargetSelected(targetId) {
                    return this.formData.target_audience.some(t => t.id === targetId);
                },

                hasTimeSlot(slot) {
                    return this.formData.schedule_times.some(t => t.key === slot);
                },

                toggleTimeSlot(slot) {
                    const index = this.formData.schedule_times.findIndex(t => t.key === slot);

                    if (index > -1) {
                        this.formData.schedule_times.splice(index, 1);
                        this.ensureDailyPostCountMatchesSlots();

                        return;
                    }

                    const maxSlots = this.maxSelectableSlots();
                    if (this.formData.schedule_times.length >= maxSlots) {
                        this.notifyTimeSlotLimit(maxSlots);

                        return;
                    }

                    const match = this.timeSlots.find(t => t.key === slot);
                    if (!match) {
                        return;
                    }

                    this.formData.schedule_times.push(match);
                    this.ensureDailyPostCountMatchesSlots();
                },

                handleDailyPostCountChange() {
                    let count = Number(this.formData.daily_post_count) || 1;
                    count = Math.max(1, Math.min(10, Math.floor(count)));

                    const currentSlots = this.formData.schedule_times.length;
                    const maxSlots = this.maxSelectableSlots(count);

                    if (currentSlots > maxSlots) {
                        this.formData.schedule_times = this.formData.schedule_times.slice(0, maxSlots);
                    }

                    if (count < this.formData.schedule_times.length) {
                        count = this.formData.schedule_times.length;
                    }

                    this.formData.daily_post_count = count;
                },

                ensureDailyPostCountMatchesSlots() {
                    const slotCount = this.formData.schedule_times.length;

                    if (slotCount === 0) {
                        return;
                    }

                    if (!this.formData.daily_post_count || this.formData.daily_post_count < slotCount) {
                        this.formData.daily_post_count = Math.min(10, slotCount);
                    }
                },

                maxSelectableSlots(count = null) {
                    const value = Number(count ?? this.formData.daily_post_count) || 1;

                    if (value <= 1) {
                        return 1;
                    }

                    if (value === 2) {
                        return 2;
                    }

                    return 3;
                },

                notifyTimeSlotLimit(limit) {
                    if (window?.toastr) {
                        const message = limit === 1 ?
                            '{{ __('Select only one time slot when posting once per day.') }}' :
                            '{{ __('Increase the number of posts per day before selecting more time slots.') }}';
                        window.toastr.info(message);
                    }
                },

                getPlatformById(id) {
                    const platform = this.platforms.find(p => p.id === id);

                    return platform;
                },

                sanitizeScrapedContent(data) {
                    if (!data || typeof data !== 'object') {
                        return null;
                    }

                    const sanitizeString = (value, maxLength) => {
                        if (typeof value !== 'string') {
                            return null;
                        }

                        const trimmed = value.trim();

                        if (!trimmed.length) {
                            return null;
                        }

                        return trimmed.slice(0, maxLength);
                    };

                    const sanitizePage = (page) => {
                        if (!page || typeof page !== 'object') {
                            return null;
                        }

                        const headings = Array.isArray(page.headings) ?
                            page.headings
                            .filter(heading => typeof heading === 'string' && heading.trim().length)
                            .map(heading => heading.trim().slice(0, 160))
                            .slice(0, 5) : [];

                        const sanitized = {
                            title: sanitizeString(page.title, 200),
                            meta_description: sanitizeString(page.meta_description, 400),
                            content: sanitizeString(page.content, 500),
                            headings: headings.length ? headings : null,
                        };

                        Object.keys(sanitized).forEach(key => {
                            if (sanitized[key] === null || (Array.isArray(sanitized[key]) && !sanitized[key].length)) {
                                delete sanitized[key];
                            }
                        });

                        return Object.keys(sanitized).length ? sanitized : null;
                    };

                    const pages = Array.isArray(data.pages) ?
                        data.pages.map(sanitizePage).filter(Boolean) : [];

                    const sanitized = {
                        base_url: sanitizeString(data.base_url, 255),
                        summary: sanitizeString(data.summary, 1000),
                        pages_count: pages.length,
                        pages,
                    };

                    Object.keys(sanitized).forEach(key => {
                        if (sanitized[key] === null || (Array.isArray(sanitized[key]) && !sanitized[key].length)) {
                            delete sanitized[key];
                        }
                    });

                    return Object.keys(sanitized).length ? sanitized : null;
                },

                async submitForm() {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif
                    this.currentStep = 8;
                    this.submitting = true;

                    const payload = {};

                    for (const [key, value] of Object.entries(this.formData)) {
                        if (key === 'platform_ids' && Array.isArray(value)) {
                            payload.platform_ids = value;
                        } else if (key === 'post_types' && Array.isArray(value)) {
                            payload.post_types = value;
                        } else if (key === 'schedule_days' && Array.isArray(value)) {
                            payload.schedule_days = value;
                        } else if (key === 'schedule_times' && Array.isArray(value)) {
                            payload.schedule_times = value;
                        } else if (key === 'target_audience' && Array.isArray(value)) {
                            payload.target_audience = value;
                        } else if (key === 'scraped_content') {
                            continue;
                        } else if (key === 'scraped_content_cache_key' && value) {
                            payload.scraped_content_cache_key = value;
                        } else if (key === 'include_hashtags' || key === 'include_emoji' || key === 'has_image') {
                            payload[key] = value ? 1 : 0;
                        } else if (value !== null && value !== undefined) {
                            payload[key] = value;
                        }
                    }

                    if (!payload.scraped_content_cache_key && this.formData.scraped_content) {
                        payload.scraped_content = this.sanitizeScrapedContent(this.formData.scraped_content);
                    }

                    let backendErrorsHandled = false;

                    try {
                        const response = await fetch('{{ route('dashboard.user.social-media.agent.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json().catch(() => ({}));

                        if (!response.ok || data.success === false) {
                            backendErrorsHandled = this.handleBackendErrors(data.errors || null);
                            const backendMessage = data.message || this.extractFirstError(data.errors) || 'Failed to create agent.';
                            throw new Error(backendMessage);
                        }

                        this.emptyStepsErrors();
                        this.currentStep = 9;
                    } catch (error) {
                        toastr.error(error.message || '{{ __('Failed to create agent. Please try again.') }}')
                        if (!backendErrorsHandled) {
                            this.currentStep = 8;
                        }
                    } finally {
                        this.submitting = false;
                    }
                },

                handleBackendErrors(errors) {
                    if (!errors || typeof errors !== 'object') {
                        return false;
                    }

                    this.emptyStepsErrors();

                    const fieldStepMap = new Map([
                        ['platform_ids', 1],
                        ['site_url', 2],
                        ['site_description', 2],
                        ['scraped_content', 2],
                        ['scraped_content_cache_key', 2],
                        ['target_audience', 3],
                        ['ai_target_audience', 3],
                        ['post_types', 4],
                        ['plan_type', 5],
                        ['tone', 5],
                        ['language', 5],
                        ['include_hashtags', 5],
                        ['hashtag_count', 5],
                        ['include_emoji', 5],
                        ['has_image', 5],
                        ['publishing_type', 5],
                        ['schedule_days', 6],
                        ['schedule_times', 6],
                        ['daily_post_count', 6],
                        ['name', 7],
                    ]);

                    let firstStep = null;
                    let hasErrors = false;

                    Object.entries(errors).forEach(([field, messages]) => {
                        const normalizedField = field.split('.')[0];
                        const step = fieldStepMap.get(normalizedField) ?? 7;
                        const normalizedMessages = Array.isArray(messages) ? messages : [messages];
                        let added = false;

                        normalizedMessages.forEach(message => {
                            if (!message) {
                                return;
                            }

                            this.stepsErrors.get(step).push(message);
                            hasErrors = true;
                            added = true;
                        });

                        if (added) {
                            if (firstStep === null) {
                                firstStep = step;
                            } else {
                                firstStep = Math.min(firstStep, step);
                            }
                        }
                    });

                    if (hasErrors && firstStep !== null) {
                        this.currentStep = firstStep;
                    }

                    return hasErrors;
                },

                extractFirstError(errors) {
                    if (!errors || typeof errors !== 'object') {
                        return null;
                    }

                    for (const messages of Object.values(errors)) {
                        if (Array.isArray(messages) && messages.length) {
                            return messages[0];
                        }

                        if (typeof messages === 'string' && messages.trim().length) {
                            return messages;
                        }
                    }

                    return null;
                }
            }))
        })
    </script>
@endpush
