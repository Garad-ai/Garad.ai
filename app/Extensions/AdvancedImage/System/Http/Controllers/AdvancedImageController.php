<?php

namespace App\Extensions\AdvancedImage\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AdvancedImage\System\Helpers\Tool;
use App\Extensions\AdvancedImage\System\Services\AdvancedFreepikService;
use App\Extensions\AdvancedImage\System\Services\AdvancedNovitaService;
use App\Extensions\AdvancedImage\System\Services\ClipDropService;
use App\Extensions\AdvancedImage\System\Services\FalAIService;
use App\Extensions\AdvancedImage\System\Services\NanoBananaService;
use App\Extensions\AdvancedImage\System\Services\OpenAIService;
use App\Extensions\AdvancedImage\System\Services\Traits\UseImage;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\OpenAIGenerator;
use App\Models\Usage;
use App\Models\UserOpenai;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdvancedImageController extends Controller
{
    use UseImage;

    public function __construct(
        public AdvancedNovitaService $novitaService,
        public ClipDropService $clipDropService,
        public AdvancedFreepikService $freepikService,
        public OpenAIService $openAIService,
        public FalAIService $falaiService,
        public NanoBananaService $nanoBananaService,
    ) {}

    public function index(): View
    {
        $openai = OpenAIGenerator::whereSlug('ai_image_generator')->firstOrFail();

        $userOpenai = UserOpenai::query()
            ->where('user_id', Auth::id())
            ->where('openai_id', $openai->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $tools = Tool::get();

        return view('advanced-image::index', compact(['userOpenai', 'openai', 'tools']));
    }

    public function getModel(): EntityEnum
    {
        $model = request('ai_model') === 'openai' ? 'gpt-image-1' : request('ai_model');

        if (request('ai_model') === 'nano-banana/edit') {
            return EntityEnum::NANO_BANANA_EDIT;
        }
        if (request('ai_model') === 'nano-banana-pro/edit') {
            return EntityEnum::NANO_BANANA_PRO_EDIT;
        }
        if (request('ai_model') === 'nano-banana-2/edit') {
            return EntityEnum::NANO_BANANA_2_EDIT;
        }

        if (request('ai_model') === 'gpt-image-1.5') {
            return EntityEnum::GPT_IMAGE_1_5;
        }

        if (request('ai_model') === 'gpt-image-2') {
            return EntityEnum::GPT_IMAGE_2;
        }

        if (request('ai_model') === 'flux-pro/kontext') {

            $tools = ['cleanup', 'image_relight', 'style_transfer'];

            if (in_array(request('selected_tool'), $tools, true)) {
                return EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI;
            }

            return EntityEnum::FLUX_PRO_KONTEXT;
        }

        return EntityEnum::fromSlug($model);
    }

    public function editor(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return $this->errorResponse(__('This feature is disabled in demo mode.'));
        }

        $this->validateRequest($request);

        $lockKey = $request->lock_key ?? 'request-' . now()->timestamp . '-' . auth()?->id();
        if (! Cache::lock($lockKey, 10)->get()) {
            return response()->json(['message' => 'Another editing in progress. Please try again later.'], 409);
        }

        $driver = Entity::driver($this->getModel())->inputImageCount(1)->calculateCredit();

        try {
            $driver->redirectIfNoCreditBalance();
        } catch (Exception $e) {
            return $this->errorResponse(__('You have no credits left. Please consider upgrading your plan.'));
        }

        $photoData = $this->handleGeneration($request);

        if ($photoData['error']) {
            return $this->errorResponse($photoData['message']);
        }

        if (isset($photoData['photo']['status']) && ! $photoData['photo']['status']) {
            return $this->errorResponse($photoData['photo']['message'] ?? __('An error occurred during image generation.'));
        }

        $data = $this->prepareUserOpenai(
            $photoData['photo'],
            $photoData['task_id'],
            $photoData['task_status'],
            $photoData['link']
        );

        $data['model'] = EntityEnum::fromSlug($request->get('ai_model'))?->value;
        $data['engine'] = EntityEnum::fromSlug($request->get('ai_model'))?->engine()?->value;

        $userOpenai = UserOpenai::query()->create($data);

        Usage::getSingle()->updateImageCounts($driver->calculate());

        $driver->decreaseCredit();

        Cache::lock($lockKey)->release();

        return response()->json([
            'message' => __('Generated Successfully'),
            'status'  => 'success',
            'type'    => 'success',
            'data'    => array_merge($data, [
                'id'      => $userOpenai->getKey(),
                'output'  => $userOpenai->output_url,
                'lockKey' => $lockKey,
            ]),
        ]);
    }

    private function validateRequest(Request $request): void
    {
        if ($request->get('selected_tool') !== 'sketch_to_image') {
            $request->validate(['uploaded_image' => 'required|file|image|mimes:jpeg,png,jpg']);
        }
    }

    private function handleGeneration(Request $request): array
    {
        $model = $request->get('ai_model');

        return match ($model) {
            'openai', 'gpt-image-1', 'gpt-image-1.5', 'gpt-image-2' => $this->handleOpenAI($request, $model),
            'freepik'                 => $this->handleFreepik($request),
            'clipdrop'                => $this->handleClipDrop($request),
            'novita'                  => $this->handleNovita($request),
            'flux-pro/kontext', 'flux-2-flex/edit', 'xai/grok-imagine-image', 'xai/grok-imagine-image/edit' => $this->handleFallAi($request),
            'nano-banana/edit'        => $this->handleNanoBanana($request),
            'nano-banana-pro/edit'    => $this->handleNanoBanana($request, true),
            'nano-banana-2/edit'      => $this->handleNanoBanana($request, false, true),
            default                   => ['error' => true, 'message' => __('Invalid AI model.')],
        };
    }

    private function handleFallAi(Request $request): array
    {
        $response = $this->falaiService
            ->setTool($request->input('selected_tool'))
            ->setRequest($request)
            ->generate();

        return [
            'error'       => false,
            'photo'       => $response['photo'] ?? '',
            'task_id'     => $response['task_id'] ?? '',
            'task_status' => $response['task_status'] ?? 'COMPLETED',
            'link'        => $response['link'] ?? '',
        ];
    }

    private function handleNanoBanana(Request $request, $isPro = false, $isNanoBanana2 = false): array
    {
        $response = $this->nanoBananaService
            ->setTool($request->input('selected_tool'))
            ->setRequest($request)
            ->generate($isPro, $isNanoBanana2);

        return [
            'error'       => false,
            'photo'       => $response['photo'] ?? '',
            'task_id'     => $response['task_id'] ?? '',
            'task_status' => $response['task_status'] ?? 'COMPLETED',
            'link'        => $response['link'] ?? '',
        ];
    }

    private function handleOpenAI(Request $request, $model): array
    {
        $photo = $this->openAIService
            ->setTool($request->input('selected_tool'))
            ->setModel($model)
            ->setRequest($request)
            ->generate();

        if (isset($response['status']) && ! $response['status']) {
            return ['error' => true, 'message' => $response['message']];
        }

        return [
            'error'       => false,
            'photo'       => $photo['path'] ?? '',
            'task_id'     => '',
            'task_status' => 'COMPLETED',
            'link'        => $photo['path'] ?? '',
        ];
    }

    private function handleFreepik(Request $request): array
    {
        $response = $this->freepikService->generate($request->input('selected_tool'), $request->all());

        if (isset($response['status']) && $response['status'] === 'error') {
            return ['error' => true, 'message' => $response['message']];
        }

        return [
            'error'       => false,
            'photo'       => $response['photo'] ?? '',
            'task_id'     => $response['task_id'] ?? '',
            'task_status' => $response['task_status'] ?? 'COMPLETED',
            'link'        => $response['link'] ?? '',
        ];
    }

    private function handleClipDrop(Request $request): array
    {
        $photo = $this->clipDropService
            ->setAction($request->input('selected_tool'))
            ->setPhoto($request->file($request->get('selected_tool') === 'sketch_to_image' ? 'sketch_file' : 'uploaded_image'))
            ->generate();

        if (isset($photo['status']) && ! $photo['status']) {
            return ['error' => true, 'message' => $photo['message']];
        }

        return [
            'error'       => false,
            'photo'       => $photo,
            'task_id'     => '',
            'task_status' => 'COMPLETED',
            'link'        => '',
        ];
    }

    private function handleNovita(Request $request): array
    {
        $data = [
            'photo'       => $request->file('uploaded_image'),
            'mask'        => $request->file('mask_file'),
            'description' => $request->get('description'),
        ];

        $response = $this->novitaService->generate($request->input('selected_tool'), $data);

        if (isset($response['status']) && $response['status'] === 'error') {
            return ['error' => true, 'message' => $response['message']];
        }

        return [
            'error'       => false,
            'photo'       => $response['photo'] ?? '',
            'task_id'     => $response['task_id'] ?? '',
            'task_status' => $response['task_status'] ?? 'COMPLETED',
            'link'        => $response['link'] ?? '',
        ];
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'type'    => 'error',
            'message' => $message,
        ]);
    }

    private function prepareUserOpenai(
        string $photo,
        string $request_id = '',
        string $status = 'COMPLETED',
        string $link = '',
    ): array {
        $openai = OpenAIGenerator::query()->where('slug', 'ai_image_generator')->firstOrFail();

        return [
            'is_advanced_image' => true,
            'request_id'        => $request_id,
            'team_id'           => auth()->user()?->teamId(),
            'title'             => Str::limit(request('description'), '20') ?: $this->title($photo),
            'slug'              => $this->generateSlug(),
            'user_id'           => auth()->user()?->getKey(),
            'openai_id'         => $openai->getKey(),
            'input'             => request('description') ?? 'Unknown',
            'response'          => 'CD',
            'output'            => $photo,
            'hash'              => Str::random(256),
            'credits'           => 1,
            'words'             => 0,
            'storage'           => 'public',
            'payload'           => [
                'taskId' => $request_id,
                'link'   => $link,
                'tool'   => request('selected_tool'),
                'model'  => request('ai_model'),
            ],
            'status'     => $status,
        ];
    }

    private function generateSlug(): string
    {
        return Str::random(7) . Str::slug(auth()->user()?->fullName()) . '-workbook';
    }
}
