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
        Schema::create('ext_blogpilot', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('name');
            $table->json('topic_options')->nullable();
            $table->json('selected_topics')->nullable();
            $table->json('post_types')->nullable();
            $table->boolean('has_image')->default(false);
            $table->boolean('has_emoji')->default(false);
            $table->boolean('has_web_search')->default(false);
            $table->boolean('has_keyword_search')->default(false);
            $table->string('language')->default('en');
            $table->string('article_length')->nullable();
            $table->string('tone')->nullable();
            $table->string('frequency')->nullable();
            $table->integer('daily_post_count')->default(1);
            $table->json('schedule_days')->nullable();
            $table->json('schedule_times')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('post_generation_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_blogpilot');
    }
};
