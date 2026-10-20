<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity as EntityDriver;
use App\Domains\Entity\Models\Entity;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIChatPro\System\Models\AgentMessageMention;
use App\Extensions\AIChatPro\System\Models\AgentResourceShare;
use App\Enums\AITokenType;
use App\Extensions\AIChatPro\System\Services\AIChatProStorageQuotaService;
use App\Extensions\AIChatProSkills\System\Models\Skill;
use App\Extensions\AiVideoPro\System\Http\Controllers\AiVideoProController;
use App\Extensions\AiVideoPro\System\Models\UserFall;
use App\Extensions\AiVideoPro\System\Services\ModelConfigurationService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use App\Models\Favourite;
use App\Models\Plan;
use App\Models\Prompt;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Models\User;
use App\Models\UserCollaborationRequest;
use App\Models\UserFollow;
use App\Models\UserOpenaiChatMessage;
use App\Models\UserOpenaiChat;
use App\Models\WorkspaceMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class ReactChatController extends Controller
{
    private ?Collection $modelDescriptorCache = null;
    private ?Plan $activePlanCache = null;
    private bool $activePlanResolved = false;

    public function index(?string $slug = null): View
    {
        [$entryScriptUrl, $styleUrls, $viteDevServer, $assetsMissing] =
            $this->resolveFrontendAssets();

        return view('ai-chat-pro::react-chat', [
            'entryScriptUrl' => $entryScriptUrl,
            'styleUrls'      => $styleUrls,
            'viteDevServer'  => $viteDevServer,
            'assetsMissing'  => $assetsMissing,
            'isAgentModePage' => request()->routeIs('agents'),
            'slug'           => $slug,
            'brand'          => $this->brandAssets(),
            'bootstrap'      => $this->bootstrapPayload(),
            'apiBaseUrl'     => rtrim((string) url('/'), '/'),
        ]);
    }

    public function account(): JsonResponse
    {
        return response()
            ->json($this->buildAccountPayload())
            ->header('Cache-Control', 'no-store');
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $validated = $request->validate([
            'theme'                   => ['sometimes', 'required', 'in:light,dark,system'],
            'language'                => ['sometimes', 'required', 'in:English,Somali'],
            'defaultMode'             => ['sometimes', 'required', 'in:chat,agents'],
            'defaultModel'            => ['sometimes', 'required', 'string', 'max:255'],
            'reducedMotion'           => ['sometimes', 'required', 'boolean'],
            'responseTraits'          => ['sometimes', 'required', 'array'],
            'responseTraits.*'        => ['string', 'max:80'],
            'customInstructions'      => ['sometimes', 'required', 'string', 'max:5000'],
            'notifications'           => ['sometimes', 'required', 'array'],
            'notifications.tasks'     => ['sometimes', 'required', 'boolean'],
            'notifications.artifacts' => ['sometimes', 'required', 'boolean'],
            'notifications.tips'      => ['sometimes', 'required', 'boolean'],
        ]);

        $preferences = array_replace_recursive(
            $this->defaultAccountPreferences(),
            $this->storedAccountPreferences($user),
            $validated,
        );
        $serializedPreferences = json_encode($preferences, JSON_THROW_ON_ERROR);
        $preferenceRow = DB::table('ext_ai_chat_pro_preferences')
            ->where('user_id', $user->id);

        if ($preferenceRow->exists()) {
            $preferenceRow->update([
                'preferences' => $serializedPreferences,
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('ext_ai_chat_pro_preferences')->insert([
                'user_id'     => $user->id,
                'preferences' => $serializedPreferences,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return response()
            ->json($preferences)
            ->header('Cache-Control', 'no-store');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if ($request->exists('username') && is_string($request->input('username'))) {
            $username = trim($request->input('username'));
            $request->merge(['username' => $username === '' ? null : Str::lower($username)]);
        }
        foreach (['allowMentions', 'allowCollaborationRequests'] as $booleanField) {
            $value = $request->input($booleanField);
            if (is_string($value)) {
                $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($parsed !== null) {
                    $request->merge([$booleanField => $parsed]);
                }
            }
        }

        $validated = $request->validate([
            'name'                         => ['required', 'string', 'max:255'],
            'surname'                      => ['required', 'string', 'max:255'],
            'phone'                        => ['nullable', 'string', 'max:15'],
            'country'                      => ['nullable', 'string', 'max:255'],
            'state'                        => ['nullable', 'string', 'max:255'],
            'city'                         => ['nullable', 'string', 'max:255'],
            'postal'                       => ['nullable', 'string', 'max:255'],
            'address'                      => ['nullable', 'string', 'max:255'],
            'username'                     => ['nullable', 'string', 'min:2', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,public_username,' . $user->id],
            'profileVisibility'            => ['nullable', 'in:public,private,connections'],
            'bio'                          => ['nullable', 'string', 'max:500'],
            'website'                      => ['nullable', 'url:http,https'],
            'allowMentions'                => ['nullable', 'boolean'],
            'allowCollaborationRequests'   => ['nullable', 'boolean'],
            'old_password'                 => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password'                 => ['nullable', 'required_with:old_password', Password::defaults(), 'confirmed'],
            'avatar'                       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        foreach (['name', 'surname', 'phone', 'country', 'state', 'city', 'postal', 'address'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('username', $validated)) {
            $user->public_username = $validated['username'] !== null ? trim((string) $validated['username']) : null;
            if ($user->public_username !== null) {
                $user->public_username = Str::lower($user->public_username);
            }
        }

        if (array_key_exists('profileVisibility', $validated)) {
            $user->profile_visibility = $validated['profileVisibility'] ?? 'private';
        }

        if (array_key_exists('bio', $validated)) {
            $user->bio = $validated['bio'];
        }

        if (array_key_exists('website', $validated)) {
            $user->website = $validated['website'];
        }

        if (array_key_exists('allowMentions', $validated)) {
            $user->allow_mentions = (bool) $validated['allowMentions'];
        }

        if (array_key_exists('allowCollaborationRequests', $validated)) {
            $user->allow_collaboration_requests = (bool) $validated['allowCollaborationRequests'];
        }

        if (! empty($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
        }

        if ($request->hasFile('avatar')) {
            $path = 'upload/images/avatar';
            $directory = public_path($path);
            File::ensureDirectoryExists($directory);
            $image = $request->file('avatar');
            $imageName = Str::random(4) . '-' . Str::slug($user->fullName()) . '-avatar.' . $image->extension();
            $image->move($directory, $imageName);
            $user->avatar = $path . '/' . $imageName;
        }

        $user->save();

        return response()
            ->json([
                'user'    => $this->accountUserPayload($user->fresh()),
                'message' => 'Profile updated successfully.',
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function showPublicProfile(string $username): JsonResponse
    {
        $user = User::query()->where('public_username', Str::lower($username))->firstOrFail();
        $viewer = Auth::user();

        if (! $user->canViewProfile($viewer)) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        return response()->json([
            'user' => [
                'id'                         => (string) $user->id,
                'name'                       => $this->displayName($user),
                'username'                   => $user->public_username,
                'profileVisibility'          => $user->profile_visibility ?? 'private',
                'bio'                        => (string) ($user->bio ?? ''),
                'website'                    => (string) ($user->website ?? ''),
                'avatarUrl'                  => $this->avatarUrl($user),
                'allowMentions'              => (bool) ($user->allow_mentions ?? true),
                'allowCollaborationRequests' => (bool) ($user->allow_collaboration_requests ?? true),
                'followersCount'             => $user->followers()->count(),
                'followingCount'             => $user->follows()->count(),
                'isFollowing'                => $viewer ? $viewer->isFollowing($user) : false,
            ],
        ]);
    }

    public function toggleFollow(Request $request, int $userId): JsonResponse
    {
        /** @var User $viewer */
        $viewer = Auth::user();

        if (! $viewer) {
            return response()->json(['message' => 'Sign in to follow people.'], 401);
        }

        if ($viewer->id === $userId) {
            return response()->json(['message' => 'You cannot follow yourself.'], 422);
        }

        $target = User::query()->find($userId);
        if (! $target) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        if (! $target->canViewProfile($viewer)) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        $followQuery = UserFollow::query()
            ->where('follower_id', $viewer->id)
            ->where('followed_user_id', $target->id);

        if ($request->isMethod('DELETE')) {
            $followQuery->delete();

            return response()->json([
                'following'      => false,
                'followersCount' => $target->followers()->count(),
            ]);
        }

        UserFollow::query()->firstOrCreate([
            'follower_id'      => $viewer->id,
            'followed_user_id' => $target->id,
        ]);

        return response()->json([
            'following'      => true,
            'followersCount' => $target->followers()->count(),
        ]);
    }

    public function storeMention(Request $request): JsonResponse
    {
        /** @var User $sender */
        $sender = Auth::user();
        $validated = $request->validate([
            'userId'   => ['nullable', 'integer', 'min:1', 'required_without:username'],
            'username' => ['nullable', 'string', 'min:2', 'max:30', 'required_without:userId'],
            'message'  => ['required', 'string', 'max:1000'],
        ]);

        $recipient = isset($validated['userId'])
            ? User::query()->find($validated['userId'])
            : User::query()
                ->where('public_username', Str::lower($validated['username']))
                ->first();

        if (! $recipient || ! $recipient->canViewProfile($sender)) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        if ($recipient->id === $sender->id) {
            return response()->json(['message' => 'You cannot mention yourself.'], 422);
        }

        if (! $recipient->allow_mentions) {
            return response()->json(['message' => 'This user does not accept mentions.'], 403);
        }

        if (! $sender->public_username) {
            return response()->json(['message' => 'Set a public username before sending a mention.'], 422);
        }

        $notification = $this->createAgentNotification(
            $recipient,
            'mention',
            '@' . $sender->public_username . ' mentioned you',
            $validated['message'],
            $sender,
        );

        return response()->json($this->notificationPayload($notification), 201);
    }

    public function searchMentionUsers(Request $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = Auth::user();
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:40'],
        ]);

        $term = addcslashes(Str::lower(trim($validated['q'])), '\\%_');
        $users = User::query()
            ->where('id', '!=', $viewer->id)
            ->whereNotNull('public_username')
            ->where('allow_mentions', true)
            ->where('profile_visibility', 'public')
            ->where(function ($query) use ($term): void {
                $query->whereRaw('LOWER(public_username) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(surname) LIKE ?', ['%' . $term . '%']);
            })
            ->orderBy('public_username')
            ->limit(12)
            ->get(['id', 'name', 'surname', 'public_username', 'profile_visibility', 'avatar'])
            ->filter(fn (User $user): bool => $user->canViewProfile($viewer))
            ->map(fn (User $user): array => [
                'id'        => (string) $user->id,
                'name'      => $this->displayName($user),
                'username'  => (string) $user->public_username,
                'avatarUrl' => $this->avatarUrl($user),
            ])
            ->values()
            ->all();

        return response()
            ->json(['data' => $users])
            ->header('Cache-Control', 'no-store');
    }

    public function storeAgentMentions(Request $request): JsonResponse
    {
        /** @var User $sender */
        $sender = Auth::user();
        $validated = $request->validate([
            'messageId'    => ['required', 'integer', 'min:1'],
            'recipientIds' => ['required', 'array', 'min:1', 'max:12'],
            'recipientIds.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $message = UserOpenaiChatMessage::query()
            ->whereKey($validated['messageId'])
            ->where('user_id', $sender->id)
            ->firstOrFail();
        $chat = UserOpenaiChat::query()
            ->whereKey($message->user_openai_chat_id)
            ->where('user_id', $sender->id)
            ->firstOrFail();
        abort_unless(
            DB::table('ext_ai_chat_pro_agent_conversations')
                ->where('chat_id', $chat->id)
                ->where('user_id', $sender->id)
                ->exists(),
            404,
        );

        $content = (string) ($message->input ?? '');
        $recipients = app(\App\Extensions\AIChatPro\System\Services\AgentMessageMentionService::class)
            ->validateRecipients($sender, $chat, $content, $validated['recipientIds']);
        app(\App\Extensions\AIChatPro\System\Services\AgentMessageMentionService::class)
            ->persist($sender, $chat, $message, $recipients);

        return response()->json(['saved' => true], 201);
    }

    public function agentMessageMentions(Request $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = Auth::user();
        $validated = $request->validate([
            'message_ids' => ['required', 'string', 'max:4000'],
        ]);
        $messageIds = collect(explode(',', $validated['message_ids']))
            ->map(fn (string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(100)
            ->values();

        if ($messageIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $mentions = AgentMessageMention::query()
            ->join('ext_ai_chat_pro_agent_conversations as agent_chats', 'agent_chats.chat_id', '=', 'ext_ai_chat_pro_message_mentions.chat_id')
            ->where('agent_chats.user_id', $viewer->id)
            ->whereIn('ext_ai_chat_pro_message_mentions.message_id', $messageIds)
            ->orderBy('ext_ai_chat_pro_message_mentions.id')
            ->get([
                'ext_ai_chat_pro_message_mentions.message_id',
                'ext_ai_chat_pro_message_mentions.recipient_id',
                'ext_ai_chat_pro_message_mentions.recipient_username',
            ])
            ->groupBy('message_id')
            ->map(fn (Collection $items): array => $items->map(fn ($mention): array => [
                'userId'   => $mention->recipient_id !== null ? (string) $mention->recipient_id : null,
                'username' => (string) $mention->recipient_username,
            ])->all());

        return response()->json(['data' => $mentions])
            ->header('Cache-Control', 'no-store');
    }

    public function searchAgentShareRecipients(Request $request): JsonResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        $validated = $request->validate([
            'q'            => ['required', 'string', 'min:2', 'max:40'],
            'resourceType' => ['required', 'in:message,conversation,task'],
            'resourceId'   => ['required', 'integer', 'min:1'],
            'resourcePart' => ['sometimes', 'in:user,assistant'],
        ]);
        $resource = $this->resolveAgentShareResource(
            $validated['resourceType'],
            (int) $validated['resourceId'],
            $owner,
            $request->input('resourcePart', 'assistant'),
        );
        $workspaceId = $resource['workspaceId'];
        $term = addcslashes(Str::lower(trim($validated['q'])), '\\%_');
        $users = User::query()
            ->where('id', '!=', $owner->id)
            ->whereNotNull('public_username')
            ->where('profile_visibility', 'public')
            ->where(function ($query) use ($term): void {
                $query->whereRaw('LOWER(public_username) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(surname) LIKE ?', ['%' . $term . '%']);
            })
            ->orderBy('public_username')
            ->limit(12)
            ->get(['id', 'name', 'surname', 'public_username', 'profile_visibility', 'avatar'])
            ->filter(function (User $recipient) use ($owner, $workspaceId): bool {
                return $recipient->canViewProfile($owner)
                    && ($workspaceId === null
                        || $recipient->hasWorkspaceRole('team', $workspaceId));
            })
            ->map(fn (User $recipient): array => [
                'id'        => (string) $recipient->id,
                'name'      => $this->displayName($recipient),
                'username'  => (string) $recipient->public_username,
                'avatarUrl' => $this->avatarUrl($recipient),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $users])
            ->header('Cache-Control', 'no-store');
    }

    public function storeAgentShare(Request $request): JsonResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        $validated = $request->validate([
            'resourceType' => ['required', 'in:message,conversation,task'],
            'resourceId'   => ['required', 'integer', 'min:1'],
            'resourcePart' => ['sometimes', 'in:user,assistant,item'],
            'recipientId'  => ['required', 'integer', 'min:1'],
            'accessLevel'  => ['required', 'in:view'],
        ]);
        $resourceType = $validated['resourceType'];
        $resourceId = (int) $validated['resourceId'];
        $resourcePart = $resourceType === 'message'
            ? ($validated['resourcePart'] ?? 'assistant')
            : 'item';
        $resource = $this->resolveAgentShareResource(
            $resourceType,
            $resourceId,
            $owner,
            $resourcePart,
        );
        $recipient = User::query()->find($validated['recipientId']);
        if (! $recipient || $recipient->id === $owner->id || ! $recipient->canViewProfile($owner)) {
            return response()->json(['message' => 'The selected recipient is unavailable.'], 404);
        }
        if ($resource['workspaceId'] !== null
            && ! $recipient->hasWorkspaceRole('team', $resource['workspaceId'])) {
            return response()->json(['message' => 'The recipient is not an active member of this workspace.'], 403);
        }

        $existing = AgentResourceShare::query()
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('resource_part', $resourcePart)
            ->where('owner_id', $owner->id)
            ->where('recipient_id', $recipient->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
        if ($existing) {
            return response()->json(['message' => 'This resource is already shared with the selected recipient.'], 409);
        }

        $token = Str::random(64);
        $share = DB::transaction(function () use (
            $owner,
            $recipient,
            $resourceType,
            $resourceId,
            $resourcePart,
            $resource,
            $token,
        ): AgentResourceShare {
            $share = AgentResourceShare::query()->create([
                'id'            => (string) Str::uuid(),
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'resource_part' => $resourcePart,
                'chat_id'       => $resource['chatId'],
                'owner_id'      => $owner->id,
                'recipient_id'  => $recipient->id,
                'access_level'  => 'view',
                'token_hash'    => hash('sha256', $token),
                'expires_at'    => now()->addDays(30),
            ]);
            $link = route('agents') . '?share=' . urlencode($token);
            $recipient->notifications()->create([
                'id'   => (string) Str::uuid(),
                'type' => 'agent_social',
                'data' => [
                    'type'          => 'resource_shared',
                    'title'         => 'An Agent resource was shared with you',
                    'detail'        => $owner->public_username
                        ? '@' . $owner->public_username . ' shared an Agent ' . $resourceType . ' with you.'
                        : $owner->fullName() . ' shared an Agent ' . $resourceType . ' with you.',
                    'actorId'       => (string) $owner->id,
                    'actorUsername' => $owner->public_username,
                    'resourceUrl'   => $link,
                    'shareId'       => (string) $share->id,
                ],
            ]);

            return $share;
        });

        return response()->json([
            'id'          => (string) $share->id,
            'resourceType' => $resourceType,
            'resourceId'  => (string) $resourceId,
            'recipientId' => (string) $recipient->id,
            'recipient'   => $this->displayName($recipient),
            'accessLevel' => 'view',
            'status'      => 'active',
            'createdAt'   => $share->created_at?->toISOString(),
            'expiresAt'   => $share->expires_at?->toISOString(),
            'url'         => route('agents') . '?share=' . urlencode($token),
        ], 201);
    }

    public function listAgentShares(Request $request): JsonResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        $validated = $request->validate([
            'resourceType' => ['required', 'in:message,conversation,task'],
            'resourceId'   => ['required', 'integer', 'min:1'],
            'resourcePart' => ['sometimes', 'in:user,assistant,item'],
        ]);
        $resourcePart = $validated['resourceType'] === 'message'
            ? ($validated['resourcePart'] ?? 'assistant')
            : 'item';
        $this->resolveAgentShareResource(
            $validated['resourceType'],
            (int) $validated['resourceId'],
            $owner,
            $resourcePart,
        );
        $shares = AgentResourceShare::query()
            ->with('recipient:id,name,surname,public_username')
            ->where('owner_id', $owner->id)
            ->where('resource_type', $validated['resourceType'])
            ->where('resource_id', $validated['resourceId'])
            ->where('resource_part', $resourcePart)
            ->latest()
            ->get()
            ->map(fn (AgentResourceShare $share): array => [
                'id'          => (string) $share->id,
                'recipientId' => (string) $share->recipient_id,
                'recipient'   => $share->recipient
                    ? $this->displayName($share->recipient)
                    : 'Unavailable user',
                'accessLevel' => $share->access_level,
                'status'      => $share->revoked_at
                    ? 'revoked'
                    : ($share->expires_at?->isPast() ? 'expired' : 'active'),
                'createdAt'   => $share->created_at?->toISOString(),
                'revokedAt'   => $share->revoked_at?->toISOString(),
                'expiresAt'   => $share->expires_at?->toISOString(),
            ]);

        return response()->json(['data' => $shares]);
    }

    public function revokeAgentShare(string $shareId): JsonResponse
    {
        /** @var User $owner */
        $owner = Auth::user();
        $share = AgentResourceShare::query()
            ->whereKey($shareId)
            ->where('owner_id', $owner->id)
            ->firstOrFail();
        if (! $share->revoked_at) {
            $share->forceFill(['revoked_at' => now()])->save();
        }

        return response()->json([
            'id'     => (string) $share->id,
            'status' => 'revoked',
        ]);
    }

    public function showAgentShare(string $token): JsonResponse
    {
        /** @var User $recipient */
        $recipient = Auth::user();
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);
        $share = AgentResourceShare::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('recipient_id', $recipient->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();
        $owner = User::query()->find($share->owner_id);
        abort_unless($owner, 404);
        $resource = $this->resolveAgentShareResource(
            $share->resource_type,
            (int) $share->resource_id,
            $owner,
            $share->resource_part,
        );
        abort_unless(
            $resource['workspaceId'] === null
                || $recipient->hasWorkspaceRole('team', $resource['workspaceId']),
            404,
        );

        return response()->json([
            'resourceType' => $share->resource_type,
            'resource'     => $resource['payload'],
            'owner'        => [
                'id'       => (string) $owner->id,
                'name'     => $this->displayName($owner),
                'username' => $owner->public_username,
            ],
            'accessLevel'  => $share->access_level,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * @return array{chatId: int|null, workspaceId: int|null, payload: array<string, mixed>}
     */
    private function resolveAgentShareResource(
        string $resourceType,
        int $resourceId,
        User $owner,
        string $resourcePart = 'assistant',
    ): array {
        if ($resourceType === 'task') {
            abort_unless(class_exists(AIAgentWorkflowRun::class) && Schema::hasTable('ext_ai_agent_workflow_runs'), 404);
            $run = AIAgentWorkflowRun::query()
                ->with(['workflow:id,user_id,name', 'steps'])
                ->whereKey($resourceId)
                ->whereHas('workflow', fn ($query) => $query->where('user_id', $owner->id))
                ->firstOrFail();
            return [
                'chatId'      => null,
                'workspaceId' => null,
                'payload'     => [
                    'id'           => (string) $run->id,
                    'type'         => 'task',
                    'name'         => (string) ($run->workflow?->name ?? 'Agent task'),
                    'status'       => $run->status?->value ?? (string) $run->status,
                    'startedAt'    => $run->started_at?->toISOString(),
                    'completedAt'  => $run->completed_at?->toISOString(),
                    'steps'        => $run->steps->map(fn ($step): array => [
                        'index'  => (int) $step->step_index,
                        'type'   => (string) $step->type,
                        'status' => (string) $step->status,
                    ])->values()->all(),
                    'deliverables' => [],
                ],
            ];
        }

        $chatId = $resourceType === 'conversation'
            ? $resourceId
            : (int) UserOpenaiChatMessage::query()->whereKey($resourceId)->value('user_openai_chat_id');
        abort_unless($chatId > 0, 404);
        $agentChat = DB::table('ext_ai_chat_pro_agent_conversations')
            ->where('chat_id', $chatId)
            ->where('user_id', $owner->id)
            ->first();
        abort_unless($agentChat, 404);
        $chat = UserOpenaiChat::query()->whereKey($chatId)->firstOrFail();
        if ($resourceType === 'message') {
            $message = UserOpenaiChatMessage::query()
                ->whereKey($resourceId)
                ->where('user_openai_chat_id', $chatId)
                ->where('user_id', $owner->id)
                ->firstOrFail();
            $text = $resourcePart === 'user'
                ? (string) ($message->input ?? '')
                : (string) ($message->output ?? $message->response ?? '');
            abort_if($text === '', 404);
            $payload = [
                'id'        => (string) $message->id,
                'type'      => 'message',
                'part'      => $resourcePart,
                'role'      => $resourcePart,
                'content'   => $text,
                'createdAt' => $message->created_at?->toISOString(),
            ];
        } else {
            $messages = UserOpenaiChatMessage::query()
                ->where('user_openai_chat_id', $chatId)
                ->where('response', '!=', 'First Initiation')
                ->oldest('id')
                ->get(['id', 'user_id', 'input', 'output', 'response', 'created_at'])
                ->flatMap(function (UserOpenaiChatMessage $message) use ($owner): array {
                    $items = [];
                    if (trim((string) $message->input) !== '') {
                        $items[] = [
                            'id'        => (string) $message->id . ':user',
                            'role'      => 'user',
                            'author'    => (string) $owner->id === (string) $message->user_id
                                ? $this->displayName($owner)
                                : 'Participant',
                            'content'   => (string) $message->input,
                            'createdAt' => $message->created_at?->toISOString(),
                        ];
                    }
                    $answer = (string) ($message->output ?? $message->response ?? '');
                    if (trim($answer) !== '') {
                        $items[] = [
                            'id'        => (string) $message->id . ':assistant',
                            'role'      => 'assistant',
                            'author'    => 'Garad AI',
                            'content'   => $answer,
                            'createdAt' => $message->updated_at?->toISOString(),
                        ];
                    }

                    return $items;
                })
                ->values()
                ->all();
            $payload = [
                'id'       => (string) $chat->id,
                'type'     => 'conversation',
                'title'    => (string) ($chat->title ?? 'Shared Agent conversation'),
                'messages' => $messages,
            ];
        }

        $workspaceId = Schema::hasColumn($chat->getTable(), 'team_id') && $chat->team_id
            ? (int) $chat->team_id
            : null;

        return [
            'chatId'      => (int) $chat->id,
            'workspaceId' => $workspaceId,
            'payload'     => $payload,
        ];
    }

    public function storeCollaborationRequest(Request $request, int $userId): JsonResponse
    {
        /** @var User $requester */
        $requester = Auth::user();
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        if ($requester->id === $userId) {
            return response()->json(['message' => 'You cannot request collaboration with yourself.'], 422);
        }

        $recipient = User::query()->find($userId);
        if (! $recipient || ! $recipient->canViewProfile($requester)) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        if (! $recipient->allow_collaboration_requests) {
            return response()->json(['message' => 'This user does not accept collaboration requests.'], 403);
        }

        $pendingKey = $requester->id . ':' . $recipient->id;

        try {
            [$collaborationRequest, $created] = DB::transaction(function () use ($requester, $recipient, $validated, $pendingKey): array {
                $existing = UserCollaborationRequest::query()
                    ->where('pending_key', $pendingKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return [$existing, false];
                }

                return [UserCollaborationRequest::query()->create([
                    'requester_id' => $requester->id,
                    'recipient_id' => $recipient->id,
                    'message'      => $validated['message'],
                    'status'       => 'pending',
                    'pending_key'  => $pendingKey,
                ]), true];
            });
        } catch (QueryException $exception) {
            $existing = UserCollaborationRequest::query()
                ->where('pending_key', $pendingKey)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return response()->json([
                'message'   => 'A collaboration request is already pending.',
                'requestId' => (string) $existing->id,
                'status'    => $existing->status,
            ], 409);
        }

        if (! $created) {
            return response()->json([
                'message'   => 'A collaboration request is already pending.',
                'requestId' => (string) $collaborationRequest->id,
                'status'    => $collaborationRequest->status,
            ], 409);
        }

        $requesterLabel = $requester->public_username
            ? '@' . $requester->public_username
            : $this->displayName($requester);
        $notification = $this->createAgentNotification(
            $recipient,
            'collaboration_request',
            'Collaboration request from ' . $requesterLabel,
            $validated['message'],
            $requester,
            ['collaborationRequestId' => (string) $collaborationRequest->id],
        );

        return response()->json([
            'requestId'      => (string) $collaborationRequest->id,
            'status'         => $collaborationRequest->status,
            'notificationId' => (string) $notification->id,
        ], 201);
    }

    public function decideCollaborationRequest(Request $request, int $requestId): JsonResponse
    {
        /** @var User $recipient */
        $recipient = Auth::user();
        $validated = $request->validate([
            'decision' => ['required', 'in:accepted,declined'],
        ]);

        $collaborationRequest = DB::transaction(function () use ($recipient, $requestId, $validated): UserCollaborationRequest {
            $collaborationRequest = UserCollaborationRequest::query()
                ->lockForUpdate()
                ->findOrFail($requestId);

            abort_unless($collaborationRequest->recipient_id === $recipient->id, 403);
            abort_unless($collaborationRequest->status === 'pending', 409, 'This collaboration request has already been decided.');

            $collaborationRequest->forceFill([
                'status'      => $validated['decision'],
                'pending_key' => null,
                'decision_by' => $recipient->id,
                'decided_at'  => now(),
            ])->save();

            return $collaborationRequest;
        });

        $requester = $collaborationRequest->requester;
        $requestNotification = $recipient->notifications()
            ->where('data->collaborationRequestId', (string) $collaborationRequest->id)
            ->first();
        if ($requestNotification) {
            $requestNotification->forceFill([
                'data' => array_merge((array) $requestNotification->data, [
                    'decision' => $collaborationRequest->status,
                ]),
                'read_at' => now(),
            ])->save();
        }

        $recipientLabel = $recipient->public_username
            ? '@' . $recipient->public_username
            : $this->displayName($recipient);
        $this->createAgentNotification(
            $requester,
            'collaboration_response',
            'Collaboration request ' . $collaborationRequest->status,
            $recipientLabel . ' ' . $collaborationRequest->status . ' your collaboration request.',
            $recipient,
            [
                'collaborationRequestId' => (string) $collaborationRequest->id,
                'decision'               => $collaborationRequest->status,
            ],
        );

        return response()->json([
            'requestId' => (string) $collaborationRequest->id,
            'status'    => $collaborationRequest->status,
            'decidedAt' => $collaborationRequest->decided_at?->toISOString(),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if ($request->has('unread')) {
            $request->merge([
                'unread' => filter_var($request->input('unread'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
        $validated = $request->validate([
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'unread'   => ['sometimes', 'boolean'],
            'type'     => ['sometimes', 'string', 'max:80'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $query = $user->notifications()->latest();

        if (($validated['unread'] ?? false) === true) {
            $query->whereNull('read_at');
        }

        if (isset($validated['type'])) {
            $query->where('data->type', $validated['type']);
        }

        $items = $query
            ->forPage((int) ($validated['page'] ?? 1), $perPage)
            ->get()
            ->map(fn ($notification): array => $this->notificationPayload($notification))
            ->all();

        return response()->json($items);
    }

    public function updateNotification(Request $request, string $notificationId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $validated = $request->validate(['read' => ['required', 'boolean']]);
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->forceFill([
            'read_at' => $validated['read'] ? now() : null,
        ])->save();

        return response()->json($this->notificationPayload($notification->fresh()));
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['updated' => true]);
    }

    public function deleteNotification(string $notificationId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->delete();

        return response()->json(['deleted' => true]);
    }

    private function createAgentNotification(
        User $recipient,
        string $type,
        string $title,
        string $detail,
        User $actor,
        array $extra = [],
    ): DatabaseNotification {
        return $recipient->notifications()->create([
            'id'   => (string) Str::uuid(),
            'type' => 'agent_social',
            'data' => array_merge([
                'type'          => $type,
                'title'         => $title,
                'detail'        => $detail,
                'actorId'       => (string) $actor->id,
                'actorUsername' => $actor->public_username,
                'actorName'     => $this->displayName($actor),
            ], $extra),
        ]);
    }

    private function notificationPayload(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $supportedTypes = [
            'agent_completed',
            'scheduled_task_completed',
            'task_failed',
            'resource_shared',
            'approval_required',
            'plugin_disconnected',
            'file_ready',
            'usage_warning',
            'mention',
            'collaboration_request',
            'collaboration_response',
            'workspace_invitation',
        ];

        return [
            'id'   => (string) $notification->id,
            'type' => in_array($data['type'] ?? null, $supportedTypes, true)
                ? $data['type']
                : 'agent_completed',
            'title'                  => (string) ($data['title'] ?? 'Notification'),
            'detail'                 => (string) ($data['detail'] ?? $data['message'] ?? ''),
            'read'                   => $notification->read_at !== null,
            'createdAt'              => $notification->created_at?->toISOString(),
            'actorId'                => isset($data['actorId']) ? (string) $data['actorId'] : null,
            'actorUsername'          => isset($data['actorUsername']) ? (string) $data['actorUsername'] : null,
            'collaborationRequestId' => isset($data['collaborationRequestId'])
                ? (string) $data['collaborationRequestId']
                : null,
            'teamInvitationId' => isset($data['teamInvitationId'])
                ? (string) $data['teamInvitationId']
                : null,
            'workspaceId'   => isset($data['workspaceId']) ? (string) $data['workspaceId'] : null,
            'workspaceName' => isset($data['workspaceName']) ? (string) $data['workspaceName'] : null,
            'decision'      => isset($data['decision']) ? (string) $data['decision'] : null,
            'resourceUrl'   => isset($data['resourceUrl']) ? (string) $data['resourceUrl'] : null,
        ];
    }

    public function workspaceMemberships(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json(
            $user->workspaceMemberships()->get()->map(fn (WorkspaceMembership $membership) => [
                'id'            => (string) $membership->id,
                'workspaceType' => $membership->workspace_type,
                'workspaceId'   => (string) $membership->workspace_id,
                'role'          => $membership->role,
                'status'        => $membership->status,
                'permissions'   => $membership->permissions ?? [],
            ])->all()
        );
    }

    public function usage(): JsonResponse
    {
        return response()
            ->json($this->buildUsagePayload())
            ->header('Cache-Control', 'no-store');
    }

    public function subscription(): JsonResponse
    {
        return response()
            ->json($this->buildSubscriptionPayload())
            ->header('Cache-Control', 'no-store');
    }

    public function models(): JsonResponse
    {
        return response()
            ->json($this->modelDescriptors()->values()->all())
            ->header('Cache-Control', 'no-store');
    }

    public function imageProxy(Request $request)
    {
        $url = (string) $request->query('url', '');
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        $allowedHosts = array_filter([
            'image.garad.ai',
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            strtolower((string) parse_url((string) config('filesystems.disks.r2.url'), PHP_URL_HOST)),
        ]);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! in_array($host, $allowedHosts, true)) {
            return response()->json(['message' => 'Image host is not allowed.'], 422);
        }

        try {
            $upstream = Http::timeout(30)->get($url);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Unable to fetch image.'], 502);
        }

        $contentType = strtolower((string) $upstream->header('Content-Type'));
        if (! $upstream->successful() || ! str_starts_with($contentType, 'image/')) {
            return response()->json(['message' => 'Image not available.'], 404);
        }

        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) ($parts['path'] ?? 'image.png'))) ?: 'image.png';
        $headers = [
            'Content-Type'           => $contentType,
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="' . $name . '"';
        }

        return response($upstream->body(), 200, $headers);
    }

    public function imageModels(): JsonResponse
    {
        $rows = Entity::query()
            ->isEnabled()
            ->whereHas('tokens', function ($query) {
                $query->where('type', AITokenType::IMAGE->value);
            })
            ->with('tokens')
            ->get()
            ->map(function (Entity $record) {
                $enum = $this->entityEnumFromModel($record);
                if (! $enum || $enum->tokenType() !== AITokenType::IMAGE) {
                    return null;
                }

                $engine = $enum->engine();
                $imageGenerator = match ($engine) {
                    EngineEnum::OPEN_AI          => $enum->value,
                    EngineEnum::FAL_AI           => 'fal-ai',
                    EngineEnum::STABLE_DIFFUSION => 'stable_diffusion',
                    EngineEnum::PI_API           => $enum->value,
                    default                      => null,
                };
                if (! $imageGenerator) {
                    return null;
                }

                if ($engine === EngineEnum::STABLE_DIFFUSION) {
                    $configuredModel = SettingTwo::getCache()->stablediffusion_default_model;
                    $configuredModel = is_object($configuredModel) && isset($configuredModel->value)
                        ? (string) $configuredModel->value
                        : trim((string) $configuredModel);
                    if ($configuredModel !== $enum->value) {
                        return null;
                    }
                }

                $displayName = trim((string) ($record->selected_title ?? $record->title ?? ''));
                if ($displayName === '') {
                    $displayName = (string) $enum->label();
                }

                $promptField = match (true) {
                    $engine === EngineEnum::OPEN_AI                                             => 'stable_description',
                    $engine === EngineEnum::STABLE_DIFFUSION                                    => 'stable_description',
                    $engine === EngineEnum::FAL_AI && $enum === EntityEnum::IDEOGRAM            => 'description_ideogram',
                    $engine === EngineEnum::FAL_AI && str_contains($enum->value, 'nano-banana') => 'description_nano_banana_2',
                    $engine === EngineEnum::FAL_AI && str_contains($enum->value, 'seedream')    => 'description_seedream_v4',
                    $engine === EngineEnum::FAL_AI && str_contains($enum->value, 'flux')        => 'description_flux_pro',
                    default                                                                     => 'description',
                };

                $referenceMode = match (true) {
                    $engine === EngineEnum::STABLE_DIFFUSION                                                      => 'image_src',
                    $engine === EngineEnum::OPEN_AI                                                               => 'image_src_array',
                    in_array($enum, [EntityEnum::FLUX_PRO_KONTEXT, EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI], true) => 'image_src_array',
                    default                                                                                       => null,
                };

                $creditBalance = 0.0;
                $isUnlimited = false;
                $available = true;
                if (Auth::check()) {
                    try {
                        $driver = EntityDriver::driver($enum);
                        $creditBalance = (float) $driver->creditBalance();
                        $isUnlimited = (bool) $driver->isUnlimitedCredit();
                        $available = $isUnlimited || $creditBalance > 0;
                    } catch (Throwable) {
                        $available = true;
                    }
                }

                $provider = Str::of($engine->value)->replace('_', ' ')->title()->toString();

                $lockedPlan = $this->mediaPlanLock($enum->value, $engine->value, 'image');

                return [
                    'id'           => $enum->value,
                    'locked'       => $lockedPlan !== null,
                    'requiredPlan' => $lockedPlan,
                    'displayName'  => $displayName,
                    'description'  => ! Auth::check()
                        ? $provider
                        : ($available
                            ? $provider . ($isUnlimited ? ' • Unlimited credits' : ' • Credits: ' . $this->formatCredit($creditBalance))
                            : $provider . ' • No credits available'),
                    'provider'        => $engine->value,
                    'imageGenerator'  => $imageGenerator,
                    'falAiModel'      => $engine === EngineEnum::FAL_AI ? $enum->value : null,
                    'promptField'     => $promptField,
                    'referenceMode'   => $referenceMode,
                    'planRequirement' => $this->planRequirementForCurrentUser(),
                    'availability'    => ! $available
                        ? 'unavailable'
                        : ($enum->isBetaEntity() ? 'beta' : 'available'),
                ];
            })
            ->filter()
            ->values();

        return response()
            ->json($rows)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Read-only adapter over AI Video Pro's ModelConfigurationService. Every capability
     * returned here comes from that registry (inputs/options), never from this method.
     */
    public function videoModels(): JsonResponse
    {
        if (! MarketplaceHelper::isRegistered('ai-video-pro')) {
            return response()->json(['message' => 'AI Video Pro is not installed or enabled.'], 404);
        }

        $cacheUser = Auth::id() ?? 'guest';
        $activePlanId = $this->activePlanForCurrentUser()?->id ?? 'free';
        $rows = Cache::remember('garad.video-models.v3.' . $cacheUser . '.' . $activePlanId, 90, function () {
            $falConfigured = (bool) ApiHelper::setFalAIKey();
            $rows = [];

            foreach (ModelConfigurationService::getConfig() as $action => $group) {
                if ($action === 'sora') {
                    continue;
                }
                $groupActive = ($group['isActive'] ?? true) !== false;
                foreach (($group['subModels'] ?? []) as $subModel => $sub) {
                    $subActive = ($sub['isActive'] ?? true) !== false;
                    foreach (($sub['features'] ?? []) as $feature => $config) {
                        $inputs = $config['inputs'] ?? [];
                        $imageInputs = array_values(array_filter($inputs, fn ($i) => ($i['type'] ?? '') === 'file' && str_contains((string) ($i['accept'] ?? ''), 'image')));
                        $videoInputs = array_values(array_filter($inputs, fn ($i) => ($i['type'] ?? '') === 'file' && str_contains((string) ($i['accept'] ?? ''), 'video')));
                        $imageRequired = (bool) array_filter($imageInputs, fn ($i) => ! empty($i['required']));
                        $videoRequired = (bool) array_filter($videoInputs, fn ($i) => ! empty($i['required']));

                        $modes = [];
                        if ($videoRequired) {
                            $modes[] = 'video_to_video';
                        } elseif ($imageRequired) {
                            $modes[] = 'image_to_video';
                        } else {
                            $modes[] = 'text_to_video';
                            if ($imageInputs) {
                                $modes[] = 'image_to_video';
                            }
                        }

                        $enum = EntityEnum::tryFrom((string) $feature);
                        $enabled = $groupActive && $subActive;
                        $creditBalance = null;
                        $unlimited = false;
                        $creditBlocked = false;
                        $engine = $enum?->engine()?->value;
                        if ($enum && Auth::check()) {
                            try {
                                $driver = EntityDriver::driver($enum);
                                $unlimited = (bool) $driver->isUnlimitedCredit();
                                $creditBalance = (float) $driver->creditBalance();
                                $creditBlocked = ! $unlimited && $creditBalance <= 0;
                            } catch (Throwable) {
                                $creditBalance = null;
                            }
                        }

                        $lockedPlan = $engine ? $this->mediaPlanLock((string) $feature, $engine, 'video') : null;
                        $configured = $falConfigured && ($action !== 'sora' || $this->soraApiAvailable());
                        $rows[] = [
                            'locked'        => $lockedPlan !== null,
                            'requiredPlan'  => $lockedPlan,
                            'id'            => (string) $feature,
                            'action'        => (string) $action,
                            'subModel'      => (string) $subModel,
                            'feature'       => (string) $feature,
                            'displayName'   => (string) ($config['label'] ?? $enum?->label() ?? $feature),
                            'family'        => (string) ($group['label'] ?? $action),
                            'description'   => (string) ($group['description'] ?? ''),
                            'engine'        => $engine,
                            'modes'         => $modes,
                            'inputs'        => $inputs,
                            'enabled'       => $enabled,
                            'configured'    => $configured,
                            'creditBalance' => $creditBalance,
                            'unlimited'     => $unlimited,
                            'creditBlocked' => $creditBlocked,
                            'availability'  => ! $enabled ? 'disabled' : (! $configured ? 'not_configured' : ($creditBlocked ? 'credit_blocked' : 'available')),
                        ];
                    }
                }
            }

            return $rows;
        });

        return response()->json($rows)->header('Cache-Control', 'no-store');
    }

    public function videoHistory(): JsonResponse
    {
        $rows = UserFall::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn ($v) => $this->videoRow($v))
            ->values();

        return response()->json($rows)->header('Cache-Control', 'no-store');
    }

    public function videoStatus(int $id): JsonResponse
    {
        $result = app(AiVideoProController::class)->videoStatusFromChatPro($id);
        $entry = UserFall::query()->where('user_id', Auth::id())->whereKey($id)->first();
        if (! $entry) {
            return response()->json(['message' => 'Video not found.'], 404);
        }

        $row = $this->videoRow($entry);
        if ($row['state'] !== 'completed' && $row['state'] !== 'failed') {
            $provider = $this->providerQueueStatus($entry);
            if ($provider) {
                $row['providerStatus'] = $provider['status'];
                $row['queuePosition'] = $provider['queue_position'];
                if ($provider['status'] === 'IN_PROGRESS') {
                    $row['state'] = 'processing';
                }
            }
        }

        return response()->json($row)->header('Cache-Control', 'no-store');
    }

    private function providerQueueStatus($entry): ?array
    {
        $url = (string) $entry->response_url;
        if (! str_starts_with($url, 'https://queue.fal.run/')) {
            return null;
        }

        try {
            $res = Http::timeout(8)
                ->withHeaders(['Authorization' => 'Key ' . ApiHelper::setFalAIKey()])
                ->get(rtrim($url, '/') . '/status');
            if (! $res->successful()) {
                return null;
            }

            return [
                'status'         => strtoupper((string) $res->json('status')),
                'queue_position' => $res->json('queue_position'),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function videoProxy(Request $request)
    {
        $id = (int) $request->query('id');
        $entry = UserFall::query()->where('user_id', Auth::id())->whereKey($id)->first();
        $url = (string) ($entry->video_url ?? '');
        if ($url === '') {
            return response()->json(['message' => 'Video not available.'], 404);
        }
        if (str_starts_with($url, '/')) {
            $url = url($url);
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return response()->json(['message' => 'Video URL is not allowed.'], 422);
        }

        if (! $request->boolean('download')) {
            return redirect()->away($url);
        }

        $name = 'garad-video-' . $id . '.mp4';

        return response()->streamDownload(function () use ($url) {
            @set_time_limit(0);
            $body = Http::timeout(600)
                ->withOptions(['stream' => true])
                ->get($url)
                ->toPsrResponse()
                ->getBody();

            while (! $body->eof()) {
                echo $body->read(65536);
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
        }, $name, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'no-store']);
    }

    private function soraApiAvailable(): bool
    {
        $key = ApiHelper::setOpenAiKey();
        if (! $key) {
            return false;
        }

        return (bool) Cache::remember('garad.sora-api-up', 1800, function () use ($key) {
            try {
                $res = Http::timeout(10)
                    ->withHeaders(['Authorization' => 'Bearer ' . $key])
                    ->get('https://api.openai.com/v1/videos', ['limit' => 1]);

                // OpenAI returns an empty 404 once the Sora video API has been retired.
                return $res->status() !== 404;
            } catch (Throwable) {
                return true;
            }
        });
    }

    private function videoRow($v): array
    {
        $status = strtolower(trim((string) $v->status));
        $state = in_array($status, ['complete', 'completed'], true) ? 'completed'
            : (in_array($status, ['error', 'failed', 'cancelled', 'canceled'], true) ? 'failed'
            : (in_array($status, ['in_progress', 'processing', 'running'], true) ? 'processing' : 'queued'));

        return [
            'id'              => (int) $v->id,
            'prompt'          => (string) $v->prompt,
            'model'           => (string) $v->model,
            'state'           => $state,
            'rawStatus'       => (string) $v->status,
            'videoUrl'        => $v->video_url,
            'error'           => $v->error_message,
            'durationSeconds' => $v->duration_seconds,
            'resolution'      => $v->resolution,
            'aspectRatio'     => $v->aspect_ratio,
            'referenceUrl'    => $v->prompt_image_url,
            'createdAt'       => optional($v->created_at)->toIso8601String(),
        ];
    }

    /**
     * Read-only capability map for the composer "+" menu. It only reports what the
     * existing Garad services already allow for the authenticated user; the actual
     * work is still done by the existing generate-stream / deep-research / skills routes.
     */
    public function composerCapabilities(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $plan = $user->relationPlan;

        $default = (string) setting('default_realtime', 'serper');
        $settingsTwo = SettingTwo::getCache();
        $webSearch = match (true) {
            $default === 'serper'     => ! is_null($settingsTwo->serper_api_key ?? null),
            $default === 'perplexity' => ! is_null(setting('perplexity_key')),
            default                   => in_array($default, ['openai', 'tool_calling'], true),
        };

        $deepResearchRegistered = MarketplaceHelper::isRegistered('ai-chat-pro-deep-research');
        $deepResearchAllowed = $deepResearchRegistered
            && ($isAdmin || (bool) $plan?->checkOpenAiItem('deep_research'));

        $canvasNative = MarketplaceHelper::isRegistered('canvas')
            && (bool) setting('ai_chat_pro_canvas', 1);
        $canvasViaResearch = ! $canvasNative
            && $deepResearchRegistered
            && (bool) setting('deep_research_auto_canvas', 1);

        $skillsInstalled = class_exists(Skill::class);
        $skillsEnabled = $skillsInstalled && (bool) setting('ai_chat_pro_skills_enabled', '1');
        $skillsAllowed = $skillsEnabled && ($isAdmin || (bool) $plan?->checkOpenAiItem('ai_chat_pro_skills'));
        $brandVoiceAllowed = $isAdmin || (bool) $plan?->checkOpenAiItem('brand_voice');

        $promptSetting = setting('user_prompt_library');

        return response()->json([
            'webSearch'     => ['available' => $webSearch, 'provider' => $default],
            'deepResearch'  => [
                'available' => $deepResearchRegistered,
                'allowed'   => $deepResearchAllowed,
            ],
            'canvas'        => ['available' => $canvasNative || $canvasViaResearch],
            'promptLibrary' => ['available' => $promptSetting === null || (bool) $promptSetting],
            'skills'        => ['available' => $skillsEnabled, 'allowed' => $skillsAllowed],
            'brandVoice'    => [
                'available' => $brandVoiceAllowed,
                'companies' => $brandVoiceAllowed
                    ? $user->getCompanies()->map(static fn ($company) => [
                        'id'          => (string) $company->id,
                        'name'        => (string) $company->name,
                        'toneOfVoice' => (string) ($company->tone_of_voice ?? ''),
                    ])->values()->all()
                    : [],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /** Same data set as PromptController::getAll(), returned as JSON instead of rendered HTML. */
    public function composerPrompts(): JsonResponse
    {
        $userId = Auth::id();

        $favourites = Favourite::query()
            ->where('type', 'prompt')
            ->where('user_id', $userId)
            ->pluck('item_id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        $prompts = Prompt::query()
            ->where('user_id', $userId)
            ->orWhere('show_for_all', true)
            ->get()
            ->map(static fn ($prompt) => [
                'id'       => (string) $prompt->id,
                'title'    => (string) $prompt->title,
                'prompt'   => (string) $prompt->prompt,
                'favorite' => in_array((string) $prompt->id, $favourites, true),
                'shared'   => (bool) $prompt->show_for_all,
            ])->values()->all();

        return response()->json($prompts)->header('Cache-Control', 'no-store');
    }

    public function composerBrandProducts(int $companyId): JsonResponse
    {
        $user = Auth::user();
        if (! $user->isAdmin() && ! $user->relationPlan?->checkOpenAiItem('brand_voice')) {
            abort(403, __('Brand Voice is unavailable on your current plan.'));
        }

        $company = $user
            ->companies()
            ->whereKey($companyId)
            ->firstOrFail();

        return response()->json(
            $company->products()
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
                ->map(static fn ($product) => [
                    'id'   => (string) $product->id,
                    'name' => (string) $product->name,
                    'type' => (int) $product->type,
                ])
                ->values()
                ->all()
        )->header('Cache-Control', 'no-store');
    }

    private function resolveFrontendAssets(): array
    {
        $assetBaseUrl = rtrim(
            (string) env('GARAD_CHAT_FRONTEND_ASSET_URL', url('garad-ai-chat-pro/dist')),
            '/'
        );

        $entryScriptUrl = null;
        $styleUrls = [];

        $manifest = $this->readFrontendManifest();
        if (is_array($manifest)) {
            $entry = $manifest['src/main.tsx'] ?? $manifest['index.html'] ?? null;

            if (! is_array($entry)) {
                $entry = collect($manifest)->first(
                    static fn ($item) => is_array($item) && ($item['isEntry'] ?? false)
                );
            }

            if (is_array($entry) && ! empty($entry['file']) && is_string($entry['file'])) {
                $entryScriptUrl = $this->assetUrl($assetBaseUrl, $entry['file']);

                $cssFiles = is_array($entry['css'] ?? null) ? $entry['css'] : [];

                $styleUrls = collect($cssFiles)
                    ->filter(static fn ($path) => is_string($path) && trim($path) !== '')
                    ->map(fn ($path) => $this->assetUrl($assetBaseUrl, (string) $path))
                    ->values()
                    ->all();
            }
        }

        $viteDevServer = null;
        if (! $entryScriptUrl) {
            $configuredDevServer = trim((string) env('GARAD_CHAT_FRONTEND_DEV_URL', ''));

            if ($configuredDevServer !== '') {
                $viteDevServer = rtrim($configuredDevServer, '/');
            }
        }

        return [
            $entryScriptUrl,
            $styleUrls,
            $viteDevServer,
            ! $entryScriptUrl && ! $viteDevServer,
        ];
    }

    private function readFrontendManifest(): ?array
    {
        $distPath = public_path('garad-ai-chat-pro/dist');

        $manifestPaths = [
            $distPath . '/.vite/manifest.json',
            $distPath . '/manifest.json',
        ];

        foreach ($manifestPaths as $manifestPath) {
            if (! File::exists($manifestPath)) {
                continue;
            }

            try {
                $decoded = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function assetUrl(string $baseUrl, string $path): string
    {
        return $baseUrl . '/' . ltrim($path, '/');
    }

    private function buildAccountPayload(bool $includeModelDescriptors = true): array
    {
        /** @var User $user */
        $user = Auth::user();

        $storedPreferences = $this->storedAccountPreferences($user);
        $defaultModelId = $includeModelDescriptors
            ? ($this->resolveDefaultModelId($this->modelDescriptors()) ?? 'gpt-4o-mini')
            : (
                trim((string) ($storedPreferences['defaultModel'] ?? ''))
                ?: ($this->defaultChatEntityEnum()?->value ?? 'gpt-4o-mini')
            );
        $usage = $this->buildUsagePayload();

        return [
            'user'         => $this->accountUserPayload($user),
            'subscription' => $this->buildSubscriptionPayload(),
            'authStatus'   => 'authenticated',
            'usage'        => $usage,
            'entitlements' => ['storage' => $usage['storageBytes']['limit']]
                + ($user->isAdmin() ? ['agents' => true] : []),
            'preferences' => array_replace_recursive(
                $this->defaultAccountPreferences($defaultModelId),
                $storedPreferences,
            ),
            'memoryEnabled' => true,
            'memories'      => [],
        ];
    }

    private function bootstrapPayload(): ?array
    {
        if (! Auth::check()) {
            return null;
        }

        try {
            return [
                'account' => $this->buildAccountPayload(false),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function accountUserPayload(User $user): array
    {
        return [
            'id'                            => (string) $user->id,
            'name'                          => $this->displayName($user),
            'givenName'                     => (string) $user->name,
            'surname'                       => (string) $user->surname,
            'email'                         => (string) $user->email,
            'username'                      => $user->public_username,
            'profileVisibility'             => $user->profile_visibility ?? 'private',
            'bio'                           => (string) ($user->bio ?? ''),
            'website'                       => (string) ($user->website ?? ''),
            'allowMentions'                 => (bool) ($user->allow_mentions ?? true),
            'allowCollaborationRequests'    => (bool) ($user->allow_collaboration_requests ?? true),
            'publicProfileUrl'              => $user->profileUrl(),
            'type'                          => $user->type->value,
            'isSuperAdmin'                  => $user->isSuperAdmin(),
            'avatarUrl'                     => $this->avatarUrl($user),
            'phone'                         => (string) ($user->phone ?? ''),
            'country'                       => (string) ($user->country ?? ''),
            'state'                         => (string) ($user->state ?? ''),
            'city'                          => (string) ($user->city ?? ''),
            'postal'                        => (string) ($user->postal ?? ''),
            'address'                       => (string) ($user->address ?? ''),
        ];
    }

    private function defaultAccountPreferences(?string $defaultModelId = null): array
    {
        return [
            'theme'              => 'light',
            'language'           => $this->languagePreference(),
            'defaultMode'        => 'chat',
            'defaultModel'       => $defaultModelId ?? 'gpt-4o-mini',
            'reducedMotion'      => false,
            'responseTraits'     => [],
            'customInstructions' => '',
            'notifications'      => [
                'tasks'     => true,
                'artifacts' => true,
                'tips'      => true,
            ],
        ];
    }

    private function storedAccountPreferences(User $user): array
    {
        $preferences = DB::table('ext_ai_chat_pro_preferences')
            ->where('user_id', $user->id)
            ->value('preferences');

        if (! is_string($preferences) || $preferences === '') {
            return [];
        }

        $decoded = json_decode($preferences, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function brandAssets(): array
    {
        $setting = Setting::getCache();
        $resolve = static fn ($path) => empty($path)
            ? null
            : url(custom_theme_url(ltrim((string) $path, '/'), true));

        $authOptions = json_decode((string) ($setting->auth_view_options ?? ''), true) ?: [];
        $publicFile = static fn ($path) => (is_string($path) && $path !== '' && is_file(public_path($path)))
            ? url('/' . ltrim($path, '/'))
            : null;

        $slides = array_values(array_filter(array_map(
            $publicFile,
            array_slice((array) ($authOptions['slides'] ?? []), 0, 5)
        )));

        return [
            'logoUrl'     => $resolve($setting->logo_path ?? null),
            'logoMarkUrl' => $resolve($setting->logo_collapsed_path ?? null),
            'authVisual'  => [
                'slides'   => $slides,
                'video'    => $publicFile($authOptions['video'] ?? null),
                'bottom'   => $publicFile($authOptions['bottom_image'] ?? null),
                'review'   => (string) ($authOptions['overlay_review'] ?? ''),
                'badge'    => (string) ($authOptions['overlay_badge'] ?? ''),
                'fallback' => $publicFile($authOptions['login_image'] ?? null),
            ],
        ];
    }

    private function avatarUrl(User $user): ?string
    {
        $avatar = trim((string) ($user->avatar ?? ''));

        if ($avatar === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $avatar)) {
            return $avatar;
        }

        return url(custom_theme_url(ltrim($avatar, '/'), true));
    }

    private function buildUsagePayload(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $plan = $this->activePlanForCurrentUser();

        $messagesUsed = UserOpenaiChatMessage::query()
            ->where('user_id', $user->id)
            ->whereNotNull('input')
            ->count();

        $messageLimit = max(0, (int) ($plan?->max_tokens ?? 0));

        if ($messageLimit > 0 && $messagesUsed > $messageLimit) {
            $messagesUsed = $messageLimit;
        }

        $storageQuota = app(AIChatProStorageQuotaService::class);

        return [
            'messages' => [
                'used'  => (int) $messagesUsed,
                'limit' => $messageLimit,
            ],
            'agentRuns' => [
                'used'  => 0,
                'limit' => max(0, (int) ($plan?->ai_agent_message_limit ?? 0)),
            ],
            'storageBytes' => [
                'used'  => $storageQuota->usedBytes((int) $user->id),
                'limit' => $storageQuota->limitBytesForPlan($plan),
            ],
        ];
    }

    private function buildSubscriptionPayload(): ?array
    {
        /** @var User $user */
        $user = Auth::user();

        $plan = $this->activePlanForCurrentUser();
        if (! $plan) {
            return null;
        }

        $activeSubscription = getCurrentActiveSubscription($user->id)
            ?? getCurrentActiveSubscriptionYokkasa($user->id);

        return [
            'id'              => (string) ($activeSubscription?->id ?? $plan->id),
            'planId'          => $this->planRequirementForCurrentUser(),
            'status'          => (string) ($activeSubscription?->stripe_status ?? 'active'),
            'billingProvider' => (string) ($activeSubscription?->paid_with ?? 'none'),
        ];
    }

    private function modelDescriptors(): Collection
    {
        if ($this->modelDescriptorCache !== null) {
            return $this->modelDescriptorCache;
        }

        $planRequirement = $this->userBypassesLocks()
            ? 'free'
            : $this->planRequirementForCurrentUser();

        $ownEnums = $this->chatModelEnumsForCurrentUser();
        $ownKeys = $ownEnums->map(static fn (EntityEnum $e) => $e->value)->all();
        $lockedPlans = [];

        $modelEnums = $ownEnums;
        if (! $this->userBypassesLocks()) {
            $extras = Entity::query()
                ->isEnabled()
                ->whereHas('tokens', fn ($q) => $q->where('type', AITokenType::WORD->value))
                ->get()
                ->map(fn (Entity $entity) => $this->entityEnumFromModel($entity))
                ->filter(static fn ($enum) => $enum instanceof EntityEnum && $enum->isChatModel() && ! in_array($enum->value, $ownKeys, true));

            foreach ($extras as $enum) {
                $required = $this->requiredPlanFor($enum->value, $enum->engine()->value, 'chat');
                if ($required !== null) {
                    $lockedPlans[$enum->value] = $required;
                }
            }

            $modelEnums = $ownEnums->concat($extras->filter(fn ($e) => isset($lockedPlans[$e->value])))->values();
        }

        $recordsById = Entity::query()
            ->whereIn('key', $modelEnums->map(static fn (EntityEnum $enum) => $enum->value)->all())
            ->get()
            ->keyBy(function (Entity $entity) {
                return $this->entityEnumFromModel($entity)?->value;
            });

        return $this->modelDescriptorCache = $modelEnums
            ->map(function (EntityEnum $enum) use ($recordsById, $planRequirement, $lockedPlans) {
                $record = $recordsById->get($enum->value);
                $lockedPlan = $lockedPlans[$enum->value] ?? null;

                $displayName = trim((string) ($record?->selected_title ?? $record?->title ?? ''));
                if ($displayName === '') {
                    $displayName = trim((string) $enum->label());
                }
                if ($displayName === '') {
                    $displayName = $enum->value;
                }

                $provider = Str::of($enum->engine()->value)
                    ->replace('_', ' ')
                    ->title()
                    ->toString();

                $creditBalance = 0.0;
                $isUnlimited = false;
                $isAvailable = true;

                if (Auth::check() && $lockedPlan === null) {
                    try {
                        $driver = EntityDriver::driver($enum);

                        $creditBalance = (float) $driver->creditBalance();
                        $isUnlimited = (bool) $driver->isUnlimitedCredit();
                        $isAvailable = $isUnlimited || $creditBalance > 0;
                    } catch (Throwable) {
                        $isAvailable = true;
                    }
                }

                $description = $provider;
                if ($lockedPlan !== null) {
                    // provider only; the plan badge is shown by the UI
                } elseif (! Auth::check()) {
                    $description = $provider;
                } elseif (! $isAvailable) {
                    $description .= ' • No credits available';
                } elseif ($isUnlimited) {
                    $description .= ' • Unlimited credits';
                } else {
                    $description .= ' • Credits: ' . $this->formatCredit($creditBalance);
                }

                $availability = ! $isAvailable
                    ? 'unavailable'
                    : ($enum->isBetaEntity() ? 'beta' : 'available');

                return [
                    'id'              => $enum->value,
                    'displayName'     => $displayName,
                    'description'     => $description,
                    'provider'        => $enum->engine()->value,
                    'capabilities'    => $this->capabilitiesForModel($enum, $displayName),
                    'planRequirement' => $planRequirement,
                    'availability'    => $availability,
                    'locked'          => $lockedPlan !== null,
                    'requiredPlan'    => $lockedPlan,
                ];
            })
            ->filter(static fn ($item) => is_array($item) && ! empty($item['id']))
            ->values();
    }

    private function userBypassesLocks(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** Name of the cheapest paid subscription plan that includes the model, or null. */
    private function requiredPlanFor(string $key, string $engine, string $category): ?string
    {
        if ($this->paidPlanCache === null) {
            $this->paidPlanCache = DB::table('plans')
                ->where('active', 1)->where('hidden', 0)->where('type', 'subscription')
                ->where('price', '>', 0)
                ->orderBy('price')
                ->get()
                ->map(function ($plan) {
                    $decoded = is_string($plan->ai_models) ? json_decode($plan->ai_models, true) : (array) $plan->ai_models;

                    return [
                        'name' => (string) $plan->name,
                        'ai'   => is_array($decoded) ? $decoded : [],
                        'chat' => DB::table('ai_chat_model_plans')
                            ->join('entities', 'entities.id', '=', 'ai_chat_model_plans.entity_id')
                            ->where('ai_chat_model_plans.plan_id', $plan->id)
                            ->pluck('entities.key')->all(),
                    ];
                })->all();
        }

        foreach ($this->paidPlanCache as $plan) {
            if ($category === 'chat') {
                if (in_array($key, $plan['chat'], true)) {
                    return $plan['name'];
                }

                continue;
            }
            $setting = $plan['ai'][$engine][str_replace('.', '__', $key)] ?? null;
            if (is_array($setting) && (! empty($setting['isUnlimited']) || (float) ($setting['credit'] ?? 0) > 0)) {
                return $plan['name'];
            }
        }

        return null;
    }

    private ?array $paidPlanCache = null;

    private function chatModelEnumsForCurrentUser(): Collection
    {
        $models = $this->userBypassesLocks()
            ? Entity::query()
                ->isEnabled()
                ->whereHas('tokens', fn ($query) => $query->where('type', AITokenType::WORD->value))
                ->get()
            : collect(Entity::planModels());

        return collect($models)
            ->map(fn ($model) => $this->entityEnumFromModel($model))
            ->filter(static fn ($enum) => $enum instanceof EntityEnum && $enum->isChatModel())
            ->unique(static fn (EntityEnum $enum) => $enum->value)
            ->values();
    }

    private function defaultChatEntityEnum(): ?EntityEnum
    {
        try {
            $setting = Setting::getCache();

            $defaultEngine = EngineEnum::fromSlug(
                setting('default_ai_engine', EngineEnum::OPEN_AI->slug())
            );

            $defaultModel = $defaultEngine->getDefaultWordModel($setting);

            if ($defaultModel instanceof EntityEnum) {
                return $defaultModel;
            }

            if (is_string($defaultModel)) {
                return EntityEnum::tryFrom($defaultModel);
            }

            if (is_object($defaultModel) && isset($defaultModel->value) && is_string($defaultModel->value)) {
                return EntityEnum::tryFrom($defaultModel->value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function resolveDefaultModelId(Collection $models): ?string
    {
        $defaultModel = $this->defaultChatEntityEnum();
        if ($defaultModel instanceof EntityEnum) {
            $exists = $models->contains(
                static fn ($model) => is_array($model) && ($model['id'] ?? null) === $defaultModel->value && empty($model['locked'])
            );

            if ($exists) {
                return $defaultModel->value;
            }
        }

        $available = $models->first(static function ($model) {
            return is_array($model) && ($model['availability'] ?? null) === 'available' && empty($model['locked']);
        });

        if (is_array($available) && ! empty($available['id'])) {
            return (string) $available['id'];
        }

        $first = $models->first();

        if (is_array($first) && ! empty($first['id'])) {
            return (string) $first['id'];
        }

        return null;
    }

    private function entityEnumFromModel(mixed $model): ?EntityEnum
    {
        if ($model instanceof Entity && $model->key instanceof EntityEnum) {
            return $model->key;
        }

        if ($model instanceof Entity && is_string($model->key)) {
            return EntityEnum::tryFrom($model->key);
        }

        if (is_string($model)) {
            return EntityEnum::tryFrom($model);
        }

        if (is_object($model) && isset($model->value) && is_string($model->value)) {
            return EntityEnum::tryFrom($model->value);
        }

        return null;
    }

    private function capabilitiesForModel(EntityEnum $enum, string $displayName): array
    {
        $signature = Str::lower($enum->value . ' ' . $displayName);

        $capabilities = ['chat', 'tools'];

        if (
            str_contains($signature, 'search') ||
            str_contains($signature, 'research') ||
            str_contains($signature, 'perplexity') ||
            str_contains($signature, 'serper')
        ) {
            $capabilities[] = 'research';
        }

        if (str_contains($signature, 'vision') || str_contains($signature, 'omni')) {
            $capabilities[] = 'vision';
        }

        if (
            str_contains($signature, 'reason') ||
            preg_match('/^o\\d/', $enum->value) === 1
        ) {
            $capabilities[] = 'reasoning';
        }

        return array_values(array_unique($capabilities));
    }

    private function displayName(User $user): string
    {
        $name = trim((string) ($user->fullName() ?? ''));

        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($user->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return (string) $user->email;
    }

    private function languagePreference(): string
    {
        $locale = Str::lower((string) app()->getLocale());

        return Str::startsWith($locale, 'so') ? 'Somali' : 'English';
    }

    private function planRequirementForCurrentUser(): string
    {
        $plan = $this->activePlanForCurrentUser();

        if (! $plan) {
            return 'free';
        }

        $signature = Str::lower(trim((string) ($plan->name . ' ' . $plan->plan_type)));

        if ($plan->is_team_plan || Str::contains($signature, ['business', 'team', 'enterprise'])) {
            return 'business';
        }

        if (Str::contains($signature, ['pro', 'premium', 'advanced'])) {
            return 'pro';
        }

        return (float) $plan->price > 0 ? 'pro' : 'free';
    }

    private function activePlanForCurrentUser(): ?Plan
    {
        if (! $this->activePlanResolved) {
            $user = Auth::user();
            $this->activePlanCache = $user instanceof User ? $user->activePlan() : null;
            $this->activePlanResolved = true;
        }

        return $this->activePlanCache;
    }

    public function mediaPlanLock(string $key, ?string $engine, string $category): ?string
    {
        if ($engine === null || $this->userBypassesLocks()) {
            return null;
        }

        $current = $this->activePlanForCurrentUser();
        if (Auth::check() && ! $current && $category === 'image' && $key === EntityEnum::SEEDREAM_4->value) {
            return null;
        }

        $required = $this->requiredPlanFor($key, $engine, $category);
        if ($required === null) {
            return null;
        }

        if ($current && $this->paidPlanPrice((string) $current->name) >= $this->paidPlanPrice($required)) {
            return null;
        }

        return $required;
    }

    private function paidPlanPrice(string $name): float
    {
        return (float) DB::table('plans')->where('name', $name)->value('price');
    }

    private function formatCredit(float $credit): string
    {
        $normalized = rtrim(rtrim(number_format(max(0, $credit), 2, '.', ''), '0'), '.');

        return $normalized === '' ? '0' : $normalized;
    }
}
