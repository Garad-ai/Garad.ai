<?php

namespace App\Extensions\LiveCustomizer\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LiveCustomizerSettingController extends Controller
{
    public function index()
    {
        return view('live-customizer::setting');
    }

    public function update(Request $request)
    {
        if (Helper::appIsNotDemo()) {
            setting([
                'show_live_customizer' => (int) $request->has('show_live_customizer'),
            ])->save();

            return redirect()->back()->with([
                'success' => __('Live customizer settings updated successfully.'),
                'type'    => 'success',
            ]);
        }

        return redirect()->back()->with([
            'message' => trans('This feature is disabled in demo mode.'),
            'type'    => 'error',
        ], 422);
    }
}
