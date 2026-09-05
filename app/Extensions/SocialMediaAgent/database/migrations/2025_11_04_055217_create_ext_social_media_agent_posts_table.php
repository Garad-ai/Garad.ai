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

        if (Schema::hasTable('ext_social_media_agent_posts')) {
            return;
        }

        Schema::create('ext_social_media_agent_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')
                ->constrained('ext_social_media_agents')
                ->cascadeOnDelete();
            $table->foreignId('platform_id')
                ->constrained('ext_social_media_platforms')
                ->cascadeOnDelete();

            // Post Content
            $table->text('content');
            $table->json('media_urls')->nullable(); // Array of image/video URLs
            $table->string('post_type')->nullable(); // 'carousel', 'single_image', 'text', 'video'

            // Scheduling & Status
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'scheduled',
                'published',
                'failed',
            ])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // AI Metadata
            $table->json('ai_metadata')->nullable(); // AI generation details, prompts, etc.
            $table->json('hashtags')->nullable(); // Generated hashtags
            $table->text('error_message')->nullable(); // For failed posts

            // Publishing Details
            $table->string('platform_post_id')->nullable(); // ID from social media platform
            $table->json('platform_response')->nullable(); // Response from platform API

            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['agent_id', 'status']);
            $table->index(['scheduled_at', 'status']);
            $table->index('platform_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_social_media_agent_posts');
    }
};
