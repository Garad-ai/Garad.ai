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
        Schema::create('ext_marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('template_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->text('content')->nullable();
            $table->string('image')->nullable();
            $table->json('contacts')->nullable();
            $table->json('segments')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->default('pending'); // Added status field
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
