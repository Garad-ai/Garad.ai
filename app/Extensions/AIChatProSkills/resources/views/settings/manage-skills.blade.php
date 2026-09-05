@php
    $skillsCount = \App\Extensions\AIChatProSkills\System\Models\Skill::query()->active()->count();
    $publicSkillsCount = \App\Extensions\AIChatProSkills\System\Models\Skill::query()->active()->public()->count();
@endphp

<div>
    <x-form-step
        auto-increment
        label="{{ __('Skills Management') }}"
    />

    <x-card class="mb-2">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="m-0 text-xs font-medium">
                {{ __(':total total skills, :public public', ['total' => $skillsCount, 'public' => $publicSkillsCount]) }}
            </p>

            <x-button
                variant="ghost-shadow"
                href="{{ route('dashboard.admin.skills.index') }}"
            >
                <x-tabler-tool class="size-4" />
                {{ __('Manage Skills') }}
            </x-button>
        </div>
    </x-card>
</div>
