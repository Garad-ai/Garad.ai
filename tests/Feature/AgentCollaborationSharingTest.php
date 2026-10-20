<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AgentCollaborationSharingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'ext_ai_chat_pro_shares',
            'ext_ai_chat_pro_message_mentions',
            'ext_ai_chat_pro_agent_conversations',
            'ext_ai_agent_workflow_run_steps',
            'ext_ai_agent_workflow_runs',
            'ext_ai_agent_workflows',
            'user_openai_chat_messages',
            'user_openai_chat',
            'usage',
            'notifications',
            'app_settings',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('password');
            $table->string('type')->default('user');
            $table->json('entity_credits')->nullable();
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
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
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
        Schema::create('user_openai_chat', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });
        Schema::create('user_openai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_openai_chat_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('input')->nullable();
            $table->text('response')->nullable();
            $table->text('output')->nullable();
            $table->timestamps();
        });
        Schema::create('ext_ai_chat_pro_agent_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('chat_id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });
        Schema::create('ext_ai_chat_pro_message_mentions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('chat_id')->index();
            $table->unsignedBigInteger('sender_id')->index();
            $table->unsignedBigInteger('recipient_id')->nullable()->index();
            $table->string('recipient_username', 30);
            $table->timestamps();
            $table->unique(['message_id', 'recipient_id']);
        });
        Schema::create('ext_ai_chat_pro_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_part', 20)->default('item');
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('recipient_id');
            $table->string('access_level', 20)->default('view');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ext_ai_agent_workflows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('ext_ai_agent_workflow_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_id')->nullable();
            $table->string('status')->default('running');
            $table->json('trigger_data')->nullable();
            $table->json('steps_output')->nullable();
            $table->json('run_context')->nullable();
            $table->unsignedInteger('current_step_order')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ext_ai_agent_workflow_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_run_id');
            $table->unsignedInteger('step_index');
            $table->string('type');
            $table->string('status');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamps();
        });
    }

    public function test_structured_mentions_persist_once_notify_only_selected_users_and_restore_for_owner(): void
    {
        $sender = $this->makeUser('Mention Sender', 'mention-sender', 'mention-sender@example.test');
        $first = $this->makeUser('First Person', 'first-person', 'first-person@example.test', true);
        $second = $this->makeUser('Second Person', 'second-person', 'second-person@example.test', true);
        $private = $this->makeUser('Private Person', 'private-person', 'private-person@example.test');
        $private->forceFill(['profile_visibility' => 'private'])->save();
        $optedOut = $this->makeUser('Opted Out', 'opted-out', 'opted-out@example.test', true);
        $optedOut->forceFill(['allow_mentions' => false])->save();

        $chatId = $this->makeAgentChat($sender);
        $messageId = $this->makeMessage(
            $chatId,
            $sender,
            'Please review @first-person and @second-person. Confidential prompt.',
            'Private answer.',
        );

        $this->actingAs($sender)
            ->postJson('/api/agent-mentions', [
                'messageId'    => $messageId,
                'recipientIds' => [$first->id, $second->id],
            ])
            ->assertCreated()
            ->assertJsonPath('saved', true);

        $this->postJson('/api/agent-mentions', [
            'messageId'    => $messageId,
            'recipientIds' => [$first->id, $second->id],
        ])->assertCreated();

        $this->assertDatabaseCount('ext_ai_chat_pro_message_mentions', 2);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $private->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $optedOut->id,
        ]);
        $this->assertStringNotContainsString(
            'Confidential prompt',
            json_encode($first->notifications()->first()->data, JSON_THROW_ON_ERROR),
        );

        $this->getJson('/api/agent-mentions/messages?message_ids=' . $messageId)
            ->assertOk()
            ->assertJsonCount(2, 'data.' . $messageId)
            ->assertJsonFragment(['userId' => (string) $first->id, 'username' => 'first-person'])
            ->assertJsonFragment(['userId' => (string) $second->id, 'username' => 'second-person']);

        $this->postJson('/api/agent-mentions', [
            'messageId'    => $messageId,
            'recipientIds' => [$private->id],
        ])->assertUnprocessable();
        $this->postJson('/api/agent-mentions', [
            'messageId'    => $messageId,
            'recipientIds' => [$optedOut->id],
        ])->assertUnprocessable();
        $this->postJson('/api/agent-mentions', [
            'messageId'    => $messageId,
            'recipientIds' => [$first->id, $first->id],
        ])->assertUnprocessable();
    }

    public function test_message_share_does_not_reveal_neighbors_and_access_is_recipient_scoped_revocable(): void
    {
        $owner = $this->makeUser('Resource Owner', 'resource-owner', 'resource-owner@example.test', true);
        $recipient = $this->makeUser('Share Recipient', 'share-recipient', 'share-recipient@example.test', true);
        $other = $this->makeUser('Other Recipient', 'other-recipient', 'other-recipient@example.test', true);
        $chatId = $this->makeAgentChat($owner);
        $this->makeMessage($chatId, $owner, 'Private adjacent prompt before.', 'Private adjacent answer before.');
        $selectedId = $this->makeMessage($chatId, $owner, 'Selected question.', 'Only the selected answer.');
        $this->makeMessage($chatId, $owner, 'Private adjacent prompt after.', 'Private adjacent answer after.');

        $response = $this->actingAs($owner)
            ->postJson('/api/agent-shares', [
                'resourceType' => 'message',
                'resourceId'   => $selectedId,
                'resourcePart' => 'assistant',
                'recipientId'  => $recipient->id,
                'accessLevel'  => 'view',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'active');
        $token = (string) parse_url($response->json('url'), PHP_URL_QUERY);
        parse_str($token, $query);
        $token = (string) ($query['share'] ?? '');
        $this->assertSame(64, strlen($token));
        $shareId = $response->json('id');
        $this->assertDatabaseMissing('ext_ai_chat_pro_shares', [
            'id'         => $shareId,
            'token_hash' => $token,
        ]);

        $this->actingAs($recipient)
            ->getJson('/api/agent-shares/' . $token)
            ->assertOk()
            ->assertJsonPath('resource.content', 'Only the selected answer.')
            ->assertJsonMissing(['Private adjacent answer before.'])
            ->assertJsonMissing(['Private adjacent answer after.']);

        $this->actingAs($other)
            ->getJson('/api/agent-shares/' . $token)
            ->assertNotFound();
        $this->actingAs($other)
            ->deleteJson('/api/agent-shares/' . $shareId)
            ->assertNotFound();

        $this->actingAs($owner)
            ->deleteJson('/api/agent-shares/' . $shareId)
            ->assertOk()
            ->assertJsonPath('status', 'revoked');
        $this->actingAs($recipient)
            ->getJson('/api/agent-shares/' . $token)
            ->assertNotFound();

        $expired = $this->actingAs($owner)
            ->postJson('/api/agent-shares', [
                'resourceType' => 'message',
                'resourceId'   => $selectedId,
                'resourcePart' => 'assistant',
                'recipientId'  => $recipient->id,
                'accessLevel'  => 'view',
            ])
            ->assertCreated();
        parse_str((string) parse_url($expired->json('url'), PHP_URL_QUERY), $expiredQuery);
        $expiredToken = (string) ($expiredQuery['share'] ?? '');
        \Illuminate\Support\Facades\DB::table('ext_ai_chat_pro_shares')
            ->where('id', $expired->json('id'))
            ->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($owner)
            ->getJson('/api/agent-shares?resourceType=message&resourceId=' . $selectedId . '&resourcePart=assistant')
            ->assertOk()
            ->assertJsonFragment([
                'id'     => (string) $expired->json('id'),
                'status' => 'expired',
            ]);
        $this->actingAs($recipient)
            ->getJson('/api/agent-shares/' . $expiredToken)
            ->assertNotFound();
        $this->actingAs($owner)
            ->getJson('/api/agent-shares/not-a-token')
            ->assertNotFound();
    }

    public function test_conversation_and_task_shares_expose_only_authorized_safe_fields(): void
    {
        $owner = $this->makeUser('Task Owner', 'task-owner', 'task-owner@example.test', true);
        $recipient = $this->makeUser('Task Recipient', 'task-recipient', 'task-recipient@example.test', true);
        $chatId = $this->makeAgentChat($owner);
        $this->makeMessage($chatId, $owner, 'Conversation prompt one.', 'Conversation result one.');
        $this->makeMessage($chatId, $owner, 'Conversation prompt two.', 'Conversation result two.');

        $conversationResponse = $this->actingAs($owner)
            ->postJson('/api/agent-shares', [
                'resourceType' => 'conversation',
                'resourceId'   => $chatId,
                'recipientId'  => $recipient->id,
                'accessLevel'  => 'view',
            ])
            ->assertCreated();
        parse_str((string) parse_url($conversationResponse->json('url'), PHP_URL_QUERY), $conversationQuery);
        $conversationToken = (string) ($conversationQuery['share'] ?? '');
        $this->actingAs($recipient)
            ->getJson('/api/agent-shares/' . $conversationToken)
            ->assertOk()
            ->assertJsonCount(4, 'resource.messages');

        $workflowId = \Illuminate\Support\Facades\DB::table('ext_ai_agent_workflows')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Quarterly report task',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $runId = \Illuminate\Support\Facades\DB::table('ext_ai_agent_workflow_runs')->insertGetId([
            'workflow_id' => $workflowId,
            'status' => 'completed',
            'steps_output' => json_encode([['output' => ['api_token' => 'do-not-share']]], JSON_THROW_ON_ERROR),
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('ext_ai_agent_workflow_run_steps')->insert([
            'workflow_run_id' => $runId,
            'step_index' => 0,
            'type' => 'generate_report',
            'status' => 'completed',
            'input' => json_encode(['secret' => 'hidden input'], JSON_THROW_ON_ERROR),
            'output' => json_encode(['secret' => 'hidden output'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $taskResponse = $this->actingAs($owner)
            ->postJson('/api/agent-shares', [
                'resourceType' => 'task',
                'resourceId'   => $runId,
                'recipientId'  => $recipient->id,
                'accessLevel'  => 'view',
            ])
            ->assertCreated();
        parse_str((string) parse_url($taskResponse->json('url'), PHP_URL_QUERY), $taskQuery);
        $taskToken = (string) ($taskQuery['share'] ?? '');
        $this->actingAs($recipient)
            ->getJson('/api/agent-shares/' . $taskToken)
            ->assertOk()
            ->assertJsonPath('resource.status', 'completed')
            ->assertJsonPath('resource.steps.0.type', 'generate_report')
            ->assertJsonMissing(['do-not-share'])
            ->assertJsonMissing(['hidden output']);
    }

    private function makeUser(
        string $name,
        string $username,
        string $email,
        bool $public = false,
    ): User {
        return User::query()->create([
            'name'               => $name,
            'surname'            => '',
            'email'              => $email,
            'public_username'    => $username,
            'profile_visibility' => $public ? 'public' : 'private',
            'password'           => 'secret',
            'type'               => 'user',
        ]);
    }

    private function makeAgentChat(User $owner): int
    {
        $chatId = \Illuminate\Support\Facades\DB::table('user_openai_chat')->insertGetId([
            'user_id'    => $owner->id,
            'title'      => 'Agent conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('ext_ai_chat_pro_agent_conversations')->insert([
            'chat_id'    => $chatId,
            'user_id'    => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $chatId;
    }

    private function makeMessage(int $chatId, User $sender, string $input, string $output): int
    {
        return \Illuminate\Support\Facades\DB::table('user_openai_chat_messages')->insertGetId([
            'user_openai_chat_id' => $chatId,
            'user_id'             => $sender->id,
            'input'               => $input,
            'response'            => $output,
            'output'              => $output,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }
}
