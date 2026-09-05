<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Controllers;

use App\Extensions\VideoEditor\System\Models\VideoEditorMedia;
use App\Extensions\VideoEditor\System\Models\VideoEditorProject;
use App\Http\Controllers\Controller;
use App\Models\SettingTwo;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VideoEditorController extends Controller
{
    public function index(): View
    {
        $projects = VideoEditorProject::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->paginate(12);

        // For projects without a saved thumbnail_path, fall back to the media
        // referenced by the first clip in their timeline. Collect those media
        // ids across the page and load them in a single query, then attach
        // each to its project via the `media` relation (the model's
        // thumbnail_url accessor walks that relation).
        $fallbackMediaByProject = [];
        $allMediaIds = [];
        foreach ($projects as $project) {
            if ($project->thumbnail_path) {
                continue;
            }
            $mediaId = $project->getFirstClipMediaId();
            if ($mediaId !== null) {
                $fallbackMediaByProject[$project->id] = $mediaId;
                $allMediaIds[] = $mediaId;
            }
        }

        if (! empty($allMediaIds)) {
            $mediaItems = VideoEditorMedia::query()
                ->where('user_id', Auth::id())
                ->whereIn('id', array_unique($allMediaIds))
                ->get()
                ->keyBy('id');

            foreach ($projects as $project) {
                $mediaId = $fallbackMediaByProject[$project->id] ?? null;
                $match = $mediaId !== null ? ($mediaItems[$mediaId] ?? null) : null;
                $project->setRelation('media', $match ? collect([$match]) : collect());
            }
        } else {
            foreach ($projects as $project) {
                $project->setRelation('media', collect());
            }
        }

        return view('video-editor::index', compact('projects'));
    }

    public function editor(?VideoEditorProject $project = null): View
    {
        if ($project) {
            abort_if($project->user_id !== Auth::id(), 404);
            $project->load('media');

            // Also load media referenced by timeline clips but not directly
            // associated with the project (e.g. uploaded before project was saved,
            // or shared across projects)
            $referencedMediaIds = $this->extractMediaIds($project->timeline_data);
            $loadedMediaIds = $project->media->pluck('id')->all();
            $missingIds = array_diff($referencedMediaIds, $loadedMediaIds);

            if (! empty($missingIds)) {
                $extraMedia = VideoEditorMedia::query()
                    ->where('user_id', Auth::id())
                    ->whereIn('id', $missingIds)
                    ->get();

                // Merge into the project's media collection
                $project->setRelation('media', $project->media->merge($extraMedia));
            }
        }

        $settings_two = SettingTwo::getCache();
        $ttsProvider = VideoEditorAiController::resolveTtsProvider($settings_two);

        return view('video-editor::editor', compact('project', 'settings_two', 'ttsProvider'));
    }

    /**
     * Extract all mediaId values from timeline_data tracks/clips.
     */
    private function extractMediaIds(?array $timelineData): array
    {
        if (! $timelineData || empty($timelineData['tracks'])) {
            return [];
        }

        $ids = [];
        foreach ($timelineData['tracks'] as $track) {
            foreach ($track['clips'] ?? [] as $clip) {
                if (! empty($clip['mediaId'])) {
                    $ids[] = (int) $clip['mediaId'];
                }
            }
        }

        return array_unique($ids);
    }
}
