<?php

namespace App\Http\Controllers\Api;

use App\Domains\Entity\Models\Entity;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Throwable;

class GaradChatGatewayController extends Controller
{
    private const CHAT_ENGINES = [
        'openai',
        'deep_seek',
        'gemini',
        'perplexity',
        'x_ai',
        'open_router',
    ];

    private function bridgeUser(Request $request): User
    {
        $expected = trim((string) config('garad_staging_sso.secret', ''));
        $provided = trim((string) $request->header(
            'X-Garad-Bridge-Secret',
            ''
        ));

        abort_if(
            $expected === '' ||
            $provided === '' ||
            ! hash_equals($expected, $provided),
            401,
            'Unauthorized'
        );

        $email = strtolower(trim((string) (
            $request->header('X-Garad-User-Email')
            ?: $request->input('email')
            ?: $request->query('email', '')
        )));

        abort_if($email === '', 422, 'User email is required.');

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        abort_if(! $user, 404, 'Garad AI user was not found.');

        Auth::setUser($user);

        return $user;
    }

    private function modelsForUser()
    {
        $planModels = collect(Entity::planModels());

        $defaultModels = Entity::query()
            ->isEnabled()
            ->where('is_selected', 1)
            ->whereHas('tokens', function ($query) {
                $query->where('type', 'word');
            })
            ->get();

        return $planModels
            ->merge($defaultModels)
            ->unique(function ($entity) {
                return $this->entityId($entity);
            })
            ->filter(function ($entity) {
                return in_array(
                    $this->engineValue($entity),
                    self::CHAT_ENGINES,
                    true
                );
            })
            ->values();
    }

    private function entityId($entity): string
    {
        try {
            return trim((string) $entity->key->value);
        } catch (Throwable) {
            return trim((string) $entity->key);
        }
    }

    private function engineValue($entity): string
    {
        try {
            return trim((string) $entity->engine->value);
        } catch (Throwable) {
            return trim((string) $entity->engine);
        }
    }

    private function modelTitle($entity): string
    {
        $selected = trim((string) ($entity->selected_title ?? ''));

        if ($selected !== '') {
            return $selected;
        }

        return trim((string) ($entity->title ?: $this->entityId($entity)));
    }

    public function models(Request $request): JsonResponse
    {
        $this->bridgeUser($request);

        $data = $this->modelsForUser()
            ->map(function ($entity) {
                return [
                    'id' => $this->entityId($entity),
                    'object' => 'model',
                    'created' => 0,
                    'owned_by' => $this->engineValue($entity),
                    'name' => $this->modelTitle($entity),
                ];
            })
            ->values();

        return response()->json([
            'object' => 'list',
            'data' => $data,
        ])->header('Cache-Control', 'no-store');
    }

