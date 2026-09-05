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
        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            $table->text('highlight_context')->nullable()->after('input');
        });
    }

    public function down(): void
    {
        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            $table->dropColumn('highlight_context');
        });
    }
};
