<?php

namespace App\Extensions\AiPresentation\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiPresentation\System\Models\AiPresentation;
use App\Extensions\AiPresentation\System\Services\GammaCreditPredictor;
use App\Extensions\AiPresentation\System\Services\GammaService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiPresentationController extends Controller
{
    public function index()
    {
        $presentations = AiPresentation::where('user_id', auth()->id())
            ->latest()
            ->limit(5)
            ->get();

        return view('ai-presentation::index', compact('presentations'));
    }

    public function generate(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['message' => __('This feature is disabled in Demo version.'), 'type' => 'error']);
        }

        $validated = $request->validate([
            'description'                => 'nullable|string|max:400000',
            'language'                   => 'required|string|max:10',
            'theme'                      => 'required|string|max:255',
            'theme_id'                   => 'required|string|max:255',
            'presentation_count'         => 'required|string|min:1|max:10',
            'text_length'                => 'required|string|max:50',
            'textMode'                   => 'nullable|string|max:50',
            'presentationFiles' => 'nullable|array',
'presentationFiles.*' => 'file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,csv,json,xml,html,md,png,jpg,jpeg,webp|max:51200',
            'exportAs'                   => 'nullable|string|max:50',
            'format'                     => 'nullable|string|max:50',
            'cardSplit'                  => 'nullable|string|max:50',
            'textOptions.tone'           => 'nullable|string|max:50',
            'textOptions.audience'       => 'nullable|string|max:50',
            'imageOptions.source'        => 'nullable|string|max:50',
            'imageOptions.model'         => 'nullable|string|max:50',
            'imageOptions.style'         => 'nullable|string|max:50',
            'cardOptions.dimensions'     => 'nullable|string|max:50',
        ]);

        $predictor = new GammaCreditPredictor;
        $prediction = $predictor->predictCredits($validated);
        $driver = Entity::driver(EntityEnum::GAMMA_AI)->inputPresentation((float) $prediction['max'])->calculateCredit();
        if (! $driver->hasCreditBalanceForInput()) {
            return redirect()->back()->with([
                'message' => __('You do not have enough credit balance to generate the presentation. You need at least :credits credits to proceed.', ['credits' => $prediction['max']]),
                'type'    => 'error',
            ]);
        }

        $validated['uploadedFiles'] = $request->file('presentationFiles', []);
        $service = new GammaService;
        $res = $service->generatePresentation($validated);

        return redirect()->back()->with(['message' => $res->msg, 'type' => $res->type]);
    }

    public function checkStatus(string $generationId): JsonResponse
    {
        $service = new GammaService;
        $presentation = $service->updatePresentationStatus($generationId);

        if (! $presentation) {
            return response()->json([
                'message' => trans('Presentation not found'),
                'type'    => 'error',
            ], 404);
        }

        return response()->json([
            'message' => trans('Status retrieved successfully'),
            'type'    => 'success',
            'data'    => [
                'id'            => $presentation->id,
                'status'        => $presentation->status,
                'gamma_url'     => $presentation->gamma_url,
                'pdf_url'       => $presentation->pdf_url,
                'pptx_url'      => $presentation->pptx_url,
                'error_message' => $presentation->error_message,
                'completed_at'  => $presentation->completed_at,
            ],
        ]);
    }

    public function delete(string $id): JsonResponse
    {
        if (Helper::appIsNotDemo()) {
            $presentation = AiPresentation::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (! $presentation) {
                return response()->json([
                    'message' => trans('Presentation not found'),
                    'type'    => 'error',
                ], 404);
            }

            $presentation->delete();
        }

        return response()->json([
            'message' => trans('Presentation deleted successfully'),
            'type'    => 'success',
        ]);
    }

    public function gallery(): JsonResponse
    {
        $presentations = AiPresentation::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return response()->json($presentations);
    }

    public function renamePdf(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'message' => trans('PDF renamed successfully'),
                'type'    => 'success',
            ]);
        }

        $request->validate([
            'url'   => 'required|string|max:1000',
            'title' => 'required|string|max:255',
        ]);

        if (str_contains($request->get('url'), 'https://')) {
            return response()->json([
                'message' => trans('PDF file name cannot be changed'),
                'type'    => 'error',
            ], 400);
        }

        $oldPath = public_path($request->get('url'));

        if (! file_exists($oldPath)) {
            return response()->json([
                'message' => trans('PDF file not found'),
                'type'    => 'error',
            ], 404);
        }

        // Get the directory and file extension
        $directory = dirname($oldPath);
        $extension = pathinfo($oldPath, PATHINFO_EXTENSION);

        // Sanitize the new title (remove special characters, keep only alphanumeric, spaces, hyphens, underscores)
        $sanitizedTitle = preg_replace('/[^A-Za-z0-9\s\-_]/', '', $request->get('title'));

        // Create new filename
        $newFilename = $sanitizedTitle . '.' . $extension;
        $newPath = $directory . '/' . $newFilename;

        // Check if a file with the new name already exists
        $counter = 1;
        while (file_exists($newPath) && $newPath !== $oldPath) {
            $newFilename = $sanitizedTitle . '_' . $counter . '.' . $extension;
            $newPath = $directory . '/' . $newFilename;
            $counter++;
        }

        // If the old and new paths are the same, no need to rename
        if ($oldPath === $newPath) {
            return response()->json([
                'message' => trans('PDF renamed successfully'),
                'newUrl'  => str_replace(public_path(), '', $newPath),
                'type'    => 'success',
            ]);
        }

        // Rename the file
        try {
            if (rename($oldPath, $newPath)) {
                $newUrl = str_replace(public_path(), '', $newPath);
                // Extract the old filename from the URL
                $oldFilename = basename($oldPath);
                AiPresentation::where('user_id', Auth::id())
                    ->where('pdf_url', 'LIKE', '%' . $oldFilename)
                    ->update([
                        'pdf_url'    => $newUrl,
                        'updated_at' => now(),
                    ]);

                return response()->json([
                    'message' => trans('PDF renamed successfully'),
                    'newUrl'  => $newUrl,
                    'type'    => 'success',
                ]);
            }

            return response()->json([
                'message' => trans('Failed to rename PDF file'),
                'type'    => 'error',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => trans('An error occurred while renaming the file'),
                'type'    => 'error',
            ], 500);
        }
    }
}
