<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_marketing_conversations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->string('telegram_group_id')->nullable();
            $table->string('whatsapp_channel_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('conversation_name')->nullable();
            $table->timestamp('connect_agent_at')->nullable();
            $table->string('session_id')->nullable();
            $table->json('customer_payload')->nullable();
            $table->boolean('is_showed_on_history')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_marketing_conversations');
    }
};
