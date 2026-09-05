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
            $table->string('image_model')->nullable()->after('image_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_social_media_agent_posts', function (Blueprint $table) {
            $table->dropColumn('image_model');
        });
    }
};
