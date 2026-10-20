<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\OpenRouter\System\Services\RouterAiService;
use Illuminate\Http\Client\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnthropicHaikuReplacementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('entities', static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('engine');
            $table->string('title');
            $table->string('selected_title')->nullable();
            $table->string('status');
            $table->boolean('is_selected')->default(false);
            $table->string('image')->nullable();
            $table->timestamps();
        });
        Schema::create('tokens', static function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();
        });
        Schema::create('ai_chat_model_plans', static function (Blueprint $table): void {
            $table->id();
            $table->integer('plan_id');
            $table->integer('entity_id');
        });
        Schema::create('app_settings', static function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->text('value');
        });
        DB::table('app_settings')->insert([
            'key' => 'anthropic_max_output_length',
            'value' => '4096',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('ai_chat_model_plans');
        Schema::dropIfExists('tokens');
        Schema::dropIfExists('entities');

        parent::tearDown();
    }

    #[Test]
    public function it_uses_the_replacement_model_and_omits_sampling_parameters(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id'         => 'msg_test',
                'type'       => 'message',
                'role'       => 'assistant',
                'content'    => [['type' => 'text', 'text' => 'ok']],
                'model'      => 'claude-haiku-4-5-20251001',
                'stop_reason'=> 'end_turn',
            ]),
        ]);

        $response = app(AnthropicService::class)
            ->setKey('test-key')
            ->setModel(EntityEnum::CLAUDE_HAIKU_4_5->value)
            ->setMessages([['role' => 'user', 'content' => 'Reply with ok.']])
            ->stream();

        self::assertSame(200, $response->status());
        Http::assertSent(static function (Request $request): bool {
            $body = $request->data();

            return $body['model'] === 'claude-haiku-4-5-20251001'
                && ! array_key_exists('temperature', $body)
                && ! array_key_exists('top_p', $body);
        });
    }

    #[Test]
    public function retired_anthropic_selection_ids_are_sent_to_the_official_replacements(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(['id' => 'msg_test']),
        ]);

        app(AnthropicService::class)
            ->setKey('test-key')
            ->setModel('claude-3-7-sonnet-20250219')
            ->setMessages([['role' => 'user', 'content' => 'Reply with ok.']])
            ->stream();

        Http::assertSent(static fn (Request $request): bool => $request->data()['model'] === 'claude-sonnet-5-5');
    }

    #[Test]
    public function retired_openrouter_haiku_routes_use_the_available_replacement(): void
    {
        Http::fake([
            RouterAiService::BASE_URL => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ]),
        ]);

        $service = app(RouterAiService::class);
        $service->responseWithHistory(
            [['role' => 'user', 'content' => 'Reply with ok.']],
            'anthropic/claude-3-5-haiku-20241022',
        );
        $service->response('Reply with ok.', 'anthropic/claude-3-5-haiku:beta');

        Http::assertSent(static fn (Request $request): bool => $request->data()['model'] === 'anthropic/claude-haiku-4.5');
        self::assertSame(2, Http::recorded()->count());
    }

    #[Test]
    public function an_empty_refusal_is_reported_without_replacing_provider_refusal_text(): void
    {
        self::assertSame(
            'Claude declined to respond to this request.',
            AnthropicService::refusalFallback('refusal', ''),
        );
        self::assertNull(AnthropicService::refusalFallback('end_turn', ''));
        self::assertNull(AnthropicService::refusalFallback('refusal', 'I cannot help with that request.'));
    }

    #[Test]
    public function retired_haiku_ids_are_not_active_entity_enum_values(): void
    {
        self::assertNull(EntityEnum::tryFrom('claude-3-haiku-20240307'));
        self::assertNull(EntityEnum::tryFrom('claude-3-haiku-20241022'));
        self::assertSame('Claude Haiku 4.5', EntityEnum::CLAUDE_HAIKU_4_5->label());
    }

    #[Test]
    public function migration_moves_active_haiku_selection_and_access_to_the_replacement(): void
    {
        DB::table('entities')->insert([
            [
                'id' => 14,
                'key' => 'claude-3-haiku-20241022',
                'engine' => 'anthropic',
                'title' => 'Claude 3.5 Haiku',
                'selected_title' => 'Claude 3.5 Haiku',
                'status' => 'enabled',
                'is_selected' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 15,
                'key' => 'claude-3-haiku-20240307',
                'engine' => 'anthropic',
                'title' => 'Claude 3 Haiku',
                'selected_title' => 'Claude 3 Haiku',
                'status' => 'enabled',
                'is_selected' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('tokens')->insert([
            ['entity_id' => 14, 'type' => 'word', 'created_at' => now(), 'updated_at' => now()],
            ['entity_id' => 15, 'type' => 'word', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('ai_chat_model_plans')->insert([
            ['entity_id' => 15, 'plan_id' => 7],
        ]);
        DB::table('app_settings')->insert([
            [
                'key' => 'anthropic_default_model',
                'value' => 'claude-3-haiku-20240307',
            ],
            [
                'key' => 'anthropic_bedrock_model',
                'value' => 'anthropic.claude-3-sonnet-20240229-v1:0',
            ],
        ]);

        $migration = require base_path('database/migrations/2026_10_17_000000_migrate_retired_anthropic_haiku_models.php');
        $migration->up();

        $replacement = DB::table('entities')->where('key', EntityEnum::CLAUDE_HAIKU_4_5->value)->first();
        self::assertNotNull($replacement);
        self::assertSame('enabled', $replacement->status);
        self::assertSame(1, (int) $replacement->is_selected);
        self::assertSame('Claude Haiku 4.5', $replacement->selected_title);
        self::assertFalse(DB::table('entities')->whereIn('key', [
            'claude-3-haiku-20240307',
            'claude-3-haiku-20241022',
        ])->where('status', 'enabled')->exists());
        self::assertTrue(DB::table('ai_chat_model_plans')
            ->where('entity_id', $replacement->id)
            ->where('plan_id', 7)
            ->exists());
        self::assertTrue(DB::table('tokens')
            ->where('entity_id', $replacement->id)
            ->where('type', 'word')
            ->exists());
        self::assertSame(
            EntityEnum::CLAUDE_HAIKU_4_5->value,
            DB::table('app_settings')->where('key', 'anthropic_default_model')->value('value'),
        );
        self::assertSame(
            'anthropic.claude-sonnet-5-5',
            DB::table('app_settings')->where('key', 'anthropic_bedrock_model')->value('value'),
        );
    }

    #[Test]
    public function retired_anthropic_selector_labels_disclose_the_request_replacement(): void
    {
        DB::table('entities')->insert([
            'key' => 'claude-3-7-sonnet-20250219',
            'engine' => 'anthropic',
            'title' => 'Claude 3.7 Sonnet',
            'selected_title' => 'Claude 3.7 Sonnet',
            'status' => 'enabled',
            'is_selected' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_10_17_000001_annotate_retired_anthropic_model_aliases.php');
        $migration->up();

        self::assertSame(
            'Claude 3.7 Sonnet (routes to Claude Sonnet 5.5)',
            DB::table('entities')->where('key', 'claude-3-7-sonnet-20250219')->value('selected_title'),
        );
    }
}
