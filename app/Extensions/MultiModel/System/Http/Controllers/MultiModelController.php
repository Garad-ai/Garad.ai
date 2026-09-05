<?php

namespace App\Extensions\MultiModel\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MultiModelController extends Controller
{
    public function acceptResponse(Request $request): JsonResponse
    {
        // Validate input
        $validated = $request->validate([
            'messageId' => 'required|integer|exists:user_openai_chat_messages,id',
        ]);

        // Retrieve the message
        $message = UserOpenaiChatMessage::find($validated['messageId']);

        if (! $message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        // Delete other messages with the same shared_uuid
        $deletedCount = UserOpenaiChatMessage::where('shared_uuid', $message->shared_uuid)
            ->where('id', '!=', $message->id)
            ->delete();

        $message->shared_uuid = null;
        $message->save();

        return response()->json([
            'success' => true,
            'deleted' => $deletedCount,
        ]);
    }
}
