<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Domains\Engine\Enums\EngineEnum;
use App\Extensions\AIChatPro\System\Services\AIChatProStorageQuotaService;
use App\Models\Plan;
use App\Services\PaymentGateways\EVCPlusService;
use App\Services\PaymentGateways\WaafiPay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Throwable;
use UnexpectedValueException;

class PlansController extends Controller
{
    private const MIN_SEATS = 2;

    private const MAX_SEATS = 100;

    private const CATEGORY_LABELS = [
        'chat'  => 'Chat',
        'agent' => 'Agent',
        'image' => 'Images',
        'video' => 'Videos',
        'audio' => 'Audio',
    ];

    private const TOKEN_CATEGORY = [
        'word'           => 'chat',
        'image'          => 'image',
        'text_to_video'  => 'video',
        'image_to_video' => 'video',
        'video_to_video' => 'video',
        'second'         => 'video',
        'text_to_speech' => 'audio',
        'speech_to_text' => 'audio',
        'character'      => 'audio',
        'minute'         => 'audio',
    ];

    public function plans(): JsonResponse
    {
        $plans = DB::table('plans')
            ->where('active', 1)
            ->where('hidden', 0)
            ->where('type', 'subscription')
            ->orderBy('price')
            ->get();

        $entities = $this->entityCatalog();

        $payload = $plans->values()->map(fn ($plan, int $index) => $this->mapPlan($plan, $entities, $index + 1));

        return response()->json(['data' => $payload]);
    }

