@php
    use Illuminate\Support\Str;
@endphp

@php
    $post_types = [
        'how-to-guide' => [
            'title' => '"How-To" Guide',
            'description' => 'Step-by-step tutorials (e.g., "How to Start a Dropshipping Store").',
        ],
        'listicle' => [
            'title' => 'Listicle (Top X / Best X)',
            'description' => 'Top 10, Top 20, Best Tools, Best Tips.',
        ],
        'informative' => [
            'title' => 'Informative / Educational',
            'description' => 'Explains a topic in detail (e.g., "What Is SEO?").',
        ],
        'ultimate-guide' => [
            'title' => 'Ultimate Guide / Comprehensive Guide',
            'description' => 'Long-form, all-in-one resources (e.g., "The Ultimate Guide to AI Marketing").',
        ],
        'beginners-guide' => [
            'title' => 'Beginner’s Guide',
            'description' => 'Content for newcomers (e.g., "AI for Beginners").',
        ],
        'faq' => [
            'title' => 'FAQ / Common Questions',
            'description' => 'Short answers to frequently asked questions.',
        ],
        'comparison' => [
            'title' => 'Comparison / VS Article',
            'description' => 'Tool A vs Tool B.',
        ],
        'best-of-year' => [
            'title' => '"Best of Year" Article',
            'description' => 'Best AI Tools in 2025, Best Laptops 2025.',
        ],
        'product-review' => [
            'title' => 'Product Review',
            'description' => 'Single product review.',
        ],
        'product-roundup' => [
            'title' => 'Product Roundup Review',
            'description' => 'Multiple product reviews in one post.',
        ],
        'buyers-guide' => [
            'title' => 'Buyer’s Guide',
            'description' => 'How to choose something (e.g., "Buyer’s Guide for Gaming Monitors").',
        ],
        'case-study' => [
            'title' => 'Case Study',
            'description' => 'Real results and data.',
        ],
        'problem-solution' => [
            'title' => 'Problem & Solution Post',
            'description' => 'Explains an issue and the fix.',
        ],
        'step-by-step-tutorial' => [
            'title' => 'Step-by-Step Tutorial',
            'description' => 'More detailed than "how-to."',
        ],
        'checklist' => [
            'title' => 'Checklist Post',
            'description' => 'Simple checklists for quick tasks.',
        ],
        'tips-tricks' => [
            'title' => 'Tips & Tricks',
            'description' => 'Fast actionable advice.',
        ],
        'story-driven' => [
            'title' => 'Story-Driven Article',
            'description' => 'Narrative intro or full storytelling.',
        ],
        'opinion-editorial' => [
            'title' => 'Opinion / Editorial',
            'description' => 'What I think about AI replacing designers.',
        ],
        'trend-analysis' => [
            'title' => 'Trend Analysis',
            'description' => 'What’s happening now in the industry.',
        ],
        'predictions' => [
            'title' => 'Predictions / Future Outlook',
            'description' => 'AI in 2030 — What’s Coming Next.',
        ],
        'myths-misconceptions' => [
            'title' => 'Myths & Misconceptions',
            'description' => 'Debunking common false beliefs.',
        ],
        'industry-report' => [
            'title' => 'Industry Report',
            'description' => 'Data-heavy insights.',
        ],
        'news-summary' => [
            'title' => 'News Summary',
            'description' => 'Summaries of recent events.',
        ],
        'strategy-guide' => [
            'title' => 'Strategy Guide',
            'description' => 'Marketing strategy, business strategy, etc.',
        ],
        'framework-explanation' => [
            'title' => 'Framework / Model Explanation',
            'description' => 'Explaining systems like SWOT, AIDA, etc.',
        ],
        'template-example' => [
            'title' => 'Template / Example Post',
            'description' => 'Includes templates or ready-to-use formats.',
        ],
        'beginners-vs-advanced' => [
            'title' => 'Beginners vs Advanced Versions',
            'description' => 'Separate content depending on level.',
        ],
        'localized' => [
            'title' => 'Localized Article',
            'description' => 'City/country specific (e.g., "Best Cafés in Istanbul for Remote Work").',
        ],
        'niche-expert' => [
            'title' => 'Niche Expert Breakdown',
            'description' => 'Deep dive for specific industries (legal, medical, etc.).',
        ],
    ];
@endphp

@extends('panel.layout.settings', ['disable_tblr' => true])
@section('title', __('Edit Agent') . ' - ' . $agent->name)
@section('titlebar_subtitle', __('Update agent settings'))
@section('titlebar_actions')
    @include('blogpilot::components.titlebar-actions')
