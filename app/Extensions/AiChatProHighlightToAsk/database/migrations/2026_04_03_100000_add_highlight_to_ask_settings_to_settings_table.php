<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'ai_chat_pro_highlight_to_ask_enabled')) {
                $table->boolean('ai_chat_pro_highlight_to_ask_enabled')->default(true)->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'ai_chat_pro_highlight_to_ask_enabled')) {
                $table->dropColumn('ai_chat_pro_highlight_to_ask_enabled');
            }
        });
    }
};