    public function methods(): JsonResponse
    {
        $gateway = DB::table('gateways')->where('code', 'evcplus')->where('is_active', 1)->first();

        $methods = [];

        if ($gateway) {
            $methods[] = [
                'id'            => 'evc_plus',
                'name'          => 'EVC Plus',
                'enabled'       => true,
                'requiresPhone' => true,
                'description'   => 'Pay from your Hormuud EVC Plus wallet',
            ];
        }

        return response()->json(['data' => $methods]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'planId' => ['required'],
            'seats'  => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_SEATS],
            'method' => ['required', 'string'],
            'phone'  => ['required', 'string', 'min:9', 'max:20'],
        ]);

        $user = $request->user();
        $plan = Plan::query()->where('active', 1)->where('hidden', 0)->find($data['planId']);

        if (! $plan || (float) $plan->price <= 0) {
            return $this->failed('This plan is not available for purchase.', 422);
        }

        $seats = 1;
        if ($plan->is_team_plan) {
            $seats = (int) ($data['seats'] ?? self::MIN_SEATS);
            if ($seats < self::MIN_SEATS || $seats > self::MAX_SEATS) {
                return $this->failed('Teams need between ' . self::MIN_SEATS . ' and ' . self::MAX_SEATS . ' users.', 422);
            }
        }

        $gateway = DB::table('gateways')->where('code', 'evcplus')->where('is_active', 1)->first();
        if (! $gateway || $data['method'] !== 'evc_plus') {
            return $this->failed('This payment method is not available.', 422);
        }

        $amount = round((float) $plan->price * $seats, 2);

        try {
            $response = (new WaafiPay)->payByWaafiPay([
                'phone'       => preg_replace('/\D+/', '', $data['phone']),
                'amount'      => $amount,
                'merchantUid' => $gateway->live_app_id,
                'apiUserId'   => $gateway->live_client_id,
                'apiKey'      => $gateway->live_client_secret,
                'description' => $plan->name . ($seats > 1 ? " x{$seats}" : ''),
                'invoiceId'   => 'INV-' . time(),
                'referenceId' => uniqid(),
                'currency'    => 'USD',
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->failed('EVC Plus could not be reached. Please try again shortly.', 502);
        }

        if (empty($response['status'])) {
            return $this->failed((string) ($response['error'] ?? 'Payment was not completed.'), 422);
        }

        DB::transaction(function () use ($user, $plan, $seats, $amount) {
            DB::table('subscriptions')
                ->where('user_id', $user->id)
                ->where('stripe_status', 'active')
                ->update(['stripe_status' => 'cancelled', 'ends_at' => now()]);

            $subscription = new Subscription;
            $subscription->user_id = $user->id;
            $subscription->name = $plan->id;
            $subscription->quantity = $seats;
            $subscription->plan_id = $plan->id;
            $subscription->paid_with = 'evcplus';
            $subscription->stripe_id = 'EVC-' . strtoupper(Str::random(12));
            $subscription->stripe_price = 'Not Needed';
            $subscription->stripe_status = 'active';
            $subscription->ends_at = now()->addMonth();
            $subscription->tax_rate = 0;
            $subscription->tax_value = 0;
            $subscription->coupon = null;
            $subscription->total_amount = $amount;
            $subscription->save();

            EVCPlusService::creditIncreaseSubscribePlan($user, $plan);
        });

        return response()->json(['status' => 'paid', 'message' => $plan->name . ' plan activated.']);
    }

    private function failed(string $message, int $code): JsonResponse
    {
        return response()->json(['status' => 'failed', 'message' => $message], $code);
    }

    private function mapPlan(object $plan, array $entities, int $rank): array
    {
        $features = collect(preg_split('/\r\n|\r|\n|,/', (string) $plan->features))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
        $storageLimit = null;

        try {
            $storageLimit = app(AIChatProStorageQuotaService::class)
                ->limitBytesForPlanName((string) $plan->name, (bool) ($plan->is_free ?? false));
        } catch (UnexpectedValueException $exception) {
            report($exception);
        }

        if ($storageLimit !== null) {
            $features = collect($features)
                ->reject(fn (string $feature) => preg_match('/\bstorage\b/i', $feature) === 1)
                ->push((int) ($storageLimit / 1_073_741_824) . ' GB Library storage')
                ->values()
                ->all();
        }

        $tools = $this->decode($plan->plan_ai_tools);
        $team = (bool) $plan->is_team_plan;

        return [
            'id'           => (string) $plan->id,
            'name'         => $plan->name,
            'monthlyPrice' => (float) $plan->price,
            'description'  => (string) $plan->description,
            'features'     => $features,
            'entitlements' => ['agents' => ! empty($tools['ai_agent'])]
                + ($storageLimit === null ? [] : ['storage' => $storageLimit]),
            'popular'      => (bool) $plan->is_featured,
            'team'         => $team,
            'rank'         => $rank,
            'tools'        => $this->mapTools($tools),
            'agents'       => $this->mapAgents($plan, $tools),
            'modelGroups'  => $groups = $this->mapModelGroups($plan, $entities),
            'flagships'    => $this->flagships($groups),
            'credits'      => (int) ($plan->shared_credits_amount ?? 0) ?: null,
        ];
    }

    private function mapTools(array $tools): array
    {
        $defs = [
            ['image', 'AI Images', ['creative_suite', 'ai_chat_pro_smart_image', 'ai_product_shot']],
            ['video', 'AI Videos', ['ugc_factory', 'video_editor', 'viral_clips']],
            ['audio', 'AI Audio', ['ai_voiceover', 'ai_speech_to_text']],
            ['social', 'Social Media', ['ai_social_media_extension', 'ext_social_media_dropdown']],
        ];

        return array_map(function ($def) use ($tools) {
            $on = collect($def[2])->contains(fn ($key) => ! empty($tools[$key]));

            return ['key' => $def[0], 'label' => $def[1], 'included' => $on];
        }, $defs);
    }

    private function mapAgents(object $plan, array $tools): array
    {
        $agents = [];
        if (! empty($tools['ai_agent'])) {
            $agents[] = 'AI Agents';
        }
        if (! empty($tools['ext_chat_bot'])) {
            $agents[] = 'Chatbot agents';
        }
        if (! empty($tools['deep_research'])) {
            $agents[] = 'Deep research';
        }
        if (! empty($tools['ext_voice_call']) || ! empty($tools['ext_phone_call_agent'])) {
            $agents[] = 'Voice and phone agents';
        }

        return $agents;
    }

    private function mapModelGroups(object $plan, array $entities): array
    {
        $aiModels = $this->decode($plan->ai_models);
        $chatIds = DB::table('ai_chat_model_plans')->where('plan_id', $plan->id)->pluck('entity_id')->all();

        $groups = [];

        foreach ($entities as $entity) {
            $setting = $aiModels[$entity['engine']][str_replace('.', '__', $entity['key'])] ?? null;
            $credit = is_array($setting) ? (float) ($setting['credit'] ?? 0) : 0.0;
            $unlimited = is_array($setting) && ! empty($setting['isUnlimited']);

            $included = $entity['category'] === 'chat'
                ? (in_array($entity['id'], $chatIds, true) || (! $chatIds && $entity['selected']))
                : ($unlimited || $credit > 0);

            if (! $included) {
                continue;
            }

            $tier = $this->costTier($entity['name'], $entity['category']);
            $pool = (float) ($plan->shared_credits_amount ?? 0);
            $perUse = $credit > 0 ? $credit : $this->generationCost($entity['name'], $entity['category'], $tier);
            $isMedia = $entity['category'] !== 'chat';

            $groups[$entity['category']][$entity['provider']][] = [
                'name'      => $entity['name'],
                'credits'   => $credit > 0 ? $credit : null,
                'unlimited' => $unlimited,
                'cost'      => $tier,
                'order'     => $entity['id'],
                'tokens'    => $isMedia ? $perUse : null,
                'perMonth'  => $isMedia && ! $unlimited && $pool > 0 ? (int) floor($pool / max($perUse, 0.01)) : null,
            ];
        }

        $result = [];
        foreach (self::CATEGORY_LABELS as $key => $label) {
            $providers = [];
            foreach ($groups[$key] ?? [] as $provider => $models) {
                // Most powerful first: heavier tier, then newer version.
                $version = static fn (string $n): float => preg_match('/(\d+(?:\.\d+)?)/', $n, $m) ? (float) $m[1] : 0.0;
                usort($models, fn ($a, $b) => [$b['cost'], $version($b['name']), $b['order']] <=> [$a['cost'], $version($a['name']), $a['order']]);
                $providers[] = ['name' => $provider, 'models' => $models];
            }
            usort($providers, fn ($a, $b) => strcmp($a['name'], $b['name']));

            $result[] = ['key' => $key, 'label' => $label, 'providers' => $providers];
        }

        return $result;
    }

    private function shortName(string $title): string
    {
        $name = html_entity_decode(Str::before($title, ' ('));
        $name = preg_split('/ (?:Flagship|Fast,|Experimental|Most|Our|Updated|Preview|Multi-step|Security|Mini model|Frontier|Enhanced|Advanced|Next generation|Cost efficiency|Complex|Refined|Low-latency|The most|The latest|Fast and)(?![a-z])/', $name)[0];
        $name = preg_replace('/\s*[-�]\s.*$/', '', $name);

        return Str::limit(trim($name), 38, '');
    }

    private function flagships(array $groups): array
    {
        $chat = collect($groups)->firstWhere('key', 'chat')['providers'] ?? [];
        $out = [];

        foreach (['OpenAI', 'Anthropic'] as $wanted) {
            $provider = collect($chat)->firstWhere('name', $wanted);
            $models = $provider['models'] ?? [];
            if (! $models) {
                continue;
            }

            // Newest model first, then the heaviest ones, 3 in total.
            $newest = collect($models)->sortByDesc('order')->first();
            $picked = [$newest['name'] => $newest];
            foreach ($models as $model) {
                if (count($picked) >= 3) {
                    break;
                }
                $picked[$model['name']] ??= $model;
            }

            foreach ($picked as $model) {
                $out[] = ['provider' => $wanted, 'name' => $model['name'], 'cost' => $model['cost'], 'credits' => $model['credits']];
            }
        }

        return $out;
    }

    // Relative credit cost (1 = lightest). The admin's per-plan credit value overrides it.
    private function costTier(string $name, string $category): int
    {
        $n = strtolower($name);

        if ($category === 'video') {
            return preg_match('/pro|ultra|veo|sora|kling|max/', $n) ? 10 : 5;
        }

        if ($category !== 'chat') {
            return preg_match('/pro|ultra|large|hd|3\b/', $n) ? 3 : 1;
        }

        if (preg_match('/nano|\bmini\b|haiku|flash|lite|instant|luna|turbo/', $n)) {
            return 1;
        }

        if (preg_match('/opus|\bpro\b|astra|\bsol\b|\bo1\b|\bo3\b|deep research|cyber|grok 4\.[56]|\br1\b/', $n)) {
            return 10;
        }

        return 3;
    }

    // Engines with no API key configured or not offered in the generators are hidden.
    private const UNAVAILABLE_ENGINES = [
        'x_ai', 'novita', 'freepik', 'clipdrop', 'pebblely', 'creatify', 'heygen',
        'synthesia', 'topview', 'vizard', 'klap', 'captions', 'google', 'gamma_ai', 'open_router',
        'perplexity', 'pexels', 'pixabay', 'unsplash', 'serper', 'plagiarism_check', 'azure',
    ];

    private function isUnavailable(object $row, ?string $category, string $name): bool
    {
        $key = (string) $row->key;

        if (in_array($row->engine, self::UNAVAILABLE_ENGINES, true)
            || str_starts_with($key, 'sora')
            || str_contains($key, 'embedding')) {
            return true;
        }

        // Only the models offered in the image / video generators.
        if ($row->engine === 'stable_diffusion') {
            return $key !== 'stable-diffusion-xl-1024-v1-0';
        }

        if ($row->engine === 'together' && $category === 'image') {
            return true;
        }

        if ($row->engine === 'fal_ai' && $category === 'video') {
            return ! preg_match('/veo|luma|kling|grok imagine|seedance|gemini omni|background removal|^minimax$/i', $name)
                || $name === 'Kling Video';
        }

        return false;
    }

    // Tokens spent per image / video / audio generation. The admin's per-plan value overrides it.
    private function generationCost(string $name, string $category, int $tier): int
    {
        $n = strtolower($name);

        if ($category === 'video') {
            if (str_contains($n, 'background removal')) {
                return 5;
            }
            if (preg_match('/fast|lite|turbo|standard|flash|kling 1|kling 2\.1|veo 2|minimax|luma/', $n)) {
                return 15;
            }

            return preg_match('/pro|veo|ultra|max|seedance 2\.5/', $n) ? 30 : 20;
        }

        return match ($category) {
            'image' => $tier >= 3 ? 6 : 3,
            default => $tier >= 3 ? 3 : 1,
        };
    }

    private function entityCatalog(): array
    {
        $rows = DB::table('entities as e')
            ->join('tokens as t', 't.entity_id', '=', 'e.id')
            ->where('e.status', 'enabled')
            ->select('e.id', 'e.key', 'e.title', 'e.engine', 'e.is_selected', 't.type')
            ->orderBy('e.id')
            ->get();

        $items = [];

        foreach ($rows as $row) {
            $category = self::TOKEN_CATEGORY[$row->type] ?? null;
            $name = $this->shortName((string) $row->title);
            if (! $category || isset($items[$row->id]) || $this->isUnavailable($row, $category, $name)) {
                continue;
            }

            $engine = EngineEnum::tryFrom((string) $row->engine);

            $items[$row->id] = [
                'id'       => (int) $row->id,
                'key'      => (string) $row->key,
                'engine'   => (string) $row->engine,
                'provider' => $engine ? (string) $engine->label() : Str::headline((string) $row->engine),
                'name'     => $name,
                'category' => str_contains((string) $row->key, 'lyria') ? 'audio' : $category,
                'selected' => (bool) $row->is_selected,
            ];
        }

        return array_values($items);
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
