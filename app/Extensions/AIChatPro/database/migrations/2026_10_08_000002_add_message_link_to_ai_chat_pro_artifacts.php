<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_chat_pro_artifacts') ||
            Schema::hasColumn('ai_chat_pro_artifacts', 'user_openai_chat_message_id')) {
            return;
        }

        Schema::table('ai_chat_pro_artifacts', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_openai_chat_message_id')
                ->nullable()
                ->index()
                ->after('user_openai_chat_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_chat_pro_artifacts') &&
            Schema::hasColumn('ai_chat_pro_artifacts', 'user_openai_chat_message_id')) {
            Schema::table('ai_chat_pro_artifacts', function (Blueprint $table): void {
                $table->dropColumn('user_openai_chat_message_id');
            });
        }
    }
};
