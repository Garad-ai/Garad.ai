<div class="header-focus-mode-nav-toggle-container me-3 hidden items-center gap-3 lg:group-[&.focus-mode]/body:flex">
    <x-focus-mode-drop-down />

    @if ($title ?? true)
        <x-header-logo />
    @endif
</div>
