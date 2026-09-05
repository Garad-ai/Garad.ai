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
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_posts', 'agent_id')) {
                $table->bigInteger('agent_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('ext_social_media_posts', 'hashtags')) {
                $table->json('hashtags')->nullable();
            }

            if (! Schema::hasColumn('ext_social_media_posts', 'post_metrics')) {
                $table->json('post_metrics')->nullable();
            }

            if (! Schema::hasColumn('ext_social_media_posts', 'post_engagement_count')) {
                $table->bigInteger('post_engagement_count')->default(0)->nullable();
            }

            if (! Schema::hasColumn('ext_social_media_posts', 'post_engagement_rate')) {
                $table->decimal('post_engagement_rate')->default(0)->nullable();
            }

            if (! Schema::hasColumn('ext_social_media_posts', 'post_metric_at')) {
                $table->timestamp('post_metric_at')->default(0)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            //
        });
    }
};
