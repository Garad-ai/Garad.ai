<?php

namespace App\Extensions\AiViralClips\System\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VizardSettingController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        return view('ai-viral-clips::settings.vizard');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vizard_api_key'   => 'required|string',
        ]);

        setting($data)->save();

        return back()->with([
            'type'    => 'success',
            'message' => trans('Settings updated successfully.'),
        ]);
    }
}
