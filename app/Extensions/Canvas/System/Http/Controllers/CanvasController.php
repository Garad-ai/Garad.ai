<?php

namespace App\Extensions\Canvas\System\Http\Controllers;

use App\Concerns\HasErrorResponse;
use App\Http\Controllers\Controller;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CanvasController extends Controller
{
    use HasErrorResponse;

    // store the content
    public function storeContent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content'    => 'required|nullable|string',
            'type'       => 'required|string',
            'message_id' => 'required',
        ]);

        try {
            $message = UserOpenaiChatMessage::find($validated['message_id']);

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
            return $this->exceptionRes($th, 'Error happen while store tiptap content');
        }
    }

    // save the title
    public function saveTitle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'required',
            'title'      => 'sometimes|nullable|string',
        ]);

        try {
            $message = UserOpenaiChatMessage::find($validated['message_id']);
            $message->tiptapContent()->updateOrCreate([], [
                'title'   => $validated['title'],
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'success',
            ]);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while save canvas title');
        }
    }
}
