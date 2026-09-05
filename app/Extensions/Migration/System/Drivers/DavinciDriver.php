<?php

namespace App\Extensions\Migration\System\Drivers;

use App\Enums\Plan\FrequencyEnum;
use App\Enums\Roles;
use App\Extensions\Migration\System\Drivers\Contracts\AbstractMigrationDriver;
use App\Extensions\Migration\System\Enums\MigrationCapabilityEnum;
use App\Extensions\Migration\System\Enums\MigrationDriverEnum;
use App\Extensions\Migration\System\Enums\MigrationReqsEnum;
use App\Extensions\Migration\System\Utils\MigrationHelpers;
use App\Http\Controllers\Finance\GatewayController;
use App\Models\Currency;
use App\Models\GatewayProducts;
use App\Models\Gateways;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserOpenai;
use Laravel\Cashier\Subscription as Subscriptions;

class DavinciDriver extends AbstractMigrationDriver
{
    protected static array $capabilityMap = [
        MigrationCapabilityEnum::USERS->value => [
            'function' => 'migrateUsers',
            'reqs'     => [MigrationReqsEnum::SQL_FILE],
            'enum'     => MigrationCapabilityEnum::USERS,
        ],
        MigrationCapabilityEnum::PLANS->value => [
            'function' => 'migratePlans',
            'reqs'     => [MigrationReqsEnum::SQL_FILE],
            'enum'     => MigrationCapabilityEnum::PLANS,
        ],
        MigrationCapabilityEnum::PAYMENT_GATEWAYS->value => [
            'function' => 'migratePaymentGateways',
            'reqs'     => [MigrationReqsEnum::SQL_FILE, MigrationReqsEnum::ENV_FILE],
            'enum'     => MigrationCapabilityEnum::PAYMENT_GATEWAYS,
        ],
        MigrationCapabilityEnum::SUBSCRIPTIONS->value => [
            'function' => 'migrateSubscribers',
            'reqs'     => [MigrationReqsEnum::SQL_FILE],
            'enum'     => MigrationCapabilityEnum::SUBSCRIPTIONS,
        ],
        MigrationCapabilityEnum::DOCUMENTS->value => [
            'function' => 'migrateDocuments',
            'reqs'     => [MigrationReqsEnum::SQL_FILE],
            'enum'     => MigrationCapabilityEnum::DOCUMENTS,
        ],
        // MigrationCapabilityEnum::CHATS->value => [
        //     'function' => 'migrateChats',
        //     'reqs'     => [MigrationReqsEnum::SQL_FILE],
        //     'enum'     => MigrationCapabilityEnum::CHATS,
        // ],
    ];

    public static function enum(): MigrationDriverEnum
    {
        return MigrationDriverEnum::DAVINCI;
    }

    protected function migrateUsers(): string
    {
        return MigrationHelpers::processMigration(
            $this->sqlFilePath,
            'users',
            static function (array $record, ?array $env = null) {
                if (! User::where('email', $record['email'] ?? '')->exists()) {
                    $normalizedCredits = [];
                    foreach ($record as $key => $value) {
                        if (str_ends_with($key, '_credits')) {
                            $modelKey = str_replace('_credits', '', $key);
                            $normalizedKey = MigrationHelpers::normalizeModelName($modelKey);
                            $normalizedCredits[$normalizedKey] = $value;
                        }
                    }
                    $newCredits = MigrationHelpers::processCredits(User::getFreshCredits(), $normalizedCredits);
                    $user = User::create([
                        'name'             => $record['name'] ?? null,
                        'surname'          => ' ',
                        'type'             => Roles::tryFrom($record['group'] ?? 'user')?->value ?? Roles::USER->value,
                        'email'            => $record['email'] ?? null,
                        'password'         => $record['password'] ?? null,
                        'status'           => ($record['status'] ?? 'active') === 'active' ? 1 : 0,
                        'phone'            => $record['phone_number'] ?? null,
                        'company_name'     => $record['company'] ?? null,
                        'company_website'  => $record['website'] ?? null,
                        'country'          => $record['country'] ?? null,
                        'address'          => $record['address'] ?? null,
                        'city'             => $record['city'] ?? null,
                        'postcode'         => $record['postal_code'] ?? null,
                        'google2fa_secret' => $record['google2fa_secret'] ?? null,
                        'last_seen'        => $record['last_seen'] ?? now(),
                        'remember_token'   => $record['remember_token'] ?? null,
                    ]);

                    $user->updateCredits($newCredits);

                    $user->old_sys_user_id = $record['id'] ?? null;
                    $user->save();

                    return true;
                }

                return false;
            }
        );
    }

