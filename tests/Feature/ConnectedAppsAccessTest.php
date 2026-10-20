<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition;
use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ConnectedAppsAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('ext_ai_chat_pro_connectors');
        Schema::dropIfExists('usage');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
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

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('surname')->default('');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('type')->default('user');
            $table->json('entity_credits')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ext_ai_chat_pro_connectors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->json('credentials')->nullable();
            $table->json('selected_access')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        app(ConnectorRegistry::class)->register(
            'test_connector',
            TestAccessConnectorDefinition::class,
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_resource_access_is_owner_scoped_and_only_available_resources_can_be_selected(): void
    {
        $owner = $this->makeUser('connector-owner@example.test');
        $outsider = $this->makeUser('connector-outsider@example.test');
        $connector = AIChatProConnector::query()->create([
            'user_id'   => $owner->id,
            'type'      => 'test_connector',
            'is_active' => true,
        ]);

        $this->actingAs($outsider)
            ->getJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/access?json=1")
            ->assertForbidden();

        $this->postJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/toggle-pause")
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/toggle-pause")
            ->assertOk()
            ->assertJsonPath('is_paused', true);
        $this->assertTrue($connector->fresh()->is_paused);

        $this->postJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/toggle-pause")
            ->assertOk()
            ->assertJsonPath('is_paused', false);

        $this->actingAs($owner)
            ->getJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/access?json=1")
            ->assertOk()
            ->assertJsonPath('access.type', 'folders')
            ->assertJsonPath('access.options.0.id', 'folder-a');

        $this->putJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/access", [
            'selection'   => ['folder-a'],
            'recent_only' => true,
        ])->assertOk();

        $this->assertSame([
            'folders'     => ['folder-a'],
            'recent_only' => true,
        ], $connector->fresh()->selected_access);

        $this->putJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/access", [
            'selection'   => ['private-folder'],
            'recent_only' => false,
        ])->assertUnprocessable();

        $this->assertSame([
            'folders'     => ['folder-a'],
            'recent_only' => true,
        ], $connector->fresh()->selected_access);
    }

    public function test_provider_scope_failure_is_reported_instead_of_returning_empty_success(): void
    {
        $owner = $this->makeUser('connector-provider-error@example.test');
        $connector = AIChatProConnector::query()->create([
            'user_id'   => $owner->id,
            'type'      => 'test_connector',
            'is_active' => true,
        ]);
        app(ConnectorRegistry::class)->register(
            'test_connector',
            FailingAccessConnectorDefinition::class,
        );

        $this->actingAs($owner)
            ->getJson("/dashboard/user/ai-chat-pro/connectors/{$connector->id}/access?json=1")
            ->assertStatus(502)
            ->assertJsonPath('message', 'Provider resource access could not be loaded.');
    }

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'name'     => 'Connector',
            'surname'  => 'User',
            'email'    => $email,
            'password' => 'secret',
        ]);
    }
}

class TestAccessConnectorDefinition implements ConnectorDefinition
{
    public function key(): string
    {
        return 'test_connector';
    }

    public function label(): string
    {
        return 'Test connector';
    }

    public function description(): string
    {
        return 'Test connector for resource access authorization.';
    }

    public function icon(): string
    {
        return 'link';
    }

    public function redirectRoute(): string
    {
        return 'dashboard.user.ai-chat-pro.index';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function tools(AIChatProConnector $connector, string $engine): array
    {
        return [];
    }

    public function handleToolCall(string $functionName, array $arguments, AIChatProConnector $connector): array
    {
        return [];
    }

    public function systemPromptHint(AIChatProConnector $connector): ?string
    {
        return null;
    }

    public function meta(AIChatProConnector $connector): array
    {
        return ['name' => $this->label(), 'key' => $this->key(), 'icon' => $this->icon()];
    }

    public function details(): array
    {
        return ['type' => 'test', 'author' => 'Tests', 'uuid' => 'test', 'website' => 'https://example.test'];
    }

    public function permissions(): array
    {
        return ['Read selected folders.'];
    }

    public function accessOptions(AIChatProConnector $connector): array
    {
        return [
            'type'    => 'folders',
            'label'   => 'Folders',
            'options' => [
                ['id' => 'folder-a', 'name' => 'Folder A'],
                ['id' => 'folder-b', 'name' => 'Folder B'],
            ],
        ];
    }

    public function revokeToken(AIChatProConnector $connector): void {}
}

final class FailingAccessConnectorDefinition extends TestAccessConnectorDefinition
{
    public function accessOptions(AIChatProConnector $connector): array
    {
        throw new RuntimeException('Provider is unavailable.');
    }
}
