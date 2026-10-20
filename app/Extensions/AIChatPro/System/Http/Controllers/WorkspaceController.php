<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class WorkspaceController extends Controller
{
    public function agents(): View
    {
        $agents = collect();
        $agentsAvailable = Schema::hasTable('ext_ai_agent_workflows');

        if ($agentsAvailable) {
            try {
                $workflowRows = collect(DB::table('ext_ai_agent_workflows')
                    ->where('user_id', Auth::id())
                    ->orderByDesc('updated_at')
                    ->limit(100)
                    ->get([
                        'id',
                        'name',
                        'description',
                        'role',
                        'status',
                        'trigger_type',
                        'trigger_config',
                        'avatar',
                        'steps',
                        'actions',
                        'last_run_at',
                        'updated_at',
                    ]));

                $runCounts = collect();
                if (Schema::hasTable('ext_ai_agent_workflow_runs') && $workflowRows->isNotEmpty()) {
                    $runCounts = DB::table('ext_ai_agent_workflow_runs')
                        ->selectRaw('workflow_id, COUNT(*) as aggregate')
                        ->whereIn('workflow_id', $workflowRows->pluck('id')->all())
                        ->groupBy('workflow_id')
                        ->pluck('aggregate', 'workflow_id');
                }

                $agents = $workflowRows
                    ->filter(function (object $workflow): bool {
                        $raw = (string) ($workflow->trigger_config ?? '');
                        if ($raw === '') {
                            return true;
                        }

                        try {
                            $trigger = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                        } catch (Throwable) {
                            return true;
                        }

                        if (! is_array($trigger)) {
                            return true;
                        }

                        return ! (bool) ($trigger['garad_task'] ?? false);
                    })
                    ->map(function (object $workflow) use ($runCounts): array {
                        $avatar = trim((string) ($workflow->avatar ?? ''));
                        if ($avatar !== '' && ! Str::startsWith($avatar, ['http://', 'https://', '/'])) {
                            $avatar = '/uploads/' . ltrim($avatar, '/');
                        }

                        $steps = json_decode((string) ($workflow->steps ?? ''), true);
                        $actions = json_decode((string) ($workflow->actions ?? ''), true);
                        $stepsCount = is_array($steps) ? count($steps) : (is_array($actions) ? count($actions) : 0);

                        return [
                            'id'           => (int) ($workflow->id ?? 0),
                            'name'         => (string) ($workflow->name ?? __('AI Agent')),
                            'description'  => (string) ($workflow->description ?? ''),
                            'role'         => (string) ($workflow->role ?? 'AI Agent'),
                            'status'       => (string) ($workflow->status ?: 'active'),
                            'trigger_type' => (string) ($workflow->trigger_type ?: 'manual'),
                            'avatar_url'   => $avatar,
                            'steps_count'  => $stepsCount,
                            'runs_count'   => isset($runCounts[$workflow->id]) ? (int) $runCounts[$workflow->id] : null,
                            'last_run_at'  => $workflow->last_run_at ?? null,
                        ];
                    })
                    ->values();
            } catch (Throwable) {
                $agents = collect();
            }
        }

        return view('ai-chat-pro::workspace.agents', compact('agents', 'agentsAvailable'));
    }

    public function code(): View
    {
        return view('ai-chat-pro::workspace.code');
    }

    public function library(): View
    {
        $documents = collect();
        $artifacts = collect();

        try {
            if (Schema::hasTable('ai_chat_pro_documents')) {
                $chatMap = collect();
                if (Schema::hasTable('user_openai_chat')) {
                    $chatIds = DB::table('ai_chat_pro_documents')
                        ->where('user_id', Auth::id())
                        ->whereNotNull('user_openai_chat_id')
                        ->pluck('user_openai_chat_id')
                        ->filter()
                        ->unique()
                        ->values();

                    if ($chatIds->isNotEmpty()) {
                        $chatMap = DB::table('user_openai_chat')
                            ->whereIn('id', $chatIds->all())
                            ->where('user_id', Auth::id())
                            ->get(['id', 'title', 'slug'])
                            ->keyBy('id');
                    }
                }

                $documents = collect(DB::table('ai_chat_pro_documents')
                    ->where('user_id', Auth::id())
                    ->orderByDesc('updated_at')
                    ->limit(100)
                    ->get([
                        'id',
                        'user_openai_chat_id',
                        'original_name',
                        'mime_type',
                        'file_size',
                        'updated_at',
                    ]))
                    ->map(function (object $doc) use ($chatMap) {
                        $doc->chat = $chatMap->get($doc->user_openai_chat_id);

                        return $doc;
                    });
            }
        } catch (Throwable) {
            $documents = collect();
        }

        try {
            if (Schema::hasTable('ai_chat_pro_artifacts')) {
                $artifacts = collect(DB::table('ai_chat_pro_artifacts')
                    ->where('user_id', Auth::id())
                    ->orderByDesc('updated_at')
                    ->limit(100)
                    ->get([
                        'id',
                        'user_openai_chat_id',
                        'type',
                        'title',
                        'original_name',
                        'file_size',
                        'updated_at',
                    ]));
            }
        } catch (Throwable) {
            $artifacts = collect();
        }

        return view('ai-chat-pro::workspace.library', compact('documents', 'artifacts'));
    }
}