@endsection

@section('settings')
    <form
        class="space-y-4"
        action="{{ route('dashboard.user.blogpilot.agent.update', $agent) }}"
        method="POST"
        x-data="blogPilotEditForm"
    >
        @csrf
        @method('PUT')

        <x-card>
            <x-forms.input
                class="order-3 ms-auto"
                class:label="text-heading-foreground text-xs"
                type="checkbox"
                switcher
                switcher-fill
                size="sm"
                name="is_active"
                :checked="old('is_active', $agent->is_active)"
                value="1"
                label="{{ __('Active?') }}"
                tooltip="{{ __('When inactive, the agent will not generate new posts automatically') }}"
            />

            <x-forms.input
                class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none mt-2"
                type="checkbox"
                name="has_image"
                label="{{ __('Include Images') }}"
                size="sm"
                switcher
                switcherFill
                value="{{ old('has_image', $agent->has_image) }}"
                :checked="old('has_image', $agent->has_image)"
            />

            <x-forms.input
                class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none mt-2"
                type="checkbox"
                name="has_emoji"
                label="{{ __('Include Emoji') }}"
                size="sm"
                switcher
                switcherFill
                value="{{ old('has_emoji', $agent->has_emoji) }}"
                :checked="old('has_emoji', $agent->has_emoji)"
            />

            <x-forms.input
                class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none mt-2"
                type="checkbox"
                name="has_web_search"
                label="{{ __('Search the Web') }}"
                size="sm"
                switcher
                switcherFill
                value="{{ old('has_web_search', $agent->has_web_search) }}"
                :checked="old('has_web_search', $agent->has_web_search)"
            />

            <x-forms.input
                class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none mt-2"
                type="checkbox"
                name="has_keyword_search"
                label="{{ __('Search for Keywords') }}"
                size="sm"
                switcher
                switcherFill
                value="{{ old('has_keyword_search', $agent->has_keyword_search) }}"
                :checked="old('has_keyword_search', $agent->has_keyword_search)"
            />
        </x-card>

        {{-- Agent Name --}}
        <div class="relative flex select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-5 py-3 backdrop-blur-xl transition-all">
            <label
                class="@error('name') text-red-500 @enderror mb-0 w-full text-2xs font-medium text-foreground/50"
                for="blogpilot-name"
            >
                @lang('Name of the Agent')
            </label>

            <input
                class="mb-0 border-none bg-transparent bg-none text-xs font-medium text-heading-foreground"
                id="blogpilot-name"
                type="text"
                name="name"
                required
                placeholder="{{ __('Astra') }}"
                value="{{ old('name', $agent->name) }}"
            >

            @error('name')
                <p class="mb-0 mt-1 text-2xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

       {{-- Topics --}}
        <div
            class="relative select-none"
            @click.outside="topicsDropdownOpen = false"
        >
            <div
                class="flex flex-wrap items-center gap-3 rounded-[10px] border border-transparent bg-foreground/5 px-5 py-3 backdrop-blur-xl transition-all"
                :class="{ 'rounded-b-none border-foreground/5': topicsDropdownOpen && selectedTopics.length < topics.length }"
                @click.prevent="topicsDropdownOpen = !topicsDropdownOpen"
            >
                <label class="mb-0 w-full text-2xs font-medium text-foreground/50">
                    @lang('Topics')
                </label>

                <p
                    class="m-0 flex items-center text-xs font-medium"
                    x-show="!selectedTopics.length"
                >
                    @lang('Select Topics')
                </p>

                <template x-for="topic in selectedTopics" :key="topic">
                    <div
                        class="flex items-center gap-2 rounded-full bg-background px-3 py-[9px] text-2xs font-medium transition"
                        x-transition:enter-start="opacity-0 scale-95 blur-sm"
                        x-transition:enter-end="opacity-100 scale-100 blur-0"
                        x-transition:leave-start="opacity-100 scale-100 blur-0"
                        x-transition:leave-end="opacity-0 scale-95 blur-sm"
                    >
                        <span x-text="topic"></span>

                        <button
                            class="inline-grid size-[17px] place-items-center rounded-full border p-0 transition hover:scale-110 hover:border-red-500 hover:bg-red-500 hover:text-white"
                            @click.prevent.stop="
                                selectedTopics = selectedTopics.filter(t => t !== topic)
                            "
                        >
                            <x-tabler-x class="size-2.5" />
                        </button>
                    </div>
                </template>
            </div>

            <div
                class="absolute inset-x-0 top-full z-5 flex origin-top flex-wrap gap-3 rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                x-cloak
                x-show="topicsDropdownOpen && selectedTopics.length < topics.length"
                x-transition:enter-start="opacity-0 scale-95 blur-sm"
                x-transition:enter-end="opacity-100 scale-100 blur-0"
                x-transition:leave-start="opacity-100 scale-100 blur-0"
                x-transition:leave-end="opacity-0 scale-95 blur-sm"
            >
                @foreach ($agent->topic_options as $topic)
                    <div
                        class="relative flex cursor-pointer items-center rounded-full bg-background px-4 py-[9px] text-2xs font-medium transition hover:-translate-y-0.5 hover:scale-105 hover:shadow-lg hover:shadow-black/5"
                        @click.prevent="
                            if (!topicIsSelected('{{ $topic }}')) {
                                selectedTopics.push('{{ $topic }}')
                            }
                        "
                        x-show="!topicIsSelected('{{ $topic }}')"
                        x-transition:enter-start="opacity-0 scale-95 blur-sm"
                        x-transition:enter-end="opacity-100 scale-100 blur-0"
                        x-transition:leave-start="opacity-100 scale-100 blur-0"
                        x-transition:leave-end="opacity-0 scale-95 blur-sm"
                    >
                        {{ $topic }}

                        <input
                            class="invisible absolute start-0 top-0 size-0"
                            type="checkbox"
                            name="selected_topics[]"
                            value="{{ $topic }}"
                            :checked="topicIsSelected('{{ $topic }}')"
                        >
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Post Types --}}
        <div
            class="relative select-none"
            @click.outside="postTypesDropdownOpen = false"
        >
            <div
                class="flex flex-wrap items-center gap-3 rounded-[10px] border border-transparent bg-foreground/5 px-5 py-3 backdrop-blur-xl transition-all"
                :class="{ 'rounded-b-none border-foreground/5': postTypesDropdownOpen && selectedPostTypes.length < postTypeKeys.length }"
                @click.prevent="postTypesDropdownOpen = !postTypesDropdownOpen"
            >
                <label class="mb-0 w-full text-2xs font-medium text-foreground/50">
                    @lang('Post Types')
                </label>

                <p
                    class="m-0 flex items-center text-xs font-medium"
                    x-show="!selectedPostTypes.length"
                >
                    @lang('Select Post Types')
                </p>

                <template x-for="type in selectedPostTypes" :key="type">
                    <div
                        class="flex items-center gap-2 rounded-full bg-background px-3 py-[9px] text-2xs font-medium transition"
                        x-transition:enter-start="opacity-0 scale-95 blur-sm"
                        x-transition:enter-end="opacity-100 scale-100 blur-0"
                        x-transition:leave-start="opacity-100 scale-100 blur-0"
                        x-transition:leave-end="opacity-0 scale-95 blur-sm"
                    >
                        <span x-text="postTypes[type]?.title ?? type"></span>

                        <button
                            class="inline-grid size-[17px] place-items-center rounded-full border p-0 transition hover:scale-110 hover:border-red-500 hover:bg-red-500 hover:text-white"
                            @click.prevent.stop="
                                selectedPostTypes = selectedPostTypes.filter(t => t !== type)
                            "
                        >
                            <x-tabler-x class="size-2.5" />
                        </button>
                    </div>
                </template>
            </div>

            <div
                class="absolute inset-x-0 top-full z-5 grid gap-3 rounded-b-[10px] border border-t-0 border-foreground/5 bg-background/50 p-4 backdrop-blur-xl transition"
                x-cloak
                x-show="postTypesDropdownOpen && selectedPostTypes.length < postTypeKeys.length"
                x-transition:enter-start="opacity-0 scale-95 blur-sm"
                x-transition:enter-end="opacity-100 scale-100 blur-0"
                x-transition:leave-start="opacity-100 scale-100 blur-0"
                x-transition:leave-end="opacity-0 scale-95 blur-sm"
            >
                @foreach ($post_types as $key => $postType)
                    <div
                        class="relative cursor-pointer rounded-lg bg-background px-4 py-3 text-2xs transition hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5"
                        @click.prevent="
                            if (!postTypeIsSelected('{{ $key }}')) {
                                selectedPostTypes.push('{{ $key }}')
                            }
                        "
                        x-show="!postTypeIsSelected('{{ $key }}')"
                        x-transition:enter-start="opacity-0 scale-95 blur-sm"
                        x-transition:enter-end="opacity-100 scale-100 blur-0"
                        x-transition:leave-start="opacity-100 scale-100 blur-0"
                        x-transition:leave-end="opacity-0 scale-95 blur-sm"
                    >
                        <p class="mb-0 font-medium text-foreground">
                            {{ $postType['title'] }}
                        </p>
                        <p class="mb-0 mt-0.5 text-foreground/60">
                            {{ $postType['description'] }}
                        </p>

                        <input
                            class="invisible absolute start-0 top-0 size-0"
                            type="checkbox"
                            name="post_types[]"
                            value="{{ $key }}"
                            :checked="postTypeIsSelected('{{ $key }}')"
                        >
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Daily Post Count --}}
        <div class="relative flex select-none flex-col gap-2 rounded-[10px] border border-transparent bg-foreground/5 px-5 py-3 backdrop-blur-xl transition-all">
            <label
                class="@error('daily_post_count') text-red-500 @enderror mb-0 text-2xs font-medium text-foreground/50"
                for="schedule-post-count"
            >
                @lang('Number of Posts Per Day')
            </label>
            <input
                class="lqd-input-stepper border-none bg-transparent bg-none text-xs font-medium text-heading-foreground"
                type="number"
                name="daily_post_count"
                value="{{ old('daily_post_count', $agent->daily_post_count) }}"
                min="1"
                max="10"
                required
            />

            @error('daily_post_count')
                <p class="mb-0 mt-1 text-2xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Agent Info (Read-only) --}}
        <x-card>
            <x-slot:head>
                <h4 class="mb-1">
                    @lang('Other Configs Agent Configuration')
                </h4>
                <p class="mb-0 text-xs font-medium text-foreground/65">
                    @lang('To change these settings, please create a new agent')
                </p>
            </x-slot:head>

            <div class="space-y-2 text-xs">
                <p class="font-medium">
                    <span class="opacity-50">
                        @lang('Language'):
                    </span>
                    {{ Str::headline(config('openai_languages.' . $agent->language)) }}
                </p>
                <p class="font-medium">
                    <span class="opacity-50">
                        @lang('Tone'):
                    </span>
                    {{ Str::headline($agent->tone) }}
                </p>
                <p class="font-medium">
                    <span class="opacity-50">
                        @lang('Article Length'):
                    </span>
                    @php
                    $lengths = [
                        '400-800' => __('Short (400-800 Words)'),
                        '800-1200' => __('Medium (800-1200 Words)'),
                        '1200-1600' => __('Long (1200-1600 Words)'),
                    ];
                    @endphp
                    {{ $lengths[$agent->article_length] ?? Str::headline($agent->article_length) }}
                </p>
                <p class="font-medium">
                    <span class="opacity-50">
                        @lang('Schedule Days'):
                    </span>
                    {{ implode(', ', array_map(fn($d) => substr($d, 0, 3), $agent->schedule_days)) }}
                </p>
                <p class="font-medium">
                    <span class="opacity-50">
                        @lang('Time of The Day'):
                    </span>
                    {{ $agent->schedule_times[0]['label'] }}
                </p>
                <p class="font-medium">
                    <span class="opacity-50">
                        @lang('Create New Posts Every'):
                    </span>
                    {{ Str::headline($agent->frequency) }}
                </p>
            </div>
        </x-card>

        {{-- Actions --}}
		@if(\App\Helpers\Classes\Helper::appIsDemo())
			<x-button
				class="w-full"
				type="button"
				size="lg"
				onclick="toastr.error('{{ __('This action is disabled in the demo.') }}');"
			>
				@lang('Update Agent')
			</x-button>
		@else
			<x-button
				class="w-full"
				type="submit"
				size="lg"
			>
				@lang('Update Agent')
			</x-button>
		@endif

        <x-button
            class="w-full"
            variant="outline"
            size="lg"
            href="{{ route('dashboard.user.blogpilot.agent.agents') }}"
        >
            @lang('Cancel')
        </x-button>
    </form>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('blogPilotEditForm', () => ({
                // topics
                topics: @json($agent->topic_options),
                selectedTopics: @json($agent->selected_topics),
                topicsDropdownOpen: false,

                topicIsSelected(topic) {
                    return this.selectedTopics.includes(topic)
                },

                /* post types */
                postTypes: @json($post_types),
                postTypeKeys: @json(array_keys($post_types)),
                selectedPostTypes: @json($agent->post_types),
                postTypesDropdownOpen: false,

                postTypeIsSelected(type) {
                    return this.selectedPostTypes.includes(type)
                },
            }))
        })
    </script>

@endpush
