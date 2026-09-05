<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_dubbings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider')->default('heygen');
            $table->string('source_type')->default('upload');
            $table->string('source_url')->nullable();
            $table->string('source_file_path')->nullable();
            $table->string('target_language');
            $table->string('source_language')->nullable()->default('auto');
            $table->unsignedInteger('speaker_num')->nullable();
            $table->string('request_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->string('output_url')->nullable();
            $table->string('caption_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('title')->nullable();
            $table->string('mode')->nullable();
            $table->boolean('translate_audio_only')->default(false);
            $table->boolean('highest_resolution')->default(false);
            $table->boolean('drop_background_audio')->default(false);
            $table->boolean('use_profanity_filter')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_dubbings');
    }
};