    public function completions(Request $request)
    {
        $this->bridgeUser($request);

        $model = trim((string) $request->input('model', ''));
        $messages = $request->input('messages');

        abort_if($model === '', 422, 'Model is required.');
        abort_if(! is_array($messages), 422, 'Messages are required.');

        $entity = $this->modelsForUser()
            ->first(fn ($item) => $this->entityId($item) === $model);

        abort_if(
            ! $entity,
            403,
            'This model is not available for this Garad AI user.'
        );

        $engine = $this->engineValue($entity);

        [$url, $key, $upstreamModel] =
            $this->provider($engine, $model);

        abort_if(
            $key === '',
            503,
            'Garad AI provider key is not configured.'
        );

        $payload = [
            'model' => $upstreamModel,
            'messages' => $messages,
            'stream' => false,
        ];

        foreach ([
            'temperature',
            'top_p',
            'max_tokens',
            'max_completion_tokens',
            'frequency_penalty',
            'presence_penalty',
            'stop',
            'tools',
            'tool_choice',
            'response_format',
            'reasoning_effort',
        ] as $field) {
            if ($request->has($field)) {
                $payload[$field] = $request->input($field);
            }
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout(180)
                ->post($url, $payload);

            return response(
                $response->body(),
                $response->status(),
                [
                    'Content-Type' => 'application/json',
                    'Cache-Control' => 'no-store',
                ]
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => [
                    'message' => 'Garad AI provider request failed.',
                    'type' => 'garad_gateway_error',
                ],
            ], 502);
        }
    }

    private function provider(string $engine, string $model): array
    {
        $this->prepareProviderKey($engine);

        $key = $this->providerKey($engine);

        return match ($engine) {
            'openai' => [
                'https://api.openai.com/v1/chat/completions',
                $key,
                $model,
            ],

            'deep_seek' => [
                'https://api.deepseek.com/chat/completions',
                $key,
                $model,
            ],

            'gemini' => [
                'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                $key,
                $model,
            ],

            'perplexity' => [
                'https://api.perplexity.ai/chat/completions',
                $key,
                $model === 'perplexity'
                    ? $this->perplexityDefaultModel()
                    : $model,
            ],

            'x_ai' => [
                'https://api.x.ai/v1/chat/completions',
                $key,
                str_starts_with($model, 'x-ai/')
                    ? substr($model, 5)
                    : $model,
            ],

            'open_router' => [
                'https://openrouter.ai/api/v1/chat/completions',
                $key,
                $model,
            ],

            default => abort(
                422,
                'This Garad AI engine is not a chat completion provider.'
            ),
        };
    }

    private function perplexityDefaultModel(): string
    {
        foreach ([
            'perplexity_default_model',
            'perplexity_model',
        ] as $name) {
            try {
                $value = trim((string) setting($name));

                if ($value !== '') {
                    return $value;
                }
            } catch (Throwable) {
                // Continue.
            }
        }

        return 'sonar';
    }

    private function prepareProviderKey(string $engine): void
    {
        $class = $this->apiHelperClass();

        if (! $class) {
            return;
        }

        $methods = match ($engine) {
            'openai' => [
                'setOpenAiKey',
                'setOpenAIKey',
            ],

            'deep_seek' => [
                'setDeepseekKey',
                'setDeepSeekKey',
            ],

            'gemini' => [
                'setGeminiKey',
            ],

            'perplexity' => [
                'setPerplexityKey',
            ],

            'x_ai' => [
                'setXAIKey',
                'setXAiKey',
            ],

            'open_router' => [
                'setOpenRouterKey',
            ],

            default => [],
        };

        foreach ($methods as $method) {
            if (method_exists($class, $method)) {
                try {
                    $class::$method();
                    return;
                } catch (Throwable) {
                    // Try the next possible accessor.
                }
            }
        }
    }

    private function apiHelperClass(): ?string
    {
        foreach ([
            'App\\Helpers\\ApiHelper',
            'App\\Helpers\\Classes\\ApiHelper',
        ] as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        $file = app_path('Services/Stream/StreamService.php');

        if (! is_file($file)) {
            return null;
        }

        $source = @file_get_contents($file);

        if (
            is_string($source) &&
            preg_match(
                '/use\s+([^;]+\\\\ApiHelper);/',
                $source,
                $matches
            )
        ) {
            $candidate = trim($matches[1]);

            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function providerKey(string $engine): string
    {
        $paths = match ($engine) {
            'openai' => [
                'openai.api_key',
                'services.openai.key',
            ],

            'deep_seek' => [
                'deepseek.api_key',
                'deep_seek.api_key',
                'services.deepseek.key',
            ],

            'gemini' => [
                'gemini.api_key',
                'services.gemini.key',
                'google.api_key',
            ],

            'perplexity' => [
                'perplexity.api_key',
                'services.perplexity.key',
            ],

            'x_ai' => [
                'xai.api_key',
                'x_ai.api_key',
                'services.xai.key',
            ],

            'open_router' => [
                'openrouter.api_key',
                'open_router.api_key',
                'services.openrouter.key',
            ],

            default => [],
        };

        foreach ($paths as $path) {
            $value = $this->normalizeSecret(
                data_get(config()->all(), $path)
            );

            if ($value !== '') {
                return $value;
            }
        }

        return $this->searchConfigForSecret($engine);
    }

    private function normalizeSecret($value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $secret = $this->normalizeSecret($item);

                if ($secret !== '') {
                    return $secret;
                }
            }

            return '';
        }

        if (! is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);

        if (
            $value === '' ||
            strtolower($value) === 'user_provided'
        ) {
            return '';
        }

        if (
            str_starts_with($value, '[') &&
            str_ends_with($value, ']')
        ) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $this->normalizeSecret($decoded);
            }
        }

        return $value;
    }

    private function searchConfigForSecret(string $engine): string
    {
        $needles = match ($engine) {
            'deep_seek' => ['deepseek', 'deep_seek'],
            'x_ai' => ['xai', 'x_ai'],
            'open_router' => ['openrouter', 'open_router'],
            default => [$engine],
        };

        $walk = function ($value, string $path = '') use (
            &$walk,
            $needles
        ): string {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $next = $path === ''
                        ? (string) $key
                        : $path . '.' . $key;

                    $found = $walk($child, $next);

                    if ($found !== '') {
                        return $found;
                    }
                }

                return '';
            }

            $lowerPath = strtolower($path);

            $providerMatch = false;

            foreach ($needles as $needle) {
                if (str_contains($lowerPath, strtolower($needle))) {
                    $providerMatch = true;
                    break;
                }
            }

            if (
                $providerMatch &&
                preg_match('/key|secret|token/i', $lowerPath)
            ) {
                return $this->normalizeSecret($value);
            }

            return '';
        };

        return $walk(config()->all());
    }
}
