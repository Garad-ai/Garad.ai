<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class SocialMediaCalendarController extends Controller
{
    public function __invoke()
    {
        $appIsNotDemo = Helper::appIsNotDemo();
        $items = SocialMediaPost::query()
            ->where('user_id', Auth::id())
            ->when($appIsNotDemo, function ($query) {
                $query->whereBetween('scheduled_at', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ]);
            })
            ->get();

        if (! $appIsNotDemo) {
            $day = 1;
            $daysInMonth = now()->daysInMonth;

            foreach ($items as $item) {
                $item->scheduled_at = now()->startOfMonth()
                    ->addDays(($day % $daysInMonth))
                    ->setTime(12, 0, 0);

                $item->posted_at = $item->scheduled_at->copy()->addHour();

                $day++;
            }
        }

        return view('social-media::calendar', [
            'items' => $items,
        ]);
    }
}
