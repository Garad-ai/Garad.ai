<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Common;

use App\Extensions\SocialMedia\System\Http\Resources\Company\CompanyResource;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;

class SocialMediaCompanyCommonController extends Controller
{
    public function __invoke()
    {
        return CompanyResource::collection(
            Company::query()
                ->where('user_id', Auth::id())
                ->with('products')
                ->get()
        );
    }
}
