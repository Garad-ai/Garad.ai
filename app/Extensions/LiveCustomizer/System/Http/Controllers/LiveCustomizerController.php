<?php

namespace App\Extensions\LiveCustomizer\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LiveCustomizerController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helper::appIsNotDemo()) {
            setting([
                setting('dash_theme') . '_' . 'live_customizer'       => $request->get('style'),
                setting('dash_theme') . '_' . 'live_customizer_fonts' => $request->get('fonts'),
                'show_live_customizer'                                => 0,
            ])->save();

            $message = trans('Theme updated successfully');

            if ($request->get('clear')) {
                $message = trans('Changes Discarded successfully');
            }

            return response()->json([
                'message' => $message,
                'status'  => 'success',
            ]);
        }

        return response()->json([
            'message' => trans('This feature is disabled in demo mode.'),
            'status'  => 'success',
        ], 422);
    }
}
