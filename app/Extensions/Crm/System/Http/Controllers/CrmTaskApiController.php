<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CrmTaskApiController extends Controller
{
    public function show(CrmTask $task): JsonResponse
    {
        abort_if($task->user_id !== Auth::id(), 404);

        $userId = Auth::id();

        $task->load(['contact', 'deal']);

        return response()->json([
            'success'  => true,
            'task'     => [
                'id'              => $task->id,
                'title'           => $task->title,
                'description'     => $task->description,
                'type'            => $task->type,
                'status'          => $task->status,
                'priority'        => $task->priority,
                'due_date'        => $task->due_date?->format('Y-m-d\TH:i'),
                'crm_contact_id'  => $task->crm_contact_id,
                'crm_deal_id'     => $task->crm_deal_id,
            ],
            'contacts' => CrmContact::where('user_id', $userId)
                ->orderBy('first_name')
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->full_name]),
            'deals'    => CrmDeal::where('user_id', $userId)
                ->orderBy('title')
                ->get()
                ->map(fn ($d) => ['id' => $d->id, 'title' => $d->title]),
        ]);
    }
}
