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
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            $table->string('role')->nullable()->after('description');
            $table->string('avatar')->nullable()->after('role');
            $table->text('system_instructions')->nullable()->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            $table->dropColumn(['role', 'avatar', 'system_instructions']);
        });
    }
};
