<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_editor_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('aspect_ratio')->default('16:9');
            $table->integer('width')->default(1920);
            $table->integer('height')->default(1080);
            $table->integer('fps')->default(30);
            $table->integer('duration_ms')->default(0);
            $table->json('timeline_data')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_editor_projects');
    }
};
