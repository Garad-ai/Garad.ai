<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_agent_posts', function (Blueprint $table) {
            $table->json('video_urls')->nullable()->after('media_urls');
            $table->string('video_request_id')->nullable()->after('video_urls');
            $table->string('video_status')->default('none')->after('video_request_id'); // none, pending, generating, completed, failed
        });
    }

    public function down(): void
    {
        Schema::table('ext_social_media_agent_posts', function (Blueprint $table) {
            $table->dropColumn(['video_urls', 'video_request_id', 'video_status']);
        });
    }
};
