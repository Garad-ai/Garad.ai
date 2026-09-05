@extends('panel.layout.settings', [
    'disable_tblr' => true,
])

@section('title', __('Conditional Discount'))
@section('titlebar_actions', '')
@section('settings')
    <div x-data="discountData">
        <form
            action="{{ route('dashboard.admin.discount-manager.discount-save', $discount?->id) }}"
            method="POST"
        >
            @method('put')
            @csrf
            <h2 class="mb-4 pb-0">@lang('Conditional Discounts')</h2>
            <p class="mb-6 text-xs/5 opacity-60 lg:max-w-[360px]">
                @lang("Create, personalize, and manage a variety of conditional discount offers designed specifically for your users' needs and behaviors.")
            </p>
            <div class="flex w-full items-center justify-center gap-3 rounded-sm border py-2 text-blue-600">
                <x-tabler-info-circle-filled class="size-4" />
                <span>
                    @lang('Prices will be automatically updated for the relevant users.')
                </span>
            </div>

            <div class="flex flex-col gap-4">
                <x-form-step
                    class="my-4"
                    step="1"
                    label="{{ __('Discount') }}"
                />

                <x-forms.input
                    id="title"
                    size="lg"
                    label="{{ __('Title') }}"
                    tooltip="{{ __('Enter a unique name for the discount') }}"
                    name="title"
                    value="{{ $discount?->title }}"
                    required
                />

                <x-forms.input
                    id="condition"
                    size="lg"
                    type="select"
                    label="{{ __('Condition') }}"
                    tooltip="{{ __('Choose whether all or any conditions must be met') }}"
                    name="condition"
                >
                    @foreach (\App\Extensions\DiscountManager\System\Enums\ConditionEnum::cases() as $case)
                        <option
                            value="{{ $case->value }}"
                            {{ $discount?->condition == $case->value ? 'selected' : '' }}
                        >{{ __($case->label()) }}</option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="type"
                    size="lg"
                    type="select"
                    label="{{ __('Type') }}"
                    tooltip="{{ __('Select whether the discount is a percentage or fixed amount') }}"
                    name="type"
                    x-model="discountType"
                >
                    @foreach (\App\Extensions\DiscountManager\System\Enums\DiscountTypeEnum::cases() as $case)
                        <option
                            value="{{ $case->value }}"
                            {{ $discount?->type == $case->value ? 'selected' : '' }}
                        >{{ __($case->label()) }}</option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    class="lqd-input-stepper"
                    class:action="p-3 h-full content-center border-none"
                    id="amount"
                    label="{{ __('Amount') }}"
                    tooltip="{{ __('Enter the discount value (percentage or fixed amount).') }}"
                    size="lg"
                    type="number"
                    name="amount"
                    min="0"
                    max="99"
                    value="{{ $discount?->amount }}"
                    placeholder="{{ __('Percentage must be between 0-99') }}"
                    required
                >
                    <x-slot:action
                        class="h-full content-center border-none p-3"
                    >
                        @foreach (\App\Extensions\DiscountManager\System\Enums\DiscountTypeEnum::cases() as $case)
                            <div
                                x-show="discountType == '{{ $case->value }}'"
                                x-cloak
                            >
                                {!! $case->symbolIcon() !!}
                            </div>
                        @endforeach
                    </x-slot:action>
                </x-forms.input>

                <x-forms.input
                    id="duration"
                    size="lg"
                    type="select"
                    label="{{ __('Duration') }}"
                    tooltip="{{ __('Specify the discounts valid duration.') }}"
                    name="duration"
                >
                    @foreach (\App\Extensions\DiscountManager\System\Enums\DurationEnum::cases() as $case)
                        <option
                            value="{{ $case->value }}"
                            {{ $discount?->duration == $case->value ? 'selected' : '' }}
                        >{{ __($case->label()) }}</option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="total_usage_limit"
                    label="{{ __('Total Usage Limit') }}"
                    tooltip="{{ __('Set the maximum number of uses for the discount. For unlimited, set to -1.') }}"
                    size="lg"
                    name="total_usage_limit"
                    value="{{ $discount?->total_usage_limit }}"
                    stepper
                    min="-1"
                    placeholder="{{ __('Total Usage Limit') }}"
                    required
                >
                </x-forms.input>

                <div class="flex w-full items-center justify-between">
                    <span class="text-2xs font-medium leading-none text-label">@lang('Show Strikethrough Price')</span>
                    <x-forms.input
                        class="bg-foreground/30 checked:bg-primary"
                        id="show_strikethrough_price"
                        type="checkbox"
                        name="show_strikethrough_price"
                        :checked="isset($discount) ? $discount->show_strikethrough_price == '1' : true"
                        switcher
                    >
                    </x-forms.input>
                </div>

				<div class="flex w-full items-center justify-between">
					<span class="text-2xs font-medium leading-none text-label">@lang('Hide Discounts for Subscribed Users')</span>
					<x-forms.input
						class="bg-foreground/30 checked:bg-primary"
						id="hide_discount_for_subscribed_users"
						type="checkbox"
						name="hide_discount_for_subscribed_users"
						:checked="isset($discount) ? $discount->hide_discount_for_subscribed_users == '1' : true"
						switcher
					>
					</x-forms.input>
				</div>
            </div>

            <div class="mt-5 flex flex-col gap-4">
                <x-form-step
                    class="my-4"
                    step="2"
                    label="{{ __('Filter') }}"
                />

                @php
                    $userTypes = explode(',', $discount?->user_type);
                    $paymentGateways = explode(',', $discount?->payment_gateway);
                    $pricingPlans = explode(',', $discount?->pricing_plans);
                @endphp
                <x-forms.input
                    id="user_type[]"
                    size="lg"
                    type="select"
                    label="{{ __('User Type') }}"
                    tooltip="{{ __('Select the eligible user types.') }}"
                    name="user_type[]"
                    multiple
                >
                    @foreach (\App\Extensions\DiscountManager\System\Enums\UserTypeEnum::cases() as $case)
                        <option
                            value="{{ $case->value }}"
                            {{ in_array($case->value, $userTypes) ? 'selected' : '' }}
                        >{{ __($case->label()) }}</option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="payment_gateway[]"
                    size="lg"
                    type="select"
                    label="{{ __('Payment Gateways') }}"
                    tooltip="{{ __('Choose which payment gateways the discount applies to.') }}"
                    name="payment_gateway[]"
                    multiple
                >
                    @foreach ($gateways as $gateway)
                        <option
                            value="{{ $gateway['code'] }}"
                            {{ in_array($gateway['code'], $paymentGateways) ? 'selected' : '' }}
                        >{{ __($gateway['title']) }}</option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="pricing_plans[]"
                    size="lg"
                    type="select"
                    label="{{ __('Pricing Plans') }}"
                    tooltip="{{ __('Select the pricing plans eligible for this discount.') }}"
                    name="pricing_plans[]"
                    multiple
                >
                    @foreach ($plans as $plan)
                        <option
                            value="{{ $plan->id }}"
                            {{ in_array($plan->id, $pricingPlans) ? 'selected' : '' }}
                        >{{ __($plan->name) }}</option>
                    @endforeach
                </x-forms.input>
            </div>

            <div class="mt-5 flex flex-col gap-4">
                <x-form-step
                    class="my-4"
                    step="3"
                    label="{{ __('Status') }}"
                />

                <div class="flex w-full items-center justify-between">
                    <span class="text-2xs font-medium leading-none text-label">@lang('Allow once per user')</span>
                    <x-forms.input
                        class="bg-foreground/30 checked:bg-primary"
                        id="allow_once_per_user"
                        name="allow_once_per_user"
                        type="checkbox"
                        switcher
                        :checked="isset($discount) ? $discount->allow_once_per_user == '1' : true"
                    >
                    </x-forms.input>
                </div>

                <div class="flex w-full items-center justify-between">
                    <span class="text-2xs font-medium leading-none text-label">@lang('Active')</span>
                    <x-forms.input
                        class="bg-foreground/30 checked:bg-primary"
                        id="active"
                        name="active"
                        type="checkbox"
                        switcher
                        :checked="isset($discount) ? $discount->active == '1' : true"
                    >
                    </x-forms.input>
                </div>

                <div class="flex w-full items-center justify-between">
                    <span class="text-2xs font-medium leading-none text-label">@lang('Schedule')</span>
                    <x-forms.input
                        class="bg-foreground/30 checked:bg-primary"
                        id="scheduled"
                        name="scheduled"
                        type="checkbox"
                        :checked="$discount?->scheduled == '1'"
                        x-model="isScheduled"
                        switcher
                    >
                    </x-forms.input>
                </div>

                <!-- Schedule Fields - Only show when scheduled is enabled -->
                <div
                    class="flex flex-col gap-4"
                    x-show="isScheduled"
                    x-transition
                >
                    <x-forms.input
                        id="start_date"
                        label="{{ __('Start Date') }}"
                        tooltip="{{ __('Set the date the discount starts.') }}"
                        size="lg"
                        ::min="formatDateTimeWithSeconds(startDateMin)"
                        name="start_date"
                        type="datetime-local"
                        x-model="startDate"
                        step="1"
                        placeholder="{{ __('Start Date') }}"
                        ::required="isScheduled"
                    />

                    <x-forms.input
                        id="end_date"
                        label="{{ __('End Date') }}"
                        tooltip="{{ __('Set the date the discount ends.') }}"
                        size="lg"
                        ::min="formatDateTimeWithSeconds(startDate)"
                        name="end_date"
                        value="{{ $discount?->end_date }}"
                        step="1"
                        type="datetime-local"
                        placeholder="{{ __('End Date') }}"
                        ::required="isScheduled"
                    />
                </div>

                <!-- Info message when not scheduled -->
                <div
                    class="rounded bg-gray-50 p-3 text-xs text-gray-600"
                    x-show="!isScheduled"
                    x-transition
                >
                    @lang('Discount will start immediately when activated and run indefinitely until usage limit is reached.')
                </div>
            </div>

            <x-button
                class="mt-6 w-full"
				onclick="{{ $app_is_demo ? 'return toastr.info(\'This feature is disabled in Demo version.\')' : '' }}"
				type="{{ $app_is_demo ? 'button' : 'submit' }}"
            >
                @lang('Save Discount')
                <span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                    <x-tabler-chevron-right class="size-4" />
                </span>
            </x-button>
        </form>
    </div>
@endsection
@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('discountData', () => ({
                discountType: '{{ !empty($discount?->type) ? $discount?->type : \App\Extensions\DiscountManager\System\Enums\DiscountTypeEnum::cases()[0]->value }}',
                isScheduled: {{ $discount?->scheduled ? 'true' : 'false' }},
                startDate: '{{ $discount?->start_date }}',
                startDateMin: new Date(),
                //  format as 'YYYY-MM-DDTHH:MM:SS'
                formatDateTimeWithSeconds(date) {
                    if (typeof date === 'string') {
                        return date;
                    }
                    const pad = n => String(n).padStart(2, '0');
                    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
                }
            }));
        })
    </script>
@endpush
