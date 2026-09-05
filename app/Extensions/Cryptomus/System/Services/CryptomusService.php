<?php

namespace App\Extensions\Cryptomus\System\Services;

use App\Models\Gateways;
use App\Models\Plan;
use App\Models\Subscriptions;
use App\Models\User;
use App\Models\UserOrder;
use App\Services\Contracts\BaseGatewayService;
use App\Services\PaymentGateways\Contracts\CreditUpdater;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CryptomusService implements BaseGatewayService
{
    use CreditUpdater;

    public static $gateway;

    protected static string $GATEWAY_CODE = 'cryptomus';

    protected static string $GATEWAY_NAME = 'Cryptomus';

    public static function getPlansPriceIdsForMigration(): null
    {
        return null;
    }

    public static function getUsersCustomerIdsForMigration(\Laravel\Cashier\Subscription $subscription): null
    {
        return null;
    }

    public static function saveAllProducts()
    {
        return true;
    }

    public static function saveProduct($plan)
    {
        return true;
    }

    public static function subscribe($plan)
    {
        [$payment_key, $merchant] = self::credentials();

        $payment = new \Cryptomus\Api\RequestBuilder($payment_key, $merchant);

        $user = Auth::user() ?: User::query()->first();

        $order_id = Str::uuid();

        $userOrder = UserOrder::query()
            ->create([
                'order_id'           => $order_id,
                'plan_id'            => $plan->id,
                'user_id'            => $user?->id,
                'payment_type'       => self::$GATEWAY_CODE,
                'price'              => $plan->price,
                'affiliate_earnings' => 0,
                'status'             => 'WAITING',
                'country'            => $user?->country ?? 'Unknown',
                'tax_rate'           => 0,
                'tax_value'          => 0,
                'type'               => 'subscription',
                'payload'            => [],
            ]);

        $price = $plan->price;

        $data = [
            'order_id'     => $order_id,
            'amount'       => "$price",
            'currency'     => 'USDT',
            'name'         => 'Recurring payment',
            'period'       => 'monthly',
            'url_callback' => url('/webhooks/' . self::$GATEWAY_CODE),
        ];

        $data = $payment->sendRequest('v1/recurrence/create', $data);

        $order_id = 'ORDER-' . strtoupper(Str::random(13));

        $newDiscountedPrice = null;

        return view('cryptomus::subscribe', compact('plan', 'order_id', 'newDiscountedPrice', 'data'));
    }

    public static function subscribeCheckout(Request $request, $referral = null)
    {
        // TODO: Implement subscribeCheckout() method.
    }

    public static function prepaidCheckout(Request $request, $referral = null) {}

    public static function prepaid($plan)
    {

        [$payment_key, $merchant] = self::credentials();

        $payment = new \Cryptomus\Api\RequestBuilder($payment_key, $merchant);

        $user = Auth::user() ?: User::query()->first();

        $order_id = Str::uuid();

        $userOrder = UserOrder::query()
            ->create([
                'order_id'           => $order_id,
                'plan_id'            => $plan->id,
                'user_id'            => $user?->id,
                'payment_type'       => self::$GATEWAY_CODE,
                'price'              => $plan->price,
                'affiliate_earnings' => 0,
                'status'             => 'WAITING',
                'country'            => $user?->country ?? 'Unknown',
                'tax_rate'           => 0,
                'tax_value'          => 0,
                'type'               => 'token-pack',
                'payload'            => [],
            ]);

        $price = $plan->price;

        $data = [
            'amount'              => "$price",
            'currency'            => 'USDT',
            'network'             => 'ETH',
            'order_id'            => $order_id,
            'url_return'          => url('dashboard'),
            'url_callback'        => url('/webhooks/' . self::$GATEWAY_CODE),
            'is_payment_multiple' => false,
            'lifetime'            => '7200',
            'to_currency'         => 'ETH',
        ];

        try {
            $data = $payment->sendRequest('v1/payment', $data);
            $order_id = 'ORDER-' . strtoupper(Str::random(13));

            $newDiscountedPrice = null;

            return view('cryptomus::prepaid', compact('plan', 'order_id', 'newDiscountedPrice', 'data'));

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public static function subscribeCancel(?User $internalUser = null)
    {
        $user = $internalUser ?: Auth::user();

        $userId = $user?->id;

        // Get current active subscription
        $activeSub = getCurrentActiveSubscription($userId);

        if (! $activeSub) {
            return;
        }

        $uuid = Str::replace('UUID:', '', $activeSub->stripe_id);

        $data = [
            'uuid' => $uuid,
        ];

        if ($activeSub->stripe_price) {
            $data['order_id'] = Str::replace('ORDERID:', '', $activeSub->stripe_price);

        }

        [$payment_key, $merchant] = self::credentials();

        $payment = new \Cryptomus\Api\RequestBuilder($payment_key, $merchant);

        $data = $payment->sendRequest('v1/recurrence/cancel', $data);

        if ($activeSub && $data) {
            $activeSub->update([
                'stripe_status' => 'canceled',
                'ends_at'       => Carbon::now(),
            ]);
        }
    }

    public static function cancelSubscribedPlan($subscription, $planId)
    {
        $user = Auth::user();

        $check = $subscription instanceof \Laravel\Cashier\Subscription;

        if (! $check) {
            $subscription = Subscriptions::where('id', $subscription)->first();
        }

        $activeSub = $subscription;

        if (! $activeSub) {
            return;
        }

        $uuid = Str::replace('UUID:', '', $activeSub->stripe_id);

        $data = [
            'uuid' => $uuid,
        ];

        if ($activeSub->stripe_price) {
            $data['order_id'] = Str::replace('ORDERID:', '', $activeSub->stripe_price);

        }

        [$payment_key, $merchant] = self::credentials();

        $payment = new \Cryptomus\Api\RequestBuilder($payment_key, $merchant);

        $data = $payment->sendRequest('v1/recurrence/cancel', $data);

        if ($activeSub && $data) {
            $activeSub->update([
                'stripe_status' => 'canceled',
                'ends_at'       => Carbon::now(),
            ]);
        }
    }

    public static function checkIfTrial()
    {
        return true;
    }

    public static function getSubscriptionRenewDate()
    {
        $user = Auth::user() ?: User::query()->first();

        $subscription = getCurrentActiveSubscription($user?->getAttribute('id'));

        $next_delivery_date = null;

        if ($subscription) {

            $uuid = Str::replace('UUID:', '', $subscription->stripe_id);

            $data = [
                'uuid' => $uuid,
            ];

            if ($subscription->stripe_price) {
                $data['order_id'] = Str::replace('ORDERID:', '', $subscription->stripe_price);

            }

            [$payment_key, $merchant] = self::credentials();

            $payment = new \Cryptomus\Api\RequestBuilder($payment_key, $merchant);

            $data = $payment->sendRequest('v1/recurrence/info', $data);

            $next_delivery_date = data_get($data, 'last_pay_off');
        }

        if ($next_delivery_date) {
            return Carbon::create($next_delivery_date)->format('F jS, Y');
        }

        return false;
    }

    public static function getSubscriptionStatus($incomingUserId = null)
    {
        $user = null;

        if ($incomingUserId) {
            $user = User::query()->where('id', $incomingUserId)->first();
        }

        if (Auth::check() && $incomingUserId == null) {
            $user = Auth::user();
        }

        if (! $user) {
            return false;
        }

        $subscription = getCurrentActiveSubscription($user->getAttribute('id'));

        if ($subscription) {

            $uuid = Str::replace('UUID:', '', $subscription->stripe_id);

            $data = [
                'uuid' => $uuid,
            ];

            if ($subscription->stripe_price) {
                $data['order_id'] = Str::replace('ORDERID:', '', $subscription->stripe_price);

            }

            [$payment_key, $merchant] = self::credentials();

            $payment = new \Cryptomus\Api\RequestBuilder($payment_key, $merchant);

            $data = $payment->sendRequest('v1/recurrence/info', $data);

            $status = data_get($data, 'status');

            if ((string) $status === 'active') {
                return true;
            }

            if ($subscription->getAttribute('created_at') < Carbon::now()->subHours(2)) {
                $subscription->update([
                    'stripe_status' => 'cancelled',
                    'ends_at'       => Carbon::now(),
                ]);
            }

            return false;
        }
    }

    public static function getSubscriptionDaysLeft() {}

    public static function handleWebhook(Request $request)
    {
        $data = $request->all();

        if (! isset($data['order_id'])) {
            return;
        }

        $order = UserOrder::query()
            ->where('order_id', $data['order_id'])
            ->first();
        if ($order && $data['status'] === 'paid') {
            $plan = Plan::query()->where('id', $order->plan_id)->first();

            $user = User::query()->where('id', $order->user_id)->first();

            self::creditIncreaseSubscribePlan($user, $plan);

            $order->update([
                'status'  => 'PAID',
                'payload' => $data,
            ]);

            try {
                if ($order['type'] === 'subscription') {
                    $subscription = new Subscriptions;
                    $subscription->user_id = $order['user_id'];
                    $subscription->name = $order['plan_id'];
                    $subscription->stripe_id = 'UUID:' . $data['uuid'];
                    $subscription->stripe_status = 'active';
                    $subscription->stripe_price = 'ORDERID:' . $data['order_id'];
                    $subscription->quantity = 1;
                    $subscription->trial_ends_at = null;
                    $subscription->ends_at = $plan?->frequency == 'lifetime_monthly' ? Carbon::now()->addMonths(1) : Carbon::now()->addYears(1);
                    $subscription->auto_renewal = 1;
                    $subscription->plan_id = $plan?->id;
                    $subscription->paid_with = self::$GATEWAY_CODE;
                    $subscription->total_amount = $plan?->price;
                    $subscription->save();
                }
            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
        }
    }

    public static function credentials()
    {
        $gateway = self::geteway();

        if (! $gateway) {
            return;
        }

        return [
            $gateway->getAttribute('live_client_secret'),
            $gateway->getAttribute('live_app_id'),
        ];
    }

    public static function geteway()
    {
        if (self::$gateway) {
            return self::$gateway;
        }

        self::$gateway = Gateways::where('code', self::$GATEWAY_CODE)->first();

        return self::$gateway;
    }

    public static function gatewayDefinitionArray(): array
    {
        return [
            'code'                  => 'cryptomus',
            'title'                 => 'Cryptomus',
            'link'                  => 'https://cryptomus.com/',
            'active'                => 0,                      // if user activated this gateway - dynamically filled in main page
            'available'             => 1,                   // if gateway is available to use
            'img'                   => '/assets/img/payments/cryptomus.svg',
            'whiteLogo'             => 0,                   // if gateway logo is white
            'mode'                  => 0,                        // Option in settings - Automatically set according to the "Development" mode. "Development" ? sandbox : live (PAYPAL - 1)
            'sandbox_client_id'     => 0,           // Option in settings 0-Hidden 1-Visible
            'sandbox_client_secret' => 0,       // Option in settings
            'sandbox_app_id'        => 0,              // Option in settings
            'live_client_id'        => 0,              // Option in settings
            'live_client_secret'    => 1,          // Option in settings
            'live_app_id'           => 1,                 // Option in settings
            'currency'              => 0,                    // Option in settings
            'currency_locale'       => 0,             // Option in settings
            'base_url'              => 0,                    // Option in settings
            'sandbox_url'           => 0,                 // Option in settings
            'locale'                => 0,                      // Option in settings
            'validate_ssl'          => 0,                // Option in settings
            'logger'                => 0,                      // Option in settings
            'notify_url'            => 0,                  // Gateway notification url at our side
            'webhook_secret'        => 0,              // Option in settings
            'tax'                   => 0,              // Option in settings
            'bank_account_details'  => 0,
            'bank_account_other'    => 0,
        ];
    }
}
