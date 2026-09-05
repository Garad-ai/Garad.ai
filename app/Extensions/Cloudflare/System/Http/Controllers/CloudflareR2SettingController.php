<?php

namespace App\Extensions\Cloudflare\System\Http\Controllers;

use App\Extensions\Cloudflare\System\Http\Requests\CloudflareR2Request;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;

class CloudflareR2SettingController extends Controller
{
    public function index()
    {
        return view('cloudflare::settings');
    }

    public function update(CloudflareR2Request $request): ?RedirectResponse
    {
        $data = $request->validated();

        $request['CLOUDFLARE_R2_URL'] = $request['CLOUDFLARE_R2_URL'] ?: $request['CLOUDFLARE_R2_ENDPOINT'];

        try {
            \App\Helpers\Classes\Helper::setEnv($data);

            return redirect()->back()->with([
                'message' => __('Settings updated successfully.'),
                'type'    => 'success',
            ]);
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with([
                'message' => $e->getMessage(),
                'type'    => 'error',
            ]);
        }
    }
}
