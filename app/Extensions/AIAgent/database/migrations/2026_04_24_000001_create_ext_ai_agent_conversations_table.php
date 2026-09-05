<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_ai_agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('channel_id')->index();
            $table->string('sender_id');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ai_agent_conversations');
    }
};
