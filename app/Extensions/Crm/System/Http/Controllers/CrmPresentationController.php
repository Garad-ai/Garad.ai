<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\Crm\System\Jobs\DownloadSlideImageJob;
use App\Extensions\Crm\System\Jobs\GeneratePresentationJob;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmPresentation;
use App\Extensions\Crm\System\Models\CrmPresentationSlide;
use App\Extensions\Crm\System\Services\CrmGammaService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use ZipArchive;

class CrmPresentationController extends Controller
{
    private const GAMMA_CARDS = 8;

    public function index(): View
    {
        $userId = Auth::id();

        $presentations = CrmPresentation::where('user_id', $userId)
            ->with(['deal', 'company', 'slides'])
            ->orderByDesc('created_at')
            ->get();

        $deals = CrmDeal::where('user_id', $userId)
            ->with(['contact', 'company'])
            ->get();

        return view('crm::presentations.index', compact('presentations', 'deals'));
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'crm_deal_id' => 'required|exists:crm_deals,id',
            'style'       => 'nullable|string|in:corporate,modern,minimal,creative',
        ]);

        $user = Auth::user();
        $userId = $user->id;

        $deal = CrmDeal::where('user_id', $userId)
            ->with(['contact', 'company'])
            ->findOrFail($validated['crm_deal_id']);

        $style = $validated['style'] ?? 'corporate';
        $engine = setting('crm_presentation_engine', 'fal') === 'gamma' ? 'gamma' : 'fal';

        $metadata = [
            'deal_title'   => $deal->title,
            'deal_value'   => $deal->value,
            'company_name' => $deal->company?->name,
            'contact_name' => $deal->contact?->full_name,
        ];

        if ($engine === 'gamma') {
            $error = $this->preCheckGamma();

            if ($error) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            $metadata['num_cards'] = self::GAMMA_CARDS;
        } else {
            // Fast credit pre-check so the user gets immediate feedback. The actual
            // deduction happens inside the job once the slide count is known.
            $chatDriver = Entity::driver()->forUser($user);

            if (! $chatDriver->hasCreditBalance()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Insufficient credits for text generation. Please purchase more credits.'),
                ], 422);
            }

            // Estimate 6 slides for image credit check
            $imageDriver = Entity::driver(EntityEnum::NANO_BANANA_PRO)
                ->forUser($user)
                ->inputImageCount(6)
                ->calculateCredit();

            if (! $imageDriver->hasCreditBalance()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Insufficient image credits for slide generation. Please purchase more credits.'),
                ], 422);
            }

            $metadata['ai_model'] = $chatDriver->enum()->value;
        }

        $presentation = CrmPresentation::create([
            'user_id'          => $userId,
            'crm_deal_id'      => $deal->id,
            'crm_company_id'   => $deal->crm_company_id,
            'crm_contact_id'   => $deal->crm_contact_id,
            'title'            => __('Pitch Deck') . ' - ' . $deal->title,
            'status'           => 'pending',
            'engine'           => $engine,
            'style'            => $style,
            'total_slides'     => 0,
            'completed_slides' => 0,
            'metadata'         => $metadata,
        ]);

        GeneratePresentationJob::dispatch($presentation->id);

        return response()->json([
            'success'         => true,
            'message'         => __('Presentation generation started. Your slides are being created.'),
            'presentation_id' => $presentation->id,
            'redirect'        => route('dashboard.user.crm.presentations.show', $presentation->id),
        ]);
    }

    public function show(CrmPresentation $presentation): View
    {
        abort_if($presentation->user_id !== Auth::id(), 404);

        $presentation->load([
            'slides' => fn ($q) => $q->orderBy('sort_order'),
            'deal',
            'company',
            'contact',
        ]);

        return view('crm::presentations.show', compact('presentation'));
    }

    public function checkStatus(CrmPresentation $presentation): JsonResponse
    {
        abort_if($presentation->user_id !== Auth::id(), 404);

        if ($presentation->isGamma()) {
            return $this->checkGammaStatus($presentation);
        }

        $updated = false;
        $pendingSlides = $presentation->slides()->where('status', 'generating')->get();

        foreach ($pendingSlides as $slide) {
            if (! $slide->fal_request_id) {
                continue;
            }

            try {
                $result = FalAIService::check($slide->fal_request_id, EntityEnum::NANO_BANANA_PRO);
            } catch (Exception $e) {
                $slide->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $updated = true;

                continue;
            }

            if ($result === null) {
                continue; // still processing
            }

            if (isset($result['status']) && $result['status'] === 'FAILED') {
                $slide->update([
                    'status'        => 'failed',
                    'error_message' => $result['error'] ?? __('Image generation failed.'),
                ]);
                $updated = true;

                continue;
            }

            $imageUrl = data_get($result, 'image.url');

            if ($imageUrl) {
                // Mark complete with the remote URL right away, then queue the
                // local download so the status request stays fast.
                $slide->update([
                    'status'    => 'completed',
                    'image_url' => $imageUrl,
                ]);

                DownloadSlideImageJob::dispatch($slide->id, $imageUrl);
                $updated = true;
            }
        }

        // Update presentation counters
        if ($updated) {
            $completedCount = $presentation->slides()->where('status', 'completed')->count();
            $failedCount = $presentation->slides()->where('status', 'failed')->count();

            $newStatus = ($completedCount + $failedCount >= $presentation->total_slides)
                ? 'completed'
                : 'generating';

            $presentation->update([
                'completed_slides' => $completedCount,
                'status'           => $newStatus,
            ]);
        }

        $freshPresentation = $presentation->fresh();
        $slides = $presentation->slides()->orderBy('sort_order')->get();

        return response()->json([
            'status'           => $freshPresentation->status,
            'completed_slides' => $freshPresentation->completed_slides,
            'total_slides'     => $freshPresentation->total_slides,
            'progress'         => $freshPresentation->progress,
            'slides'           => $slides->map(fn ($s) => [
                'id'         => $s->id,
                'sort_order' => $s->sort_order,
                'title'      => $s->title,
                'slide_type' => $s->slide_type,
                'status'     => $s->status,
                'image_url'  => $s->image_url,
                'content'    => $s->content,
            ]),
        ]);
    }

    public function delete(CrmPresentation $presentation)
    {
        abort_if($presentation->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $presentation->delete();

        return redirect()->route('dashboard.user.crm.presentations.index')
            ->with(['message' => __('Presentation deleted.'), 'type' => 'success']);
    }

    public function downloadSlide(CrmPresentationSlide $slide)
    {
        $presentation = $slide->presentation;
        abort_if($presentation->user_id !== Auth::id(), 404);

        if (! $slide->image_url) {
            return back()->with(['message' => __('Slide image not available.'), 'type' => 'error']);
        }

        $filename = Str::slug($presentation->title) . '-slide-' . ($slide->sort_order + 1) . '.png';

        // Handle local path
        if (str_starts_with($slide->image_url, '/uploads/')) {
            $localPath = public_path($slide->image_url);

            if (file_exists($localPath)) {
                return response()->download($localPath, $filename);
            }
        }

        // Handle URL (S3/R2)
        return redirect($slide->image_url);
    }

    public function downloadAll(CrmPresentation $presentation)
    {
        abort_if($presentation->user_id !== Auth::id(), 404);

        $slides = $presentation->slides()
            ->where('status', 'completed')
            ->whereNotNull('image_url')
            ->orderBy('sort_order')
            ->get();

        if ($slides->isEmpty()) {
            return back()->with(['message' => __('No completed slides to download.'), 'type' => 'error']);
        }

        $zipName = Str::slug($presentation->title) . '-slides.zip';
        $tempPath = storage_path('app/temp/' . $zipName);

        // Ensure temp directory exists
        if (! is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive;

        if ($zip->open($tempPath, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) {
            return back()->with(['message' => __('Failed to create download.'), 'type' => 'error']);
        }

        foreach ($slides as $slide) {
            $slideFilename = 'slide-' . ($slide->sort_order + 1) . '-' . Str::slug($slide->title) . '.png';

            if (str_starts_with($slide->image_url, '/uploads/')) {
                $localPath = public_path($slide->image_url);

                if (file_exists($localPath)) {
                    $zip->addFile($localPath, $slideFilename);
                }
            } else {
                // Download from remote URL
                try {
                    $contents = file_get_contents($slide->image_url);

                    if ($contents !== false) {
                        $zip->addFromString($slideFilename, $contents);
                    }
                } catch (Exception $e) {
                    // Skip slides that can't be downloaded
                }
            }
        }

        $zip->close();

        return response()->download($tempPath, $zipName)->deleteFileAfterSend(true);
    }

    public function downloadPdf(CrmPresentation $presentation)
    {
        abort_if($presentation->user_id !== Auth::id(), 404);

        if ($presentation->isGamma()) {
            if (! $presentation->pdf_url) {
                return back()->with(['message' => __('PDF not available yet.'), 'type' => 'error']);
            }

            return redirect($presentation->pdf_url);
        }

        $slides = $presentation->slides()
            ->where('status', 'completed')
            ->whereNotNull('image_url')
            ->orderBy('sort_order')
            ->get();

        if ($slides->isEmpty()) {
            return back()->with(['message' => __('No completed slides to download.'), 'type' => 'error']);
        }

        $pdfSlides = $slides->map(fn (CrmPresentationSlide $slide): array => [
            'image'      => $this->slideImageDataUri($slide->image_url),
            'title'      => $slide->title,
            'content'    => $slide->content,
            'slide_type' => $slide->slide_type,
        ])->all();

        $pdf = Pdf::loadView('crm::presentations.pdf', [
            'presentation' => $presentation,
            'slides'       => $pdfSlides,
        ])->setPaper('a4', 'landscape');

        return $pdf->download(Str::slug($presentation->title) . '.pdf');
    }

    private function slideImageDataUri(string $imageUrl): ?string
    {
        $contents = null;

        if (str_starts_with($imageUrl, '/uploads/')) {
            $localPath = public_path($imageUrl);

            if (file_exists($localPath)) {
                $contents = file_get_contents($localPath);
            }
        } else {
            try {
                $contents = file_get_contents($imageUrl);
            } catch (Exception $e) {
                $contents = false;
            }
        }

        if (! $contents) {
            return null;
        }

        $extension = strtolower(pathinfo((string) parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function preCheckGamma(): ?string
    {
        if (! CrmGammaService::isConfigured()) {
            return __('The Gamma presentation engine is not configured. Please contact an administrator.');
        }

        // Rough estimate: ~3 credits per card. Real deduction is reconciled
        // from Gamma's reported usage during status polling.
        $estimate = self::GAMMA_CARDS * 3;

        $driver = Entity::driver(EntityEnum::GAMMA_AI)
            ->inputPresentation((float) $estimate)
            ->calculateCredit();

        if (! $driver->hasCreditBalanceForInput()) {
            return __('You do not have enough credits to generate this presentation.');
        }

        return null;
    }

    private function checkGammaStatus(CrmPresentation $presentation): JsonResponse
    {
        $fresh = (new CrmGammaService)->syncStatus($presentation);

        return response()->json([
            'status'           => $fresh->status,
            'engine'           => 'gamma',
            'gamma_url'        => $fresh->gamma_url,
            'pdf_url'          => $fresh->pdf_url,
            'completed_slides' => 0,
            'total_slides'     => $fresh->total_slides,
            'progress'         => $fresh->isCompleted() ? 100 : 0,
            'slides'           => [],
        ]);
    }
}
