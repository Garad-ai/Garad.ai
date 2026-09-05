<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_messages', function (Blueprint $table) {
            $table->bigInteger('conversation_id')->nullable()->index()->after('channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_messages', function (Blueprint $table) {
            $table->dropColumn('conversation_id');
        });
    }
};
