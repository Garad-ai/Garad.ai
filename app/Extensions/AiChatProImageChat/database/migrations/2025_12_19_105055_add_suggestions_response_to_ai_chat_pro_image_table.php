<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->text('suggestions_response')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->dropColumn('suggestions_response');
        });
    }
};
