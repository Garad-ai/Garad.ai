<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_editor_export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('video_editor_project_id')->constrained('video_editor_projects')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('format')->default('mp4');
            $table->string('resolution')->default('1080p');
            $table->integer('progress')->default(0);
            $table->string('output_path')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('credits_charged')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_editor_export_jobs');
    }
};
