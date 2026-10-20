<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTeamInviteEmail;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AgentWorkspaceAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('usage');
        Schema::dropIfExists('users');

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('subject');
            $table->text('content');
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

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->integer('allow_seats')->default(0);
            $table->integer('used_image_credit')->default(0);
            $table->integer('word_credit')->default(0);
            $table->timestamps();
        });

        Schema::create('team_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role')->default('member');
            $table->string('email')->nullable();
            $table->string('status')->default('waiting');
            $table->boolean('allow_unlimited_credits')->default(false);
            $table->integer('remaining_images')->nullable();
            $table->integer('remaining_words')->nullable();
            $table->integer('used_image_credit')->default(0);
            $table->integer('used_word_credit')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_memberships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('workspace_type');
            $table->unsignedBigInteger('workspace_id');
            $table->string('role')->default('member');
            $table->json('permissions')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function test_workspace_membership_isolation_and_owner_controls_are_enforced(): void
    {
        Queue::fake();
        $owner = $this->makeUser('team-owner@example.test');
        $admin = $this->makeUser('team-admin@example.test');
        $member = $this->makeUser('team-member@example.test');
        $outsider = $this->makeUser('team-outsider@example.test');

        $team = Team::query()->create([
            'user_id'     => $owner->id,
            'name'        => 'Operations',
            'allow_seats' => 6,
        ]);
        $otherTeam = Team::query()->create([
            'user_id'     => $outsider->id,
            'name'        => 'Private team',
            'allow_seats' => 2,
        ]);
        $adminMembership = $team->members()->create([
            'user_id'   => $admin->id,
            'role'      => 'admin',
            'status'    => 'active',
            'joined_at' => now(),
        ]);
        $memberMembership = $team->members()->create([
            'user_id'   => $member->id,
            'role'      => 'viewer',
            'status'    => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson('/api/workspaces/teams')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Operations')
            ->assertJsonPath('0.role', 'owner')
            ->assertJsonPath('0.canManageMembers', true)
            ->assertJsonPath('0.members.0.role', 'owner');

        $this->actingAs($admin)
            ->getJson('/api/workspaces/teams')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.role', 'admin')
            ->assertJsonPath('0.canManageMembers', true)
            ->assertJsonPath('0.members.2.email', 'team-member@example.test');

        $this->actingAs($member)
            ->getJson('/api/workspaces/teams')
            ->assertOk()
            ->assertJsonPath('0.role', 'viewer')
            ->assertJsonPath('0.canManageMembers', false)
            ->assertJsonPath('0.members.1.email', null);

        $this->actingAs($outsider)
            ->getJson('/api/workspaces/teams')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $otherTeam->id);

        $this->actingAs($member)
            ->postJson("/api/workspaces/teams/{$team->id}/invitations", [
                'email' => 'new-member@example.test',
                'role'  => 'member',
            ])->assertForbidden();

        $this->actingAs($admin)
            ->postJson("/api/workspaces/teams/{$team->id}/invitations", [
                'email' => 'new-admin@example.test',
                'role'  => 'admin',
            ])->assertForbidden();

        $this->postJson("/api/workspaces/teams/{$team->id}/invitations", [
            'email' => 'invited@example.test',
            'role'  => 'viewer',
        ])->assertCreated()
            ->assertJsonPath('member.role', 'viewer')
            ->assertJsonPath('member.status', 'waiting');
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'email'   => 'invited@example.test',
            'role'    => 'viewer',
            'status'  => 'waiting',
        ]);
        Queue::assertPushed(SendTeamInviteEmail::class);

        $existingUser = $this->makeUser('existing-user@example.test');
        $existingInvitation = $this->actingAs($owner)
            ->postJson("/api/workspaces/teams/{$team->id}/invitations", [
                'email' => $existingUser->email,
                'role'  => 'member',
            ])->assertCreated()
            ->assertJsonPath('member.userId', (string) $existingUser->id)
            ->assertJsonPath('member.status', 'waiting')
            ->json('member.id');

        $this->actingAs($outsider)
            ->postJson("/api/workspaces/teams/invitations/{$existingInvitation}/decision", [
                'decision' => 'accepted',
            ])->assertNotFound();

        $this->actingAs($existingUser)
            ->getJson('/api/notifications?type=workspace_invitation')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.teamInvitationId', $existingInvitation)
            ->assertJsonPath('0.workspaceName', 'Operations');

        $this->postJson("/api/workspaces/teams/invitations/{$existingInvitation}/decision", [
            'decision' => 'accepted',
        ])->assertOk()->assertJsonPath('status', 'accepted');
        $this->assertDatabaseHas('team_members', [
            'id'      => $existingInvitation,
            'user_id' => $existingUser->id,
            'status'  => 'active',
        ]);
        $this->getJson('/api/notifications?type=workspace_invitation')
            ->assertJsonPath('0.decision', 'accepted');

        $declinedUser = $this->makeUser('declined-user@example.test');
        $declinedInvitation = $this->actingAs($owner)
            ->postJson("/api/workspaces/teams/{$team->id}/invitations", [
                'email' => $declinedUser->email,
                'role'  => 'viewer',
            ])->assertCreated()
            ->json('member.id');
        $this->actingAs($declinedUser)
            ->postJson("/api/workspaces/teams/invitations/{$declinedInvitation}/decision", [
                'decision' => 'declined',
            ])->assertOk()->assertJsonPath('status', 'declined');
        $this->postJson("/api/workspaces/teams/invitations/{$declinedInvitation}/decision", [
            'decision' => 'accepted',
        ])->assertConflict();
        $this->actingAs($owner)
            ->getJson('/api/workspaces/teams')
            ->assertOk()
            ->assertJsonPath('0.usedSeats', 4);

        $this->actingAs($outsider)
            ->patchJson("/api/workspaces/teams/{$team->id}/members/{$memberMembership->id}", [
                'role' => 'admin',
            ])->assertForbidden();

        $this->actingAs($owner)
            ->patchJson("/api/workspaces/teams/{$team->id}/members/{$memberMembership->id}", [
                'role' => 'approver',
            ])->assertOk()
            ->assertJsonPath('member.role', 'approver');

        $this->actingAs($admin)
            ->patchJson("/api/workspaces/teams/{$team->id}/members/{$adminMembership->id}", [
                'role' => 'member',
            ])->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/api/workspaces/teams/{$team->id}/members/{$memberMembership->id}")
            ->assertOk()
            ->assertJsonPath('removed', true);
        $this->assertDatabaseMissing('team_members', ['id' => $memberMembership->id]);

        $secondTeam = Team::query()->create([
            'user_id'     => $owner->id,
            'name'        => 'Customer Success',
            'allow_seats' => 2,
        ]);
        $this->actingAs($owner)
            ->postJson("/api/workspaces/teams/{$secondTeam->id}/invitations", [
                'email' => 'invited@example.test',
                'role'  => 'member',
            ])->assertCreated()
            ->assertJsonPath('member.email', 'invited@example.test');
    }

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'name'     => 'Workspace',
            'surname'  => 'User',
            'email'    => $email,
            'password' => 'secret',
        ]);
    }
}
