@if (auth()->user()?->isAdmin() || auth()->user()?->relationPlan?->checkOpenAiItem('deep_research'))
    <p
        class="mx-auto mb-0 text-balance px-5 text-center lg:w-4/5"
        id="deep-research-note"
        x-show="hasToolSelected('deep_research')"
        x-cloak
        x-collapse
    >
        <strong>
            {{ __('Deep Research Mode') }}:
        </strong>
        {{ __('Your query will be researched in depth using AI. This may take a few minutes. Results will include sources and citations.') }}
    </p>
@endif
