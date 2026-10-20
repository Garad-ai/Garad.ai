<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Models\AIChatProArtifact;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtifactController extends Controller
{
    public function download(AIChatProArtifact $artifact): StreamedResponse
    {
        abort_unless(Auth::check() && (int) $artifact->user_id === (int) Auth::id(), 403);
        abort_unless(Storage::disk($artifact->storage_disk)->exists($artifact->storage_path), 404);

        return Storage::disk($artifact->storage_disk)->download(
            $artifact->storage_path,
            $artifact->original_name,
            ['Content-Type' => $artifact->mime_type],
        );
    }
}
