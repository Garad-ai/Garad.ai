<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_campaign_message_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->string('meta_message_id')->unique();
            $table->string('recipient_phone', 50);
            $table->string('meta_template_name')->nullable()->index();
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent')->index();
            $table->timestamp('sent_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('error_title')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('ext_marketing_campaigns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_campaign_message_analytics');
    }
};
