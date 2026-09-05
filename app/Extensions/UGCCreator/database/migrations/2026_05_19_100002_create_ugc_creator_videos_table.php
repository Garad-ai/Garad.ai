<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ugc_creator_videos')) {
            return;
        }

        Schema::create('ugc_creator_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('prompt');
            $table->string('camera_preset', 64)->nullable();
            $table->text('video_details')->nullable();
            $table->longText('final_prompt')->nullable();
            $table->json('asset_snapshot')->nullable();
            $table->json('product_asset_ids')->nullable();
            $table->json('character_asset_ids')->nullable();
            $table->boolean('audio_enabled')->default(false);
            $table->string('voice_provider', 32)->nullable(); // openai | elevenlabs
            $table->string('voice_id')->nullable();
            $table->string('voice_language', 32)->nullable();
            $table->string('audio_path')->nullable();
            $table->string('audio_url_resolved', 1024)->nullable();
            $table->string('model', 120);
            $table->string('quality', 16)->default('standard');
            $table->string('fal_request_id')->nullable()->index();
            $table->string('video_path')->nullable();
            $table->string('video_url', 1024)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('credits_used', 12, 4)->default(0);
            $table->string('status', 32)->default('queued'); // queued | processing | completed | failed
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ugc_creator_videos');
    }
};
