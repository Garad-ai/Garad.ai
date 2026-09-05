<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_editor_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('video_editor_project_id')->nullable()->constrained('video_editor_projects')->nullOnDelete();
            $table->string('type');
            $table->string('filename');
            $table->string('original_filename');
            $table->text('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->integer('duration_ms')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->text('thumbnail_path')->nullable();
            $table->string('source')->default('upload');
            $table->string('source_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('video_editor_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_editor_media');
    }
};
