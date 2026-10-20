<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Marketplace\Repositories\ExtensionRepository;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VipStatusController extends Controller
{
    public function adminVipButton(): JsonResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        return response()->json([
            'data' => data_get(app(ExtensionRepository::class)->subscription(), 'data'),
        ]);
    }

    public function checkVipStatus(): JsonResponse
    {
        return response()->json([
            'showIntercom' => Helper::showIntercomForVipMembership(),
        ]);
    }
}
