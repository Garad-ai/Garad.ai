<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_fall', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('video_url');
            $table->string('thumbnail_url')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('user_fall', function (Blueprint $table) {
            $table->dropColumn(['duration_seconds', 'thumbnail_url']);
        });
    }
};
