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
        Schema::table('ext_phone_call_agents', function (Blueprint $table) {
            $table->json('booking_tool_ids')->nullable()->after('booking_api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_phone_call_agents', function (Blueprint $table) {
            $table->dropColumn('booking_tool_ids');
        });
    }
};
