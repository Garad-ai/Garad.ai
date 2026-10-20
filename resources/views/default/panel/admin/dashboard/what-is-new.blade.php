@php
    /*
    |--------------------------------------------------------------------------
    | Garad AI — Latest Update
    |--------------------------------------------------------------------------
    | Version: existing Garad AI setting/version.txt
    | Update time: version.txt modification time
    | Release notes: optional cache('garad_release_notes')
    */

    $garadVersion = null;

    try {
        $garadVersion = format_double($setting->script_version);
    } catch (\Throwable $e) {
        $garadVersion = null;
    }

    if (blank($garadVersion) && file_exists(base_path('version.txt'))) {
        $garadVersion = trim((string) file_get_contents(base_path('version.txt')));
    }

    $garadVersion = $garadVersion ?: __('Current');

    $versionFile = base_path('version.txt');

    $garadLastUpdatedAt = file_exists($versionFile)
        ? \Illuminate\Support\Carbon::createFromTimestamp(filemtime($versionFile))
        : now();

    /*
     * Optional release notes.
     *
     * Example:
     * cache()->forever('garad_release_notes', [
     *     'Improved Chat Pro model selection',
     *     'Fixed AI Agents workflows',
     *     'Improved video and presentation generation',
     * ]);
     */
    $garadReleaseNotes = collect(cache()->get('garad_release_notes', []))
        ->filter(fn ($item) => filled($item))
        ->take(5);

    $garadStats = [
        [
            'label' => __('Support Tickets'),
            'value' => cache()->get('tickets') ?? 0,
            'description' => __('Open and recent support activity'),
            'icon' => 'lifebuoy',
            'route' => \Illuminate\Support\Facades\Route::has('dashboard.support.list')
                ? route('dashboard.support.list')
                : null,
        ],
        [
            'label' => __('Transactions'),
            'value' => cache()->get('transactions') ?? 0,
            'description' => __('Recorded platform transactions'),
            'icon' => 'coins',
            'route' => \Illuminate\Support\Facades\Route::has('dashboard.admin.users.index')
                ? route('dashboard.admin.users.index')
                : null,
        ],
        [
            'label' => __('Documents'),
            'value' => cache()->get('documents') ?? 0,
            'description' => __('Documents created across Garad AI'),
            'icon' => 'clipboard-text',
            'route' => \Illuminate\Support\Facades\Route::has('dashboard.user.openai.documents.all')
                ? route('dashboard.user.openai.documents.all')
                : null,
        ],
    ];
@endphp

<x-card
    class="garad-admin-whats-new overflow-hidden p-0"
    class:body="p-0"
    id="{{ 'admin-card-' . ($widget?->name?->value ?? 'what-is-new') }}"
    size="md"
>
    {{-- Header --}}
    <div class="flex flex-col gap-4 border-b px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <span
                    class="inline-flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <x-tabler-sparkles class="size-4" />
                </span>

                <h2 class="m-0 text-lg font-bold">
                    {{ __('Garad AI Overview') }}
                </h2>
            </div>

            <p class="m-0 text-xs text-foreground/55">
                {{ __('Platform activity and latest deployed version') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-full border bg-background px-3 py-1.5 text-xs font-semibold"
            >
                <span class="size-1.5 rounded-full bg-green-500"></span>
                {{ __('Operational') }}
            </span>

            <span
                class="rounded-full bg-foreground/5 px-3 py-1.5 text-xs font-semibold text-foreground/70"
            >
                v{{ $garadVersion }}
            </span>
        </div>
    </div>

    {{-- Quick metrics --}}
    <div class="grid grid-cols-1 gap-3 p-6 md:grid-cols-3">
        @foreach ($garadStats as $stat)
            <div
                class="group relative overflow-hidden rounded-2xl border bg-background p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-lg hover:shadow-black/5"
            >
                <div class="mb-7 flex items-start justify-between">
                    <span
                        class="inline-flex size-10 items-center justify-center rounded-xl bg-foreground/[0.045] text-foreground/70 transition-colors group-hover:bg-primary/10 group-hover:text-primary"
                    >
                        @if ($stat['icon'] === 'lifebuoy')
                            <x-tabler-lifebuoy class="size-5" />
                        @elseif ($stat['icon'] === 'coins')
                            <x-tabler-coins class="size-5" />
                        @else
                            <x-tabler-clipboard-text class="size-5" />
                        @endif
                    </span>

                    @if ($stat['route'])
                        <x-tabler-arrow-up-right
                            class="size-4 text-foreground/30 transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary"
                        />
                    @endif
                </div>

                <div>
                    <div class="mb-1 text-3xl font-bold tracking-tight">
                        {{ number_format((int) $stat['value']) }}
                    </div>

                    <h3 class="mb-1 text-sm font-semibold">
                        {{ $stat['label'] }}
                    </h3>

                    <p class="m-0 text-xs leading-relaxed text-foreground/50">
                        {{ $stat['description'] }}
                    </p>
                </div>

                @if ($stat['route'])
                    <a
                        class="absolute inset-0 z-10"
                        href="{{ $stat['route'] }}"
                        aria-label="{{ $stat['label'] }}"
                    ></a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Latest update --}}
    <div class="px-6 pb-6">
        <div
            class="relative overflow-hidden rounded-2xl border bg-foreground/[0.025] p-5 sm:p-6"
        >
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div
                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-green-500/10 text-green-600 dark:text-green-400"
                    >
                        <x-tabler-circle-check class="size-6" />
                    </div>

                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <h3 class="m-0 text-base font-bold">
                                {{ __('Garad AI is up to date') }}
                            </h3>

                            <span
                                class="rounded-md bg-primary/10 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-primary"
                            >
                                {{ __('Latest Release') }}
                            </span>
                        </div>

                        <p class="mb-1 text-sm text-foreground/65">
                            {{ __('Version') }}
                            <span class="font-semibold text-foreground">
                                {{ $garadVersion }}
                            </span>
                        </p>

                        <p class="m-0 text-xs text-foreground/45">
                            {{ __('Last updated') }}:
                            {{ $garadLastUpdatedAt->diffForHumans() }}
                            ·
                            {{ $garadLastUpdatedAt->format('d M Y, H:i') }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex shrink-0 items-center gap-2 rounded-xl border bg-background px-4 py-3"
                >
                    <span class="relative flex size-2.5">
                        <span
                            class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-50"
                        ></span>
                        <span
                            class="relative inline-flex size-2.5 rounded-full bg-green-500"
                        ></span>
                    </span>

                    <span class="text-xs font-semibold">
                        {{ __('System Current') }}
                    </span>
                </div>
            </div>

            {{-- Release notes --}}
            @if ($garadReleaseNotes->isNotEmpty())
                <div class="mt-6 border-t pt-5">
                    <p class="mb-3 text-xs font-bold uppercase tracking-[0.12em] text-foreground/40">
                        {{ __('Latest improvements') }}
                    </p>

                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($garadReleaseNotes as $note)
                            <div
                                class="flex items-start gap-2 rounded-lg bg-background/60 px-3 py-2.5"
                            >
                                <x-tabler-check
                                    class="mt-0.5 size-3.5 shrink-0 text-green-500"
                                />

                                <span class="text-xs leading-relaxed text-foreground/70">
                                    {{ $note }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-card>