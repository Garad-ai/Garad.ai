<?php

namespace App\Services\PaymentGateways;

use Illuminate\Http\Request;
use App\Services\PaymentGateways\Contracts\CreditUpdater;
use App\Services\PaymentGateways\WaafiPay;
class EVCPlusService
{
    use CreditUpdater;
    protected static $GATEWAY_CODE = 'evcplus';

    protected static $GATEWAY_NAME = 'EVC Plus';

    public static function gatewayDefinitionArray(): array
    {
        return [
            'code'                  => 'evcplus',
            'title'                 => 'EVC Plus',
            'link'                  => 'https://waafipay.com/',
            'active'                => 0,
            'available'             => 1,
            'img'                   => '/assets/img/payments/evcplus.png',
            'whiteLogo'             => 0,

            'mode'                  => 0,

            'sandbox_client_id'     => 0,
            'sandbox_client_secret' => 0,

            'live_client_id'        => 1,
            'live_client_secret'    => 1,
            'live_app_id'           => 1,

            'currency'              => 1,
            'currency_locale'       => 0,
            'notify_url'            => 0,
            'base_url'              => 0,
            'sandbox_url'           => 0,
            'locale'                => 0,
            'validate_ssl'          => 0,
            'webhook_secret'        => 0,
            'logger'                => 0,
            'tax'                   => 1,
            'bank_account_details'  => 0,
            'bank_account_other'    => 0,
        ];
    }

    public static function saveAllProducts(): void
    {
        return;
    }

    public static function saveProduct($plan): void
    {
        return;
    }

    public static function subscribe($plan)
{
    $gateway = \App\Models\Gateways::where('code', 'evcplus')
        ->where('is_active', 1)
        ->first();

    $planId = $plan->id;
    $taxRate = 0;
    $taxValue = 0;
    $newDiscountedPrice = $plan->price;

    $billingPlanId = 'Not Needed';
    $productId = $plan->id;
    $orderId = uniqid('evc_');
    $exception = null;

    return view(
        'panel.user.finance.subscription.evcplus',
        compact(
            'plan',
            'taxRate',
            'taxValue',
            'newDiscountedPrice',
            'billingPlanId',
            'exception',
            'orderId',
            'productId',
            'gateway',
            'planId'
        )
    );
}

    public static function subscribeCheckout(Request $request)
{
    try {

        $phone = $request->phone;
        $planID = $request->planID;

        $plan = \App\Models\Plan::findOrFail($planID);

        $gateway = \App\Models\Gateways::where('code','evcplus')
            ->where('is_active',1)
            ->firstOrFail();

        $waafi = new WaafiPay();

        $response = $waafi->payByWaafiPay([
            'phone'       => $phone,
            'amount'      => $plan->price,

            'merchantUid' => $gateway->live_app_id,
            'apiUserId'   => $gateway->live_client_id,
            'apiKey'      => $gateway->live_client_secret,

            'description' => $plan->name,
            'invoiceId'   => 'INV-' . time(),
            'referenceId' => uniqid(),

            'currency'    => 'USD',

            'hppSuccessCallbackUrl' => url('/dashboard'),
            'hppFailureCallbackUrl' => url('/dashboard'),
        ]);
        
        if (!$response['status']) {
    return back()->with([
        'message' => $response['error'],
        'type' => 'error'
    ]);
}

        $user = auth()->user();

$subscription = new \Laravel\Cashier\Subscription();
$subscription->user_id = $user->id;
$subscription->name = $plan->id;
$subscription->quantity = 1;
$subscription->plan_id = $plan->id;
$subscription->paid_with = 'evcplus';

$subscription->stripe_id = 'EVC-' . strtoupper(\Illuminate\Support\Str::random(12));
$subscription->stripe_price = 'Not Needed';
$subscription->stripe_status = 'active';
$subscription->ends_at = now()->addMonth();

$subscription->tax_rate = 0;
$subscription->tax_value = 0;
$subscription->coupon = null;
$subscription->total_amount = $plan->price;
$subscription->save();

self::creditIncreaseSubscribePlan($user, $plan);

return redirect()->route(
    'dashboard.' . $user->type->value . '.index'
)->with([
    'message' => 'Payment Successful',
    'type' => 'success'
]);

        

    } catch (\Exception $e) {

        return back()->with([
            'message' => $e->getMessage(),
            'type' => 'error'
        ]);
    }
}

    public static function prepaid($plan)
    {
        return response()->json([
            'status'  => true,
            'plan_id' => $plan->id
        ]);
    }

    public static function prepaidCheckout(Request $request)
    {
        return response()->json([
            'status' => true,
            'data'   => $request->all()
        ]);
    }

    public static function getSubscriptionStatus()
    {
        return true;
    }

    public static function getSubscriptionDaysLeft()
    {
        return 0;
    }

    public static function getSubscriptionRenewDate()
    {
        return null;
    }

    public static function subscribeCancel()
{
    $subscription = getCurrentActiveSubscription(auth()->id());

    if ($subscription) {
        $subscription->stripe_status = 'cancelled';
        $subscription->ends_at = now();
        $subscription->save();
    }

    return redirect()->route(
        'dashboard.' . auth()->user()->type->value . '.index'
    )->with([
        'message' => 'Subscription cancelled successfully',
        'type' => 'success'
    ]);
}

    public static function cancelSubscribedPlan($planId, $subsId)
{
    $subscription = \Laravel\Cashier\Subscription::find($subsId);

    if ($subscription) {
        $subscription->stripe_status = 'cancelled';
        $subscription->ends_at = now();
        $subscription->save();
    }

    return true;
}

public static function checkIfTrial()
{
    return false;
}
}