<?php

namespace App\Extensions\AIChatProFolders\System\Http\Controllers;

use App\Extensions\AIChatProFolders\System\Models\AiChatProFolder;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use App\Models\UserOpenaiChat;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AIChatProFoldersController extends Controller
{
    /**
     * Create a new folder
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ai_chat_pro_folders', 'name')
                    ->where('created_by', Auth::id()),
            ],
        ], [
            'name.unique'   => __('A folder with this name already exists.'),
            'name.required' => __('Folder name is required.'),
        ]);

        try {
            $folder = AiChatProFolder::create([
                'name'       => trim($validated['name']),
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'folder'  => $folder,
                'message' => __('Folder created successfully'),
            ]);
        } catch (Exception $e) {
            Log::error('Folder creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to create folder'),
            ], 500);
        }
    }

    /**
     * Update folder name
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $folder = AiChatProFolder::where('id', $id)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ai_chat_pro_folders', 'name')
                    ->where('created_by', Auth::id())
                    ->ignore($folder->id),
            ],
        ], [
            'name.unique'   => __('A folder with this name already exists.'),
            'name.required' => __('Folder name is required.'),
        ]);

        try {
            $folder->update(['name' => trim($validated['name'])]);

            return response()->json([
                'success' => true,
                'folder'  => $folder->fresh(),
                'message' => __('Folder renamed successfully'),
            ]);
        } catch (Exception $e) {
            Log::error('Folder update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to rename folder'),
            ], 500);
        }
    }

    /**
     * Delete folder (chats will be moved to uncategorized)
     */
    public function destroy(int $id): JsonResponse
    {
        $folder = AiChatProFolder::where('id', $id)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Move all chats in this folder to uncategorized
            UserOpenaiChat::where('folder_id', $folder->id)
                ->update(['folder_id' => null]);

            $folder->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Folder deleted successfully'),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Folder deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to delete folder'),
            ], 500);
        }
    }

    /**
     * Get paginated chats for the current user
     */
    public function getChats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_id'    => 'nullable|integer',
            'per_page'     => 'nullable|integer|min:1|max:100',
            'category_id'  => 'nullable|integer',
            'search'       => 'nullable|string|max:255',
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $folderId = $validated['folder_id'] ?? null;
        $categoryId = $validated['category_id'] ?? null;
        $search = $validated['search'] ?? null;

        $query = UserOpenaiChat::where('user_id', Auth::id())
            ->where('is_chatbot', 0)
            ->where(function ($q) {
                $q->whereNull('chat_type')
                    ->orWhereIn('chat_type', ['chatpro', 'chatpro-temp', 'chatPro']);
            })
            ->select(['id', 'title', 'created_at', 'is_pinned', 'updated_at', 'reference_url', 'doc_name', 'website_url', 'folder_id'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at');

        if (MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            $query->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('user_openai_chat_messages as msg')
                    ->join('ai_chat_pro_image as ai', 'ai.message_id', '=', 'msg.id')
                    ->whereColumn('msg.user_openai_chat_id', 'user_openai_chat.id');
            });
        }

        // Filter by folder if not searching
        if ($search === null || $search === '') {
            if ($folderId === null) {
                // Show only chats without folder (root level)
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $folderId);
            }
        } else {
            // When searching, search across all chats
            $query->where('title', 'like', "%{$search}%");
        }

        if ($categoryId !== null) {
            $query->where('openai_chat_category_id', $categoryId);
        }

        $chats = $query->paginate($perPage);

        return response()->json([
            'success'    => true,
            'chats'      => $chats->items(),
            'pagination' => [
                'current_page' => $chats->currentPage(),
                'last_page'    => $chats->lastPage(),
                'per_page'     => $chats->perPage(),
                'total'        => $chats->total(),
                'has_more'     => $chats->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get all folders for the current user
     */
    public function getFolders(): JsonResponse
    {
        $folders = AiChatProFolder::where('created_by', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        return response()->json([
            'success' => true,
            'folders' => $folders,
        ]);
    }

    /**
     * Move chat to folder
     */
    public function moveChat(Request $request, int $chatId): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('ai_chat_pro_folders', 'id')
                    ->where('created_by', Auth::id()),
            ],
        ], [
            'folder_id.exists' => __('The selected folder does not exist.'),
        ]);

        try {
            // Get the chat and verify ownership
            $chat = UserOpenaiChat::where('id', $chatId)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $chat->update(['folder_id' => $validated['folder_id']]);

            return response()->json([
                'success' => true,
                'message' => __('Chat moved successfully'),
            ]);
        } catch (Exception $e) {
            Log::error('Chat move failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to move chat'),
            ], 500);
        }
    }
}
