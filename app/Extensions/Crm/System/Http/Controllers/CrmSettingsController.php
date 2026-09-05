<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Common\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmSettingsController extends Controller
{
    /**
     * Currencies offered as the default for Sales documents.
     *
     * @return array<int, string>
     */
    public static function currencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'TRY'];
    }

    public function index(): View
    {
        return view('crm::settings.index', [
            'currencies' => self::currencies(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsNotDemo()) {
            $validated = $request->validate([
                'crm_default_currency'    => ['required', 'string', 'in:' . implode(',', self::currencies())],
                'crm_presentation_engine' => ['required', 'string', 'in:fal,gamma'],
                'gamma_api_secret'        => ['nullable', 'string'],
            ]);

            $settings = [
                'crm_default_currency'        => $validated['crm_default_currency'],
                'crm_presentation_engine'     => $validated['crm_presentation_engine'],
                'crm_enabled'                 => $request->boolean('crm_enabled') ? '1' : '0',
                'crm_assistant_enabled'       => $request->boolean('crm_assistant_enabled') ? '1' : '0',
                'crm_presentations_enabled'   => $request->boolean('crm_presentations_enabled') ? '1' : '0',
                'crm_contact_sync_enabled'    => $request->boolean('crm_contact_sync_enabled') ? '1' : '0',
                'crm_sync_marketing_whatsapp' => $request->boolean('crm_sync_marketing_whatsapp') ? '1' : '0',
                'crm_sync_marketing_telegram' => $request->boolean('crm_sync_marketing_telegram') ? '1' : '0',
                'crm_sync_chatbot'            => $request->boolean('crm_sync_chatbot') ? '1' : '0',
            ];

            // Only overwrite the shared Gamma key when a value is provided.
            if (! empty($validated['gamma_api_secret'])) {
                $settings['gamma_api_secret'] = $validated['gamma_api_secret'];
            }

            setting($settings)->save();

            app(MenuService::class)->regenerate();
        }

        return back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
