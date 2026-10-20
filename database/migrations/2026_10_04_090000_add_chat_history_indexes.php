<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_openai_chat', function (Blueprint $table) {
            $table->index(
                ['user_id', 'is_chatbot', 'is_pinned', 'updated_at'],
                'user_chat_sidebar_recent_idx'
            );
        });

        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            $table->index(
                ['user_openai_chat_id', 'created_at', 'id'],
                'user_chat_messages_history_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('user_openai_chat', function (Blueprint $table) {
            $table->dropIndex('user_chat_sidebar_recent_idx');
        });

        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            $table->dropIndex('user_chat_messages_history_idx');
        });
    }
};
