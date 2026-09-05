<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Controllers;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\UGCFactory\System\Actions\FinalizeUGCActor;
use App\Extensions\UGCFactory\System\Http\Requests\StoreActorGenerateRequest;
use App\Extensions\UGCFactory\System\Http\Requests\StoreActorUploadRequest;
use App\Extensions\UGCFactory\System\Jobs\GenerateUGCActorJob;
use App\Extensions\UGCFactory\System\Models\UGCFactoryActor;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UGCFactoryActorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $actors = UGCFactoryActor::query()
            ->where('user_id', $userId)
            ->whereIn('type', [UGCFactoryActor::TYPE_UPLOAD, UGCFactoryActor::TYPE_GENERATED])
            ->latest()
            ->get()
            ->map(fn (UGCFactoryActor $actor) => $this->serialize($actor));

        return response()->json(['data' => $actors]);
    }

    public function uploadStore(StoreActorUploadRequest $request): JsonResponse
    {
        if ($demo = $this->guardDemoMode()) {
            return $demo;
        }

        $path = $request->file('image')->store('ugc-factory/actors', 'public');

        $actor = UGCFactoryActor::create([
            'user_id'    => $request->user()->id,
            'type'       => UGCFactoryActor::TYPE_UPLOAD,
            'name'       => (string) $request->input('name'),
            'image_path' => $path,
            'status'     => UGCFactoryActor::STATUS_READY,
        ]);

        return response()->json(['data' => $this->serialize($actor)], 201);
    }

    public function generateStore(StoreActorGenerateRequest $request): JsonResponse
    {
        if ($demo = $this->guardDemoMode()) {
            return $demo;
        }

        $actor = UGCFactoryActor::create([
            'user_id' => $request->user()->id,
            'type'    => UGCFactoryActor::TYPE_GENERATED,
            'name'    => (string) $request->input('name'),
            'prompt'  => (string) $request->input('prompt'),
            'status'  => UGCFactoryActor::STATUS_PENDING,
        ]);

        if (config('queue.default') === 'sync') {
            GenerateUGCActorJob::dispatchAfterResponse($actor->id);
        } else {
            GenerateUGCActorJob::dispatch($actor->id);
        }

        return response()->json(['data' => $this->serialize($actor)], 201);
    }

    /**
     * Polled by the frontend while a generated actor is pending. When the
     * configured provider is fal-based, this checks fal.ai inline and
     * finalizes the actor when the image is ready. Mirrors UGCFactoryVideo's
     * status endpoint.
     */
    public function status(Request $request, int $actor, FinalizeUGCActor $finalize): JsonResponse
    {
        $model = UGCFactoryActor::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($actor);

        if ($model->status === UGCFactoryActor::STATUS_PENDING && $model->fal_request_id && $model->fal_entity) {
            try {
                $entity = EntityEnum::tryFrom((string) $model->fal_entity) ?? EntityEnum::FLUX_PRO;
                $result = FalAIService::check($model->fal_request_id, $entity);

                if ($result !== null) {
                    if (($result['status'] ?? null) === 'FAILED') {
                        $model->update([
                            'status' => UGCFactoryActor::STATUS_FAILED,
                            'error'  => $result['error'] ?? __('Image generation failed.'),
                        ]);
                    } elseif ($imageUrl = data_get($result, 'image.url')) {
                        $finalize($model, (string) $imageUrl);
                        $model->refresh();
                    }
                }
            } catch (Throwable $e) {
                // Soft-fail: leave pending, frontend will retry.
            }
        }

        return response()->json(['data' => $this->serialize($model)]);
    }

    public function destroy(Request $request, int $actor): JsonResponse
    {
        if ($demo = $this->guardDemoMode()) {
            return $demo;
        }

        $model = UGCFactoryActor::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('type', [UGCFactoryActor::TYPE_UPLOAD, UGCFactoryActor::TYPE_GENERATED])
            ->findOrFail($actor);

        $model->delete();

        return response()->json(['ok' => true]);
    }

    private function guardDemoMode(): ?JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'error' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        return null;
    }

    private function serialize(UGCFactoryActor $actor): array
    {
        return [
            'id'         => $actor->id,
            'type'       => $actor->type,
            'name'       => $actor->name,
            'image_url'  => $actor->image_url,
            'status'     => $actor->status,
            'created_at' => $actor->created_at?->toIso8601String(),
        ];
    }
}
