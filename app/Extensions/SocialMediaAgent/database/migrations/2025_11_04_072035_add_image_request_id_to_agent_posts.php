<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ext_social_media_agent_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_agent_posts', 'image_request_id')) {
                $table->string('image_request_id')->nullable()->after('media_urls');
            }

            if (! Schema::hasColumn('ext_social_media_agent_posts', 'image_status')) {
                $table->string('image_status')->default('none')->after('image_request_id'); // none, pending, completed, failed
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_social_media_agent_posts', function (Blueprint $table) {
            $table->dropColumn(['image_request_id', 'image_status']);
        });
    }
};
