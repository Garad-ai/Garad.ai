<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;

class SocialMediaController extends Controller
{
    public function __invoke()
    {
        $platforms = PlatformEnum::all();

        $posts = SocialMediaPost::query()
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        $posts_stats = [
            'today'        => $this->getPostStats(Carbon::today(), Carbon::now(), 70),
            'last_7_days'  => $this->getPostStats(Carbon::now()->subDays(7), Carbon::now(), 90),
            'last_30_days' => $this->getPostStats(Carbon::now()->subDays(30), Carbon::now(), 120),
        ];

        $platforms_published_posts = $this->getPublishedPostsByMonth();

        return view('social-media::index', compact('posts', 'platforms', 'posts_stats', 'platforms_published_posts'));
    }

    public function getPublishedPostsByMonth()
    {

        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        // Platformları tanımla
        $platforms = array_map(
            fn (PlatformEnum $platform) => $platform->value,
            PlatformEnum::all()
        );

        // Veritabanından platform bazlı aylık yayınlanan postları getir
        $query = SocialMediaPost::selectRaw("
            social_media_platform as platform,
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        ")
            ->where('status', 'published')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('platform', 'month')
            ->orderBy('month')
            ->get();

        // Son 12 ayın listesini oluştur
        $months = collect(range(0, 11))->map(function ($i) {
            return Carbon::now()->subMonths($i)->format('Y-m');
        })->reverse()->values();

        // Platform bazlı istatistikleri oluştur
        $result = collect($platforms)->map(function ($platform) use ($query, $months) {
            return [
                'name' => $platform,
                'data' => $months->map(function ($month) use ($query, $platform) {
                    return $query->where('platform', $platform)->where('month', $month)->sum('count') ?? 0;
                })->toArray(),
            ];
        })->toArray();

        if (Helper::appIsDemo()) {
            return $this->generateRealisticDemoData($result);
        }

        return $result;
    }

    public function getPostStats($startDate, $endDate, int $demoLimit = 0)
    {
        $query = SocialMediaPost::query()
            ->selectRaw("
            COUNT(*) as all_posts,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_posts,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_posts
        ")
            ->where('user_id', auth()->id())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->first();

        $demoData = random_int(40, $demoLimit);

        $publishedPosts = random_int(0, $demoData - 5);

        return [
            'all_posts'       => Helper::appIsDemo() ? $demoData : ($query->all_posts ?? 0),
            'published_posts' => Helper::appIsDemo() ? $publishedPosts : ($query->published_posts ?? 0),
            'scheduled_posts' => Helper::appIsDemo() ? ($demoData - $publishedPosts) : ($query->scheduled_posts ?? 0),
            'failed_posts'    => $query->failed_posts ?? 0,
        ];
    }

    private function generateRealisticDemoData($originalData)
    {
        $demoData = [];

        // Platform bazlı ortalama değerler (sosyal medya kullanım oranlarına göre)
        $platformAverages = [
            'facebook'       => 90,
            'instagram'      => 90,
            'x'              => 80,
            'linkedin'       => 60,
            'tiktok'         => 70,
            'youtube'        => 65,
            'youtube-shorts' => 75,
        ];

        foreach ($originalData as $platform) {
            $platformName = $platform['name'];
            $baseValue = $platformAverages[$platformName] ?? 400;

            $platformData = [
                'name' => $platformName,
                'data' => [],
            ];

            // Her ay için base değer etrafında %30 varyasyon
            for ($i = 0; $i < 12; $i++) {
                $variation = random_int(-30, 30) / 100; // -30% ile +30% arası
                $value = round($baseValue + ($baseValue * $variation));
                $platformData['data'][] = (int) max(0, $value); // Negatif değerleri önle
            }

            $demoData[] = $platformData;
        }

        return $demoData;
    }
}
