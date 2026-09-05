<?php

namespace App\Extensions\AdvancedImage\System\Http\Controllers;

use App\Extensions\AdvancedImage\System\Helpers\Tool;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdvancedImageSettingController extends Controller
{
    public function index()
    {
        return view('advanced-image::setting.index', [
            'tools' => Tool::get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->all();

        unset($data['_token']);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Successfully updated'),
        ]);
    }
}
