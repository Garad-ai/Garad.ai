<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_post_daily_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_post_daily_metrics', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('social_media_post_id');
                $table->index('agent_id', 'ext_social_media_post_daily_metrics_agent_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_social_media_post_daily_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('ext_social_media_post_daily_metrics', 'agent_id')) {
                $table->dropIndex('ext_social_media_post_daily_metrics_agent_id_index');
                $table->dropColumn('agent_id');
            }
        });
    }
};
