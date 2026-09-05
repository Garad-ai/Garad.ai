<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Http\Requests\SocialMediaPostStoreRequest;
use App\Extensions\SocialMedia\System\Http\Requests\SocialMediaPostUpdateRequest;
use App\Extensions\SocialMedia\System\Models\SocialMediaCampaign;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;
use App\Extensions\SocialMedia\System\Services\Publisher\PublisherDriver;
use App\Extensions\SocialMedia\System\Services\SocialMediaShareService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialMediaPostController extends Controller
{
    public function __construct(
        public SocialMediaShareService $service
    ) {}

    public function index(Request $request)
    {
        $listOnly = $request->listOnly;
        $filter = $request->filter ?? 'all';
        $sort = $request->sort ?? 'created_at';
        $sortAscDesc = $request->sortAscDesc ?? 'desc';
        $platforms = PlatformEnum::all();

        if ($sort === 'platform') {
            $sort = 'social_media_platform_id';
        }

        $posts = SocialMediaPost::query()
            ->where('user_id', Auth::id())
            ->orderBy($sort, $sortAscDesc)
            ->paginate(10);

        if ($listOnly) {
            return view('social-media::post.index', compact('posts', 'platforms'))->render();
        }

        return view('social-media::post.index', compact('posts', 'platforms'));
    }

    public function show(Request $request, string $id)
    {
        $filter = $request->filter ?? 'all';
        $sort = $request->sort ?? 'created_at';
        $sortAscDesc = $request->sortAscDesc ?? 'desc';

        $post = SocialMediaPost::find($id);
        $prev_post_id = SocialMediaPost::where('id', '<', $id)
            ->orderBy('id', 'asc')
            ->max('id');
        $next_post_id = SocialMediaPost::where('id', '>', $id)
            ->orderBy('id', 'desc')
            ->min('id');

        return view('social-media::post.show', compact('post', 'prev_post_id', 'next_post_id'))->render();
    }

    public function create(Request $request)
    {
        $this->service->setRequest($request);

        $currentPlatform = $this->service->setRequest($request)->selectedPlatform();

        $userPlatforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->connected()
            ->get();

        return view('social-media::post.create', [
            'companies'       => Company::query()->where('user_id', Auth::id())->get(),
            'campaigns'       => SocialMediaCampaign::query()->where('user_id', Auth::id())->get(),
            'platforms'       => PlatformEnum::all(),
            'currentPlatform' => $currentPlatform,
            'userPlatforms'   => $userPlatforms,
            'prefillVideo'    => $request->get('prefill_video'),
            'prefillContent'  => $request->get('prefill_content'),
        ]);
    }

    public function edit(SocialMediaPost $post)
    {
        $userPlatforms = SocialMediaPlatform::query()
            ->where('platform', $post->social_media_platform)
            ->where('user_id', Auth::id())
            ->connected()
            ->get();

        return view('social-media::post.edit', [
            'companies'       => Company::query()->where('user_id', Auth::id())->get(),
            'campaigns'       => SocialMediaCampaign::query()->where('user_id', Auth::id())->get(),
            'platforms'       => PlatformEnum::all(),
            'currentPlatform' => $post->social_media_platform,
            'editingPost'     => $post,
            'userPlatforms'   => $userPlatforms,
        ]);
    }

    public function duplicate(Request $request, SocialMediaPost $post)
    {
        $request->validate([
            'platform_id' => 'required|exists:ext_social_media_platforms,id',
        ]);

        $replicate = $post->replicate();

        $replicate->status = 'draft';

        $replicate->social_media_platform_id = $request->platform_id;

        $replicate->save();

        return response()->json([
            'redirect' => route('dashboard.user.social-media.post.edit', $replicate->id),
            'status'   => 'success',
            'message'  => 'Post duplicated successfully',
        ]);
    }

    public function store(SocialMediaPostStoreRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $validated = $request->validated();

        $posts = $this->service->storeBulk($validated);

        if ($request->get('post_now')) {
            foreach ($posts as $post) {
                $driver = app(PublisherDriver::class)->setPost($post)
                    ->getDriver();

                if ($driver instanceof BasePublisherService) {
                    $driver->publish();
                }
            }
        }

        return response()->json(['status' => 'success', 'message' => trans('Post created successfully')]);
    }

    public function update(SocialMediaPostUpdateRequest $request, SocialMediaPost $post): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $validated = $request->validated();

        $this->service->update($post, $validated);

        if ($request->get('post_now')) {
            app(PublisherDriver::class)->setPost($post)
                ->getDriver()
                ->publish();
        }

        return response()->json(['status' => 'success', 'message' => trans('Post updated successfully')]);
    }

    public function destroy(SocialMediaPost $post): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        if ($post->user_id != Auth::id()) {
            return back()->with([
                'type'    => 'error',
                'message' => trans('You are not authorized to delete this post'),
            ]);
        }

        $post->delete();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Post deleted successfully'),
        ]);
    }
}
