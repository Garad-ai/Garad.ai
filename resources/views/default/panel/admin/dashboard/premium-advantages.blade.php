@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    /*
    |--------------------------------------------------------------------------
    | GARAD AI — AI SERVICES STATUS
    |--------------------------------------------------------------------------
    | Dedicated operational widget.
    |
    | Purpose:
    | - Show whether core Garad AI services are available
    | - Do NOT duplicate Overview / Finance / Users widgets
    |--------------------------------------------------------------------------
    */

    $garadFailedJobs = 0;

    try {
        if (Schema::hasTable('failed_jobs')) {
            $garadFailedJobs = DB::table('failed_jobs')->count();
        }
    } catch (\Throwable $e) {
        $garadFailedJobs = 0;
    }

    /*
    |--------------------------------------------------------------------------
    | SERVICE AVAILABILITY
    |--------------------------------------------------------------------------
    | Existing Garad AI modules are used.
    | No fake frontend-only status.
    |--------------------------------------------------------------------------
    */

    $garadServices = collect([
        [
            'name'        => __('Chat Pro'),
            'description' => __('Advanced AI chat, files, tools and model access'),
            'icon'        => 'message-circle-2',
            'enabled'     => \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro'),
        ],

        [
            'name'        => __('Image Generation'),
            'description' => __('AI image creation and visual generation'),
            'icon'        => 'photo-ai',
            'enabled'     =>
                \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-smart-image')
                || (bool) setting('ai_chat_pro_image_generation_feature', 1),
        ],

        [
            'name'        => __('Video Generation'),
            'description' => __('AI video generation and processing'),
            'icon'        => 'video',
            'enabled'     =>
                \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-video-pro'),
        ],

        [
            'name'        => __('Presentations'),
            'description' => __('AI presentation and slide generation'),
            'icon'        => 'presentation',
            'enabled'     =>
                \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-presentation'),
        ],

        [
            'name'        => __('AI Agents'),
            'description' => __('Agent workflows and autonomous task execution'),
            'icon'        => 'robot',
            'enabled'     =>
                \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-agent'),
        ],

        [
            'name'        => __('Voice & Audio'),
            'description' => __('Voice, speech and realtime audio capabilities'),
            'icon'        => 'microphone-2',
            'enabled'     =>
                \App\Helpers\Classes\MarketplaceHelper::isRegistered('openai-realtime-chat')
                || (bool) setting('realtime_voice_chat', 0),
        ],
    ]);

    $garadActiveServices = $garadServices
        ->where('enabled', true)
        ->count();

    $garadServiceTotal = $garadServices->count();

    $garadAvailability = $garadServiceTotal > 0
        ? round(($garadActiveServices / $garadServiceTotal) * 100)
        : 0;

    $garadHealthy =
        $garadActiveServices === $garadServiceTotal
        && $garadFailedJobs === 0;
@endphp


<x-card
    class="garad-ai-services-card overflow-hidden"
    class:body="p-0"
    id="{{ 'admin-card-' . ($widget?->name?->value ?? 'ai-services-status') }}"
    size="md"
>
    {{-- Header --}}
    <div class="flex items-start justify-between gap-5 border-b px-6 py-5">
        <div class="flex items-start gap-3">
            <div
                class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
            >
                <x-tabler-cpu-2 class="size-5" />
            </div>

            <div>
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <h2 class="m-0 text-base font-bold">
                        {{ __('AI Services Status') }}
                    </h2>

                    <span
                        @class([
                            'inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-semibold',
                            'bg-green-500/10 text-green-600 dark:text-green-400' => $garadHealthy,
                            'bg-amber-500/10 text-amber-600 dark:text-amber-400' => ! $garadHealthy,
                        ])
                    >
                        <span
                            @class([
                                'size-1.5 rounded-full',
                                'bg-green-500' => $garadHealthy,
                                'bg-amber-500' => ! $garadHealthy,
                            ])
                        ></span>

                        {{ $garadHealthy
                            ? __('Healthy')
                            : __('Review') }}
                    </span>
                </div>

                <p class="m-0 text-xs leading-relaxed text-foreground/50">
                    {{ __('Live availability of core Garad AI capabilities') }}
                </p>
            </div>
        </div>

        <div class="text-end">
            <div class="text-2xl font-bold tracking-tight">
                {{ $garadAvailability }}%
            </div>

            <div class="text-[10px] text-foreground/40">
                {{ __('Available') }}
            </div>
        </div>
    </div>


    {{-- Services --}}
    <div class="grid grid-cols-1 gap-px bg-border sm:grid-cols-2">
        @foreach ($garadServices as $service)
            <div class="group bg-background px-5 py-4">
                <div class="flex items-start gap-3">

                    <div
                        @class([
                            'inline-flex size-9 shrink-0 items-center justify-center rounded-xl',
                            'bg-green-500/10 text-green-600 dark:text-green-400' => $service['enabled'],
                            'bg-foreground/5 text-foreground/35' => ! $service['enabled'],
                        ])
                    >
                        @switch($service['icon'])

                            @case('message-circle-2')
                                <x-tabler-message-circle-2 class="size-4.5" />
                                @break

                            @case('photo-ai')
                                <x-tabler-photo-ai class="size-4.5" />
                                @break

                            @case('video')
                                <x-tabler-video class="size-4.5" />
                                @break

                            @case('presentation')
                                <x-tabler-presentation class="size-4.5" />
                                @break

                            @case('robot')
                                <x-tabler-robot class="size-4.5" />
                                @break

                            @default
                                <x-tabler-microphone-2 class="size-4.5" />

                        @endswitch
                    </div>


                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="m-0 truncate text-xs font-bold">
                                {{ $service['name'] }}
                            </h3>

                            <span
                                @class([
                                    'shrink-0 rounded-full px-2 py-1 text-[9px] font-bold uppercase tracking-wide',
                                    'bg-green-500/10 text-green-600 dark:text-green-400' => $service['enabled'],
                                    'bg-foreground/5 text-foreground/40' => ! $service['enabled'],
                                ])
                            >
                                {{ $service['enabled']
                                    ? __('Active')
                                    : __('Unavailable') }}
                            </span>
                        </div>

                        <p
                            class="mb-0 mt-1 line-clamp-2 text-[10.5px] leading-relaxed text-foreground/45"
                        >
                            {{ $service['description'] }}
                        </p>
                    </div>

                </div>
            </div>
        @endforeach
    </div>


    {{-- Footer operational health --}}
    <div class="grid grid-cols-2 gap-px border-t bg-border">

        <div class="bg-background px-5 py-4">
            <div class="mb-1 text-[10px] font-medium text-foreground/40">
                {{ __('Active Services') }}
            </div>

            <div class="flex items-end gap-1">
                <span class="text-xl font-bold">
                    {{ $garadActiveServices }}
                </span>

                <span class="mb-0.5 text-[10px] text-foreground/40">
                    / {{ $garadServiceTotal }}
                </span>
            </div>
        </div>


        <div class="bg-background px-5 py-4">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-[10px] font-medium text-foreground/40">
                    {{ __('Failed Jobs') }}
                </span>

                <span
                    @class([
                        'size-1.5 rounded-full',
                        'bg-green-500' => $garadFailedJobs === 0,
                        'bg-red-500' => $garadFailedJobs > 0,
                    ])
                ></span>
            </div>

            <div class="text-xl font-bold">
                {{ number_format($garadFailedJobs) }}
            </div>
        </div>

    </div>
</x-card>