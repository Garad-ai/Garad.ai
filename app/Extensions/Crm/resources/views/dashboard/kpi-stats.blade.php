<x-card class:body="flex justify-between flex-wrap md:flex-nowrap py-6 px-10 max-sm:gap-8">
    <div class="flex gap-4 max-sm:w-full">
        <div class="flex grow flex-col gap-1">
            <div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
                <span class="size-2.5 rounded-sm bg-primary"></span>
                {{ __('Contacts') }}
            </div>
            <h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
                {{ number_format($totalContacts) }}
            </h3>
            <p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
                @lang('vs Last Week')
                <x-change-indicator value="{{ $contactsChange }}" />
            </p>
        </div>
    </div>

    <span class="h-px w-full bg-border sm:h-auto sm:w-px"></span>

    <div class="flex gap-4 max-sm:w-full">
        <div class="flex grow flex-col gap-1">
            <div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
                <span class="size-2.5 rounded-sm bg-secondary"></span>
                {{ __('Active Deals') }}
            </div>
            <h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
                {{ number_format($totalDeals) }}
            </h3>
            <p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
                @lang('vs Last Week')
                <x-change-indicator value="{{ $dealsChange }}" />
            </p>
        </div>
    </div>

    <span class="h-px w-full bg-border sm:h-auto sm:w-px"></span>

    <div class="flex gap-4 max-sm:w-full">
        <div class="flex grow flex-col gap-1">
            <div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
                <span class="size-2.5 rounded-sm bg-[#20C69F]"></span>
                {{ __('Pipeline Value') }}
            </div>
            <h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
                <span class="text-xl">$</span>{{ number_format($totalDealsValue, 0) }}
            </h3>
            <p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
                @lang('vs Last Week')
                <x-change-indicator value="{{ $valueChange }}" />
            </p>
        </div>
    </div>

    <span class="h-px w-full bg-border sm:h-auto sm:w-px"></span>

    <div class="flex gap-4 max-sm:w-full">
        <div class="flex grow flex-col gap-1">
            <div class="mb-1 flex items-center gap-2 text-sm font-medium text-heading-foreground">
                <span class="size-2.5 rounded-sm bg-[#3C82F6]"></span>
                {{ __('Pending Tasks') }}
            </div>
            <h3 class="mb-0.5 flex items-center gap-2 text-2xl sm:text-[30px]">
                {{ number_format($pendingTasks) }}
                @if ($overdueTasks > 0)
                    <span class="text-sm font-medium text-red-500">{{ $overdueTasks }} {{ __('overdue') }}</span>
                @endif
            </h3>
            <p class="mb-0 flex items-center gap-1 text-[12px] text-heading-foreground/50">
                @lang('Completed This Week')
                <x-change-indicator value="{{ $tasksChange }}" />
            </p>
        </div>
    </div>
</x-card>
