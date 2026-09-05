<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_memories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
        });

        Schema::table('ext_ai_agent_memories', function (Blueprint $table) {
            $table->dropColumn('key');
        });

        Schema::table('ext_ai_agent_memories', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('ext_ai_agent_memories', function (Blueprint $table) {
            $table->renameColumn('value', 'memory');
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_memories', function (Blueprint $table) {
            $table->renameColumn('memory', 'value');
            $table->string('key')->default('');
            $table->string('type')->default('preference');
            $table->unique(['user_id', 'key']);
        });
    }
};
