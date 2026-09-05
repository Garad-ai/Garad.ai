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

        if (Schema::hasTable('social_media_agents')) {
            return;
        }

        Schema::create('ext_social_media_agents', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('name');
            $table->json('platform_ids')->nullable(); // IDs from ext_social_media_platforms

            // Site Information (Step 2)
            $table->string('site_url')->nullable();
            $table->text('site_description')->nullable();
            $table->json('scraped_content')->nullable(); // Scraped pages content

            // Target Audience (Step 3)
            $table->json('target_audience')->nullable(); // AI generated targets

            // Post Configuration (Step 4)
            $table->json('post_types')->nullable(); // ['carousel', 'single_image', 'text', 'video']
            $table->string('tone')->nullable(); // 'friendly', 'professional', 'casual'
            $table->json('cta_templates')->nullable(); // Call-to-action templates
            $table->json('categories')->nullable(); // Post categories
            $table->json('goals')->nullable(); // Marketing goals
            $table->text('branding_description')->nullable();
            $table->string('creativity')->nullable(); // Creativity level
            $table->integer('hashtag_count')->default(0);
            $table->integer('approximate_words')->default(20);

            // Schedule & Language (Step 5)
            $table->string('language')->default('en');
            $table->json('schedule_days')->nullable(); // ['Monday', 'Tuesday', ...]
            $table->json('schedule_times')->nullable(); // [{'start': '12:00', 'end': '14:00'}]
            $table->integer('daily_post_count')->default(1);
            $table->integer('reserved_post_day')->default(7); // Days to keep posts reserved
            $table->integer('start_train_post_count')->default(30); // Initial training posts

            // Additional Settings
            $table->boolean('has_image')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Additional custom settings

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_social_media_agents');
    }
};
