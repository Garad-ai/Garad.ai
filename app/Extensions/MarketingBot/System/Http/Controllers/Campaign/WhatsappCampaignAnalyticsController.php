<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Campaign;

use App\Extensions\MarketingBot\System\Models\CampaignMessageAnalytic;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WhatsappCampaignAnalyticsController extends Controller
{
    public function index(MarketingCampaign $whatsappCampaign): View
    {
        $whatsappChannel = WhatsappChannel::query()
            ->where('user_id', Auth::id())
            ->first();

        $isMeta = $whatsappChannel?->isMeta() ?? false;

        $stats = null;
        $recipients = collect();

        if ($isMeta) {
            $stats = CampaignMessageAnalytic::query()
                ->where('campaign_id', $whatsappCampaign->getKey())
                ->selectRaw('
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status IN ("delivered","read") THEN 1 ELSE 0 END) as delivered_count,
                    SUM(CASE WHEN status = "read" THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count
                ')
                ->first();

            $recipients = CampaignMessageAnalytic::query()
                ->where('campaign_id', $whatsappCampaign->getKey())
                ->orderByDesc('sent_at')
                ->paginate(20);
        }

        return view('marketing-bot::whatsapp-campaign.analytics', [
            'title'           => __('Campaign Analytics'),
            'campaign'        => $whatsappCampaign,
            'whatsappChannel' => $whatsappChannel,
            'isMeta'          => $isMeta,
            'stats'           => $stats,
            'recipients'      => $recipients,
        ]);
    }

    public function templates(): JsonResponse
    {
        $data = CampaignMessageAnalytic::query()
            ->whereHas('campaign', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->whereNotNull('meta_template_name')
            ->selectRaw('
                meta_template_name,
                COUNT(*) as total_sent,
                SUM(CASE WHEN status IN ("delivered","read") THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN status = "read" THEN 1 ELSE 0 END) as read_count
            ')
            ->groupBy('meta_template_name')
            ->get();

        return response()->json(['templates' => $data]);
    }
}
