<?php

namespace App\Extensions\CreativeSuite\System\Http\Controllers;

use App\Extensions\CreativeSuite\System\Models\CreativeSuiteDocument;
use App\Http\Controllers\Controller;
use App\Models\OpenAIGenerator;
use Illuminate\Support\Facades\Auth;

class CreativeSuiteController extends Controller
{
    public function __invoke()
    {
        $openai = OpenAIGenerator::whereSlug('ai_image_generator')->firstOrFail();

        $documents = CreativeSuiteDocument::query()
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        return view('creative-suite::index', compact(['documents', 'openai']));
    }
}
