<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers;

use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Enums\CampaignType;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\Models\Whatsapp\Contact;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class MarketingDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $lastTenDays = $this->lastTenDays();

        return view('marketing-bot::dashboard.index', [
            'totals'                 => $this->getTotals(),
            'chartCampaigns'         => $this->getCampaigns(),
            'chartNewContacts'       => $this->getNewContacts(),
            'campaignList'           => MarketingCampaign::query()
                ->where('user_id', Auth::id())
                ->when(request('search'), function ($query) {
                    $query->where('name', 'like', '%' . request('search') . '%');
                })
                ->orderByDesc('id')
                ->paginate(10),
            'lastTenDays' => $lastTenDays,
        ]);
    }

    public function getNewContacts(): array
    {
        if (Helper::appIsDemo()) {
            return [
                [
                    'name' => 'whatsapp',
                    'data' => array_map(fn () => random_int(1, 5), range(1, 10)),
                ],
                [
                    'name' => 'telegram',
                    'data' => array_map(fn () => random_int(1, 10), range(1, 10)),
                ],
            ];
        }

        $startDate = Carbon::now()->subDays(10);

        $endDate = Carbon::now();

        $query = Contact::selectRaw("
            DATE_FORMAT(created_at, '%d-%m') as day_month,
            COUNT(*) as count
        ")
            ->where('user_id', Auth::id())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('day_month')
            ->orderBy('day_month')
            ->get();

        $days = collect(range(0, 9))->map(function ($i) {
            return Carbon::now()->subDays($i)->format('d-m');
        })->reverse()->values();

        return [
            [
                'name' => 'whatsapp',
                'data' => $days->map(function ($day) use ($query) {
                    return $query->where('day_month', $day)->sum('count') ?? 0;
                })->toArray(),
            ],
        ];
    }

    public function getCampaigns(): array
    {
        if (Helper::appIsDemo()) {
            return [
                [
                    'name' => 'whatsapp',
                    'data' => array_map(static fn () => random_int(1, 5), range(1, 10)),
                ],
                [
                    'name' => 'telegram',
                    'data' => array_map(static fn () => random_int(1, 10), range(1, 10)),
                ],
            ];
        }

        $startDate = Carbon::now()->subDays(10);

        $endDate = Carbon::now();

        $types = [
            CampaignType::whatsapp->value,
            CampaignType::telegram->value,
        ];

        $query = MarketingCampaign::selectRaw("
            type,
            DATE_FORMAT(scheduled_at, '%d-%m') as day_month,
            COUNT(*) as count
        ")
            ->where('user_id', Auth::id())
            ->where('status', CampaignStatus::published->value)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->groupBy('type', 'day_month')
            ->orderBy('day_month')
            ->get();

        $days = collect(range(0, 9))->map(function ($i) {
            return Carbon::now()->subDays($i)->format('d-m');
        })->reverse()->values();

        return collect($types)->map(function ($type) use ($query, $days) {
            return [
                'name' => $type,
                'data' => $days->map(function ($day) use ($query, $type) {
                    return $query->where('type', $type)->where('day_month', $day)->sum('count') ?? 0;
                })
                    ->toArray(),
            ];
        })->toArray();
    }

    public function getTotals(): array
    {
        if (Helper::appIsDemo()) {
            return [
                'today'        => [
                    'total_campaigns'     => random_int(1, 5),
                    'scheduled_campaigns' => random_int(1, 3),
                    'published_campaigns' => random_int(1, 2),
                    'total_contacts'      => random_int(10, 50),
                ],
                'last_7_days'  => [
                    'total_campaigns'     => random_int(5, 50),
                    'scheduled_campaigns' => random_int(3, 18),
                    'published_campaigns' => random_int(2, 10),
                    'total_contacts'      => random_int(50, 369),
                ],
                'last_30_days' => [
                    'total_campaigns'     => random_int(50, 200),
                    'scheduled_campaigns' => random_int(18, 100),
                    'published_campaigns' => random_int(10, 50),
                    'total_contacts'      => random_int(369, 1500),
                ],
            ];
        }

        return [
            'today'        => $this->total(Carbon::today(), Carbon::now()),
            'last_7_days'  => $this->total(Carbon::now()->subDays(7), Carbon::now()),
            'last_30_days' => $this->total(Carbon::now()->subDays(30), Carbon::now()),
        ];
    }

    public function total($startDate, $endDate): array
    {
        $data = MarketingCampaign::query()
            ->where('user_id', Auth::id())
            ->selectRaw("
            COUNT(*) as campaigns,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_campaigns,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_campaigns
        ")
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->first();

        $totalContacts = MarketingCampaign::query()
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->count();

        return [
            'total_campaigns'           => $data?->campaigns ?: 0,
            'scheduled_campaigns'       => $data?->scheduled_campaigns ?: 0,
            'published_campaigns'       => $data?->published_campaigns ?: 0,
            'total_contacts'            => $totalContacts,
        ];
    }

    public function lastTenDays(): array
    {
        $list = [];
        $bugun = Carbon::today();

        for ($i = 9; $i >= 0; $i--) {
            $list[] = $bugun->copy()->subDays($i)->format('d M');
        }

        return $list;
    }
}
