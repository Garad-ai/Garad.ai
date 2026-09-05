<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_captions_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_demo')->default(false);
            $table->string('title')->nullable();
            $table->string('template_id')->nullable();
            $table->string('template_name')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_file_path')->nullable();
            $table->string('captions_video_id')->nullable();
            $table->string('status')->default('uploading');
            $table->text('error_message')->nullable();
            $table->text('output_url')->nullable();
            $table->string('local_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('usage_deducted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_captions_videos');
    }
};
