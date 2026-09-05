<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->index(
                ['status', 'message_id', 'id'],
                'ai_chat_pro_image_status_message_id_id_idx'
            );
        });

        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            $table->index(
                ['user_openai_chat_id', 'id'],
                'uocm_chat_id_id_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->dropIndex('ai_chat_pro_image_status_message_id_id_idx');
        });

        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            $table->dropIndex('uocm_chat_id_id_idx');
        });
    }
};
