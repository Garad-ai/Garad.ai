<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add compound index for export job queries (active export check)
        Schema::table('video_editor_export_jobs', function (Blueprint $table) {
            $table->index(['video_editor_project_id', 'status'], 'veditor_export_project_status_idx');
        });

        // Add compound index for media listing sorted by created_at
        Schema::table('video_editor_media', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'veditor_media_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('video_editor_export_jobs', function (Blueprint $table) {
            $table->dropIndex('veditor_export_project_status_idx');
        });

        Schema::table('video_editor_media', function (Blueprint $table) {
            $table->dropIndex('veditor_media_user_created_idx');
        });
    }
};
