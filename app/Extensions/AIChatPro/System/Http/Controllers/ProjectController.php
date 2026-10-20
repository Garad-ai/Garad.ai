<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatProFolders\System\Models\AiChatProFolder;
use App\Http\Controllers\Controller;
use App\Models\UserOpenaiChat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        if (! Schema::hasTable('ai_chat_pro_folders')) {
            return view('ai-chat-pro::workspace.projects', ['projects' => collect()]);
        }

        try {
            $projects = AiChatProFolder::query()
            ->where('created_by', Auth::id())
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (AiChatProFolder $project): array {
                $chats = UserOpenaiChat::query()
                    ->where('user_id', Auth::id())
                    ->where('folder_id', $project->id)
                    ->latest('updated_at')
                    ->limit(5)
                    ->get(['id', 'title', 'slug', 'updated_at']);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'instructions' => $project->instructions,
                    'chat_count' => UserOpenaiChat::query()->where('user_id', Auth::id())->where('folder_id', $project->id)->count(),
                    'updated_at' => $project->updated_at,
                    'chats' => $chats,
                ];
            });
        } catch (Throwable) {
            $projects = collect();
        }

        return view('ai-chat-pro::workspace.projects', compact('projects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ai_chat_pro_folders', 'name')->where('created_by', Auth::id())],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        AiChatProFolder::query()->create($data + ['created_by' => Auth::id()]);
        return back()->with(['type' => 'success', 'message' => __('Project created.')]);
    }

    public function update(Request $request, int $projectId): RedirectResponse
    {
        $project = AiChatProFolder::query()->where('id', $projectId)->where('created_by', Auth::id())->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ai_chat_pro_folders', 'name')->where('created_by', Auth::id())->ignore($project->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);
        $project->update($data);
        return back()->with(['type' => 'success', 'message' => __('Project updated.')]);
    }

    public function destroy(int $projectId): RedirectResponse
    {
        $project = AiChatProFolder::query()->where('id', $projectId)->where('created_by', Auth::id())->firstOrFail();
        DB::transaction(function () use ($project): void {
            UserOpenaiChat::query()->where('user_id', Auth::id())->where('folder_id', $project->id)->update(['folder_id' => null]);
            $project->delete();
        });
        return back()->with(['type' => 'success', 'message' => __('Project deleted. Chats were moved back to Chats.')]);
    }
}
