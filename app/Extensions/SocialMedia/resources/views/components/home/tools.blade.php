@php
    $tools = [
        [
            'title' => 'Viral Ideas',
            'href' => route('dashboard.user.openai.generator.workbook', 'viral_ideas'),
            'slug' => 'viral_ideas',
            'icon' => '💸',
        ],
        [
            'title' => 'Ad Script',
            'href' => route('dashboard.user.openai.generator.workbook', 'ad_script'),
            'slug' => 'ad_script',
            'icon' => '📣',
        ],
        [
            'title' => 'Marketing Plan',
            'href' => route('dashboard.user.openai.generator.workbook', 'marketing_plan'),
            'slug' => 'marketing_plan',
            'icon' => '📄',
        ],
        [
            'title' => 'Video Script',
            'href' => route('dashboard.user.openai.generator.workbook', 'video_script'),
            'slug' => 'video_script',
            'icon' => '📷',
        ],
    ];

    $checkArray = \App\Models\OpenAIGenerator::query()->whereIn('slug', \Illuminate\Support\Arr::pluck($tools, 'slug'))->pluck('slug')->toArray();
@endphp

<div class="lqd-social-media-tools-grid-wrap">
    <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <h3 class="m-0">
            @lang('AI  Tools')
        </h3>

        <x-button
            class="text-2xs"
            variant="link"
            href="{{ (bool) \App\Helpers\Classes\Helper::setting('feature_ai_writer', null, $setting) === true ? route('dashboard.user.openai.list') : '#' }}"
        >
            @lang('View All')
            <x-tabler-chevron-right class="size-4" />
        </x-button>
    </div>

    <div class="lqd-social-media-tools-grid grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
        @foreach ($tools as $tool)
            @continue(!in_array($tool['slug'], $checkArray))
            <x-card
                class="lqd-social-media-tool text-sm font-medium text-heading-foreground hover:scale-105 hover:shadow-lg hover:shadow-black/5"
                class:body="flex items-center justify-center text-center gap-3"
            >
                <span class="text-2xl">
                    {!! $tool['icon'] !!}
                </span>
                @lang($tool['title'])

                <a
                    class="absolute inset-0 z-1"
                    href="{{ $tool['href'] }}"
                ></a>
            </x-card>
        @endforeach
    </div>
</div>
