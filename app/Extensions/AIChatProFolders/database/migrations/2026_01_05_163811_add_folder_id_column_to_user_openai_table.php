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
        Schema::table('user_openai_chat', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('ai_chat_pro_folders')->onDelete('set null');

            $table->index('folder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
