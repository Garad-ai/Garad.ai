@php
    $variant = $variant ?? 'default';

    $defaultDescription = __('This can take a couple of minutes. We will refresh automatically once it is ready.');

    $variants = [
        'default' => [
            'wrapper'        => 'w-full px-4 py-8',
            'spinner'        => 'size-10',
            'message'        => 'text-xs',
            'description'    => 'text-3xs',
            'description_text' => $defaultDescription,
        ],
        'compact' => [
            'wrapper'        => 'w-full px-3 py-4 min-h-[110px]',
            'spinner'        => 'size-7',
            'message'        => 'text-[11px]',
            'description'    => 'text-[9px]',
            'description_text' => $defaultDescription,
        ],
        'micro' => [
            'wrapper'        => 'px-2 py-2 min-h-[64px]',
            'spinner'        => 'size-5',
            'message'        => 'text-[10px]',
            'description'    => 'text-[8px]',
            'description_text' => __('We will refresh automatically once it is ready.'),
        ],
    ];

    $config = $variants[$variant] ?? $variants['default'];

    $baseClasses = 'relative z-1 grid aspect-video place-items-center overflow-hidden rounded-lg border border-dashed border-primary/40 bg-primary/5 text-center shadow-sm shadow-black/5';
    $wrapperClasses = trim($baseClasses . ' ' . $config['wrapper'] . ' ' . ($class ?? ''));
@endphp

<figure class="{{ $wrapperClasses }}">
    <div class="flex flex-col items-center gap-3">
        <x-tabler-loader-2 class="{{ $config['spinner'] }} animate-spin text-primary" />
        <div class="{{ $config['message'] }} font-medium text-heading-foreground">
            <p class="m-0">{{ $message ?? __('Generating your video...') }}</p>
            <p class="m-0 {{ $config['description'] }} opacity-70">
                {{ $description ?? $config['description_text'] }}
            </p>
        </div>
    </div>
</figure>
