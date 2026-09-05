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
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->string('request_id')->nullable()->after('engine')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_chat_pro_image', function (Blueprint $table) {
            $table->dropColumn('request_id');
        });
    }
};
