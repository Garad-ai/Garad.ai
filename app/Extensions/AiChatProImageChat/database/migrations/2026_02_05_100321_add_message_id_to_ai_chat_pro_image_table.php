<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->foreignId('message_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_openai_chat_messages')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
            $table->dropColumn('message_id');
        });
    }
};
