<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Http\Controllers;

use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentCall;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentCallTag;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhoneCallAgentCallTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $call = ExtPhoneCallAgentCall::query()
            ->whereHas('agent', fn ($q) => $q->where('user_id', Auth::id()))
            ->findOrFail($request->integer('call_id'));

        $tags = ExtPhoneCallAgentCallTag::query()
            ->where('user_id', Auth::id())
            ->get();

        $assigned = $call->tags()->pluck('ext_phone_call_agent_call_tags.id')->toArray();

        return response()->json(['tags' => $tags, 'assigned' => $assigned]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $data = $request->validate([
            'tag'       => ['required', 'string', 'max:50'],
            'tag_color' => ['nullable', 'string', 'max:50'],
        ]);

        $data['user_id'] = Auth::id();

        $tag = ExtPhoneCallAgentCallTag::query()->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => __('Tag created.'),
            'tag'     => $tag,
        ], 201);
    }

    public function sync(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $call = ExtPhoneCallAgentCall::query()
            ->whereHas('agent', fn ($q) => $q->where('user_id', Auth::id()))
            ->findOrFail($request->integer('call_id'));

        $call->tags()->sync($request->input('tag_ids', []));

        $call->load('tags');

        return response()->json([
            'status'  => 'success',
            'message' => __('Tags updated.'),
            'data'    => ['call_tags' => $call->tags],
        ]);
    }
}
