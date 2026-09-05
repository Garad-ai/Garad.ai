<?php

namespace App\Extensions\ContentManager\System\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentManagerSettingsController extends Controller
{
    /**
     * Display the settings page for the Content Manager.
     */
    public function index(): View
    {
        return view('content-manager::settings.index');
    }

    /**
     * Update the settings for the Content Manager.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'media_max_files'         => 'required|integer|min:1',
            'media_max_size'          => 'required|numeric|min:0.1',
            'media_allowed_types'     => 'required|string',
        ]);

        setting([
            'media_max_files'         => $request->media_max_files,
            'media_max_size'          => $request->media_max_size,
            'media_allowed_types'     => $request->media_allowed_types,
            'content_manager_enabled' => $request->has('content_manager_enabled') ? '1' : '0',
        ])->save();

        return redirect()->back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
