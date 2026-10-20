<?php

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Services\GuestChatAllowance;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestChatStatusController extends Controller
{
    public function show(Request $request, GuestChatAllowance $allowance): JsonResponse
    {
        if ($request->user()) {
            abort(404);
        }

        return response()->json($allowance->status($request));
    }
}
