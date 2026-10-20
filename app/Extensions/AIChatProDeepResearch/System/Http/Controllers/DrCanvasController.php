<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProDeepResearch\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DrCanvasController extends Controller
{
    public function storeContent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content'    => 'required|nullable|string',
            'type'       => 'required|string',
            'message_id' => 'required',
        ]);

        $message = UserOpenaiChatMessage::query()
            ->where('user_id', auth()->id())
            ->findOrFail($validated['message_id']);

        try {
            if ($validated['type'] == 'input') {
                $message->tiptapContent()->updateOrCreate([], [
                    'input'   => $validated['content'],
                    'user_id' => auth()->id(),
                ]);
            } else {
                $message->tiptapContent()->updateOrCreate([], [
                    'output'  => $validated['content'],
                    'user_id' => auth()->id(),
                ]);
            }

            return response()->json(['status' => 'success']);
        } catch (Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error happen while store tiptap content',
            ], 500);
        }
    }

    public function saveTitle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'required',
            'title'      => 'sometimes|nullable|string',
        ]);

        $message = UserOpenaiChatMessage::query()
            ->where('user_id', auth()->id())
            ->findOrFail($validated['message_id']);

        try {
            $message->tiptapContent()->updateOrCreate([], [
                'title'   => $validated['title'],
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'success',
            ]);
        } catch (Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error happen while save canvas title',
            ], 500);
        }
    }
}
