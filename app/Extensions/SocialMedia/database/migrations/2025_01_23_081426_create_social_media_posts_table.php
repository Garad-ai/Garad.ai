<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->bigInteger('company_id')->nullable();
            $table->bigInteger('campaign_id')->nullable();
            $table->bigInteger('social_media_platform_id')->nullable();
            $table->boolean('is_personalized_content')->default(false);
            $table->string('tone')->nullable();
            $table->longText('content')->nullable();
            $table->string('link')->nullable();
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->boolean('is_repeated')->default(false);
            $table->string('repeat_period')->default(false);
            $table->date('repeat_start_date')->nullable();
            $table->time('repeat_time')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ext_social_media_shared_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('social_media_post_id')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_social_media_posts');
        Schema::dropIfExists('ext_social_media_shared_logs');
    }
};