    protected function migratePlans(): string
    {
        return MigrationHelpers::processMigration(
            $this->sqlFilePath,
            'subscription_plans',
            static function (array $record, ?array $env = null) {
                if (! Plan::where('name', $record['plan_name'] ?? '')
                    ->where('price', $record['price'] ?? '')
                    ->exists()) {
                    $plan = Plan::createFreshPlan();
                    $normalizedCredits = [];
                    foreach ($record as $key => $value) {
                        if (str_ends_with($key, '_credits')) {
                            $modelKey = str_replace('_credits', '', $key);
                            $normalizedKey = MigrationHelpers::normalizeModelName($modelKey);
                            $normalizedCredits[$normalizedKey] = $value;
                        }
                    }
                    $aiModels = $plan->ai_models;
                    $newCredits = MigrationHelpers::processCredits($aiModels, $normalizedCredits);

                    $plan->fill([
                        'name'                 => $record['plan_name'] ?? null,
                        'price'                => (float) ($record['price'] ?? 0),
                        'description'          => ' ',
                        'active'               => ($record['status'] ?? 'active') === 'active' ? 1 : 0,
                        'currency'             => $record['currency'] ?? 'USD',
                        'frequency'            => FrequencyEnum::tryFrom($record['payment_frequency'] ?? '')?->value ?? FrequencyEnum::MONTHLY->value,
                        'is_featured'          => (int) ($record['featured'] ?? 1),
                        'stripe_product_id'    => $record['stripe_gateway_plan_id'] ?? null,
                        'max_tokens'           => $record['max_tokens'] ?? null,
                        'features'             => $record['plan_features'] ?? null,
                        'trial_days'           => (int) ($record['days'] ?? 0),
                        'can_create_ai_images' => (int) ($record['image_feature'] ?? 0),
                        'created_at'           => $record['created_at'] ?? null,
                        'updated_at'           => $record['updated_at'] ?? null,
                        'ai_models'            => $newCredits,
                        'user_api'             => (int) (($record['personal_openai_api'] ?? false) || ($record['personal_claude_api'] ?? false) || ($record['personal_gemini_api'] ?? false)),
                        'plan_allow_seat'      => $record['team_members'] ?? 0,
                    ]);

                    $plan->old_sys_plan_id = $record['id'] ?? null;
                    $plan->save();
                    MigrationHelpers::createGatewayProducts($record, $plan);

                    return true;
                }

                return false;
            },
        );
    }

    protected function migratePaymentGateways(): string
    {
        return MigrationHelpers::processMigration(
            $this->sqlFilePath,
            'payment_gateways',
            static function (array $record, ?array $env = null) {
                $magicaiGateways = app(GatewayController::class)->gatewayCodesArray();
                $gatewayName = $record['name'] ?? null;
                $gatewayCode = strtolower($gatewayName);
                $upperGatewayName = strtoupper($gatewayName ?? '');
                $isEnabled = ($record['enabled'] ?? false) || ($env[$upperGatewayName . '_ENABLED'] ?? false);

                if (! empty($gatewayName) && $isEnabled && in_array(strtolower($gatewayName), $magicaiGateways)) {
                    $toBeChecked = $env[$upperGatewayName . '_SECRET'] ?? '' . $env[$upperGatewayName . '_BASE_URI'] ?? '';
                    $mode = str_contains($toBeChecked, 'test') || str_contains($toBeChecked, 'sandbox') ? 'sandbox' : 'live';
                    $client_id = $env[$upperGatewayName . '_KEY'] ?? $env[$upperGatewayName . '_CLIENT_ID'] ?? $env[$upperGatewayName . '_PUBLIC_KEY'] ?? $env[$upperGatewayName . '_KEY_ID'];
                    $client_secret = $env[$upperGatewayName . '_SECRET'] ?? $env[$upperGatewayName . '_CLIENT_SECRET'] ?? $env[$upperGatewayName . '_SECRET_KEY'] ?? $env[$upperGatewayName . '_KEY_SECRET'];
                    $currId = Currency::where('code', $env['DEFAULT_SYSTEM_CURRENCY'] ?? 'USD')->first()?->id;

                    $gateway = Gateways::query()->firstOrCreate([
                        'code' => $gatewayCode,
                    ], [
                        'is_active'             => 1,
                        'currency'              => $currId ?? '124',
                        'title'                 => $gatewayName,
                        'mode'                  => $mode,
                        'sandbox_client_id'     => $client_id,
                        'sandbox_client_secret' => $client_secret,
                        'live_client_id'        => $client_id,
                        'live_client_secret'    => $client_secret,
                        'base_url'              => $env[$upperGatewayName . '_BASE_URI'] ?? null,
                        'sandbox_url'           => $env[$upperGatewayName . '_BASE_URI'] ?? null,
                        'webhook_secret'        => $env[$upperGatewayName . '_WEBHOOK_SECRET'] ?? null,
                        'tax'                   => (float) ($env['PAYMENT_TAX'] ?? 0),
                    ]);

                    MigrationHelpers::getPlansPriceIds($gateway);

                    return true;
                }

                return false;
            },
            $this->envFilePath
        );
    }

