<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_ai_chat_pro_agent_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('chat_id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ext_ai_chat_pro_message_mentions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('chat_id')->index();
            $table->unsignedBigInteger('sender_id')->index();
            $table->unsignedBigInteger('recipient_id')->nullable()->index();
            $table->string('recipient_username', 30);
            $table->timestamps();
            $table->foreign('message_id')
                ->references('id')
                ->on('user_openai_chat_messages')
                ->cascadeOnDelete();
            $table->foreign('recipient_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->unique(['message_id', 'recipient_id']);
        });

        Schema::create('ext_ai_chat_pro_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_part', 20)->default('item');
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->unsignedBigInteger('owner_id')->index();
            $table->unsignedBigInteger('recipient_id')->index();
            $table->string('access_level', 20)->default('view');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id']);
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('recipient_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ai_chat_pro_shares');
        Schema::dropIfExists('ext_ai_chat_pro_message_mentions');
        Schema::dropIfExists('ext_ai_chat_pro_agent_conversations');
    }
};
