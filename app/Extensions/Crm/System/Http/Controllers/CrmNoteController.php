<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmNote;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmNoteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'notable_type' => 'required|in:contact,deal',
            'notable_id'   => 'required|integer',
            'type'         => 'required|in:note,call,meeting,email',
            'content'      => 'required|string|max:5000',
            'scheduled_at' => 'nullable|date',
        ]);

        $userId = Auth::id();

        // Verify the notable record belongs to the user
        $model = match ($validated['notable_type']) {
            'contact' => CrmContact::where('user_id', $userId)->findOrFail($validated['notable_id']),
            'deal'    => CrmDeal::where('user_id', $userId)->findOrFail($validated['notable_id']),
        };

        $note = CrmNote::create([
            'user_id'      => $userId,
            'notable_type' => get_class($model),
            'notable_id'   => $model->id,
            'type'         => $validated['type'],
            'content'      => $validated['content'],
            'scheduled_at' => in_array($validated['type'], ['call', 'meeting']) ? ($validated['scheduled_at'] ?? null) : null,
        ]);

        return response()->json([
            'success' => true,
            'note'    => [
                'id'         => $note->id,
                'type'       => $note->type,
                'content'    => $note->content,
                'created_at' => $note->created_at->diffForHumans(null, true, true),
                'user_name'  => Auth::user()->name,
            ],
        ]);
    }

    public function delete(int $note): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        CrmNote::where('id', $note)
            ->where('user_id', Auth::id())
            ->delete();

        return redirect()->back();
    }
}
