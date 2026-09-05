<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmNote;
use App\Extensions\Crm\System\Models\CrmProject;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmCalendarController extends Controller
{
    public function index(): View
    {
        return view('crm::calendar.index');
    }

    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date',
            'types' => 'nullable|string',
        ]);

        $userId = Auth::id();
        $start = $request->input('start');
        $end = $request->input('end');
        $types = $request->input('types') ? explode(',', $request->input('types')) : ['tasks', 'deals', 'projects', 'activities'];

        $events = [];

        if (in_array('tasks', $types)) {
            $tasks = CrmTask::where('user_id', $userId)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$start, $end])
                ->with(['contact', 'deal', 'project'])
                ->get();

            foreach ($tasks as $task) {
                $events[] = [
                    'id'             => "task-{$task->id}",
                    'title'          => $task->title,
                    'start'          => $task->due_date->toDateString(),
                    'end'            => $task->due_date->toDateString(),
                    'allDay'         => true,
                    'classNames'     => ['group'],
                    'extendedProps'  => [
                        'eventType'   => 'task',
                        'entityId'    => $task->id,
                        'status'      => $task->status,
                        'priority'    => $task->priority,
                        'type'        => $task->type,
                        'contact'     => $task->contact?->full_name,
                        'deal'        => $task->deal?->title,
                        'project'     => $task->project?->name,
                        'description' => $task->description,
                        'time'        => $task->due_date->format('H:i'),
                    ],
                ];
            }
        }

        if (in_array('deals', $types)) {
            $deals = CrmDeal::where('user_id', $userId)
                ->whereNotNull('expected_close_date')
                ->whereBetween('expected_close_date', [$start, $end])
                ->with(['stage', 'contact', 'company'])
                ->get();

            foreach ($deals as $deal) {
                $events[] = [
                    'id'            => "deal-{$deal->id}",
                    'title'         => $deal->title,
                    'start'         => $deal->expected_close_date->toDateString(),
                    'end'           => $deal->expected_close_date->toDateString(),
                    'allDay'        => true,
                    'classNames'    => ['group'],
                    'extendedProps' => [
                        'eventType'   => 'deal',
                        'entityId'    => $deal->id,
                        'stage'       => $deal->stage?->name,
                        'stageColor'  => $deal->stage?->color,
                        'value'       => $deal->value,
                        'currency'    => $deal->currency,
                        'contact'     => $deal->contact?->full_name,
                        'company'     => $deal->company?->name,
                        'description' => $deal->description,
                    ],
                ];
            }
        }

        if (in_array('projects', $types)) {
            $projects = CrmProject::where('user_id', $userId)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                        ->orWhereBetween('due_date', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->where('start_date', '<=', $start)
                                ->where('due_date', '>=', $end);
                        });
                })
                ->with(['contact', 'company', 'tasks'])
                ->get();

            foreach ($projects as $project) {
                $events[] = [
                    'id'            => "project-{$project->id}",
                    'title'         => $project->name,
                    'start'         => ($project->start_date ?? $project->due_date)?->toDateString(),
                    'end'           => $project->due_date?->toDateString() ?? ($project->start_date?->toDateString()),
                    'allDay'        => true,
                    'classNames'    => ['group'],
                    'extendedProps' => [
                        'eventType'   => 'project',
                        'entityId'    => $project->id,
                        'status'      => $project->status,
                        'priority'    => $project->priority,
                        'contact'     => $project->contact?->full_name,
                        'company'     => $project->company?->name,
                        'budget'      => $project->budget,
                        'currency'    => $project->currency,
                        'taskCount'   => $project->tasks->count(),
                        'tasksDone'   => $project->tasks->where('status', 'completed')->count(),
                        'description' => $project->description,
                    ],
                ];
            }
        }

        if (in_array('activities', $types)) {
            $labels = ['call' => __('Call'), 'meeting' => __('Meeting')];

            $activities = CrmNote::where('user_id', $userId)
                ->whereIn('type', ['call', 'meeting'])
                ->whereNotNull('scheduled_at')
                ->whereBetween('scheduled_at', [$start, $end])
                ->with('notable')
                ->get();

            foreach ($activities as $activity) {
                $notable = $activity->notable;
                $isDeal = $notable instanceof CrmDeal;
                $related = $isDeal ? $notable->title : ($notable?->full_name ?? null);
                $label = $labels[$activity->type] ?? __('Activity');

                $events[] = [
                    'id'            => "activity-{$activity->id}",
                    'title'         => $related ? "{$label} — {$related}" : $label,
                    'start'         => $activity->scheduled_at->toIso8601String(),
                    'end'           => $activity->scheduled_at->toIso8601String(),
                    'allDay'        => false,
                    'classNames'    => ['group'],
                    'extendedProps' => [
                        'eventType'    => $activity->type,
                        'entityId'     => $activity->id,
                        'related'      => $related,
                        'description'  => $activity->content,
                        'time'         => $activity->scheduled_at->format('H:i'),
                        'notableType'  => $notable ? ($isDeal ? 'deal' : 'contact') : null,
                        'notableId'    => $notable?->id,
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'events'  => $events,
        ]);
    }
}
