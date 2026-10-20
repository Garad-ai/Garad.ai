<nav class="garad-primary-nav" aria-label="{{ __('Garad workspace') }}">
    @auth
    <a class="garad-primary-nav-item" href="{{ route('dashboard.user.openai.chat.pro.workspace.library') }}">
        <x-tabler-library class="size-[18px]" /><span>{{ __('Library') }}</span>
    </a>
    <a class="garad-primary-nav-item" href="{{ route('dashboard.user.openai.chat.pro.scheduled.index') }}">
        <x-tabler-clock-bolt class="size-[18px]" /><span>{{ __('Scheduled') }}</span>
    </a>
    <a class="garad-primary-nav-item" href="{{ route('dashboard.user.openai.chat.pro.workspace.agents') }}">
        <x-tabler-sparkles class="size-[18px]" /><span>{{ __('Agents') }}</span>
    </a>
    @endauth
</nav>
