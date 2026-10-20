<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Extensions\AIChatPro\System\Http\Controllers\ReactChatController;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProfileVisibilityAndWorkspaceMembershipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('user_follows');
        Schema::dropIfExists('user_collaboration_requests');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('usage');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('users');

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('usage', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('total_user_count')->default(0);
            $table->unsignedInteger('this_week_user_count')->default(0);
            $table->unsignedInteger('last_week_user_count')->default(0);
            $table->unsignedInteger('total_word_count')->default(0);
            $table->unsignedInteger('this_week_word_count')->default(0);
            $table->unsignedInteger('last_week_word_count')->default(0);
            $table->unsignedInteger('total_image_count')->default(0);
            $table->unsignedInteger('this_week_image_count')->default(0);
            $table->unsignedInteger('last_week_image_count')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('team_manager_id')->nullable();
            $table->string('name');
            $table->string('surname')->default('');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('public_username')->nullable()->unique();
            $table->string('profile_visibility')->default('private');
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->boolean('allow_mentions')->default(true);
            $table->boolean('allow_collaboration_requests')->default(true);
            $table->string('avatar')->default('assets/img/auth/default-avatar.png');
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('postal')->nullable();
            $table->text('address')->nullable();
            $table->string('password');
            $table->string('type')->default('user');
            $table->json('entity_credits')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_id');
            $table->foreignId('followed_user_id');
            $table->timestamps();
            $table->unique(['follower_id', 'followed_user_id']);
        });

        Schema::create('workspace_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('workspace_type');
            $table->unsignedBigInteger('workspace_id');
            $table->string('role')->default('member');
            $table->json('permissions')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'workspace_type', 'workspace_id']);
        });

        Schema::create('user_collaboration_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requester_id');
            $table->foreignId('recipient_id');
            $table->text('message');
            $table->string('status')->default('pending')->index();
            $table->string('pending_key')->nullable()->unique();
            $table->foreignId('decision_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'status']);
        });
    }

    public function test_profiles_respect_visibility_and_connections(): void
    {
        $viewer = User::create([
            'name'     => 'Viewer',
            'surname'  => 'User',
            'email'    => 'viewer@example.test',
            'password' => 'secret',
        ]);

        $publicUser = User::create([
            'name'               => 'Public',
            'surname'            => 'Profile',
            'email'              => 'public@example.test',
            'public_username'    => 'jabir',
            'profile_visibility' => 'public',
            'password'           => 'secret',
        ]);

        $privateUser = User::create([
            'name'               => 'Private',
            'surname'            => 'Profile',
            'email'              => 'private@example.test',
            'public_username'    => 'hidden',
            'profile_visibility' => 'private',
            'password'           => 'secret',
        ]);

        $connectedUser = User::create([
            'name'               => 'Connected',
            'surname'            => 'Profile',
            'email'              => 'connected@example.test',
            'public_username'    => 'mentor',
            'profile_visibility' => 'connections',
            'password'           => 'secret',
        ]);

        UserFollow::create([
            'follower_id'      => $viewer->id,
            'followed_user_id' => $connectedUser->id,
        ]);

        $this->assertTrue($publicUser->canViewProfile($viewer));
        $this->assertFalse($privateUser->canViewProfile($viewer));
        $this->assertFalse($connectedUser->canViewProfile($viewer));

        UserFollow::create([
            'follower_id'      => $connectedUser->id,
            'followed_user_id' => $viewer->id,
        ]);

        $this->assertTrue($connectedUser->canViewProfile($viewer));
        $this->assertFalse($connectedUser->canViewProfile(User::create([
            'name'     => 'Other',
            'surname'  => 'Person',
            'email'    => 'other@example.test',
            'password' => 'secret',
        ])));
    }

    public function test_private_profiles_are_not_revealed_by_public_profile_lookup(): void
    {
        $privateUser = User::create([
            'name'               => 'Private',
            'surname'            => 'Profile',
            'email'              => 'private-public-route@example.test',
            'public_username'    => 'hidden-account',
            'profile_visibility' => 'private',
            'password'           => 'secret',
        ]);

        auth()->logout();

        $response = app(ReactChatController::class)
            ->showPublicProfile('hidden-account');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Profile not found.'],
            $response->getData(true),
        );

        $this->actingAs($privateUser);
        $ownerResponse = app(ReactChatController::class)
            ->showPublicProfile('hidden-account');

        $this->assertSame(200, $ownerResponse->getStatusCode());
        $this->assertArrayNotHasKey('email', $ownerResponse->getData(true)['user']);
    }

    public function test_public_profile_api_hides_private_profiles_from_unauthorized_viewers(): void
    {
        $publicUser = User::create([
            'name'               => 'Public',
            'surname'            => 'Profile',
            'email'              => 'public-api@example.test',
            'public_username'    => 'public-api',
            'profile_visibility' => 'public',
            'password'           => 'secret',
        ]);
        $privateUser = User::create([
            'name'               => 'Private',
            'surname'            => 'Profile',
            'email'              => 'private-api@example.test',
            'public_username'    => 'private-api',
            'profile_visibility' => 'private',
            'password'           => 'secret',
        ]);

        $this->getJson('/api/profile/public-api')
            ->assertOk()
            ->assertJsonPath('user.username', 'public-api')
            ->assertJsonMissingPath('user.email');
        $this->getJson('/api/profile/private-api')
            ->assertNotFound()
            ->assertJsonPath('message', 'Profile not found.');

        $viewer = User::create([
            'name'     => 'API',
            'surname'  => 'Viewer',
            'email'    => 'profile-api-viewer@example.test',
            'password' => 'secret',
        ]);
        $this->actingAs($viewer)
            ->getJson('/api/profile/private-api')
            ->assertNotFound();

        $this->assertDatabaseMissing('user_follows', [
            'follower_id'      => $viewer->id,
            'followed_user_id' => $publicUser->id,
        ]);
        $this->assertDatabaseMissing('user_follows', [
            'follower_id'      => $viewer->id,
            'followed_user_id' => $privateUser->id,
        ]);
    }

    public function test_follow_api_requires_auth_and_is_idempotent_for_public_profiles(): void
    {
        $publicUser = User::create([
            'name'               => 'Public',
            'surname'            => 'Target',
            'email'              => 'follow-target@example.test',
            'public_username'    => 'follow-target',
            'profile_visibility' => 'public',
            'password'           => 'secret',
        ]);
        $privateUser = User::create([
            'name'               => 'Private',
            'surname'            => 'Target',
            'email'              => 'private-follow-target@example.test',
            'public_username'    => 'private-follow-target',
            'profile_visibility' => 'private',
            'password'           => 'secret',
        ]);

        $this->postJson("/api/profile/{$publicUser->id}/follow")
            ->assertUnauthorized();

        $viewer = User::create([
            'name'     => 'Follow',
            'surname'  => 'Viewer',
            'email'    => 'follow-viewer@example.test',
            'password' => 'secret',
        ]);
        $this->actingAs($viewer);

        $this->postJson("/api/profile/{$privateUser->id}/follow")
            ->assertNotFound();
        $this->postJson("/api/profile/{$publicUser->id}/follow")
            ->assertOk()
            ->assertJsonPath('following', true)
            ->assertJsonPath('followersCount', 1);
        $this->postJson("/api/profile/{$publicUser->id}/follow")
            ->assertOk()
            ->assertJsonPath('following', true)
            ->assertJsonPath('followersCount', 1);

        $this->deleteJson("/api/profile/{$publicUser->id}/follow")
            ->assertOk()
            ->assertJsonPath('following', false)
            ->assertJsonPath('followersCount', 0);
        $this->deleteJson("/api/profile/{$publicUser->id}/follow")
            ->assertOk()
            ->assertJsonPath('following', false)
            ->assertJsonPath('followersCount', 0);

        $this->assertDatabaseMissing('user_follows', [
            'follower_id'      => $viewer->id,
            'followed_user_id' => $publicUser->id,
        ]);
    }

    public function test_profile_username_uniqueness_is_case_insensitive(): void
    {
        User::create([
            'name'            => 'Existing',
            'surname'         => 'User',
            'email'           => 'existing-username@example.test',
            'public_username' => 'taken-name',
            'password'        => 'secret',
        ]);
        $editor = User::create([
            'name'            => 'Profile',
            'surname'         => 'Editor',
            'email'           => 'username-editor@example.test',
            'public_username' => 'editor-name',
            'password'        => 'secret',
        ]);

        $this->actingAs($editor)
            ->patchJson('/api/account/profile', [
                'name'     => 'Profile',
                'surname'  => 'Editor',
                'username' => 'TAKEN-NAME',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        $this->patchJson('/api/account/profile', [
            'name'     => 'Profile',
            'surname'  => 'Editor',
            'username' => ' EDITOR-NAME ',
        ])
            ->assertOk()
            ->assertJsonPath('user.username', 'editor-name');
    }

    public function test_profile_boolean_preferences_accept_multipart_true_and_false_values(): void
    {
        $editor = User::create([
            'name'     => 'Profile',
            'surname'  => 'Editor',
            'email'    => 'profile-bools@example.test',
            'password' => 'secret',
        ]);

        $this->actingAs($editor)
            ->post('/api/account/profile', [
                '_method'                     => 'PATCH',
                'name'                        => 'Profile',
                'surname'                     => 'Editor',
                'allowMentions'               => 'false',
                'allowCollaborationRequests'  => 'true',
            ])
            ->assertOk()
            ->assertJsonPath('user.allowMentions', false)
            ->assertJsonPath('user.allowCollaborationRequests', true);

        $this->assertFalse((bool) $editor->fresh()->allow_mentions);
        $this->assertTrue((bool) $editor->fresh()->allow_collaboration_requests);
    }

    public function test_workspace_memberships_enforce_role_checks(): void
    {
        $member = User::create([
            'name'     => 'Team',
            'surname'  => 'Member',
            'email'    => 'member@example.test',
            'password' => 'secret',
        ]);

        WorkspaceMembership::create([
            'user_id'        => $member->id,
            'workspace_type' => 'team',
            'workspace_id'   => 42,
            'role'           => 'owner',
            'permissions'    => ['approve', 'manage_members'],
            'status'         => 'active',
        ]);

        $this->assertTrue($member->hasWorkspaceRole('team', 42, ['owner']));
        $this->assertFalse($member->hasWorkspaceRole('team', 99, ['owner']));
        $this->assertTrue($member->hasWorkspaceRole('team', 42));
    }

    public function test_mentions_respect_profile_visibility_preferences_and_notification_ownership(): void
    {
        $sender = User::create([
            'name'            => 'Mention',
            'surname'         => 'Sender',
            'email'           => 'mention-sender@example.test',
            'public_username' => 'mention-sender',
            'password'        => 'secret',
        ]);
        $recipient = User::create([
            'name'               => 'Mention',
            'surname'            => 'Recipient',
            'email'              => 'mention-recipient@example.test',
            'public_username'    => 'mention-recipient',
            'profile_visibility' => 'public',
            'password'           => 'secret',
        ]);
        $privateRecipient = User::create([
            'name'               => 'Private',
            'surname'            => 'Recipient',
            'email'              => 'mention-private@example.test',
            'public_username'    => 'mention-private',
            'profile_visibility' => 'private',
            'password'           => 'secret',
        ]);
        $optedOutRecipient = User::create([
            'name'               => 'Opted',
            'surname'            => 'Out',
            'email'              => 'mention-opted-out@example.test',
            'public_username'    => 'mention-opted-out',
            'profile_visibility' => 'public',
            'allow_mentions'     => false,
            'password'           => 'secret',
        ]);

        $this->postJson('/api/mentions', [
            'username' => 'mention-recipient',
            'message'  => 'Hello',
        ])->assertUnauthorized();

        $this->actingAs($sender)
            ->postJson('/api/mentions', [
                'username' => 'mention-private',
                'message'  => 'Hello',
            ])->assertNotFound();
        $this->postJson('/api/mentions', [
            'username' => 'mention-opted-out',
            'message'  => 'Hello',
        ])->assertForbidden();

        $response = $this->postJson('/api/mentions', [
            'username' => 'mention-recipient',
            'message'  => 'Can we talk about the proposal?',
        ])->assertCreated()
            ->assertJsonPath('type', 'mention')
            ->assertJsonPath('detail', 'Can we talk about the proposal?');

        $notificationId = $response->json('id');
        $this->assertDatabaseHas('notifications', [
            'id'            => $notificationId,
            'notifiable_id' => $recipient->id,
        ]);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0);

        $this->patchJson("/api/notifications/{$notificationId}", ['read' => true])
            ->assertNotFound();

        $this->actingAs($recipient)
            ->getJson('/api/notifications?type=mention')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $notificationId)
            ->assertJsonPath('0.actorUsername', 'mention-sender');

        $this->patchJson("/api/notifications/{$notificationId}", ['read' => true])
            ->assertOk()
            ->assertJsonPath('read', true);
    }

    public function test_mention_search_returns_only_public_opted_in_users_without_private_fields(): void
    {
        $viewer = User::create([
            'name'            => 'Mention',
            'surname'         => 'Viewer',
            'email'           => 'mention-search-viewer@example.test',
            'public_username' => 'mention-search-viewer',
            'password'        => 'secret',
        ]);
        $publicUser = User::create([
            'name'               => 'Searchable',
            'surname'            => 'Person',
            'email'              => 'mention-search-public@example.test',
            'public_username'    => 'searchable-person',
            'profile_visibility' => 'public',
            'password'           => 'secret',
        ]);
        $privateUser = User::create([
            'name'               => 'Searchable',
            'surname'            => 'Private',
            'email'              => 'mention-search-private@example.test',
            'public_username'    => 'searchable-private',
            'profile_visibility' => 'private',
            'password'           => 'secret',
        ]);
        $optedOutUser = User::create([
            'name'               => 'Searchable',
            'surname'            => 'Opted Out',
            'email'              => 'mention-search-opted-out@example.test',
            'public_username'    => 'searchable-opted-out',
            'profile_visibility' => 'public',
            'allow_mentions'     => false,
            'password'           => 'secret',
        ]);

        $this->assertSame('public', $publicUser->fresh()->profile_visibility);
        $this->assertTrue((bool) $publicUser->fresh()->allow_mentions);
        $this->assertTrue($publicUser->fresh()->canViewProfile($viewer));
        $this->assertSame(1, User::query()
            ->whereNotNull('public_username')
            ->where('allow_mentions', true)
            ->where('profile_visibility', 'public')
            ->whereRaw('LOWER(public_username) LIKE ?', ['%searchable%'])
            ->count());

        $this->actingAs($viewer)
            ->getJson('/api/mentions/search?q=searchable')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $publicUser->id)
            ->assertJsonPath('data.0.username', 'searchable-person')
            ->assertJsonMissingPath('data.0.email');

        $this->postJson('/api/mentions', [
            'userId'  => $publicUser->id,
            'message' => 'A single message that mentions this user.',
        ])->assertCreated()
            ->assertJsonPath('type', 'mention');
        $this->postJson('/api/mentions', [
            'userId'  => $privateUser->id,
            'message' => 'This recipient is private.',
        ])->assertNotFound();
        $this->postJson('/api/mentions', [
            'userId'  => $optedOutUser->id,
            'message' => 'This recipient opted out.',
        ])->assertForbidden();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $publicUser->id,
            'type'          => 'agent_social',
        ]);
    }

    public function test_collaboration_requests_are_persisted_and_only_the_recipient_can_decide(): void
    {
        $requester = User::create([
            'name'            => 'Collaboration',
            'surname'         => 'Requester',
            'email'           => 'collaboration-requester@example.test',
            'public_username' => 'collaboration-requester',
            'password'        => 'secret',
        ]);
        $recipient = User::create([
            'name'               => 'Collaboration',
            'surname'            => 'Recipient',
            'email'              => 'collaboration-recipient@example.test',
            'public_username'    => 'collaboration-recipient',
            'profile_visibility' => 'public',
            'password'           => 'secret',
        ]);
        $otherUser = User::create([
            'name'     => 'Other',
            'surname'  => 'User',
            'email'    => 'collaboration-other@example.test',
            'password' => 'secret',
        ]);

        $this->actingAs($requester);
        $response = $this->postJson("/api/profile/{$recipient->id}/collaboration-requests", [
            'message' => 'Would you like to collaborate?',
        ])->assertCreated()
            ->assertJsonPath('status', 'pending');

        $requestId = $response->json('requestId');
        $this->assertDatabaseHas('user_collaboration_requests', [
            'id'           => $requestId,
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'status'       => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
        ]);
        $this->postJson("/api/profile/{$recipient->id}/collaboration-requests", [
            'message' => 'Another request',
        ])->assertStatus(409);

        $this->actingAs($otherUser)
            ->postJson("/api/collaboration-requests/{$requestId}/decision", [
                'decision' => 'accepted',
            ])->assertForbidden();

        $this->actingAs($recipient)
            ->getJson('/api/notifications?type=collaboration_request')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.collaborationRequestId', (string) $requestId);
        $this->postJson("/api/collaboration-requests/{$requestId}/decision", [
            'decision' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('user_collaboration_requests', [
            'id'          => $requestId,
            'status'      => 'accepted',
            'pending_key' => null,
            'decision_by' => $recipient->id,
        ]);
        $this->getJson('/api/notifications?type=collaboration_request')
            ->assertJsonPath('0.decision', 'accepted');

        $this->actingAs($requester)
            ->getJson('/api/notifications?type=collaboration_response')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.decision', 'accepted');

        $this->actingAs($recipient)
            ->postJson("/api/collaboration-requests/{$requestId}/decision", [
                'decision' => 'declined',
            ])->assertStatus(409);
    }
}