    protected function migrateSubscribers(): string
    {
        return MigrationHelpers::processMigration(
            $this->sqlFilePath,
            'subscribers',
            static function (array $record, ?array $env = null) {
                if (! User::where('old_sys_user_id', $record['user_id'] ?? 0)->exists() || ! Plan::where('old_sys_plan_id', $record['plan_id'] ?? 0)->exists()) {
                    return false;
                }
                $user = User::where('old_sys_user_id', $record['user_id'] ?? 0)->first();
                $plan = Plan::where('old_sys_plan_id', $record['plan_id'] ?? 0)->first();

                if (isset($record['subscription_id']) && $user && $plan) {
                    $product = GatewayProducts::query()->where('plan_id', $plan->id)
                        ->where('gateway_code', strtolower($record['gateway'] ?? ''))
                        ->first();
                    $subscription = Subscriptions::query()->firstOrCreate(
                        [
                            'stripe_id' => $record['subscription_id'],
                        ],
                        [
                            'user_id'            => $user->id,
                            'plan_id'            => $plan->id,
                            'name'               => $plan->id,
                            'paid_with'          => strtolower($record['gateway'] ?? 'stripe'),
                            'stripe_status'      => ($record['status'] ?? '') === 'Active' ? 'active' : 'canceled',
                            'stripe_price'       => $product?->price_id ?? null,
                            'quantity'           => 1,
                        ]
                    );
                    $gateway = Gateways::query()->where('code', strtolower($record['gateway'] ?? 'stripe'))->first();
                    MigrationHelpers::getUsersCustomerIds($gateway, $subscription);
                }

                return true;
            },
        );
    }

    protected function migrateDocuments(): string
    {
        return MigrationHelpers::processMigration(
            $this->sqlFilePath,
            'contents',
            static function (array $record, ?array $env = null) {
                $user = User::where('old_sys_user_id', $record['user_id'] ?? 0)->first();
                if ($user) {
                    UserOpenai::firstOrCreate(
                        [
                            'title'   => $record['title'] ?? null,
                            'user_id' => $user->id,
                        ],
                        [
                            'team_id'   => $user->team_id ?? null,
                            'title'     => $record['title'] ?? null,
                            'slug'      => str()->random(7) . str($user?->fullName())->slug() . '-workbook',
                            'user_id'   => $user->id,
                            'input'     => $record['input_text'] ?? null,
                            'response'  => $record['result_text'] ?? null,
                            'output'    => $record['result_text'] ?? null,
                            'hash'      => str()->random(256),
                            'credits'   => (int) ($record['tokens'] ?? 0),
                            'words'     => (int) ($record['words'] ?? 0),
                        ]);
                }

                return true;
            },
        );
    }

    // protected function migrateChats(): string
    // {
    //     return MigrationHelpers::processMigration(
    //         $this->sqlFilePath,
    //         'chats',
    //         static function (array $record, ?array $env = null) {
    //             return true;
    //         },
    //     );
    // }
}
