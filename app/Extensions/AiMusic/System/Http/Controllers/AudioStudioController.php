<?php

namespace App\Extensions\AiMusic\System\Http\Controllers;

use App\Extensions\AiMusic\System\Models\UserMusic;
use App\Extensions\VideoEditor\System\Models\VideoEditorMedia;
use App\Http\Controllers\Controller;
use App\Models\OpenAIGenerator;
use App\Models\UserOpenai;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AudioStudioController extends Controller
{
    public function history(): JsonResponse
    {
        $userId = Auth::id();
        $songs = UserMusic::query()->where('user_id', $userId)->latest('id')->limit(30)->get()->map(fn (UserMusic $song) => [
            'id' => 'music-'.$song->id,
            'recordId' => $song->id,
            'kind' => 'song',
            'source' => 'legacy-music',
            'title' => $song->title ?: 'Generated song',
            'audioUrl' => $song->audio_url,
            'createdAt' => optional($song->created_at)->toIso8601String(),
        ]);

        $voiceovers = collect();
        $generator = OpenAIGenerator::query()->where('slug', 'ai_voiceover')->first();
        if ($generator) {
            $voiceovers = UserOpenai::query()
                ->where('user_id', $userId)
                ->where('openai_id', $generator->id)
                ->whereNotNull('output')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(function (UserOpenai $record) {
                    $speech = json_decode((string) $record->input, true);
                    $text = is_array($speech) ? (string) data_get($speech, '0.content', '') : '';
                    return [
                        'id' => 'speech-'.$record->id,
                        'kind' => 'voiceover',
                        'source' => 'legacy-tts',
                        'title' => $record->title ?: 'Generated voice-over',
                        'audioUrl' => '/uploads/'.ltrim((string) $record->output, '/'),
                        'createdAt' => optional($record->created_at)->toIso8601String(),
                        'text' => $text,
                    ];
                });
        }

        $editorAudio = collect();
        if (class_exists(VideoEditorMedia::class)) {
            $editorAudio = VideoEditorMedia::query()
                ->where('user_id', $userId)
                ->where('type', 'audio')
                ->where('source', 'ai_generated')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (VideoEditorMedia $media) => [
                    'id' => 'video-editor-'.$media->id,
                    'recordId' => $media->id,
                    'kind' => str_starts_with((string) $media->original_filename, 'AI Music -') ? 'song' : 'voiceover',
                    'source' => 'video-editor',
                    'title' => $media->original_filename ?: 'Generated audio',
                    'audioUrl' => $media->url,
                    'createdAt' => optional($media->created_at)->toIso8601String(),
                ]);
        }

        return response()->json($songs->concat($voiceovers)->concat($editorAudio)->sortByDesc('createdAt')->values());
    }

    public function deleteSong(int $id): JsonResponse
    {
        $song = UserMusic::query()->where('user_id', Auth::id())->whereKey($id)->first();
        if (! $song) {
            return response()->json(['message' => __('Song not found.')], 404);
        }

        $fileName = basename((string) $song->audio_url);
        if ($fileName !== '') {
            Storage::disk('public')->delete($fileName);
        }
        $song->delete();

        return response()->json(['deleted' => $id]);
    }
}