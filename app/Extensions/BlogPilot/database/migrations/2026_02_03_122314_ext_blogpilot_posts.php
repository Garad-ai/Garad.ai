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
        Schema::create('ext_blogpilot_posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->unsignedBigInteger('agent_id');
            $table->text('title');
            $table->text('content');
            $table->text('thumbnail');
            $table->json('tags')->nullable();
            $table->json('categories')->nullable();
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

            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['agent_id', 'status']);
            $table->index(['scheduled_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_blogpilot_posts');
    }
};
