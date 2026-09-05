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

<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 2"
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <h2 class="mb-9 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Set the tone and structure')
        </span>
        @lang('Select the article type')
    </h2>

    <div class="flex flex-wrap gap-2 rounded-[20px] border px-5 py-7 text-center max-h-[300px] overflow-y-auto overflow-x-hidden">
        @foreach ($post_types as $key => $post_type)
            <label
                class="group relative flex min-h-[42px] items-center gap-1.5 rounded-full bg-foreground/5 px-4 py-2.5 text-xs text-heading-foreground has-[input:checked]:bg-primary/5 has-[input:checked]:text-primary"
            >
                <input
                    class="peer absolute inset-0 z-1 cursor-pointer opacity-0"
                    type="checkbox"
                    value="{{ $key }}"
                    x-model="formData.post_types"
                >
                <span>
                    {{ $post_type['title'] }}
                </span>
                <span
                    class="pointer-events-none absolute bottom-full left-1/2 mb-2 w-[clamp(269px,269px,85vw)] origin-bottom -translate-x-1/2 translate-y-1 scale-95 rounded-md border bg-background/95 px-3 py-2 text-center text-2xs font-medium text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm transition group-hover:translate-y-0 group-hover:scale-100 group-hover:opacity-100"
                >
                    {{ $post_type['description'] }}</span>
                <span
                    class="inline-grid size-[22px] place-items-center rounded-full border border-foreground/15 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground"
                >
                    <x-tabler-check class="hidden size-4 group-has-[input:checked]:flex" />
                </span>
            </label>
        @endforeach
    </div>

    <div class="mt-2">
        @include('blogpilot::create.step-error', ['step' => 4])
    </div>

    <x-button
        class="mt-5 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground disabled:from-foreground/5 disabled:via-foreground/5 disabled:to-foreground/5"
        type="button"
        @click.prevent="nextStep()"
    >
        @lang('Continue')
        <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
