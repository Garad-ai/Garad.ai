<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Setting;

use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WhatsappSettingController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $data = $request->validate([
            'whatsapp_provider'      => 'required|string|in:twilio,meta',
            'whatsapp_sid'           => 'required_if:whatsapp_provider,twilio|nullable|string',
            'whatsapp_token'         => 'required_if:whatsapp_provider,twilio|nullable|string',
            'whatsapp_phone'         => 'required_if:whatsapp_provider,twilio|nullable|string',
            'whatsapp_sandbox_phone' => 'nullable|string',
            'whatsapp_environment'   => 'required_if:whatsapp_provider,twilio|nullable|string',
            'meta_access_token'      => 'required_if:whatsapp_provider,meta|nullable|string',
            'meta_phone_number_id'   => 'required_if:whatsapp_provider,meta|nullable|string',
            'meta_verify_token'      => 'nullable|string',
            'meta_waba_id'           => 'required_if:whatsapp_provider,meta|nullable|string',
        ]);

        WhatsappChannel::query()->updateOrCreate([
            'user_id' => auth()->id(),
        ], $data ?? []);

        return back()->with([
            'message' => __('WhatsApp settings updated successfully.'),
            'type'    => 'success',
        ]);
    }
}
