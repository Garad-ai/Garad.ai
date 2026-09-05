<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            $table->json('steps')->nullable()->after('actions');
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            $table->dropColumn('steps');
        });
    }
};
