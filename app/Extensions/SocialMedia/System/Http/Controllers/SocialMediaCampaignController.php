<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\SocialMedia\System\Models\SocialMediaCampaign;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Usage;
use App\Services\Ai\AiCompletionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SocialMediaCampaignController extends Controller
{
    public function index(): View
    {
        return view('social-media::campaign.index', [
            'items' => SocialMediaCampaign::query()->where('user_id', Auth::id())->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'cam_name'            => 'required|string',
            'cam_target'          => 'required|string',
        ]);

        SocialMediaCampaign::query()->create([
            'user_id'         => Auth::id(),
            'name'            => $request->get('cam_name'),
            'target_audience' => $request->get('cam_target'),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => trans('Campaign created successfully'),
        ]);
    }

    public function destroy(SocialMediaCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return back()->with(['message' => trans('Deleted Successfully'), 'type' => 'success']);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate(['platform' => 'required|string']);

        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        try {
            $driver = Entity::driver(Helper::defaultWordModel());

            $driver->redirectIfNoCreditBalance();

            $response = match ((bool) $request->get('is_personalized_content')) {
                true  => $this->hasPersonalizedContent($request),
                false => $this->hasNotPersonalizedContent($request),
            };

            $driver->input($response)->calculateCredit()->decreaseCredit();

            Usage::getSingle()->updateWordCounts($driver->calculate());

            return response()->json([
                'result' => $response,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type'    => 'error',
            ], 500);
        }
    }

    private function hasPersonalizedContent(Request $request): string
    {
        $platform = $request->get('platform');

        $limit = config('social-media.' . $platform . '.requirements.text.limit');

        $company = Company::query()->find($request->get('selected_company'));

        $product = $company?->products()->where('id', $request->get('selected_product'))->first();

        $campaign = SocialMediaCampaign::query()->find($request->get('campaign_id'));

        $limit = (int) $limit;
        $platform = ucfirst($platform);
        $tone = $request['tone'];
        $content = $request['content'];

        $brandTone = $company?->tone ?? 'brand tone';
        $brandDesc = $company?->description ?? 'brand description';
        $productDesc = $product?->description ?? 'product description';
        $campaignName = $campaign?->name ?? 'campaign';

        $systemPrompt = "You are a social media content creator. Generate engaging and attention-grabbing content that does not exceed {$limit} characters under any circumstances for {$platform}, including emojis.";

        $userPrompt = <<<EOT
		Create an engaging {$platform}-style post using the following input: "{$content}".
		The post should be {$tone} and optimized for engagement.
		Strictly keep it within {$limit} characters including emojis and punctuation.
		Include relevant hashtags, emojis, and a strong call to action.
		Ensure the post aligns with the brand’s {$brandTone}, {$brandDesc}, and {$productDesc}.
		The post should also resonate with the target audience for the {$campaignName} campaign.
		EOT;

        return app(AiCompletionService::class)->complete($systemPrompt, $userPrompt);
    }

    private function hasNotPersonalizedContent(Request $request): string
    {
        $platform = $request->get('platform');

        $limit = config('social-media.' . $platform . '.requirements.text.limit');

        $limit = (int) $limit;
        $platform = ucfirst($platform);
        $tone = $request->get('tone');
        $userContent = $request['content'];

        $systemPrompt = "You are a social media content creator. Generate engaging and attention-grabbing content that does not exceed {$limit} characters under any circumstances for {$platform}, including emojis.";

        $userPrompt = "Create an engaging {$platform}-style post using the following input: '{$userContent}'. "
            . "The post should be {$tone} and optimized for engagement. "
            . "Keep it within {$limit} characters including emojis. Make sure not to exceed under any circumstances"
            . 'Include relevant hashtags, emojis, and a strong call to action.';

        return app(AiCompletionService::class)->complete($systemPrompt, $userPrompt);
    }

    protected function chatCreate(?string $campaignName = null, $isSelectedCampaign = false): string
    {
        return app(AiCompletionService::class)->completeUserOnly(
            "Generate only a list of target audience attributes, including demographics, interests, and pain points, for the purpose of $campaignName campaign. Must result as array json data only. Result format is [attribute1, attribute2, ..., attributen]. Ensure that the result does not contain backticks (\`) or the string \"```json\"."
        );
    }
}
