@php
    $currencySymbol = $currency ?? currency()->symbol;
@endphp

<div @class([
    'rounded-2xl p-2.5',
    'bg-gradient-fade' => $plan->is_featured,
])>
    <div class="flex flex-col items-start rounded-md bg-background p-5 text-start transition-all lg:py-9">
        <h5 class="mb-5 inline-flex rounded-full border px-3.5 py-1 text-sm font-medium">
            {!! $plan->name !!}
        </h5>

        <p class="mb-6 font-heading text-[34px] font-semibold leading-none text-heading-foreground">
            {!! displayPlanPrice($plan, currency()) !!}
            <span class="mt-3 block text-2xs font-normal text-[#4A5C7380]">
                {{ $period ?? $plan->frequency }}
            </span>
        </p>

        <a
            @class([
                'border border-border rounded-full text-base font-medium p-4 w-full text-center hover:bg-heading-foreground hover:text-heading-background transition-all hover:shadow-xl hover:shadow-black/10 hover:scale-105',
                'bg-heading-foreground text-heading-background border-heading-foreground shadow-xl shadow-black/10' =>
                    $plan->is_featured,
            ])
            href="{{ route('register', ['plan' => $plan->id]) }}"
        >
            {{ __('Select') }} {{ __($plan->name) }}
        </a>

        @if ($plan->description)
            <p class="mt-6 text-xs font-medium text-heading-foreground">
                {!! $plan->description !!}
            </p>
        @endif

        <x-plan-details-card
            :plan="$plan"
            :period="$plan->frequency"
        />

    </div>
</div>
