<?php

namespace App\Extensions\AISocialMedia\System\Http\Controllers;

use App\Domains\Entity\Facades\Entity;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Services\Ai\AiCompletionService;
use Exception;
use Illuminate\Http\Request;

class GenerateContentController extends Controller
{
    public function __construct(private AiCompletionService $aiCompletionService) {}

    public function generateContent(Request $request)
    {
        $driver = Entity::driver();
        $driver->redirectIfNoCreditBalance();

        try {
            $platform = $request->platform;
            $company_id = $request->company_id;

            $company = Company::find($company_id);
            $camp_target = $request->camp_target;

            $productIds = $request->productIds;
            $products = Product::whereIn('id', $productIds)->get();
            $productDescriptions = $products->map(function ($product, $index) {
                switch ($product->type) {
                    case 0: // Product
                        return '(product' . ($index + 1) . ': ' . $product->name . ')';
                    case 1: // Service
                        return '(service' . ($index + 1) . ': ' . $product->name . ')';
                    default: // Other
                        return '(' . ($index + 1) . ': ' . $product->name . ')';
                }
            });
            $productString = $productDescriptions->implode(', ');

            $driver
                ->input($productString)
                ->calculateCredit()->decreaseCredit();

            $tone = $request->tone;
            $seo = $request->seo;
            $topics = $request->topics;
            $num_res = $request->num_res;
            $cam_injected_name = $request->cam_injected_name;

            $topics = is_array($topics) ? implode(', ', $topics) : $topics;

            $prompt = "Craft a with a maximum length of $num_res characters, without any emojis, emoticons a text compelling social media post for $platform platform, to promote a " . ($seo ? 'SEO optimized. ' : '') . 'campaign ' . ($company->name ? 'by ' . $company->name . '. ' : '. ') . ($camp_target ? 'The campaign aims to reach: [' . $camp_target . ']. ' : '') . 'Focus on this provided: [' . $productString . '] ' . ($cam_injected_name ? 'Campaign Name: ' . $cam_injected_name . '. ' : '') . ($topics ? 'Topics: ' . $topics . '. ' : '') . ($seo ? 'SEO optimized. ' : '') . ($tone ? 'Tone of Voice: ' . $tone . '. ' : '') . 'Do not include or links' . ($company->website ? 'other than the website.' : '.') . '. Must not ever increase the length of the post.';

            $driver = Entity::driver(Helper::defaultWordModel());

            $driver->redirectIfNoCreditBalance();

            $response = $this->aiCompletionService->completeUserOnly($prompt);

            $driver->input($response)->calculateCredit()->decreaseCredit();

            return response()->json([
                'result' => $response,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
