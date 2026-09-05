<?php

namespace App\Extensions\AIChatProMemory\System\Http\Controllers;

use App\Extensions\AIChatProMemory\System\Models\UserChatInstruction;
use App\Http\Controllers\Controller;
use App\Models\UserOpenaiChat;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AIChatProMemoryController extends Controller
{
    /**
     * Get instructions for current user/guest
     */
    public function getInstructions(Request $request): JsonResponse
    {
        $request->validate([
            'chat_id' => 'required|integer',
        ]);

        $chatId = $request->input('chat_id');
        $chat = UserOpenaiChat::with('category')->find($chatId);

        if (! $chat) {
            return response()->json([
                'success' => false,
                'message' => __('Chat not found.'),
            ], 404);
        }

        // Check access for authenticated users
        if (Auth::check() && $chat->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => __('Access denied.'),
            ], 403);
        }

        $categoryId = $chat->category->id;
        $adminInstructions = $chat->category->instructions; // Admin's default instructions

        if (Auth::check()) {
            // Get user-specific instructions
            $userInstructions = UserChatInstruction::getForUser(Auth::id(), $categoryId);
        } else {
            // Get guest instructions by IP
            $ipAddress = $request->header('CF-Connecting-IP') ?? $request->ip();
            $userInstructions = UserChatInstruction::getForGuest($ipAddress, $categoryId);
        }

        return response()->json([
            'success'            => true,
            'admin_instructions' => $adminInstructions ?? '',
            'user_instructions'  => $userInstructions ?? '',
            'has_user_override'  => ! empty($userInstructions),
            'type'               => Auth::check() ? 'user' : 'guest',
        ]);
    }

    /**
     * Save instructions for current user/guest
     */
    public function saveInstructions(Request $request): JsonResponse
    {
        $request->validate([
            'instructions' => 'nullable|string|max:5000',
            'chat_id'      => 'required|integer',
        ]);

        $chatId = $request->input('chat_id');
        $chat = UserOpenaiChat::with('category')->find($chatId);

        if (! $chat) {
            return response()->json([
                'success' => false,
                'message' => __('Chat not found.'),
            ], 404);
        }

        // Check access for authenticated users
        if (Auth::check() && $chat->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => __('Access denied.'),
            ], 403);
        }

        $categoryId = $chat->category->id;
        $instructions = $request->input('instructions');

        try {
            if (Auth::check()) {
                // Save for authenticated user
                UserChatInstruction::setForUser(Auth::id(), $categoryId, $instructions);
            } else {
                // Save for guest by IP
                $ipAddress = $request->header('CF-Connecting-IP') ?? $request->ip();
                UserChatInstruction::setForGuest($ipAddress, $categoryId, $instructions);
            }

            return response()->json([
                'success' => true,
                'message' => __('Your personal instructions have been saved successfully.'),
                'type'    => Auth::check() ? 'user' : 'guest',
            ]);
        } catch (Exception $e) {
            Log::error('Error saving user instructions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to save instructions. Please try again.'),
            ], 500);
        }
    }

    /**
     * Clear user instructions (revert to admin defaults)
     */
    public function clearInstructions(Request $request): JsonResponse
    {
        $request->validate([
            'chat_id' => 'required|integer',
        ]);

        $chatId = $request->input('chat_id');
        $chat = UserOpenaiChat::with('category')->find($chatId);

        if (! $chat) {
            return response()->json([
                'success' => false,
                'message' => __('Chat not found.'),
            ], 404);
        }

        // Check access for authenticated users
        if (Auth::check() && $chat->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => __('Access denied.'),
            ], 403);
        }

        $categoryId = $chat->category->id;

        try {
            if (Auth::check()) {
                // Delete user-specific instruction
                UserChatInstruction::where('user_id', Auth::id())
                    ->where('openai_chat_category_id', $categoryId)
                    ->delete();
            } else {
                // Delete guest instruction
                $ipAddress = $request->header('CF-Connecting-IP') ?? $request->ip();
                UserChatInstruction::whereNull('user_id')
                    ->where('ip_address', $ipAddress)
                    ->where('openai_chat_category_id', $categoryId)
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'message' => __('Your personal instructions have been cleared. Admin defaults will be used.'),
            ]);
        } catch (Exception $e) {
            Log::error('Error clearing user instructions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to clear instructions. Please try again.'),
            ], 500);
        }
    }
}
