<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Common;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\SocialMedia\System\Models\SocialMediaCampaign;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Ai\AiCompletionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialMediaCampaignCommonController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'data' => SocialMediaCampaign::query()
                ->select('id', 'name', 'target_audience')
                ->where('user_id', Auth::id())->get(),
        ]);
    }

    public function generate(Request $request)
    {
        $driver = Entity::driver(Helper::defaultWordModel());
        $driver->redirectIfNoCreditBalance();

        try {
            $campaign_name = $request->campaign_name ?? 'any';

            $response = app(AiCompletionService::class)->completeUserOnly(
                "Generate only a list of target audience attributes, including demographics, interests, and pain points, for the purpose of $campaign_name campaign. Must result as array json data only. Result format is [attribute1, attribute2, ..., attributen]. Ensure that the result does not contain backticks (\`) or the string \"```json\"."
            );
            $driver->input($response)->calculateCredit()->decreaseCredit();

            return response()->json(['result' => $response]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
