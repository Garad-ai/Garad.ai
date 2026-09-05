<?php

namespace App\Extensions\AiAvatar\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiAvatar\System\Http\Requests\AiAvatarRequest;
use App\Extensions\AiAvatar\System\Models\AiAvatar;
use App\Extensions\AiAvatar\System\Services\AiAvatarService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAvatarController extends Controller
{
    public function __construct(
        public AiAvatarService $service
    ) {}

    public function index(): View
    {
        $list = $this->service->listVideos();

        $model = AiAvatar::query()->where('user_id', auth()->user()->id)->get();

        $list = collect($list)->filter(function ($item) use ($model) {
            $avatarIds = $model->pluck('avatar_id')->toArray();

            return isset($item['id']) && in_array($item['id'], $avatarIds, true);
        })->toArray();

        $inProgress = collect($list)->filter(function ($entry) {
            return $entry['status'] === 'in_progress';
        })->pluck('id')->toArray();

        if (Helper::appIsDemo()) {
            $prefix = '/uploads/demo/';
            $videos = ['fitness-health.mp4', 'motivational-speech.mp4', 'Santa.mp4', 'tech-review.mp4', 'travel-vlog.mp4'];
            $list = collect($videos)->map(function ($item) use ($prefix) {
                return [
                    'id'          => $item,
                    'title'       => str_replace('-', ' ', ucfirst(explode('.', $item)[0])),
                    'duration'    => '00:00:120000',
                    'download'    => $prefix . $item,
                    'status'      => 'complete',
                    'createdAt'   => now()->toDateTimeString(),
                ];
            })->toArray();
        }

        return view('ai-avatar::index', compact('list', 'inProgress'));
    }

    public function create(): View
    {
        return view('ai-avatar::create', [
            'avatars'     => $this->service->listAvatars(),
            'backgrounds' => $this->service->listBackgrounds(),
        ]);
    }

    public function store(AiAvatarRequest $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $avatarSettings = [
            'style'           => $request->get('style'),
            'backgroundColor' => $request->get('backgroundColor'),
        ];

        if ($request->get('style') === 'rectangular') {
            $avatarSettings['horizontalAlign'] = $request->get('horizontalAlign');
        }

        $body = [
            [
                'avatarSettings' => $avatarSettings,
                'scriptText'     => $request->get('scriptText'),
                'avatar'         => $request->get('avatar'),
                'background'     => $request->get('background'),
            ],
        ];

        $driver = Entity::driver(EntityEnum::SYNTHESIA)->inputVideoCount(1)->calculateCredit();

        try {
            $driver->redirectIfNoCreditBalance();
        } catch (Exception $e) {
            return redirect()->back()->with([
                'message' => $e->getMessage(),
                'type'    => 'You have no credits left. Please consider upgrading your plan.',
            ]);
        }

        $service = new AiAvatarService;

        $response = $service->createVideo($body, $request->get('visibility'),

            $request->get('title'), $request->get('description'), $request->get('test'));

        if (! empty($response['status']) && $response['id']) {

            AiAvatar::query()->create([
                'user_id'   => auth()->user()->id,
                'avatar_id' => $response['id'],
                'status'    => $response['status'],
            ]);

            $driver->decreaseCredit();

            return redirect()->route('dashboard.user.ai-avatar.index')->with([
                'message' => __('Video Created Successfully'),
                'type'    => 'success',
            ]);
        }

        return redirect()->back()->with([
            'message' => $response['message'] ?? __('Failed to create video'),
            'type'    => 'error',
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(string $id)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $model = $this->service->deleteVideo($id);

        if ($model->getStatusCode() === 200 || $model->getStatusCode() === 204) {

            $builder = AiAvatar::query()->where('avatar_id', $id)->first();

            $builder?->delete();

            return back()->with(['message' => __('Deleted Successfully'), 'type' => 'success']);
        }

        return back()->with(['message' => __('Delete Failed'), 'type' => 'danger']);
    }

    public function checkVideoStatus(Request $request): JsonResponse
    {
        $ids = AiAvatar::query()->where('status', 'in_progress')->pluck('avatar_id')->toArray();

        if (! count($ids)) {
            return response()->json(['data' => []]);

        }

        $service = new AiAvatarService;

        $list = $service->listVideos();

        $data = [];

        foreach ($list as $entry) {
            if (in_array($entry['id'], $ids) && $entry['status'] === 'complete') {
                $data[] = [
                    'divId' => 'video-' . $entry['id'],
                    'html'  => view('ai-avatar::video-item', ['entry' => $entry])->render(),
                ];

                AiAvatar::query()->where('avatar_id', $entry['id'])->update([
                    'status' => 'complete',
                ]);
            }
        }

        return response()->json(['data' => $data]);
    }
}
