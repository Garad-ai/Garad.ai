<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Whatsapp;

use App\Extensions\MarketingBot\System\Http\Requests\SegmentRequest;
use App\Extensions\MarketingBot\System\Models\Whatsapp\Segment;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SegmentController extends Controller
{
    public function index()
    {
        return view('marketing-bot::segment.index', [
            'items' => Segment::query()->where('user_id', Auth::id())->get(),
        ]);
    }

    public function store(SegmentRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }
        Segment::query()->create($request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => __('Segment created successfully'),
        ]);
    }

    public function edit(Segment $segment)
    {
        $this->authorize('edit', $segment);

        return view('marketing-bot::segment.edit', [
            'item' => $segment,
        ]);
    }

    public function update(SegmentRequest $request, Segment $segment): RedirectResponse
    {
        $this->authorize('update', $segment);

        if (Helper::appIsDemo()) {
            return back()->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $segment->update($request->validated());

        return back()->with([
            'status'  => 'success',
            'message' => __('Segment updated successfully'),
            'type'    => 'success',
        ]);
    }

    public function destroy(Segment $segment): JsonResponse
    {
        $this->authorize('delete', $segment);

        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $segment->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Segment deleted successfully'),
        ]);
    }
}
