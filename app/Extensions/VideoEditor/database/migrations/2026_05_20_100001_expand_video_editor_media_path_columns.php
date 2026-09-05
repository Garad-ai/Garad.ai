<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_editor_media', function (Blueprint $table) {
            $table->text('path')->change();
            $table->text('thumbnail_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('video_editor_media', function (Blueprint $table) {
            $table->string('path')->change();
            $table->string('thumbnail_path')->nullable()->change();
        });
    }
};
