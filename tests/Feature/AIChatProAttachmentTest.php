<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Extensions\AIChatPro\System\Models\AIChatProArtifact;
use App\Extensions\AIChatPro\System\Models\AIChatProAttachment;
use App\Extensions\AIChatPro\System\Services\AIChatProStorageQuotaService;
use App\Extensions\AIChatPro\System\Services\GaradArtifactService;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use UnexpectedValueException;

final class StorageQuotaTestUser extends User
{
    protected $table = 'users';

    public function activePlan()
    {
        return null;
    }
}

final class AIChatProAttachmentTest extends TestCase
{
    private array $writtenPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default'                                    => 'sqlite',
            'database.connections.sqlite.database'                => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('ai_chat_pro_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('storage_disk', 40);
            $table->string('storage_path');
            $table->char('checksum', 64);
            $table->string('upload_status', 20);
            $table->string('processing_status', 20);
            $table->string('extraction_status', 20);
            $table->timestamps();
        });

        Schema::create('ai_chat_pro_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_openai_chat_id')->nullable();
            $table->string('type', 32);
            $table->string('title');
            $table->string('original_name');
            $table->string('mime_type', 160);
            $table->string('storage_disk', 40);
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_chat_pro_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('user_openai_chat_id')->nullable();
            $table->string('reference_url');
            $table->timestamps();
        });

        Schema::create('user_openai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('input')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $disk = Storage::disk('ai_chat_pro_attachments');
        foreach ($this->writtenPaths as $path) {
            $disk->deleteDirectory(dirname($path));
            $ownerDirectory = dirname(dirname($disk->path($path)));
            if (is_dir($ownerDirectory)) {
                @rmdir($ownerDirectory);
            }
        }

        parent::tearDown();
    }

    public function test_upload_stays_on_private_storage_and_repeating_same_id_is_idempotent(): void
    {
        $owner = $this->makeUser('owner');
        $uuid = Str::uuid()->toString();
        $payload = $this->payload($uuid);

        $first = $this->actingAs($owner)->postJson('/dashboard/user/ai-chat-pro/attachments', $payload);
        $second = $this->actingAs($owner)->postJson('/dashboard/user/ai-chat-pro/attachments', $payload);

        $first->assertCreated()->assertJsonPath('files.0.path', '/dashboard/user/ai-chat-pro/attachments/' . $uuid);
        $second->assertOk()->assertJsonPath('idempotent', true);
        self::assertSame(1, AIChatProAttachment::query()->count());

        $attachment = AIChatProAttachment::query()->firstOrFail();
        $disk = Storage::disk('ai_chat_pro_attachments');
        $this->writtenPaths[] = $attachment->storage_path;
        self::assertTrue($disk->exists($attachment->storage_path));
        self::assertSame(strlen("%PDF-1.4\n%%EOF"), $disk->size($attachment->storage_path));
        self::assertSame('ai_chat_pro_attachments', $attachment->storage_disk);
        self::assertStringStartsWith(storage_path('app/private'), config('filesystems.disks.ai_chat_pro_attachments.root'));
        self::assertFalse(str_starts_with($disk->path($attachment->storage_path), public_path()));
    }

    public function test_another_owner_cannot_reuse_an_attachment_id_or_download_the_attachment(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $uuid = Str::uuid()->toString();
        $payload = $this->payload($uuid);

        $this->actingAs($owner)->postJson('/dashboard/user/ai-chat-pro/attachments', $payload)->assertCreated();
        $attachment = AIChatProAttachment::query()->firstOrFail();
        $this->writtenPaths[] = $attachment->storage_path;

        $this->actingAs($other)
            ->postJson('/dashboard/user/ai-chat-pro/attachments', $payload)
            ->assertStatus(409);
        $this->actingAs($other)
            ->get('/dashboard/user/ai-chat-pro/attachments/' . $uuid)
            ->assertNotFound();

        self::assertSame(1, AIChatProAttachment::query()->count());
    }

    public function test_library_includes_uploaded_files_and_deleting_one_frees_storage(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $uuid = Str::uuid()->toString();
        $this->actingAs($owner)
            ->postJson('/dashboard/user/ai-chat-pro/attachments', $this->payload($uuid))
            ->assertCreated();
        $attachment = AIChatProAttachment::query()->where('uuid', $uuid)->firstOrFail();
        $this->writtenPaths[] = $attachment->storage_path;

        $this->getJson('/api/library')
            ->assertOk()
            ->assertJsonPath('data.0.id', $uuid)
            ->assertJsonPath('data.0.kind', 'pdf')
            ->assertJsonPath('data.0.size', strlen("%PDF-1.4\n%%EOF"));

        AIChatProArtifact::query()->create([
            'user_id'       => $owner->id,
            'type'          => 'txt',
            'title'         => 'Saved answer',
            'original_name' => 'saved-answer.txt',
            'mime_type'     => 'text/plain',
            'storage_disk'  => 'local',
            'storage_path'  => 'saved-answer.txt',
            'file_size'     => 41,
        ]);
        $this->getJson('/api/library')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'saved-answer.txt']);

        self::assertSame(
            strlen("%PDF-1.4\n%%EOF") + 41,
            app(AIChatProStorageQuotaService::class)->usedBytes((int) $owner->id)
        );

        DB::table('ai_chat_pro_documents')->insert([
            'user_id'       => $owner->id,
            'reference_url' => '/dashboard/user/ai-chat-pro/attachments/' . $uuid,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($other)
            ->deleteJson('/dashboard/user/ai-chat-pro/attachments/' . $uuid)
            ->assertNotFound();

        $this->actingAs($owner)
            ->deleteJson('/dashboard/user/ai-chat-pro/attachments/' . $uuid)
            ->assertOk()
            ->assertJsonPath('ok', true);

        self::assertDatabaseMissing('ai_chat_pro_attachments', ['uuid' => $uuid]);
        self::assertDatabaseMissing('ai_chat_pro_documents', ['reference_url' => '/dashboard/user/ai-chat-pro/attachments/' . $uuid]);
        self::assertFalse(Storage::disk('ai_chat_pro_attachments')->exists($attachment->storage_path));
        self::assertSame(41, app(AIChatProStorageQuotaService::class)->usedBytes((int) $owner->id));
    }

    public function test_plan_names_use_the_approved_storage_limits(): void
    {
        $storageQuota = app(AIChatProStorageQuotaService::class);
        $limits = [
            'Basic (free)' => 2_147_483_648,
            'Standard'     => 7_516_192_768,
            'Professional' => 21_474_836_480,
            'Business'     => 107_374_182_400,
        ];

        foreach ($limits as $name => $expectedBytes) {
            $plan = new Plan;
            $plan->name = $name;
            self::assertSame($expectedBytes, $storageQuota->limitBytesForPlan($plan));
        }

        self::assertSame(2_147_483_648, $storageQuota->limitBytesForPlan(null));

        $unknownPlan = new Plan;
        $unknownPlan->name = 'Enterprise';
        $this->expectException(UnexpectedValueException::class);
        $storageQuota->limitBytesForPlan($unknownPlan);
    }

    public function test_usage_endpoint_reports_saved_library_bytes_and_basic_capacity(): void
    {
        $owner = $this->makeUser('owner');
        AIChatProAttachment::query()->create([
            'uuid'              => Str::uuid()->toString(),
            'user_id'           => $owner->id,
            'original_name'     => 'brief.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => 17,
            'storage_disk'      => 'ai_chat_pro_attachments',
            'storage_path'      => $owner->id . '/brief.pdf',
            'checksum'          => hash('sha256', 'brief'),
            'upload_status'     => 'uploaded',
            'processing_status' => 'pending',
            'extraction_status' => 'pending',
        ]);
        AIChatProArtifact::query()->create([
            'user_id'       => $owner->id,
            'type'          => 'txt',
            'title'         => 'Saved answer',
            'original_name' => 'answer.txt',
            'mime_type'     => 'text/plain',
            'storage_disk'  => 'local',
            'storage_path'  => 'answer.txt',
            'file_size'     => 23,
        ]);

        $this->actingAs($owner)
            ->getJson('/api/usage')
            ->assertOk()
            ->assertJsonPath('storageBytes.used', 40)
            ->assertJsonPath('storageBytes.limit', 2_147_483_648);
    }

    public function test_deleting_an_artifact_removes_its_storage_and_restores_capacity(): void
    {
        $owner = $this->makeUser('owner');
        // The host license gate redirects in this test environment; exercise storage deletion directly.
        $this->withoutMiddleware();
        Storage::fake('local');
        Storage::disk('local')->put('library/file.txt', 'saved file');
        $artifact = AIChatProArtifact::query()->create([
            'user_id'       => $owner->id,
            'type'          => 'txt',
            'title'         => 'Saved answer',
            'original_name' => 'saved-answer.txt',
            'mime_type'     => 'text/plain',
            'storage_disk'  => 'local',
            'storage_path'  => 'library/file.txt',
            'file_size'     => strlen('saved file'),
        ]);

        self::assertSame(
            strlen('saved file'),
            app(AIChatProStorageQuotaService::class)->usedBytes((int) $owner->id)
        );

        $this->actingAs($owner)
            ->deleteJson('/api/library/' . $artifact->id)
            ->assertOk()
            ->assertJsonPath('ok', true);

        self::assertDatabaseMissing('ai_chat_pro_artifacts', ['id' => $artifact->id]);
        self::assertFalse(Storage::disk('local')->exists('library/file.txt'));
        self::assertSame(0, app(AIChatProStorageQuotaService::class)->usedBytes((int) $owner->id));
    }

    public function test_full_basic_storage_rejects_a_new_upload_without_writing_it(): void
    {
        $owner = $this->makeUser('owner');
        AIChatProAttachment::query()->create([
            'uuid'              => Str::uuid()->toString(),
            'user_id'           => $owner->id,
            'original_name'     => 'existing.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => 2_147_483_648,
            'storage_disk'      => 'ai_chat_pro_attachments',
            'storage_path'      => $owner->id . '/existing.pdf',
            'checksum'          => hash('sha256', 'existing'),
            'upload_status'     => 'uploaded',
            'processing_status' => 'pending',
            'extraction_status' => 'pending',
        ]);
        $uuid = Str::uuid()->toString();

        $this->actingAs($owner)
            ->postJson('/dashboard/user/ai-chat-pro/attachments', $this->payload($uuid))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('storage');

        self::assertDatabaseMissing('ai_chat_pro_attachments', ['uuid' => $uuid]);
        self::assertSame(1, AIChatProAttachment::query()->count());
    }

    public function test_generated_artifacts_are_rejected_when_storage_is_full(): void
    {
        $owner = $this->makeUser('owner');
        AIChatProAttachment::query()->create([
            'uuid'              => Str::uuid()->toString(),
            'user_id'           => $owner->id,
            'original_name'     => 'existing.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => 2_147_483_648,
            'storage_disk'      => 'ai_chat_pro_attachments',
            'storage_path'      => $owner->id . '/existing.pdf',
            'checksum'          => hash('sha256', 'existing'),
            'upload_status'     => 'uploaded',
            'processing_status' => 'pending',
            'extraction_status' => 'pending',
        ]);
        $this->actingAs($owner);

        try {
            app(GaradArtifactService::class)->importBinary(
                'New file',
                'new.txt',
                'txt',
                'text/plain',
                'new file'
            );
            self::fail('Expected a full-storage validation error.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('storage', $exception->errors());
        }

        self::assertSame(0, AIChatProArtifact::query()->count());
    }

    private function makeUser(string $suffix): StorageQuotaTestUser
    {
        // UserObserver writes platform tables (usage, app_settings, credits) unrelated to attachments.
        return StorageQuotaTestUser::withoutEvents(fn () => StorageQuotaTestUser::query()->create([
            'name'     => $suffix,
            'email'    => $suffix . '@example.test',
            'password' => 'password',
        ]));
    }

    private function payload(string $uuid): array
    {
        $pdf = "%PDF-1.4\n%%EOF";

        return [
            'attachment_id' => $uuid,
            'files'         => [[
                'name' => 'brief.pdf',
                'data' => 'data:application/pdf;base64,' . base64_encode($pdf),
            ]],
        ];
    }
}
