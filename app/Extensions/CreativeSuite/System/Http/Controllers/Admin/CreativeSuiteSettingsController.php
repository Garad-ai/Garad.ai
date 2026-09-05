<?php

declare(strict_types=1);

namespace App\Extensions\CreativeSuite\System\Http\Controllers\Admin;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreativeSuiteSettingsController extends Controller
{
    public function index(): View
    {
        return view('creative-suite::admin.settings');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'creative_suite_ai_model'             => ['required', 'string'],
            'creative_suite_template_engine'      => ['nullable', 'string'],
            'creative_suite_template_image_model' => ['nullable', 'string'],
        ]);

        if (Helper::appIsNotDemo()) {
            setting([
                'creative_suite_ai_model'             => $validated['creative_suite_ai_model'],
                'creative_suite_template_engine'      => $validated['creative_suite_template_engine'] ?? '',
                'creative_suite_template_image_model' => $validated['creative_suite_template_image_model'] ?? 'gpt-image-1',
            ])->save();
        }

        return back()->with([
            'type'    => 'success',
            'message' => __('Creative Suite settings updated.'),
        ]);
    }
}
