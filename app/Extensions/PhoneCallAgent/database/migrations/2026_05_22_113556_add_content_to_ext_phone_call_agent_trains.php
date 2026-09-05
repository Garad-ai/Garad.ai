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
        Schema::table('ext_phone_call_agent_trains', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('ext_phone_call_agent_trains', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
