<x-card
    class="mb-9 w-full"
    size="sm"
>
    <div class="mb-3.5 flex flex-wrap justify-between gap-3">
        <p class="m-0 text-[12px] font-medium text-foreground/80">
            {{ __('Pipeline Overview') }}
        </p>

        <p class="m-0 text-[12px] font-medium text-foreground/80 underline underline-offset-2">
            {{ __('Total Value:') }}
            ${{ number_format($totalDealsValue, 0) }}
        </p>
    </div>

    <div class="mb-4 flex flex-nowrap items-center gap-10 max-sm:flex-wrap max-sm:gap-4">
        <div class="flex h-2 w-full flex-nowrap gap-0.5 overflow-hidden rounded-lg">
            @foreach ($stages as $stage)
                @if ($stage->deals_count > 0)
                    <span style="width: {{ $totalDealsInPipeline > 0 ? ($stage->deals_count / $totalDealsInPipeline) * 100 : 0 }}%; background-color: {{ $stage->color }}"></span>
                @endif
            @endforeach
        </div>
    </div>

    <div class="flex flex-wrap gap-7 px-2 pt-1 max-sm:gap-3 sm:justify-around">
        @foreach ($stages->sortByDesc(fn($stage) => $stage->deals_count > 0 ? 1 : 0) as $stage)
            <div class="inline-flex items-center gap-2">
                <span
                    class="size-2.5 rounded-sm"
                    style="background-color: {{ $stage->color }}"
                ></span>
                <span class="text-xs">
                    {{ $stage->name }}
                </span>
                <span class="opacity-65">
                    {{ $stage->deals_count }}
                </span>
            </div>
        @endforeach
    </div>
</x-card>
