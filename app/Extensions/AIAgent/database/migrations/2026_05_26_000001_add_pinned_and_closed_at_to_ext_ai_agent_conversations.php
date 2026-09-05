<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_conversations', function (Blueprint $table) {
            $table->unsignedInteger('pinned')->default(0)->index();
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_conversations', function (Blueprint $table) {
            $table->dropIndex(['pinned']);
            $table->dropColumn(['pinned', 'closed_at']);
        });
    }
};
