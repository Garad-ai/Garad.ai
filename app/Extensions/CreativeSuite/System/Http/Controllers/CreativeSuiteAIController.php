<?php

namespace App\Extensions\CreativeSuite\System\Http\Controllers;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\CreativeSuite\System\Http\Requests\CreativeSuiteAIRequest;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\RateLimiter\RateLimiter;
use App\Http\Controllers\Controller;
use App\Models\OpenAIGenerator;
use App\Models\Usage;
use App\Models\UserOpenai;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreativeSuiteAIController extends Controller
{
    private const PENDING_STATUSES = ['CREATED', 'IN_PROGRESS', 'IN_QUEUE'];

    public function editor(CreativeSuiteAIRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            $rateLimiter = new RateLimiter('creative_suite_demo_rate_limit', 1);
            $clientIp = Helper::getRequestIp();

            if (! $rateLimiter->attempt($clientIp)) {
                return $this->errorResponse(__('You have reached the daily limit of 1 request. Please try again tomorrow.'));
            }
        }

        $lockKey = $request->lock_key ?? 'cs-ai-' . now()->timestamp . '-' . Auth::id();

        if (! Cache::lock($lockKey, 10)->get()) {
            return response()->json(['message' => __('Another editing in progress. Please try again later.')], 409);
        }

        $entity = $this->getEntity();
        $driver = Entity::driver($entity)->inputImageCount(1)->calculateCredit();

        try {
            $driver->redirectIfNoCreditBalance();
        } catch (Exception) {
            Cache::lock($lockKey)->release();

            return $this->errorResponse(__('You have no credits left. Please consider upgrading your plan.'));
        }

        try {
            $prompt = $this->buildPrompt($request);

            $requestId = FalAIService::generateKontext(
                $prompt,
                $entity,
                [$request->file('uploaded_image')]
            );

            $data = $this->createUserOpenai($request, $requestId, $entity);
            $userOpenai = UserOpenai::query()->create($data);

            Usage::getSingle()->updateImageCounts($driver->calculate());
            $driver->decreaseCredit();

            Cache::lock($lockKey)->release();

            return response()->json([
                'message' => __('Generated Successfully'),
                'status'  => 'success',
                'data'    => array_merge($data, [
                    'id'      => $userOpenai->getKey(),
                    'output'  => $userOpenai->output_url,
                    'lockKey' => $lockKey,
                ]),
            ]);
        } catch (Exception $e) {
            Cache::lock($lockKey)->release();
            $driver->increaseCredit($driver->calculate());

            return $this->errorResponse($e->getMessage());
        }
    }

    public function status(int $id): JsonResponse
    {
        $task = UserOpenai::findOrFail($id);

        if (in_array($task->status, self::PENDING_STATUSES, true)) {
            $entity = EntityEnum::fromSlug(data_get($task->payload, 'model')) ?? $this->getEntity();
            $result = FalAIService::check($task->request_id, $entity);

            if ($result && isset($result['image']['url'])) {
                $path = $this->downloadAndStore($result['image']['url']);

                if ($path) {
                    $task->update([
                        'status' => 'COMPLETED',
                        'output' => $path,
                    ]);
                }
            } elseif ($result && isset($result['status']) && $result['status'] === 'FAILED') {
                $task->update(['status' => 'FAILED']);
            }
        }

        if ($task->output) {
            $task->output = $task->output_url;
        }

        return response()->json([
            'message' => __('Generated Successfully'),
            'status'  => 'success',
            'data'    => $task,
        ]);
    }

    private function getEntity(): EntityEnum
    {
        $model = setting('creative_suite_ai_model', 'flux-pro/kontext');

        try {
            return EntityEnum::fromSlug($model);
        } catch (Exception) {
            return EntityEnum::FLUX_PRO_KONTEXT;
        }
    }

    private function buildPrompt(CreativeSuiteAIRequest $request): string
    {
        $description = $request->validated('description', '');

        return match ($request->validated('selected_tool')) {
            'edit_with_ai'      => $description,
            'reimagine'         => 'Reimagine this image with a fresh creative interpretation. Keep the core subject but transform the style, mood, and artistic direction into something new and visually striking.',
            'remove_background' => 'Remove the background, keeping only the main subject. Transparent.',
        };
    }

    private function createUserOpenai(CreativeSuiteAIRequest $request, string $requestId, EntityEnum $entity): array
    {
        $openai = OpenAIGenerator::query()->where('slug', 'ai_image_generator')->firstOrFail();

        return [
            'request_id'        => $requestId,
            'team_id'           => Auth::user()?->teamId(),
            'title'             => Str::limit($request->validated('description'), 20) ?: __('Creative Suite AI'),
            'slug'              => Str::random(7) . Str::slug(Auth::user()?->fullName()) . '-workbook',
            'user_id'           => Auth::id(),
            'openai_id'         => $openai->getKey(),
            'input'             => $request->validated('description') ?? 'Unknown',
            'response'          => 'CD',
            'output'            => '',
            'hash'              => Str::random(256),
            'credits'           => 1,
            'words'             => 0,
            'storage'           => 'public',
            'payload'           => [
                'taskId' => $requestId,
                'tool'   => $request->validated('selected_tool'),
                'model'  => $entity->value,
            ],
            'status' => 'IN_PROGRESS',
            'model'  => $entity->value,
            'engine' => $entity->engine()?->value,
        ];
    }

    private function downloadAndStore(string $url): ?string
    {
        $response = Http::get($url);

        if (! $response->successful()) {
            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
        $fileName = Str::uuid() . '.' . $extension;
        $path = 'creative-suite/' . $fileName;

        Storage::disk('uploads')->put($path, $response->body());

        return '/uploads/' . $path;
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'type'    => 'error',
            'message' => $message,
        ]);
    }
}
