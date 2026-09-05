<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProEntityHighlight\System\Http\Controllers;

use App\Extensions\AiChatProEntityHighlight\System\Models\ChatEntityHighlight;
use App\Extensions\AiChatProEntityHighlight\System\Services\EntityDetailService;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class EntityDetailController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'entity_text' => 'required|string|max:200',
            'entity_type' => 'required|string|max:50',
            'message_id'  => 'required|integer|exists:user_openai_chat_messages,id',
            'context'     => 'nullable|string|max:500',
        ]);

        // Verify the user owns this message (authorization check)
        $message = UserOpenaiChatMessage::where('id', $request->message_id)
            ->whereHas('chat', function ($q) {
                $q->where('user_id', Auth::id());
            })
            ->firstOrFail();

        // Check if we already have cached details for this entity + message
        $cached = ChatEntityHighlight::where('message_id', $message->id)->first();

        if ($cached) {
            $existingEntity = collect($cached->entities)
                ->firstWhere('text', $request->entity_text);

            if ($existingEntity && ! empty($existingEntity['detail'])) {
                return response()->json($existingEntity['detail']);
            }
        }

        // Build richer context from the actual message so ambiguous entities
        $messageContext = trim(
            ($message->input ? "User question: {$message->input}\n" : '')
            . ($message->output ? 'AI response: ' . mb_substr($message->output, 0, 1000) : '')
        ) ?: $request->context;

        // Fetch fresh details (AI call only — image is deferred to frontend)
        $details = EntityDetailService::fetchDetails(
            $request->entity_text,
            $request->entity_type,
            $messageContext
        );

        // Cache the detail in the entity record to avoid future AI calls for the same entity
        if ($cached) {
            $entities = $cached->entities;
            foreach ($entities as &$entity) {
                if ($entity['text'] === $request->entity_text) {
                    $entity['detail'] = $details;

                    break;
                }
            }
            $cached->update(['entities' => $entities]);
        }

        return response()->json($details);
    }

    /**
     * Fetch an image for an entity detail (called lazily by frontend after drawer renders).
     * Caches the image URL in the entity record so it's only fetched once.
     */
    public function image(Request $request): JsonResponse
    {
        $request->validate([
            'query'       => 'required|string|max:300',
            'message_id'  => 'nullable|integer',
            'entity_text' => 'nullable|string|max:200',
        ]);

        $messageId = $request->input('message_id');
        $entityText = $request->input('entity_text');

        // Check if we already have a cached image URL for this entity
        if ($messageId && $entityText) {
            $cached = ChatEntityHighlight::where('message_id', $messageId)->first();
            if ($cached) {
                $existingEntity = collect($cached->entities)->firstWhere('text', $entityText);
                if (! empty($existingEntity['detail']['image_url'])) {
                    return response()->json(['image_url' => $existingEntity['detail']['image_url']]);
                }
            }
        }

        $imageData = EntityDetailService::fetchEntityImage($request->input('query'));

        $imageUrl = $imageData['image_url'] ?? null;
        $imageSource = $imageData['source'] ?? '';
        $imageDomain = $imageData['domain'] ?? '';
        $imageLink = $imageData['link'] ?? '';

        // Cache the image URL in the entity detail record
        if ($imageUrl && $messageId && $entityText) {
            $record = ChatEntityHighlight::where('message_id', $messageId)->first();
            if ($record) {
                $entities = $record->entities;
                foreach ($entities as &$entity) {
                    if ($entity['text'] === $entityText && ! empty($entity['detail'])) {
                        $entity['detail']['image_url'] = $imageUrl;
                        $entity['detail']['image_source'] = $imageSource;
                        $entity['detail']['image_domain'] = $imageDomain;
                        $entity['detail']['image_link'] = $imageLink;

                        break;
                    }
                }
                $record->update(['entities' => $entities]);
            }
        }

        return response()->json([
            'image_url'    => $imageUrl,
            'image_source' => $imageSource,
            'image_domain' => $imageDomain,
            'image_link'   => $imageLink,
        ]);
    }
}
