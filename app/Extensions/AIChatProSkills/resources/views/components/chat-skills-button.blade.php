@if ((bool) setting('ai_chat_pro_skills_enabled', '1'))
    <button
        class="lqd-generator-skills-trigger group"
        type="button"
        title="{{ __('Skills') }}"
        @click.prevent="window.dispatchEvent(new CustomEvent('open-skills-modal')); toggle(false)"
    >
        <x-tabler-clipboard class="size-4.5 shrink-0 opacity-75" />

        <span class="truncate">
            {{ __('Skills') }}
        </span>
    </button>

    @pushOnce('script')
        <script>
            window.__skillsAuth = {{ auth()->check() ? 'true' : 'false' }};
            window.__skillsPlan =
                {{ auth()->check() && (auth()->user()->isAdmin() || (auth()->user()->relationPlan && auth()->user()->relationPlan->checkOpenAiItem('ai_chat_pro_skills'))) ? 'true' : 'false' }};
        </script>
    @endPushOnce
@endif
