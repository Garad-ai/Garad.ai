@extends('panel.layout.app')
@section('title', __('Subscription Payment'))
@section('titlebar_actions', '')

@section('content')

<div class="py-10">
    <div class="container-xl">
        <div class="row row-cards">

        <div class="col-sm-8 col-lg-8">
            @include('panel.user.finance.coupon.index')

            <div class="card">
                <div class="card-body">

                    <div class="text-center mb-4">
                        <img src="/themes/default/assets/img/payments/evcplus.png" alt="EVC Plus" style="max-height:80px;">
                    </div>

                    <form action="{{ route('dashboard.user.payment.subscription.checkout') }}" method="POST">
                        @csrf

                        <input type="hidden" name="gateway" value="evcplus">
                        <input type="hidden" name="planID" value="{{ $plan->id }}">
                        <input type="hidden" name="productId" value="{{ $productId }}">
                        <input type="hidden" name="billingPlanId" value="{{ $billingPlanId }}">
                        <input type="hidden" name="couponID" id="coupon">

                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input
                                type="text"
                                class="form-control"
                                name="phone"
                                placeholder="25261XXXXXXX"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-success w-full">
                            Pay
                            {!! displayCurr(currency()->symbol, $plan->price, $taxValue, $newDiscountedPrice, tax_included: $plan->price_tax_included) !!}
                            with EVC Plus
                        </button>

                    </form>

                </div>
            </div>

            <p class="mt-3">
                {{ __('By purchasing you confirm our') }}
                <a href="{{ url('/') . '/terms' }}">
                    {{ __('Terms and Conditions') }}
                </a>
            </p>
        </div>

        <div class="col-sm-4 col-lg-4">
            @include('panel.user.finance.partials.plan_card')
        </div>

    </div>
</div>

</div>
@endsection

@push('script')

<script>
$(document).ready(function() {
    var currentURL = window.location.href;

    if (currentURL.includes('coupon=')) {
        var couponValue = getParameterByName('coupon', currentURL);
        document.getElementById('coupon').value = couponValue;
    }
});

function getParameterByName(name, url) {
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
    var results = regex.exec(url);

    if (!results) return null;
    if (!results[2]) return '';

    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}
</script>

@endpush
