<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_social_media_post_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_media_post_id');
            $table->unsignedBigInteger('social_media_platform_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('post_identifier')->nullable();
            $table->date('date');
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->unsignedBigInteger('share_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->json('last_totals')->nullable();
            $table->timestamps();

            $table->unique(['social_media_post_id', 'date'], 'ext_sm_post_daily_metrics_post_date_unique');

            $table->foreign('social_media_post_id', 'ext_sm_post_daily_metrics_post_fk')
                ->references('id')
                ->on('ext_social_media_posts')
                ->cascadeOnDelete();

            $table->foreign('social_media_platform_id', 'ext_sm_post_daily_metrics_platform_fk')
                ->references('id')
                ->on('ext_social_media_platforms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_social_media_post_daily_metrics');
    }
};
