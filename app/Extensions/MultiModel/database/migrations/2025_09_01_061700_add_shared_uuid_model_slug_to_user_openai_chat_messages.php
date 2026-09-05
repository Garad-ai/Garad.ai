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
            $table->uuid('shared_uuid')->nullable()->after('id')->index();
            $table->string('model_slug')->nullable()->after('shared_uuid')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            $table->dropColumn('shared_uuid');
            $table->dropColumn('model_slug');
        });
    }
};
