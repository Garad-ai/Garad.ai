<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Http\Controllers;

use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentCall;
use App\Extensions\PhoneCallAgent\System\Services\CallHistoryExportService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PhoneCallAgentHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExtPhoneCallAgentCall::query()
            ->whereHas('agent', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['agent', 'transcripts', 'tags']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent') && $request->agent !== 'all') {
            $query->where('agent_id', $request->agent);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->where('caller_number', 'like', "%{$search}%")
                    ->orWhere('called_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('started_at', [
                $request->start_date,
                $request->end_date . ' 23:59:59',
            ]);
        }

        if ($request->filled('provider') && $request->provider !== 'all') {
            $query->whereHas('agent', fn ($q) => $q->where('provider', $request->provider));
        }

        $query->orderBy('pinned', 'desc')
            ->orderBy('started_at', $request->get('sort', 'newest') === 'oldest' ? 'asc' : 'desc');

        return response()->json($query->paginate(20));
    }

    public function pin(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')], 403);
        }

        $request->validate(['call_id' => 'required|exists:ext_phone_call_agent_calls,id']);

        $call = ExtPhoneCallAgentCall::query()->findOrFail($request->call_id);

        if ($call->agent?->user_id !== Auth::id()) {
            abort(403);
        }

        $maxPinned = ExtPhoneCallAgentCall::query()->max('pinned') ?? 0;
        $newPinned = ($call->getAttribute('pinned') ?? 0) > 0 ? 0 : $maxPinned + 1;

        $call->update(['pinned' => $newPinned]);

        return response()->json([
            'type'    => 'success',
            'message' => $newPinned > 0 ? __('Call pinned.') : __('Call unpinned.'),
            'pinned'  => $newPinned,
        ]);
    }

    public function destroy(ExtPhoneCallAgentCall $call): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')], 403);
        }

        if ($call->agent?->user_id !== Auth::id()) {
            abort(403);
        }

        $call->transcripts()->delete();
        $call->tags()->detach();
        $call->delete();

        return response()->json([
            'type'    => 'success',
            'message' => __('Call deleted successfully.'),
        ]);
    }

    public function export(Request $request, CallHistoryExportService $exportService): SymfonyResponse
    {
        $query = ExtPhoneCallAgentCall::query()
            ->whereHas('agent', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['agent', 'transcripts']);

        if ($request->filled('call_id')) {
            $query->where('id', $request->call_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent') && $request->agent !== 'all') {
            $query->where('agent_id', $request->agent);
        }

        if ($request->filled('provider') && $request->provider !== 'all') {
            $query->whereHas('agent', fn ($q) => $q->where('provider', $request->provider));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->where('caller_number', 'like', "%{$search}%")
                    ->orWhere('called_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('started_at', [
                $request->start_date,
                $request->end_date . ' 23:59:59',
            ]);
        }

        $query->orderBy('started_at', $request->get('sort', 'newest') === 'oldest' ? 'asc' : 'desc');

        $calls = $query->get();
        $format = $request->get('format', 'csv');

        return $exportService->export($calls, $format);
    }
}
